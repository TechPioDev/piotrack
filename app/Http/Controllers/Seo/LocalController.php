<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Citation;
use App\Models\LandingPage;
use App\Models\SeoLocation;
use App\Services\Seo\LocalAuthorityService;
use App\Services\Seo\NapConsistencyChecker;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LocalController extends Controller
{
    public function __construct(
        private NapConsistencyChecker $nap,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('seo/local/index', [
            'locations' => SeoLocation::with('citations')->latest('id')->get()->map(fn (SeoLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'address' => trim(implode(', ', array_filter([$l->street, $l->city, $l->region, $l->postal_code]))),
                'phone' => $l->phone,
                'website' => $l->website,
                'citations' => $l->citations->map(fn (Citation $c) => [
                    'id' => $c->id,
                    'source' => $c->source,
                    'status' => $c->status,
                    'mismatches' => $c->mismatches ?? [],
                    'listed_name' => $c->listed_name,
                    'listed_address' => $c->listed_address,
                    'listed_phone' => $c->listed_phone,
                ])->all(),
                // LSEO-001/009/010: the Map-Pack readiness checklist.
                'gbp' => app(LocalAuthorityService::class)->gbpReadiness($l),
                // LSEO-015/016: the branch's local authority rollup.
                'authority' => app(LocalAuthorityService::class)->authority($l),
            ]),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $location = SeoLocation::create($request->validate([
            'name' => ['required', 'string', 'max:200'],
            'street' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
        ]));

        $this->audit->log('seo.location.created', context: ['name' => $location->name], resourceType: 'seo_location', resourceId: (string) $location->id, organizationId: $location->organization_id);

        return back()->with('status', __('Location added.'));
    }

    public function destroyLocation(SeoLocation $location): RedirectResponse
    {
        $location->delete();

        return back()->with('status', __('Location removed.'));
    }

    /**
     * Generate a draft location landing page from a branch's NAP data
     * (LSEO-006/007/008/014): "{Service} in {City}" with the address, phone
     * and service area baked into the body. Draft, so copy is reviewed before
     * publishing at /p/{slug}.
     */
    public function createPage(Request $request, SeoLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'service' => ['required', 'string', 'max:120'],
        ]);

        $service = trim($data['service']);
        $city = trim((string) $location->city) !== '' ? trim((string) $location->city) : $location->name;
        $region = trim((string) $location->region);
        $where = $region !== '' ? "{$city}, {$region}" : $city;

        $base = Str::slug("{$service} {$city}");
        $slug = $base;
        $i = 1;
        while (LandingPage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $nap = e(trim(implode(', ', array_filter([$location->street, $location->city, $region, $location->postal_code]))));
        $phone = e((string) $location->phone);

        $page = LandingPage::create([
            'name' => "{$service} — {$city}",
            'slug' => $slug,
            'headline' => "{$service} in {$where}",
            'subheadline' => "Local {$service} for businesses in and around {$city}.",
            'body_html' => '<h2>'.e($service).' in '.e($where).'</h2>'
                .'<p>Our '.e($city).' team delivers '.e(Str::lower($service)).' with local, on-site response.</p>'
                .'<h3>Visit or call</h3>'
                .'<p>'.$nap.($phone !== '' ? '<br>Phone: '.$phone : '').'</p>'
                .'<h3>Service area</h3>'
                .'<p>Serving '.e($where).' and the surrounding area.</p>',
            'status' => 'draft',
        ]);

        $this->audit->log('seo.local.page_created', context: ['location' => $location->name, 'slug' => $page->slug],
            resourceType: 'landing_page', resourceId: (string) $page->id, organizationId: $page->organization_id);

        return back()->with('status', __('Draft landing page ":name" created — review it under Marketing → Landing pages.', ['name' => $page->name]));
    }

    public function storeCitation(Request $request, SeoLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:120'],
            'listed_name' => ['nullable', 'string', 'max:200'],
            'listed_address' => ['nullable', 'string', 'max:255'],
            'listed_phone' => ['nullable', 'string', 'max:40'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        $result = $this->nap->check($location, [
            'name' => $data['listed_name'] ?? null,
            'address' => $data['listed_address'] ?? null,
            'phone' => $data['listed_phone'] ?? null,
        ]);

        $location->citations()->create([
            ...$data,
            'status' => $result['status'],
            'mismatches' => $result['mismatches'],
            'checked_at' => now(),
        ]);

        $this->audit->log('seo.citation.checked', context: ['source' => $data['source'], 'status' => $result['status']], resourceType: 'seo_location', resourceId: (string) $location->id, organizationId: $location->organization_id);

        return back()->with('status', __('Citation :status.', ['status' => $result['status']]));
    }

    public function checkCitation(SeoLocation $location, Citation $citation): RedirectResponse
    {
        $result = $this->nap->check($location, [
            'name' => $citation->listed_name,
            'address' => $citation->listed_address,
            'phone' => $citation->listed_phone,
        ]);

        $citation->update(['status' => $result['status'], 'mismatches' => $result['mismatches'], 'checked_at' => now()]);

        return back()->with('status', __('Citation re-checked: :status.', ['status' => $result['status']]));
    }

    public function destroyCitation(SeoLocation $location, Citation $citation): RedirectResponse
    {
        $citation->delete();

        return back()->with('status', __('Citation removed.'));
    }
}
