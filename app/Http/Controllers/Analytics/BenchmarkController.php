<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\BenchmarkService;
use Inertia\Inertia;
use Inertia\Response;

class BenchmarkController extends Controller
{
    public function index(BenchmarkService $benchmarks): Response
    {
        return Inertia::render('analytics/benchmarks', [
            'benchmarks' => $benchmarks->all(),
            'metrics' => BenchmarkService::METRICS,
            'min_cohort' => $benchmarks->minCohort(),
            // BENCH-003/004/013/014/015/016: per-segment peer benchmarks,
            // each segment k-floored independently.
            'segmented' => $benchmarks->segmented(),
        ]);
    }
}
