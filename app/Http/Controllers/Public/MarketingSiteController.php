<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public product marketing pages (MSITE). Each page ships its title, meta
 * description, canonical URL and JSON-LD server-side through Blade view data —
 * the app has no SSR, so anything SEO-critical must be in the initial HTML.
 */
class MarketingSiteController extends Controller
{
    public function features(): Response
    {
        return $this->page('site/features', [
            'title' => 'MSP Marketing Software Features | Piotrack',
            'description' => 'CRM, marketing automation, SEO and AI visibility tracking, booking, and revenue attribution for MSPs — every growth tool in one platform, measured to revenue.',
            'path' => '/features',
        ]);
    }

    public function howItWorks(): Response
    {
        return $this->page('site/how-it-works', [
            'title' => 'How Piotrack Works: MSP Growth Platform',
            'description' => 'From a one-line tracking snippet to attributed MRR: how MSPs use Piotrack to capture visitors, qualify leads, book meetings, close deals, and prove what worked.',
            'path' => '/how-it-works',
        ]);
    }

    public function results(): Response
    {
        return $this->page('site/results', [
            'title' => 'MSP Marketing Results You Can Prove | Piotrack',
            'description' => 'No vanity metrics: Piotrack measures funnel conversion, attributed MRR, search rankings, and AI recommendation share with stored evidence you can audit.',
            'path' => '/results',
        ]);
    }

    public function about(): Response
    {
        return $this->page('site/about', [
            'title' => 'About Piotrack | The Growth OS for MSPs',
            'description' => 'Piotrack is the growth operating system for managed service providers — built on one rule: every number in the product must be traceable to something real.',
            'path' => '/about',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Piotrack',
                'url' => url('/'),
                'description' => 'The growth operating system for managed service providers: CRM, marketing automation, SEO and AI visibility, and revenue attribution in one platform.',
            ],
        ]);
    }

    public function contact(): Response
    {
        return $this->page('site/contact', [
            'title' => 'Contact Piotrack | MSP Growth Platform',
            'description' => 'Questions about the platform, plans, or a demo? Send the Piotrack team a message — we read every one and reply by email.',
            'path' => '/contact',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'ContactPage',
                'name' => 'Contact Piotrack',
                'url' => url('/contact'),
            ],
        ]);
    }

    public function faq(): Response
    {
        $items = self::faqItems();

        return $this->page('site/faq', [
            'title' => 'Piotrack FAQ | MSP Marketing Platform Questions',
            'description' => 'Answers on pricing, the 14-day free trial, visitor tracking, AI visibility, data isolation, and how Piotrack fits alongside your PSA and RMM.',
            'path' => '/faq',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ], $items),
            ],
        ], ['items' => $items]);
    }

    /**
     * The FAQ content — single source of truth for both the rendered page and
     * the FAQPage JSON-LD, so the two can never drift apart.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function faqItems(): array
    {
        return [
            [
                'q' => 'What is Piotrack?',
                'a' => 'Piotrack is a growth platform built for managed service providers. It combines CRM and pipeline, marketing automation, website visitor tracking, SEO rank tracking, AI visibility monitoring, appointment booking, and revenue attribution in one tenant-isolated workspace — so your marketing is measured to closed revenue, not clicks.',
            ],
            [
                'q' => 'Who is Piotrack for?',
                'a' => 'MSPs and IT service providers that want predictable pipeline from their marketing, and agencies that run growth programs for MSP clients. The interface, funnel stages, and reporting are built around how IT services are actually sold: long cycles, monthly recurring revenue, and trust-driven buying.',
            ],
            [
                'q' => 'Is there a free trial?',
                'a' => 'Yes. Every new organization starts with a 14-day free trial of the Growth plan, and no credit card is required to start.',
            ],
            [
                'q' => 'How much does Piotrack cost?',
                'a' => 'Plans start at $49 per month for Starter. Growth is $149, Professional is $349, and Agency is $749 per month, with a discount for annual billing. Enterprise is custom-priced. Every plan lists exactly which modules and limits it includes.',
            ],
            [
                'q' => 'Does Piotrack replace my PSA or RMM?',
                'a' => 'No — it sits beside them. Your PSA keeps running ticketing and billing and your RMM keeps monitoring endpoints. Piotrack owns the growth side: attracting visitors, capturing and qualifying leads, booking meetings, closing deals, and proving which marketing produced the revenue.',
            ],
            [
                'q' => 'How does website visitor tracking work?',
                'a' => 'You add one line of script to your website. Piotrack sets a first-party cookie, records page visits and sessions, keeps the first-touch UTM source immutable, and scores intent by the pages a visitor reads. When a visitor submits a form or books a meeting, their anonymous history is linked to the new lead automatically.',
            ],
            [
                'q' => 'What is AI visibility tracking?',
                'a' => 'It measures whether AI assistants like ChatGPT and Gemini actually recommend your MSP when buyers ask for providers in your area. With engine API keys connected, Piotrack stores the answer excerpts as evidence you can read. Without keys, the module runs in a clearly labeled simulated mode — it never presents simulated data as live.',
            ],
            [
                'q' => 'Is my data isolated from other customers?',
                'a' => 'Yes. Every business table is scoped to your organization, every endpoint is permission-checked, and every module ships with tenant-isolation and authorization tests. Your team sees your data and nothing else.',
            ],
            [
                'q' => 'Can I import my existing CRM data?',
                'a' => 'Yes. Companies, leads, and deals import from CSV with column mapping and per-row error reporting, and everything exports back to CSV whenever you want it — your data is never locked in.',
            ],
            [
                'q' => 'How do I get help?',
                'a' => 'Send us a message through the contact page and we will reply by email. The full user guide is also available as a PDF from the site footer.',
            ],
        ];
    }

    /**
     * @param  array{title: string, description: string, path: string, jsonLd?: array<string, mixed>}  $meta
     * @param  array<string, mixed>  $props
     */
    private function page(string $component, array $meta, array $props = []): Response
    {
        return Inertia::render($component, $props)->withViewData([
            'metaTitle' => $meta['title'],
            'metaDescription' => $meta['description'],
            'canonical' => url($meta['path']),
            'jsonLd' => $meta['jsonLd'] ?? null,
        ]);
    }
}
