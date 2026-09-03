<?php

namespace App\Http\Controllers\Ai;

use App\Ai\AiProviderManager;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Services\Ai\AiGateway;
use App\Services\Ai\ScoreCalibration;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class AiDashboardController extends Controller
{
    public function __invoke(
        AiGateway $gateway,
        AiProviderManager $providers,
        UsageMeter $usage,
        CurrentOrganization $current,
        ScoreCalibration $calibration,
    ): Response {
        $organization = $current->get();

        return Inertia::render('ai/dashboard', [
            'usage' => $gateway->usageSummary(),
            // AISA-012/013: advisory-score quality as the tenant's own measured
            // number (scored deals vs actual outcomes), never our claim.
            'calibration' => $calibration->report(),
            // Stated plainly so fixture output is never mistaken for live inference.
            'driver' => ['name' => $providers->driver()->name(), 'model' => $providers->driver()->model(), 'live' => $providers->isLive()],
            'credits' => [
                'used' => $organization !== null ? $usage->usage($organization, Limit::AiCredits) : 0,
                'remaining' => $organization !== null ? $usage->remaining($organization, Limit::AiCredits) : null,
            ],
            'recent' => AiRequest::latest('id')->limit(50)->get()->map(fn (AiRequest $r) => [
                'id' => $r->id,
                'feature' => $r->feature,
                'model' => $r->model,
                'tokens' => $r->prompt_tokens + $r->completion_tokens,
                'cost' => $r->estimated_cost,
                'duration_ms' => $r->duration_ms,
                'attempts' => $r->attempts,
                'status' => $r->status,
                'error' => $r->error,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
