<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AssignmentRule;
use App\Models\Contact;
use App\Models\ScoringRule;
use App\Services\Ai\AiSalesAgent;
use App\Services\Sales\LeadScoringService;
use App\Services\Sales\PredictiveScoringService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ScoringController extends Controller
{
    public function __construct(
        private LeadScoringService $scoring,
        private AuditLogger $audit,
    ) {}

    public function index(PredictiveScoringService $predictive): Response
    {
        return Inertia::render('sales/scoring/index', [
            'rules' => ScoringRule::latest('id')->get()->map(fn (ScoringRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'category' => $r->category,
                'attribute' => $r->attribute,
                'operator' => $r->operator,
                'value' => $r->value,
                'points' => $r->points,
                'is_active' => $r->is_active,
            ]),
            'contacts' => Contact::orderByDesc('lead_score')->limit(50)->get()->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'lead_score' => $c->lead_score,
                'temperature' => $this->scoring->temperature($c->lead_score),
                'lifecycle_stage' => $c->lifecycle_stage,
                // LSCR-014: empirical close probability, or null while the
                // model floor is unmet (the status card explains).
                'win_probability' => $predictive->status()['active']
                    ? $predictive->predict($c)['probability']
                    : null,
            ]),
            // LSCR-014: the model's honest state — active or refusing with counts.
            'predictive' => $predictive->status(),
            // CRM-025: routing rules run before the round-robin fallback.
            'assignment_rules' => AssignmentRule::with('user:id,name')->orderBy('position')->orderBy('id')->get()
                ->map(fn (AssignmentRule $r) => [
                    'id' => $r->id,
                    'position' => $r->position,
                    'field' => $r->field,
                    'value' => $r->value,
                    'user' => $r->user?->name,
                ]),
            'assignment_fields' => AssignmentRule::FIELDS,
            'members' => app(CurrentOrganization::class)->get()->members()
                ->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    /** CRM-025: add a routing rule (first match wins, position order). */
    public function storeAssignmentRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(AssignmentRule::FIELDS)],
            'value' => ['required', 'string', 'max:150'],
            'user_id' => ['required', Rule::exists('organization_user', 'user_id')->where('organization_id', app(CurrentOrganization::class)->id())],
        ]);

        AssignmentRule::create($data + ['position' => (int) AssignmentRule::max('position') + 1]);

        return back()->with('status', __('Routing rule added.'));
    }

    public function destroyAssignmentRule(AssignmentRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Routing rule removed.'));
    }

    /**
     * LSCR-015: the P25-tested AI advisory score, surfaced on the scoring
     * page. Persisted and calibrated by the agent; NEVER written over the
     * deterministic lead score.
     */
    public function aiScore(Contact $contact, AiSalesAgent $agent): RedirectResponse
    {
        try {
            $result = $agent->scoreLead($contact);
        } catch (Throwable) {
            return back()->withErrors(['ai' => __('The AI driver is unavailable - the deterministic score stands on its own.')]);
        }

        return back()->with('status', __('AI opinion for :name: :score/100 - ":reason". Advisory only; the deterministic score is unchanged.', [
            'name' => $contact->fullName(), 'score' => $result['score'], 'reason' => $result['reason'],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = ScoringRule::create($this->validateData($request));
        $this->audit->log('sales.rule.created', context: ['name' => $rule->name], resourceType: 'scoring_rule', resourceId: (string) $rule->id, organizationId: $rule->organization_id);

        return back()->with('status', __('Scoring rule created.'));
    }

    public function update(Request $request, ScoringRule $rule): RedirectResponse
    {
        $rule->update($this->validateData($request));

        return back()->with('status', __('Scoring rule updated.'));
    }

    public function destroy(ScoringRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Scoring rule removed.'));
    }

    public function recompute(): RedirectResponse
    {
        $count = $this->scoring->recomputeAll();

        return back()->with('status', __('Recomputed scores for :n contacts.', ['n' => $count]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['demographic', 'firmographic', 'behavioral', 'intent'])],
            'attribute' => ['required', Rule::in(['lifecycle_stage', 'lead_source', 'title', 'email_opt_in', 'has_company', 'intent_score'])],
            'operator' => ['required', Rule::in(['equals', 'contains', 'gte', 'is_true'])],
            'value' => ['nullable', 'string', 'max:200'],
            'points' => ['required', 'integer', 'min:-100', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }
}
