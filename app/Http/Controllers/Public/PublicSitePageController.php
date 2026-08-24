<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Models\ChatWidget;
use App\Models\PageSection;
use App\Models\SeoLocation;
use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use App\Services\Seo\SchemaGenerator;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public website page rendering (WEB). Unauthenticated: the page is resolved by
 * slug across tenants, then the tenant context is set so everything downstream
 * stays scoped — the same pattern the public form, booking and landing pages use.
 *
 * A draft page 404s: an unpublished URL must not be reachable by guessing it.
 *
 * These are marketing pages, so this assembles what a page needs to be found and
 * to convert, not merely its own words: navigation and related pages so the site
 * is internally linked rather than a set of orphans, the branch's name, address
 * and phone for local search, and structured data describing what the page is.
 */
class PublicSitePageController extends Controller
{
    public function show(string $slug, CurrentOrganization $current, SchemaGenerator $schema): View
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
        $page->load(['serviceLine', 'vertical', 'location', 'form']);

        $sections = PageSection::where('site_page_id', $page->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        // Loaded explicitly rather than through a relation: these are
        // tenant-scoped, and a public request should not depend on the global
        // scope having been primed to resolve the page's own organization.
        $orgId = $page->organization_id;

        $brand = BrandProfile::withoutGlobalScope('tenant')->where('organization_id', $orgId)->first();

        // A branch address and phone are what local search matches on, so fall
        // back to the organization's first location when the page has none.
        $location = $page->location ?: SeoLocation::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)->orderBy('id')->first();

        // The chat widget rides along on every published page. Which pages it
        // actually appears on is the widget's own business: it already evaluates
        // URL include/exclude rules, device and visitor targeting in the browser,
        // so a second setting here would only be able to disagree with it.
        $chatWidget = ChatWidget::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        $navigation = SiteNavigationItem::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->with('page:id,slug,title,status')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('placement');

        return view('public.site-page', [
            'page' => $page,
            'sections' => $sections,
            'organization' => $page->organization,
            'brand' => $brand,
            'location' => $location,
            'headerNav' => $this->navLinks($navigation->get('header'), $page),
            'footerNav' => $this->navLinks($navigation->get('footer'), $page),
            'related' => $this->relatedPages($page),
            'chatWidget' => $chatWidget,
            'schema' => $this->structuredData($page, $location, $sections, $schema),
        ]);
    }

    /**
     * Navigation entries that actually resolve, as label + href.
     *
     * An item pointing at a page that has since been unpublished would render a
     * link straight into a 404, which costs more than the missing link does.
     *
     * @param  Collection<int, SiteNavigationItem>|null  $items
     * @return list<array{label: string, href: string, current: bool}>
     */
    private function navLinks(?Collection $items, SitePage $page): array
    {
        $out = [];
        foreach ($items ?? [] as $item) {
            if ($item->page !== null && $item->page->status === SitePage::STATUS_PUBLISHED) {
                $out[] = [
                    'label' => $item->label,
                    'href' => url('/s/'.$item->page->slug),
                    'current' => $item->page->id === $page->id,
                ];
            } elseif ($item->url !== null && $item->url !== '') {
                $out[] = ['label' => $item->label, 'href' => $item->url, 'current' => false];
            }
        }

        return $out;
    }

    /**
     * Other published pages worth linking to, most related first.
     *
     * Internal links are how a visitor finds the rest of the site and how a
     * crawler discovers pages that nothing else points at. Ordering by shared
     * service line, vertical or location keeps the link relevant rather than
     * just filling a row.
     *
     * @return list<array{title: string, href: string, context: ?string}>
     */
    private function relatedPages(SitePage $page): array
    {
        $candidates = SitePage::withoutGlobalScope('tenant')
            ->where('organization_id', $page->organization_id)
            ->where('status', SitePage::STATUS_PUBLISHED)
            ->whereKeyNot($page->getKey())
            ->with(['serviceLine:id,name', 'vertical:id,name', 'location:id,name'])
            ->limit(24)
            ->get();

        $scored = $candidates->map(function (SitePage $candidate) use ($page) {
            $score = 0;
            if ($page->service_line_id && $candidate->service_line_id === $page->service_line_id) {
                $score += 3;
            }
            if ($page->vertical_id && $candidate->vertical_id === $page->vertical_id) {
                $score += 2;
            }
            if ($page->seo_location_id && $candidate->seo_location_id === $page->seo_location_id) {
                $score += 2;
            }
            if ($candidate->type === $page->type) {
                $score += 1;
            }

            return ['page' => $candidate, 'score' => $score];
        })->sortByDesc('score')->take(3);

        return $scored->map(fn (array $row) => [
            'title' => $row['page']->title,
            'href' => url('/s/'.$row['page']->slug),
            'context' => $row['page']->serviceLine->name
                ?? $row['page']->vertical->name
                ?? $row['page']->location?->name,
        ])->values()->all();
    }

    /**
     * Question/answer pairs from any FAQ sections on the page.
     *
     * Authors write these as one line each — "Do you offer 24/7 cover? Yes, …" —
     * so a plain string is split at the first question mark. An explicit
     * question/answer pair is honoured as given.
     *
     * @param  Collection<int, PageSection>  $sections
     * @return list<array{question: string, answer: string}>
     */
    private function faqPairs(Collection $sections): array
    {
        $pairs = [];
        foreach ($sections->where('type', 'faq') as $section) {
            foreach ((array) ($section->settings['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $question = trim((string) ($item['question'] ?? $item['label'] ?? ''));
                    $answer = trim((string) ($item['answer'] ?? $item['body'] ?? ''));
                } else {
                    $text = trim((string) $item);
                    $at = mb_strpos($text, '?');
                    $question = $at === false ? $text : mb_substr($text, 0, $at + 1);
                    $answer = $at === false ? '' : trim(mb_substr($text, $at + 1));
                }

                if ($question !== '' && $answer !== '') {
                    $pairs[] = ['question' => $question, 'answer' => $answer];
                }
            }
        }

        return $pairs;
    }

    /**
     * JSON-LD describing the page.
     *
     * A service page is a Service offered by the business; anything tied to a
     * branch is that LocalBusiness. Breadcrumbs are emitted separately so search
     * results can show the path rather than a bare URL.
     *
     * @param  Collection<int, PageSection>  $sections
     * @return list<array<string, mixed>>
     */
    private function structuredData(SitePage $page, ?SeoLocation $location, Collection $sections, SchemaGenerator $schema): array
    {
        $org = $page->organization;
        $out = [];

        if ($location !== null) {
            $out[] = $schema->generate('LocalBusiness', [
                'name' => $org->name,
                'phone' => $location->phone,
                'url' => url('/s/'.$page->slug),
                'street' => $location->street,
                'city' => $location->city,
                'region' => $location->region,
                'postal_code' => $location->postal_code,
                'country' => $location->country,
            ]);
        } else {
            $out[] = $schema->generate('Organization', [
                'name' => $org->name,
                'url' => url('/s/'.$page->slug),
            ]);
        }

        if ($page->serviceLine !== null) {
            $out[] = $schema->generate('Service', [
                'name' => $page->serviceLine->name,
                'provider' => $org->name,
                'description' => $page->meta_description,
            ]);
        }

        // Questions and answers a search engine can show directly in results,
        // which is the whole reason to write an FAQ section rather than a prose
        // block saying the same thing.
        $faq = $this->faqPairs($sections);
        if ($faq !== []) {
            $out[] = $schema->generate('FAQPage', ['faqs' => $faq]);
        }

        $out[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $org->name, 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => url('/s/'.$page->slug)],
            ],
        ];

        return $out;
    }
}
