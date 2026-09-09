<?php

namespace App\Http\Controllers;

use App\Services\Ops\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Application health endpoint for monitors and deployment checks.
     * The framework's bare liveness endpoint is /up; this one verifies
     * critical dependencies and reports per-component status. The checks
     * themselves live in HealthCheckService so the admin alerter (OBS-004)
     * always agrees with this endpoint on what "healthy" means.
     */
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $checks = $health->checks();
        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'metrics' => $health->metrics(),
            'version' => config('app.version'),
        ], $healthy ? 200 : 503);
    }
}
