<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\Chat\ChatBookingSlots;
use App\Services\Sales\BookingService;
use App\Services\Sales\IcsCalendar;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public, unauthenticated appointment booking (BOOK). The tenant is resolved
 * from the booking-page slug and set as the current organization so the booking
 * (+ deduped contact + activity) is created tenant-scoped.
 */
class PublicBookingController extends Controller
{
    /** How many slots / how far ahead the public page offers (vs chat's six). */
    private const PAGE_SLOTS = 24;

    private const PAGE_DAYS_AHEAD = 14;

    public function __construct(
        private CurrentOrganization $currentOrganization,
        private BookingService $booking,
        private ChatBookingSlots $slots,
    ) {}

    public function show(Request $request, string $slug): View
    {
        $page = $this->resolve($slug);

        return view('public.booking', [
            'page' => $page,
            // BOOK-003: real availability — inside the page's hours, not taken.
            'slots' => $this->slots->available($page, self::PAGE_SLOTS, self::PAGE_DAYS_AHEAD),
            'questions' => $page->questions ?? [],
            // BOOK-006: the booking link's own campaign attribution rides along.
            'utm' => array_filter([
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
            ], fn ($v) => is_string($v) && $v !== ''),
        ]);
    }

    public function book(Request $request, string $slug): RedirectResponse|View
    {
        $page = $this->resolve($slug);
        $questions = $page->questions ?? [];

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200'],
            'slot_id' => ['nullable', 'string', 'max:60'],
            'scheduled_at' => ['required_without:slot_id', 'nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
        ];
        foreach ($questions as $i => $question) {
            $rules['answers.'.$i] = [($question['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules);

        // BOOK-003: a picked slot is revalidated server-side — the list the
        // visitor saw may be minutes old, and the server decides what is free.
        if (! empty($data['slot_id'])) {
            $at = $this->slots->resolve($page, $data['slot_id'], self::PAGE_SLOTS, self::PAGE_DAYS_AHEAD);
            if ($at === null) {
                throw ValidationException::withMessages(['slot_id' => __('That time was just taken — pick another.')]);
            }
            $data['scheduled_at'] = $at;
        }

        // Answers keyed by their question label, not a bare index.
        $answers = [];
        foreach ($questions as $i => $question) {
            $value = $data['answers'][$i] ?? null;
            if ($value !== null && $value !== '') {
                $answers[(string) $question['label']] = $value;
            }
        }

        $utm = array_filter([
            'source' => $data['utm_source'] ?? null,
            'medium' => $data['utm_medium'] ?? null,
            'campaign' => $data['utm_campaign'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $this->booking->book($page, [
            'name' => $data['name'],
            'email' => $data['email'],
            'scheduled_at' => $data['scheduled_at'],
            'notes' => $data['notes'] ?? null,
            'city' => $data['city'] ?? null,
            'answers' => $answers ?: null,
            'utm' => $utm ?: null,
        ], $request->cookie('_pt_vid'));

        return view('public.message', [
            'title' => __('Booked!'),
            'message' => __('Your :type is confirmed. Check your email for the calendar invite.', ['type' => $page->meeting_type]),
        ]);
    }

    /** BOOK-001: the per-booking calendar invite the confirmation email links. */
    public function ics(string $token, IcsCalendar $ics): Response
    {
        $booking = Booking::withoutGlobalScope('tenant')->where('ics_token', $token)->first();
        abort_if($booking === null, 404);

        return response($ics->event($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="booking.ics"',
        ]);
    }

    /** BOOK-001: the page's subscribable feed of upcoming bookings. */
    public function feed(string $token, IcsCalendar $ics): Response
    {
        $page = BookingPage::withoutGlobalScope('tenant')->where('ics_feed_token', $token)->first();
        abort_if($page === null || $page->ics_feed_token === null, 404);

        return response($ics->feed($page), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    private function resolve(string $slug): BookingPage
    {
        $page = BookingPage::withoutGlobalScope('tenant')
            ->where('slug', $slug)->where('is_active', true)->first();

        abort_if($page === null, 404);

        $organization = $page->organization()->first();
        abort_if($organization === null, 404);

        $this->currentOrganization->set($organization);

        return $page;
    }
}
