<?php

namespace App\Http\Controllers;

use App\Models\BrandProfile;
use App\Models\Competitor;
use App\Models\KpiTarget;
use App\Models\ScoringRule;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\Vertical;
use App\Services\IntegrationService;
use App\Services\OnboardingSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guided setup wizard (ONBD-006..012). Behind organization.update — setup
 * decides taxonomy, goals, scoring and competitors for the whole tenant.
 */
class OnboardingSetupController extends Controller
{
    public function __construct(private OnboardingSetup $setup) {}

    public function show(IntegrationService $integrations): Response
    {
        $brand = BrandProfile::first();

        return Inertia::render('onboarding/setup', [
            'services' => ServiceLine::orderBy('name')->get(['id', 'key', 'name', 'is_active']),
            'verticals' => Vertical::orderBy('name')->get(['id', 'key', 'name', 'is_active']),
            'location' => SeoLocation::orderBy('id')->first(['id', 'city', 'region']),
            'website_url' => $brand?->website_url,
            'goals' => KpiTarget::whereIn('metric', ['leads', 'sqls', 'mrr'])->get(['metric', 'target_value'])
                ->mapWithKeys(fn (KpiTarget $t) => [$t->metric => $t->target_value]),
            'icpRules' => ScoringRule::where('name', 'like', 'ICP:%')->get(['name', 'attribute', 'value', 'points', 'is_active']),
            'competitors' => Competitor::orderBy('name')->get(['id', 'name', 'domain']),
            // ONBD-011: the connector registry with its real state — connections
            // themselves wait on vendor OAuth apps, and the wizard says so.
            'connectors' => $integrations->catalog(),
        ]);
    }

    public function businessProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'services' => ['array'],
            'services.*' => ['string', 'max:60'],
            'verticals' => ['array'],
            'verticals.*' => ['string', 'max:60'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
        ]);

        $this->setup->businessProfile($data['services'] ?? [], $data['verticals'] ?? [], $data['city'] ?? null, $data['region'] ?? null);

        return back()->with('status', __('Business profile saved.'));
    }

    public function website(Request $request): RedirectResponse
    {
        $data = $request->validate(['website_url' => ['required', 'url', 'max:2048']]);

        $this->setup->website($data['website_url']);

        return back()->with('status', __('Website saved.'));
    }

    public function goals(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leads' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sqls' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'mrr' => ['nullable', 'integer', 'min:1'],
        ]);

        $count = $this->setup->goals($data);

        return back()->with('status', trans_choice('{0}No goals set yet.|{1}1 goal target set.|[2,*]:count goal targets set.', $count, ['count' => $count]));
    }

    public function icp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'industry' => ['nullable', 'string', 'max:120'],
            'company_size' => ['nullable', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:120'],
        ]);

        $count = $this->setup->icp($data['industry'] ?? null, $data['company_size'] ?? null, $data['region'] ?? null);

        return back()->with('status', __(':n ICP scoring rules active — matching leads score higher from now on.', ['n' => $count]));
    }

    public function competitors(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'competitors' => ['required', 'array', 'max:10'],
            'competitors.*.name' => ['required', 'string', 'max:150'],
            'competitors.*.domain' => ['nullable', 'string', 'max:255'],
        ]);

        $created = $this->setup->competitors($data['competitors']);

        return back()->with('status', __(':n competitors added.', ['n' => $created]));
    }

    public function complete(): RedirectResponse
    {
        $audit = $this->setup->complete();

        return redirect()->route('dashboard')->with('status', $audit !== null
            ? __('Setup complete — your first site audit just ran (score :score/100).', ['score' => $audit->score])
            : __('Setup complete. Add your website URL later to run the first site audit.'));
    }
}
