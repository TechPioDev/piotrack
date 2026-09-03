<?php

namespace App\Services\Web;

use App\Models\PageSection;
use App\Models\SitePage;
use App\Services\Seo\TechnicalSeoAuditor;
use App\Support\AuditLogger;
use App\Support\UrlGuard;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The website page builder (WEB). Typed pages built from ordered section blocks,
 * wired to navigation, and published to a public URL.
 */
class SiteBuilderService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPage(array $data): SitePage
    {
        $page = SitePage::create([
            'type' => $data['type'] ?? 'landing',
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
            'title' => $data['title'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'headline' => $data['headline'] ?? null,
            'subheadline' => $data['subheadline'] ?? null,
            'template' => $data['template'] ?? 'standard',
            // Column defaults are not hydrated onto the in-memory model, so
            // anything read back in the same request is set explicitly.
            'status' => SitePage::STATUS_DRAFT,
            'view_count' => 0,
            'service_line_id' => $data['service_line_id'] ?? null,
            'vertical_id' => $data['vertical_id'] ?? null,
            'seo_location_id' => $data['seo_location_id'] ?? null,
            'form_id' => $data['form_id'] ?? null,
        ]);

        $this->audit->log('web.page.created', context: ['type' => $page->type, 'slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSection(SitePage $page, array $data): PageSection
    {
        $type = $data['type'];

        if (! in_array($type, PageSection::TYPES, true)) {
            throw new RuntimeException("Unknown section type [{$type}].");
        }

        return PageSection::create([
            'site_page_id' => $page->id,
            'type' => $type,
            'heading' => $data['heading'] ?? null,
            'body' => $data['body'] ?? null,
            'settings' => $data['settings'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) PageSection::where('site_page_id', $page->id)->max('sort_order') + 1),
            'is_visible' => $data['is_visible'] ?? true,
        ]);
    }

    /**
     * Reorder sections to the given id order.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderSections(SitePage $page, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            PageSection::where('site_page_id', $page->id)->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    /**
     * Publish a page. A page with no title or no visible section cannot go live —
     * an empty published URL is worse than no URL.
     */
    public function publish(SitePage $page): SitePage
    {
        if (trim($page->title) === '') {
            throw new RuntimeException('A page needs a title before it can be published.');
        }

        if (! PageSection::where('site_page_id', $page->id)->where('is_visible', true)->exists()) {
            throw new RuntimeException('A page needs at least one visible section before it can be published.');
        }

        $page->update(['status' => SitePage::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->audit->log('web.page.published', context: ['slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        // WEB-052: a page going live gets its first technical audit at once.
        // Guarded, never blocking: an unfetchable app URL (local dev) or a
        // failing fetch skips silently — the publish itself must not depend on
        // the audit succeeding.
        $this->auditPublished($page);

        return $page->refresh();
    }

    /**
     * Audit one published page's public URL through the Stage 7 auditor
     * (WEB-052) — only when that URL is actually fetchable from here.
     */
    public function auditPublished(SitePage $page): bool
    {
        $url = url('/s/'.$page->slug);

        if (! app(UrlGuard::class)->isFetchable($url)) {
            return false;
        }

        try {
            app(TechnicalSeoAuditor::class)->crawl($url);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The template gallery (WEB-007..010): buyer-journey page blueprints an
     * MSP actually converts with — ordered section structures with structural
     * guidance copy (the keyword-library discipline: domain knowledge, never
     * fake proof or invented numbers). Applying one creates a DRAFT the tenant
     * edits; the existing health checks refuse to publish it without real CTA
     * and proof content.
     *
     * @return array<string, array{name: string, type: string, description: string, sections: list<array{type: string, heading: string, body: string}>}>
     */
    public static function templates(): array
    {
        return [
            'msp_home' => [
                'name' => 'MSP homepage',
                'type' => 'home',
                'description' => 'Hero with one promise, the pains you remove, services, proof, and a single call to action.',
                'sections' => [
                    ['type' => 'hero', 'heading' => 'State the outcome you deliver, for whom, in one sentence', 'body' => 'One promise, one audience. Example structure: "Managed IT for [vertical] in [city] that [outcome]". Avoid listing services here - the visitor decides in seconds whether this page is for them.'],
                    ['type' => 'content', 'heading' => 'The three problems you take off their plate', 'body' => 'Name the pains your buyers actually say out loud (downtime, audit anxiety, slow tickets). One short paragraph each, in their words, not yours.'],
                    ['type' => 'services', 'heading' => 'What we run for you', 'body' => 'Your active service lines, each in one line of outcome language. Link each to its service page.'],
                    ['type' => 'trust', 'heading' => 'Why teams switch to us', 'body' => 'Your real differentiators - the brand page checks them against your site and your competitors. Replace with the ones that survived.'],
                    ['type' => 'reviews', 'heading' => 'What clients say', 'body' => 'Pull real reviews from Reputation - never write testimonials yourself.'],
                    ['type' => 'faq', 'heading' => 'Answers before the call', 'body' => 'The 4-6 questions prospects ask on every first call. Each answer in two sentences - these also become FAQ schema.'],
                    ['type' => 'cta', 'heading' => 'Book the 20-minute fit call', 'body' => 'One action, one button, pointing at your booking page. No second competing offer.'],
                ],
            ],
            'service_page' => [
                'name' => 'Service page',
                'type' => 'service',
                'description' => 'One service, sold on outcomes: problem, approach, proof, FAQ, CTA.',
                'sections' => [
                    ['type' => 'hero', 'heading' => '[Service] that [outcome], for [audience]', 'body' => 'Name the service AND the market - "Managed cybersecurity for Philadelphia manufacturers" outranks and outconverts "Our security services".'],
                    ['type' => 'content', 'heading' => 'The problem this solves', 'body' => 'What breaks, what it costs, and why it keeps happening without this service. Concrete failure scenarios beat adjectives.'],
                    ['type' => 'content', 'heading' => 'How we run it', 'body' => 'Your actual delivery: tooling, cadence, SLAs, who they talk to. Specifics here are what a buyer forwards to their boss.'],
                    ['type' => 'case_studies', 'heading' => 'Proof from clients like them', 'body' => 'Link the published case studies for this service line - the proof page generator can draft one.'],
                    ['type' => 'faq', 'heading' => 'Common questions', 'body' => 'Pricing model, onboarding time, contract terms, what happens to their current provider.'],
                    ['type' => 'cta', 'heading' => 'Get the assessment', 'body' => 'The next step for THIS service - assessment, audit, or fit call. One button.'],
                ],
            ],
            'vertical_page' => [
                'name' => 'Vertical page',
                'type' => 'vertical',
                'description' => 'Speak one industry through its own risks, compliance and proof.',
                'sections' => [
                    ['type' => 'hero', 'heading' => 'IT for [industry], built around [their #1 constraint]', 'body' => 'Lead with the constraint that keeps their owners up at night - compliance for manufacturers, uptime for logistics, privacy for healthcare.'],
                    ['type' => 'content', 'heading' => 'What [industry] IT gets wrong', 'body' => 'The failure modes specific to this vertical. Generic MSP copy is why vertical pages underperform - this section is the reason the page exists.'],
                    ['type' => 'content', 'heading' => 'Compliance, handled', 'body' => 'The frameworks this vertical answers to (CMMC, HIPAA, SOC 2...) and exactly what you take ownership of. The vertical record carries compliance notes that feed this.'],
                    ['type' => 'logos', 'heading' => 'Clients in [industry]', 'body' => 'Real client logos from Reputation - only ones from this vertical.'],
                    ['type' => 'cta', 'heading' => 'Talk to someone who knows [industry]', 'body' => 'The booking page, framed in their language.'],
                ],
            ],
            'lead_magnet_landing' => [
                'name' => 'Lead-magnet landing page',
                'type' => 'landing',
                'description' => 'One gated asset, one form, zero navigation distractions.',
                'sections' => [
                    ['type' => 'hero', 'heading' => 'The [asset]: what they get in one line', 'body' => 'Name the deliverable and the outcome of reading it - "The 12-point CMMC readiness checklist our auditors use".'],
                    ['type' => 'content', 'heading' => 'What is inside', 'body' => 'Three to five bullets of the actual contents. Specific beats mysterious - people trade their email for known value.'],
                    ['type' => 'offer', 'heading' => 'Get the download', 'body' => 'Attach the form here, and set its lead-magnet file so the download is gated and counted. This section is the whole page job.'],
                ],
            ],
        ];
    }

    /**
     * Create a draft page + ordered sections from one template.
     */
    public function applyTemplate(string $key): SitePage
    {
        $template = self::templates()[$key] ?? null;
        if ($template === null) {
            throw new RuntimeException('Unknown template.');
        }

        $page = $this->createPage([
            'title' => $template['name'],
            'type' => $template['type'],
        ]);

        foreach ($template['sections'] as $i => $section) {
            PageSection::create([
                'site_page_id' => $page->id,
                'type' => $section['type'],
                'heading' => $section['heading'],
                'body' => $section['body'],
                'sort_order' => $i,
                'is_visible' => true,
            ]);
        }

        $this->audit->log('web.page.from_template', context: ['template' => $key],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page;
    }

    public function unpublish(SitePage $page): SitePage
    {
        $page->update(['status' => SitePage::STATUS_DRAFT, 'published_at' => null]);

        $this->audit->log('web.page.unpublished', context: ['slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page->refresh();
    }

    /**
     * A URL-safe slug unique across ALL tenants: published pages share one public
     * URL space (/s/{slug}), so uniqueness must be global or one tenant's page
     * would shadow another's.
     */
    public function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (SitePage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
