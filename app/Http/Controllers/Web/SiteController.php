<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\PageSection;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use App\Models\Vertical;
use App\Services\Web\LocationService;
use App\Services\Web\SiteBuilderService;
use App\Services\Web\SiteHealthService;
use App\Services\Web\TaxonomyService;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SiteController extends Controller
{
    public function __construct(
        private SiteBuilderService $builder,
        private SiteHealthService $health,
    ) {}

    public function index(): Response
    {
        return Inertia::render('web/pages', [
            'pages' => SitePage::with('sections')->latest('id')->get()->map(fn (SitePage $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'slug' => $p->slug,
                'title' => $p->title,
                'meta_title' => $p->meta_title,
                'meta_description' => $p->meta_description,
                'headline' => $p->headline,
                'subheadline' => $p->subheadline,
                'status' => $p->status,
                'view_count' => $p->view_count,
                'published_at' => $p->published_at?->toIso8601String(),
                'service_line_id' => $p->service_line_id,
                'vertical_id' => $p->vertical_id,
                'seo_location_id' => $p->seo_location_id,
                'form_id' => $p->form_id,
                'health' => $this->health->score($p),
                'sections' => $p->sections->map(fn (PageSection $s) => [
                    'id' => $s->id,
                    'type' => $s->type,
                    'heading' => $s->heading,
                    'body' => $s->body,
                    'sort_order' => $s->sort_order,
                    'is_visible' => $s->is_visible,
                ])->all(),
            ]),
            'report' => $this->health->siteReport(),
            'navigation' => SiteNavigationItem::orderBy('placement')->orderBy('sort_order')->get()
                ->map(fn (SiteNavigationItem $n) => [
                    'id' => $n->id,
                    'label' => $n->label,
                    'url' => $n->url,
                    'site_page_id' => $n->site_page_id,
                    'placement' => $n->placement,
                    'sort_order' => $n->sort_order,
                ]),
            'service_lines' => ServiceLine::orderBy('name')->get(['id', 'name', 'key']),
            'verticals' => Vertical::orderBy('name')->get(['id', 'name', 'key']),
            'locations' => SeoLocation::orderBy('name')->get(['id', 'name', 'city']),
            'forms' => Form::orderBy('name')->get(['id', 'name', 'slug']),
            'page_types' => SitePage::TYPES,
            'section_types' => PageSection::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->builder->createPage($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::in(SitePage::TYPES)],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'headline' => ['nullable', 'string', 'max:200'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'service_line_id' => ['nullable', 'integer', TenantExists::in('service_lines')],
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
            'seo_location_id' => ['nullable', 'integer', TenantExists::in('seo_locations')],
            'form_id' => ['nullable', 'integer', TenantExists::in('forms')],
        ]));

        return back()->with('status', __('Page created.'));
    }

    public function update(Request $request, SitePage $page): RedirectResponse
    {
        $page->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'headline' => ['nullable', 'string', 'max:200'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'form_id' => ['nullable', 'integer', TenantExists::in('forms')],
            // Re-targeting must be editable: the coverage report exists to expose
            // gaps, and a gap you can only close by creating a NEW page rather
            // than re-pointing an existing one makes the report far less useful.
            'type' => ['sometimes', Rule::in(SitePage::TYPES)],
            'service_line_id' => ['nullable', 'integer', TenantExists::in('service_lines')],
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
            'seo_location_id' => ['nullable', 'integer', TenantExists::in('seo_locations')],
        ]));

        return back()->with('status', __('Page updated.'));
    }

    public function destroy(SitePage $page): RedirectResponse
    {
        $page->delete();

        return back()->with('status', __('Page removed.'));
    }

    public function storeSection(Request $request, SitePage $page): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PageSection::TYPES)],
            'heading' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'is_visible' => ['boolean'],
        ]);

        $this->builder->addSection($page, $data);

        return back()->with('status', __('Section added.'));
    }

    public function updateSection(Request $request, PageSection $section): RedirectResponse
    {
        $section->update($request->validate([
            'heading' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'is_visible' => ['boolean'],
        ]));

        return back()->with('status', __('Section updated.'));
    }

    public function destroySection(PageSection $section): RedirectResponse
    {
        $section->delete();

        return back()->with('status', __('Section removed.'));
    }

    public function reorderSections(Request $request, SitePage $page): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        /** @var list<int> $ids */
        $ids = array_values($data['ids']);
        $this->builder->reorderSections($page, $ids);

        return back()->with('status', __('Sections reordered.'));
    }

    public function publish(SitePage $page): RedirectResponse
    {
        try {
            $this->builder->publish($page);
        } catch (RuntimeException $e) {
            return back()->withErrors(['page' => $e->getMessage()]);
        }

        return back()->with('status', __('Page published.'));
    }

    public function unpublish(SitePage $page): RedirectResponse
    {
        $this->builder->unpublish($page);

        return back()->with('status', __('Page unpublished.'));
    }

    public function storeNavigation(Request $request): RedirectResponse
    {
        SiteNavigationItem::create($request->validate([
            'label' => ['required', 'string', 'max:100'],
            // A nav item needs somewhere to go: either an internal page or an
            // external URL. Without this both could be omitted and the item
            // would render as a dead link.
            'site_page_id' => ['nullable', 'required_without:url', 'integer', TenantExists::in('site_pages')],
            'url' => ['nullable', 'required_without:site_page_id', 'string', 'max:2048'],
            'placement' => ['required', Rule::in(['header', 'footer'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('status', __('Navigation item added.'));
    }

    public function destroyNavigation(SiteNavigationItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', __('Navigation item removed.'));
    }

    /**
     * Service lines, verticals and locations — the three targeting axes.
     */
    public function taxonomy(TaxonomyService $taxonomy, LocationService $locations): Response
    {
        return Inertia::render('web/taxonomy', [
            'service_lines' => $taxonomy->serviceGaps(),
            'verticals' => $taxonomy->verticalGaps(),
            'locations' => $locations->report(),
            // MLOC-008/010/011: the regional programme and brand posture.
            'calendar' => $locations->regionalCalendar(),
            'compliance' => $locations->brandCompliance(),
        ]);
    }

    public function provisionTaxonomy(TaxonomyService $taxonomy): RedirectResponse
    {
        $added = $taxonomy->provision();

        return back()->with('status', __(':services service lines and :verticals verticals added.', [
            'services' => $added['service_lines'],
            'verticals' => $added['verticals'],
        ]));
    }

    public function storeLocation(Request $request, LocationService $locations): RedirectResponse
    {
        $locations->create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'territory' => ['nullable', 'string', 'max:100'],
            'gbp_place_id' => ['nullable', 'string', 'max:255'],
            // BOOK-005: the branch rep who takes territory-matched bookings.
            'owner_id' => ['nullable', 'integer', Rule::exists('organization_user', 'user_id')->where('organization_id', app(CurrentOrganization::class)->id())],
        ]));

        return back()->with('status', __('Location added.'));
    }
}
