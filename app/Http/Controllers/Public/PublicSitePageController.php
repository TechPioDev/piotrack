<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Models\PageSection;
use App\Models\SitePage;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public website page rendering (WEB). Unauthenticated: the page is resolved by
 * slug across tenants, then the tenant context is set so everything downstream
 * stays scoped — the same pattern the public form, booking and landing pages use.
 *
 * A draft page 404s: an unpublished URL must not be reachable by guessing it.
 */
class PublicSitePageController extends Controller
{
    public function show(string $slug, CurrentOrganization $current): View
    {
        $page = SitePage::withoutGlobalScope('tenant')
            ->where('slug', $slug)
            ->where('status', SitePage::STATUS_PUBLISHED)
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        $current->set($page->organization);

        $page->increment('view_count');

        $sections = PageSection::where('site_page_id', $page->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        // Loaded explicitly rather than through a relation: the brand profile is
        // tenant-scoped, and a public request should not depend on the global
        // scope having been primed to resolve the page's own branding.
        $brand = BrandProfile::withoutGlobalScope('tenant')
            ->where('organization_id', $page->organization_id)
            ->first();

        return view('public.site-page', [
            'page' => $page,
            'sections' => $sections,
            'organization' => $page->organization,
            'brand' => $brand,
        ]);
    }
}
