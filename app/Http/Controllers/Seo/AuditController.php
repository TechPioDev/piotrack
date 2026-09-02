<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoAudit;
use App\Models\SiteCrawl;
use App\Services\Seo\SiteCrawler;
use App\Services\Seo\TechnicalSeoAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(
        private TechnicalSeoAuditor $auditor,
        private SiteCrawler $crawler,
    ) {}

    public function index(): Response
    {
        return Inertia::render('seo/audits/index', [
            'audits' => SeoAudit::latest('id')->limit(50)->get()->map(fn (SeoAudit $a) => [
                'id' => $a->id,
                'url' => $a->url,
                'score' => $a->score,
                'issues_count' => $a->issues_count,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
            'crawls' => SiteCrawl::latest('id')->limit(20)->get()->map(fn (SiteCrawl $c) => [
                'id' => $c->id,
                'start_url' => $c->start_url,
                'pages_crawled' => $c->pages_crawled,
                'issues_count' => $c->issues_count,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Bounded multi-page crawl (TSEO-002). An SSRF-refused URL surfaces as a
     * normal validation error rather than a 500.
     */
    public function storeCrawl(Request $request): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);

        try {
            $crawl = $this->crawler->crawl($data['url']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        return redirect()->route('seo.audits.crawl.show', $crawl->id)
            ->with('status', __('Crawled :n pages.', ['n' => $crawl->pages_crawled]));
    }

    public function showCrawl(SiteCrawl $crawl): Response
    {
        return Inertia::render('seo/audits/crawl', [
            'crawl' => [
                'id' => $crawl->id,
                'start_url' => $crawl->start_url,
                'pages_crawled' => $crawl->pages_crawled,
                'issues_count' => $crawl->issues_count,
                'report' => $crawl->report ?? ['sections' => [], 'pages' => [], 'depths' => []],
                'created_at' => $crawl->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);

        $audit = $this->auditor->crawl($data['url']);

        return redirect()->route('seo.audits.show', $audit->id)->with('status', __('Audit complete.'));
    }

    public function show(SeoAudit $audit): Response
    {
        return Inertia::render('seo/audits/show', [
            'audit' => [
                'id' => $audit->id,
                'url' => $audit->url,
                'score' => $audit->score,
                'issues_count' => $audit->issues_count,
                'fetched_status' => $audit->fetched_status,
                'checks' => $audit->checks ?? [],
                'created_at' => $audit->created_at?->toIso8601String(),
            ],
        ]);
    }
}
