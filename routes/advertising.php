<?php

use App\Http\Controllers\Advertising\AdDashboardController;
use App\Http\Controllers\Advertising\AdGroupController;
use App\Http\Controllers\Advertising\CampaignController;
use App\Http\Controllers\Advertising\LinkedInAdsController;
use App\Http\Controllers\Advertising\MetaAdsController;
use App\Http\Controllers\Advertising\PpcController;
use App\Http\Controllers\Advertising\RetargetingController;
use Illuminate\Support\Facades\Route;

/*
 * Advertising (Stage 8). Requires an authenticated, verified user with an active
 * organization on a plan that includes the `advertising` feature; actions are
 * gated by ads.* permissions. Route-model binding is tenant-scoped.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:advertising'])
    ->prefix('ads')->name('ads.')->group(function () {

        Route::get('/', AdDashboardController::class)->middleware('can:ads.view')->name('dashboard');

        // Campaigns.
        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('can:ads.view')->name('campaigns.index');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('can:ads.campaigns.manage')->name('campaigns.store');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('can:ads.view')->name('campaigns.show');
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('can:ads.campaigns.manage')->name('campaigns.update');
        Route::post('campaigns/{campaign}/status', [CampaignController::class, 'status'])->middleware('can:ads.campaigns.manage')->name('campaigns.status');
        Route::post('campaigns/{campaign}/refresh-metrics', [CampaignController::class, 'refreshMetrics'])->middleware('can:ads.campaigns.manage')->name('campaigns.refresh-metrics');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('can:ads.campaigns.manage')->name('campaigns.destroy');

        // Ad groups + nested ads + keywords.
        Route::middleware('can:ads.campaigns.manage')->group(function () {
            Route::post('campaigns/{campaign}/groups', [AdGroupController::class, 'storeGroup'])->name('groups.store');
            Route::delete('groups/{group}', [AdGroupController::class, 'destroyGroup'])->name('groups.destroy');
            Route::post('groups/{group}/ads', [AdGroupController::class, 'storeAd'])->name('ads.store');
            Route::delete('ads/{ad}', [AdGroupController::class, 'destroyAd'])->name('ads.destroy');
            Route::post('groups/{group}/keywords', [AdGroupController::class, 'storeKeyword'])->name('keywords.store');
            Route::delete('keywords/{keyword}', [AdGroupController::class, 'destroyKeyword'])->name('keywords.destroy');
        });

        // PPC management (Phase 36): copy drafts, bid advice, editor export,
        // extensions, call-tracking + landing-page bridges.
        Route::middleware('can:ads.campaigns.manage')->group(function () {
            Route::post('groups/{group}/draft-copy', [PpcController::class, 'draftCopy'])->name('groups.draft-copy');
            Route::post('campaigns/{campaign}/bid-advice', [PpcController::class, 'bidAdvice'])->name('campaigns.bid-advice');
            Route::get('campaigns/{campaign}/export', [PpcController::class, 'export'])->name('campaigns.export');
            Route::post('campaigns/{campaign}/extensions', [PpcController::class, 'storeExtension'])->name('extensions.store');
            Route::delete('extensions/{extension}', [PpcController::class, 'destroyExtension'])->name('extensions.destroy');
            Route::post('campaigns/{campaign}/tracking-number', [PpcController::class, 'attachTrackingNumber'])->name('campaigns.tracking-number');
            Route::post('campaigns/{campaign}/landing-page', [PpcController::class, 'createLandingPage'])->name('campaigns.landing-page');
        });

        // LinkedIn Advertising (Phase 37): drafts, briefs and imports only —
        // no Marketing API calls (ADR-0006).
        Route::middleware('can:ads.campaigns.manage')->group(function () {
            Route::post('linkedin/promote-content', [LinkedInAdsController::class, 'promoteContent'])->name('linkedin.promote-content');
            Route::post('linkedin/abm', [LinkedInAdsController::class, 'abmCampaign'])->name('linkedin.abm');
            Route::post('linkedin/leads', [LinkedInAdsController::class, 'importLeads'])->name('linkedin.leads');
            Route::post('campaigns/{campaign}/audience', [LinkedInAdsController::class, 'attachAudience'])->name('campaigns.audience');
            Route::get('campaigns/{campaign}/brief', [LinkedInAdsController::class, 'brief'])->name('campaigns.brief');
        });

        // Meta Advertising (Phase 38): drafts and imports only (ADR-0006).
        Route::middleware('can:ads.campaigns.manage')->group(function () {
            Route::post('meta/promote-content', [MetaAdsController::class, 'promoteContent'])->name('meta.promote-content');
            Route::post('meta/proof', [MetaAdsController::class, 'proofCampaign'])->name('meta.proof');
            Route::post('meta/leads', [MetaAdsController::class, 'importLeads'])->name('meta.leads');
        });

        // Retargeting audiences.
        Route::get('retargeting', [RetargetingController::class, 'index'])->middleware('can:ads.view')->name('retargeting.index');
        Route::post('retargeting', [RetargetingController::class, 'store'])->middleware('can:ads.retargeting.manage')->name('retargeting.store');
        Route::post('retargeting/{audience}/rebuild', [RetargetingController::class, 'rebuild'])->middleware('can:ads.retargeting.manage')->name('retargeting.rebuild');
        Route::get('retargeting/{audience}/export', [RetargetingController::class, 'export'])->middleware('can:ads.retargeting.manage')->name('retargeting.export');
        Route::post('retargeting/{audience}/sms', [RetargetingController::class, 'sms'])->middleware('can:ads.retargeting.manage')->name('retargeting.sms');
        Route::delete('retargeting/{audience}', [RetargetingController::class, 'destroy'])->middleware('can:ads.retargeting.manage')->name('retargeting.destroy');
    });
