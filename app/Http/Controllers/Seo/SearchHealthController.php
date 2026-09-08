<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Seo\Contracts\SearchConsoleProvider;
use App\Seo\Contracts\WebVitalsProvider;
use App\Services\Seo\BacklinkAuditService;
use App\Services\Seo\CwvLabAuditor;
use App\Services\Seo\PenaltyAuditService;
use App\Support\UrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * TSEO-019/023/024/025: the search-health page — Search Console monitoring
 * through the provider seam, the penalty audit with its recovery plan, and
 * the on-demand CWV audit (first-party lab checks + seam field data).
 */
class SearchHealthController extends Controller
{
    public function index(
        Request $request,
        SearchConsoleProvider $searchConsole,
        WebVitalsProvider $vitals,
        BacklinkAuditService $backlinks,
        PenaltyAuditService $penalties,
        CwvLabAuditor $lab,
        UrlGuard $urls,
    ): Response {
        $domain = $backlinks->ownDomain();

        return Inertia::render('seo/health', [
            'domain' => $domain,
            'search_console' => [
                'provider' => $searchConsole->name(),
                'performance' => $domain !== null ? $searchConsole->searchPerformance($domain) : [],
                'coverage' => $domain !== null ? $searchConsole->indexCoverage($domain) : null,
                'manual_actions' => $domain !== null ? $searchConsole->manualActions($domain) : [],
            ],
            'penalty' => $penalties->audit(),
            'recovery' => $penalties->recoveryPlan(),
            'vitals_provider' => $vitals->name(),
            // TSEO-019: ?cwv_url= runs the guarded lab + field audit inline.
            'cwv' => $this->cwv($request, $lab, $vitals, $urls),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cwv(Request $request, CwvLabAuditor $lab, WebVitalsProvider $vitals, UrlGuard $urls): ?array
    {
        $url = (string) $request->query('cwv_url', '');
        if ($url === '') {
            return null;
        }

        try {
            // Tenant-supplied URL: SSRF-guarded and never redirected, exactly
            // like the technical auditor's crawl (SEC-001).
            $urls->assertFetchable($url);
            $response = Http::timeout(15)->withoutRedirecting()->get($url);

            if ($response->failed()) {
                return ['url' => $url, 'error' => __('Could not fetch the page (HTTP :status).', ['status' => $response->status()])];
            }

            return [
                'url' => $url,
                'error' => null,
                'lab' => $lab->analyze($response->body(), $url),
                'field' => $vitals->fieldData($url),
            ];
        } catch (Throwable) {
            return ['url' => $url, 'error' => __('That URL cannot be fetched.')];
        }
    }
}
