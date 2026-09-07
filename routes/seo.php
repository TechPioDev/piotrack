<?php

use App\Http\Controllers\Crm\EntityExportController;
use App\Http\Controllers\Seo\AiVisibilityController;
use App\Http\Controllers\Seo\AuditController;
use App\Http\Controllers\Seo\KeywordController;
use App\Http\Controllers\Seo\LlmoController;
use App\Http\Controllers\Seo\LocalController;
use App\Http\Controllers\Seo\SchemaController;
use App\Http\Controllers\Seo\SeoDashboardController;
use Illuminate\Support\Facades\Route;

/*
 * SEO & search intelligence (Stage 7). Requires an authenticated, verified user
 * with an active organization. TSEO/KSEO/LSEO are gated by the `seo` plan
 * feature; AEO/GEO/LLMO (AI visibility) additionally by `ai_visibility`.
 * Individual actions are gated by seo.* permissions; binding is tenant-scoped.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:seo'])
    ->prefix('seo')->name('seo.')->group(function () {

        Route::get('/', SeoDashboardController::class)->middleware('can:seo.view')->name('dashboard');

        // Technical audits + bounded site crawls.
        Route::get('audits', [AuditController::class, 'index'])->middleware('can:seo.view')->name('audits.index');
        Route::post('audits', [AuditController::class, 'store'])->middleware('can:seo.audits.manage')->name('audits.store');
        Route::post('audits/crawl', [AuditController::class, 'storeCrawl'])->middleware('can:seo.audits.manage')->name('audits.crawl');
        Route::get('audits/crawl/{crawl}', [AuditController::class, 'showCrawl'])->middleware('can:seo.view')->name('audits.crawl.show');
        Route::get('audits/{audit}', [AuditController::class, 'show'])->middleware('can:seo.view')->name('audits.show');

        // Keywords + rank tracking.
        Route::get('keywords/export-csv', EntityExportController::class)
            ->defaults('entity', 'keywords')->middleware('can:seo.view')->name('keywords.export');
        Route::get('keywords', [KeywordController::class, 'index'])->middleware('can:seo.view')->name('keywords.index');
        Route::post('keywords', [KeywordController::class, 'store'])->middleware('can:seo.keywords.manage')->name('keywords.store');
        Route::post('keywords/seed', [KeywordController::class, 'seed'])->middleware('can:seo.keywords.manage')->name('keywords.seed');
        Route::post('keywords/recluster', [KeywordController::class, 'recluster'])->middleware('can:seo.keywords.manage')->name('keywords.recluster');
        Route::patch('keywords/{keyword}', [KeywordController::class, 'update'])->middleware('can:seo.keywords.manage')->name('keywords.update');
        Route::post('keywords/{keyword}/rank', [KeywordController::class, 'rank'])->middleware('can:seo.keywords.manage')->name('keywords.rank');
        Route::delete('keywords/{keyword}', [KeywordController::class, 'destroy'])->middleware('can:seo.keywords.manage')->name('keywords.destroy');

        // Local SEO.
        Route::get('local', [LocalController::class, 'index'])->middleware('can:seo.view')->name('local.index');
        Route::post('local', [LocalController::class, 'storeLocation'])->middleware('can:seo.local.manage')->name('local.store');
        Route::delete('local/{location}', [LocalController::class, 'destroyLocation'])->middleware('can:seo.local.manage')->name('local.destroy');
        Route::post('local/{location}/page', [LocalController::class, 'createPage'])->middleware('can:seo.local.manage')->name('local.page.create');
        Route::post('local/{location}/citations', [LocalController::class, 'storeCitation'])->middleware('can:seo.local.manage')->name('local.citations.store');
        Route::post('local/{location}/citations/{citation}/check', [LocalController::class, 'checkCitation'])->middleware('can:seo.local.manage')->name('local.citations.check');
        Route::delete('local/{location}/citations/{citation}', [LocalController::class, 'destroyCitation'])->middleware('can:seo.local.manage')->name('local.citations.destroy');

        // Schema / structured data.
        Route::get('schema', [SchemaController::class, 'index'])->middleware('can:seo.view')->name('schema.index');
        Route::post('schema', [SchemaController::class, 'store'])->middleware('can:seo.audits.manage')->name('schema.store');
        Route::delete('schema/{schema}', [SchemaController::class, 'destroy'])->middleware('can:seo.audits.manage')->name('schema.destroy');

        // AI visibility (AEO/GEO/LLMO) — also gated by the `ai_visibility` feature.
        Route::middleware('entitlement:ai_visibility')->group(function () {
            Route::get('ai-visibility', [AiVisibilityController::class, 'index'])->middleware('can:seo.view')->name('ai.index');
            Route::post('ai-visibility', [AiVisibilityController::class, 'check'])->middleware('can:seo.ai.manage')->name('ai.check');
            // GEO-015/016: put a cited source into the outreach pipeline.
            Route::post('ai-visibility/target-source', [AiVisibilityController::class, 'targetSource'])->middleware('can:seo.ai.manage')->name('ai.target-source');

            // LLMO: knowledge graph + retrieval readiness + content scoring.
            Route::get('llmo', [LlmoController::class, 'index'])->middleware('can:seo.view')->name('llmo.index');
            Route::post('llmo/entity', [LlmoController::class, 'updateEntity'])->middleware('can:seo.ai.manage')->name('llmo.entity');
            // AEO-001: mined questions land in the prompt library.
            Route::post('llmo/questions', [LlmoController::class, 'storeQuestion'])->middleware('can:seo.ai.manage')->name('llmo.questions.store');
            Route::post('llmo/experts', [LlmoController::class, 'storeExpert'])->middleware('can:seo.ai.manage')->name('llmo.experts.store');
            Route::delete('llmo/experts/{expert}', [LlmoController::class, 'destroyExpert'])->middleware('can:seo.ai.manage')->name('llmo.experts.destroy');
            Route::post('llmo/graph', [LlmoController::class, 'publishGraph'])->middleware('can:seo.ai.manage')->name('llmo.graph');
            Route::post('llmo/score', [LlmoController::class, 'scoreContent'])->middleware('can:seo.ai.manage')->name('llmo.score');
        });
    });
