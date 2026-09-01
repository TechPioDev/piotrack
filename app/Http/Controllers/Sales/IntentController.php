<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\IntentSignal;
use App\Services\Sales\AlertService;
use App\Services\Sales\IntentService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IntentController extends Controller
{
    private const TYPES = ['page_visit', 'service_view', 'pricing_view', 'download', 'campaign_engagement', 'ad_engagement', 'crm_activity', 'demo_request'];

    public function __construct(
        private IntentService $intent,
        private AlertService $alerts,
    ) {}

    public function index(): Response
    {
        $signals = IntentSignal::with('contact:id,first_name,last_name')->latest('id')->limit(50)->get();

        // Contacts with recent intent, ranked, with a recommended next action.
        $contacts = Contact::whereHas('intentSignals')->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'intent_score' => $this->intent->intentScore($c),
                'high_intent' => $this->intent->isHighIntent($c),
                'buying_window' => $this->intent->inBuyingWindow($c),
                'next_action' => $this->intent->nextAction($c),
            ])
            ->sortByDesc('intent_score')->take(50)->values();

        return Inertia::render('sales/intent/index', [
            'signals' => $signals->map(fn (IntentSignal $s) => [
                'id' => $s->id,
                'contact' => $s->contact?->fullName(),
                'type' => $s->type,
                'weight' => $s->weight,
                'url' => $s->url,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ]),
            'contacts' => $contacts,
            'types' => self::TYPES,
            'contactOptions' => Contact::orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name'])
                ->map(fn (Contact $c) => ['id' => $c->id, 'name' => $c->fullName()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', TenantExists::active('contacts')],
            'type' => ['required', Rule::in(self::TYPES)],
            'weight' => ['required', 'integer', 'min:1', 'max:50'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);
        $this->intent->record($contact, $data['type'], $data['weight'], $data['url'] ?? null);

        // High buyer intent can raise a sales alert immediately.
        $this->alerts->evaluate($contact);

        return back()->with('status', __('Intent signal recorded.'));
    }
}
