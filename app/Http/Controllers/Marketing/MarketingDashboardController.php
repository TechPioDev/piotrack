<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ListMembership;
use App\Models\MarketingList;
use App\Models\OutboundMessage;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Services\Analytics\PeriodComparison;
use Inertia\Inertia;
use Inertia\Response;

class MarketingDashboardController extends Controller
{
    public function __invoke(): Response
    {
        // New contacts per day for the trend chart (design-shell module).
        // Days with no signups appear as zero: a gap is information here.
        $since = now()->subDays(29)->startOfDay();
        $byDay = Contact::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $trend = collect(range(0, 29))->map(function (int $offset) use ($since, $byDay) {
            $day = $since->copy()->addDays($offset);

            return ['label' => $day->format('M j'), 'value' => (int) ($byDay[$day->toDateString()] ?? 0)];
        })->values();

        $period = new PeriodComparison;

        return Inertia::render('marketing/dashboard', [
            'trend' => $trend,
            'stats' => [
                'lists' => MarketingList::count(),
                'list_members' => ListMembership::count(),
                'forms' => Form::count(),
                'campaigns' => Campaign::count(),
                'workflows' => Workflow::where('status', 'active')->count(),
                'workflows_total' => Workflow::count(),
                'contacts' => Contact::count(),
                'leads' => Contact::where('lifecycle_stage', 'lead')->count(),
            ],
            // Activity in the last 30 days against the 30 before, each on the
            // timestamp that records the event itself.
            'flows' => [
                'new_contacts' => $period->count(Contact::query(), 'created_at'),
                'submissions' => $period->count(FormSubmission::query(), 'created_at'),
                'messages_sent' => $period->count(OutboundMessage::query(), 'sent_at'),
                'messages_opened' => OutboundMessage::where('sent_at', '>=', $period->windowStart(1))->whereNotNull('opened_at')->count(),
                'enrollments' => $period->count(WorkflowEnrollment::query(), 'enrolled_at'),
            ],
            'lifecycle' => Contact::query()
                ->selectRaw('lifecycle_stage, count(*) as total')
                ->groupBy('lifecycle_stage')
                ->pluck('total', 'lifecycle_stage'),
            'recentCampaigns' => Campaign::latest('id')->limit(5)->get()
                ->map(fn (Campaign $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'channel' => $c->channel,
                    'status' => $c->status,
                    'sent' => $c->stat_sent,
                    'opened' => $c->stat_opened,
                    'clicked' => $c->stat_clicked,
                ]),
        ]);
    }
}
