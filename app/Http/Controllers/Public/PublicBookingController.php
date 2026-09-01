<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BookingPage;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, unauthenticated appointment booking (BOOK). The tenant is resolved
 * from the booking-page slug and set as the current organization so the booking
 * (+ deduped contact + activity) is created tenant-scoped.
 */
class PublicBookingController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private BookingService $booking,
    ) {}

    public function show(string $slug): View
    {
        $page = $this->resolve($slug);

        return view('public.booking', ['page' => $page]);
    }

    public function book(Request $request, string $slug): RedirectResponse|View
    {
        $page = $this->resolve($slug);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // The tracker cookie (when the tenant's snippet runs on this site) ties
        // the meeting to the visitor's history and first-touch source (BOOK-010).
        $this->booking->book($page, $data, $request->cookie('_pt_vid'));

        return view('public.message', [
            'title' => __('Booked!'),
            'message' => __('Your :type is confirmed. We will send a reminder.', ['type' => $page->meeting_type]),
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
