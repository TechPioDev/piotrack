<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\SalesAlert;
use App\Models\TargetAccount;
use App\Services\Sales\LeadScoringService;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __construct(private LeadScoringService $scoring) {}

    public function __invoke(): Response
    {
        $contacts = Contact::query()->get(['id', 'lead_score']);

        // Open pipeline value by stage (design-shell module). Values stay in
        // minor units; the page formats currency. Won/lost stages are excluded:
        // this chart answers "what is still in play, and where is it stuck?".
        $pipeline = PipelineStage::query()
            ->whereHas('pipeline', fn ($q) => $q->where('is_default', true))
            ->where('is_won', false)->where('is_lost', false)
            ->orderBy('sort_order')
            ->withSum(['deals as open_value' => fn ($q) => $q->where('status', 'open')], 'value')
            ->withCount(['deals as open_count' => fn ($q) => $q->where('status', 'open')])
            ->get()
            ->map(function (PipelineStage $stage) {
                // withSum/withCount aliases are query-time attributes, not
                // model properties — read them explicitly.
                $count = (int) $stage->getAttribute('open_count');

                return [
                    'label' => $stage->name,
                    'value' => (int) ($stage->getAttribute('open_value') ?? 0),
                    'hint' => $count.' '.($count === 1 ? 'deal' : 'deals'),
                ];
            })
            ->values();

        return Inertia::render('sales/dashboard', [
            'pipeline' => $pipeline,
            'temperature' => [
                'hot' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'hot')->count(),
                'warm' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'warm')->count(),
                'cold' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'cold')->count(),
            ],
            'stats' => [
                'unread_alerts' => SalesAlert::where('is_read', false)->count(),
                'upcoming_bookings' => Booking::where('status', 'booked')->where('scheduled_at', '>=', now())->count(),
                'target_accounts' => TargetAccount::count(),
            ],
            'recentAlerts' => SalesAlert::with('contact:id,first_name,last_name')->latest('id')->limit(6)->get()
                ->map(fn (SalesAlert $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'message' => $a->message,
                    'is_read' => $a->is_read,
                    'contact' => $a->contact?->fullName(),
                ]),
        ]);
    }
}
