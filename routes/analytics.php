<?php

use App\Http\Controllers\Analytics\AnalyticsDashboardController;
use App\Http\Controllers\Analytics\AttributionController;
use App\Http\Controllers\Analytics\BehaviorController;
use App\Http\Controllers\Analytics\BenchmarkController;
use App\Http\Controllers\Analytics\CallController;
use App\Http\Controllers\Analytics\CompetitorController;
use App\Http\Controllers\Analytics\ExperimentController;
use App\Http\Controllers\Analytics\GrowthScoreController;
use App\Http\Controllers\Analytics\OmnichannelController;
use App\Http\Controllers\Analytics\ReportExportController;
use App\Http\Controllers\Crm\EntityExportController;
use Illuminate\Support\Facades\Route;

/*
 * Analytics (Stage 11). Requires an authenticated, verified user with an active
 * organization on a plan that includes the `analytics` feature; actions are gated
 * by analytics.* permissions. Route-model binding is tenant-scoped.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:analytics'])
    ->prefix('analytics')->name('analytics.')->group(function () {

        // IMEX-004: the Command Center as a native one-page PDF.
        Route::get('report.pdf', ReportExportController::class)->middleware('can:analytics.view')->name('report.pdf');

        Route::middleware('can:analytics.view')->group(function () {
            Route::get('/', AnalyticsDashboardController::class)->name('dashboard');
            Route::get('attribution', [AttributionController::class, 'index'])->name('attribution.index');
            Route::get('benchmarks', [BenchmarkController::class, 'index'])->name('benchmarks.index');
            Route::get('omnichannel', [OmnichannelController::class, 'index'])->name('omnichannel.index');
            // CRO-010/011/014: first-party heatmaps, behavior and bounce rates.
            Route::get('behavior', [BehaviorController::class, 'index'])->name('behavior.index');
            Route::get('growth-score', [GrowthScoreController::class, 'index'])->name('growth-score.index');
        });

        // Storing a snapshot writes tenant data, so it needs a manage permission
        // rather than read access (mirrors sales.scoring.recompute).
        Route::post('growth-score/snapshot', [GrowthScoreController::class, 'snapshot'])
            ->middleware('can:analytics.growth-score.manage')->name('growth-score.snapshot');

        // Call tracking.
        Route::get('calls', [CallController::class, 'index'])->middleware('can:analytics.view')->name('calls.index');
        Route::middleware('can:analytics.calls.manage')->group(function () {
            Route::post('calls/numbers', [CallController::class, 'storeNumber'])->name('calls.numbers.store');
            Route::delete('calls/numbers/{number}', [CallController::class, 'destroyNumber'])->name('calls.numbers.destroy');
            Route::post('calls', [CallController::class, 'storeCall'])->name('calls.store');
            Route::post('calls/{call}/convert', [CallController::class, 'convert'])->name('calls.convert');
            // AISA-014: real transcripts in, AI summaries out.
            Route::patch('calls/{call}/transcript', [CallController::class, 'transcript'])->name('calls.transcript');
            Route::post('calls/{call}/summarize', [CallController::class, 'summarize'])->name('calls.summarize');
            // CALL-003/004: recording attachment + provider-seam transcription.
            Route::patch('calls/{call}/recording', [CallController::class, 'recording'])->name('calls.recording');
            Route::post('calls/{call}/transcribe', [CallController::class, 'transcribe'])->name('calls.transcribe');
        });

        // CRO experiments.
        Route::get('experiments', [ExperimentController::class, 'index'])->middleware('can:analytics.view')->name('experiments.index');
        Route::middleware('can:analytics.experiments.manage')->group(function () {
            Route::post('experiments', [ExperimentController::class, 'store'])->name('experiments.store');
            Route::post('experiments/{experiment}/start', [ExperimentController::class, 'start'])->name('experiments.start');
            Route::post('experiments/{experiment}/conclude', [ExperimentController::class, 'conclude'])->name('experiments.conclude');
            Route::delete('experiments/{experiment}', [ExperimentController::class, 'destroy'])->name('experiments.destroy');
            Route::post('experiments/variants/{variant}/record', [ExperimentController::class, 'record'])->name('experiments.record');
        });

        // Competitive intelligence.
        Route::get('competitors/export-csv', EntityExportController::class)
            ->defaults('entity', 'competitors')->middleware('can:analytics.view')->name('competitors.export');
        Route::get('competitors', [CompetitorController::class, 'index'])->middleware('can:analytics.view')->name('competitors.index');
        Route::middleware('can:analytics.competitors.manage')->group(function () {
            Route::post('competitors', [CompetitorController::class, 'store'])->name('competitors.store');
            Route::post('competitors/{competitor}/check-content', [CompetitorController::class, 'checkContent'])->name('competitors.check');
            Route::patch('competitors/{competitor}', [CompetitorController::class, 'update'])->name('competitors.update');
            Route::delete('competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('competitors.destroy');
        });
    });
