<?php

namespace App\Http\Controllers\Analytics;

use App\Ai\Exceptions\AiCreditsExhaustedException;
use App\Ai\Exceptions\AiProviderException;
use App\Calls\TranscriptionProvider;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Services\Ai\AiSalesAgent;
use App\Services\Analytics\CallTrackingService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CallController extends Controller
{
    public function __construct(private CallTrackingService $calls) {}

    public function index(): Response
    {
        return Inertia::render('analytics/calls', [
            'numbers' => CallTrackingNumber::latest('id')->get()->map(fn (CallTrackingNumber $n) => [
                'id' => $n->id,
                'phone_number' => $n->phone_number,
                'label' => $n->label,
                'source' => $n->source,
                'campaign' => $n->campaign,
                'is_active' => $n->is_active,
            ]),
            'calls' => Call::with('contact:id,first_name,last_name')->latest('id')->limit(100)->get()
                ->map(fn (Call $c) => [
                    'id' => $c->id,
                    'from_number' => $c->from_number,
                    'direction' => $c->direction,
                    'duration_seconds' => $c->duration_seconds,
                    'status' => $c->status,
                    'source' => $c->source,
                    'campaign' => $c->campaign,
                    'score' => $c->score,
                    'is_qualified' => $c->is_qualified,
                    'converted' => $c->converted,
                    'contact' => $c->contact?->fullName(),
                    'occurred_at' => $c->occurred_at?->toIso8601String(),
                    'has_transcript' => $c->transcript !== null && $c->transcript !== '',
                    'summary' => $c->summary,
                    'recording_url' => $c->recording_url,
                ]),
            'breakdown' => $this->calls->sourceBreakdown(),
        ]);
    }

    public function storeNumber(Request $request): RedirectResponse
    {
        $this->calls->provisionNumber($request->validate([
            'label' => ['nullable', 'string', 'max:150'],
            'source' => ['required', 'string', 'max:100'],
            'campaign' => ['nullable', 'string', 'max:150'],
        ]));

        return back()->with('status', __('Tracking number provisioned.'));
    }

    public function storeCall(Request $request): RedirectResponse
    {
        $this->calls->logCall($request->validate([
            'call_tracking_number_id' => ['nullable', 'integer', TenantExists::in('call_tracking_numbers')],
            'contact_id' => ['nullable', 'integer', TenantExists::active('contacts')],
            'owner_id' => ['nullable', 'integer', TenantExists::member()],
            'from_number' => ['nullable', 'string', 'max:32'],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'duration_seconds' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['completed', 'missed', 'voicemail'])],
            'converted' => ['boolean'],
        ]));

        return back()->with('status', __('Call logged.'));
    }

    /**
     * AISA-014: attach a real transcript (pasted from any source) so the AI
     * summary works on what was actually said, without the call provider.
     */
    public function transcript(Request $request, Call $call): RedirectResponse
    {
        $call->update($request->validate([
            'transcript' => ['required', 'string', 'max:20000'],
        ]));

        return back()->with('status', __('Transcript attached.'));
    }

    /** CALL-003: attach the call recording; the calls page plays it inline. */
    public function recording(Request $request, Call $call): RedirectResponse
    {
        $call->update($request->validate([
            'recording_url' => ['required', 'url', 'starts_with:https://', 'max:500'],
        ]));

        return back()->with('status', __('Recording attached.'));
    }

    /** CALL-004: fill the transcript from the recording via the provider seam. */
    public function transcribe(Call $call, TranscriptionProvider $transcription): RedirectResponse
    {
        if ($call->recording_url === null || $call->recording_url === '') {
            return back()->withErrors(['recording_url' => __('Attach a recording first — transcription reads the audio.')]);
        }

        $call->update(['transcript' => $transcription->transcribe($call->recording_url)]);

        return back()->with('status', __('Transcribed via the :name driver — review, then summarize.', ['name' => $transcription->name()]));
    }

    /**
     * AISA-014: summarize the call — from its transcript when one exists,
     * honestly from metadata otherwise.
     */
    public function summarize(Call $call, AiSalesAgent $agent): RedirectResponse
    {
        try {
            $agent->summarizeCall($call);
        } catch (AiCreditsExhaustedException|AiProviderException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', __('Call summarized.'));
    }

    public function convert(Call $call): RedirectResponse
    {
        $this->calls->markConverted($call);

        return back()->with('status', __('Call marked as converted.'));
    }

    public function destroyNumber(CallTrackingNumber $number): RedirectResponse
    {
        $number->delete();

        return back()->with('status', __('Tracking number removed.'));
    }
}
