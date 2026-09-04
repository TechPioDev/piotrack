<?php

use App\Http\Controllers\Content\ContentDashboardController;
use App\Http\Controllers\Content\ContentPieceController;
use App\Http\Controllers\Content\OutreachController;
use App\Http\Controllers\Content\ReputationController;
use App\Http\Controllers\Content\SocialController;
use Illuminate\Support\Facades\Route;

/*
 * Content & authority (Stage 9). Requires an authenticated, verified user with an
 * active organization on a plan that includes the `content` feature; actions are
 * gated by content.* permissions. Route-model binding is tenant-scoped.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:content'])
    ->prefix('content')->name('content.')->group(function () {

        Route::get('/', ContentDashboardController::class)->middleware('can:content.view')->name('dashboard');

        // Content pieces + editorial workflow.
        Route::get('pieces', [ContentPieceController::class, 'index'])->middleware('can:content.view')->name('pieces.index');
        Route::post('pieces', [ContentPieceController::class, 'store'])->middleware('can:content.pieces.manage')->name('pieces.store');
        Route::get('pieces/{piece}', [ContentPieceController::class, 'show'])->middleware('can:content.view')->name('pieces.show');
        Route::patch('pieces/{piece}', [ContentPieceController::class, 'update'])->middleware('can:content.pieces.manage')->name('pieces.update');
        Route::post('pieces/{piece}/status', [ContentPieceController::class, 'status'])->middleware('can:content.pieces.manage')->name('pieces.status');
        // POD-004/009: multimedia promotion + clip schedules.
        Route::post('pieces/{piece}/promote', [ContentPieceController::class, 'promote'])->middleware('can:content.pieces.manage')->name('pieces.promote');
        Route::post('pieces/{piece}/clips', [ContentPieceController::class, 'clips'])->middleware('can:content.pieces.manage')->name('pieces.clips');
        Route::delete('pieces/{piece}', [ContentPieceController::class, 'destroy'])->middleware('can:content.pieces.manage')->name('pieces.destroy');

        // Social posts.
        Route::get('social', [SocialController::class, 'index'])->middleware('can:content.view')->name('social.index');
        Route::middleware('can:content.social.manage')->group(function () {
            Route::post('social', [SocialController::class, 'store'])->name('social.store');
            Route::post('social/{post}/schedule', [SocialController::class, 'schedule'])->name('social.schedule');
            Route::post('social/{post}/publish', [SocialController::class, 'publish'])->name('social.publish');
            Route::post('social/{post}/refresh-metrics', [SocialController::class, 'refreshMetrics'])->name('social.refresh-metrics');
            Route::delete('social/{post}', [SocialController::class, 'destroy'])->name('social.destroy');
        });

        // Reputation.
        Route::get('reputation', [ReputationController::class, 'index'])->middleware('can:content.view')->name('reputation.index');
        Route::middleware('can:content.reputation.manage')->group(function () {
            Route::post('reputation/reviews', [ReputationController::class, 'storeReview'])->name('reputation.reviews.store');
            Route::post('reputation/reviews/{review}/respond', [ReputationController::class, 'respond'])->name('reputation.reviews.respond');
            Route::post('reputation/import', [ReputationController::class, 'import'])->name('reputation.import');
            Route::post('reputation/requests', [ReputationController::class, 'storeRequest'])->name('reputation.requests.store');
            Route::post('reputation/requests/{reviewRequest}/send', [ReputationController::class, 'sendRequest'])->name('reputation.requests.send');
            Route::post('reputation/assets', [ReputationController::class, 'storeAsset'])->name('reputation.assets.store');
            Route::post('reputation/proof-page', [ReputationController::class, 'createProofPage'])->name('reputation.proof-page');
            Route::delete('reputation/assets/{asset}', [ReputationController::class, 'destroyAsset'])->name('reputation.assets.destroy');
        });

        // Outreach (PR + link building).
        Route::get('outreach', [OutreachController::class, 'index'])->middleware('can:content.view')->name('outreach.index');
        Route::middleware('can:content.outreach.manage')->group(function () {
            Route::post('outreach', [OutreachController::class, 'storeCampaign'])->name('outreach.store');
            Route::delete('outreach/{campaign}', [OutreachController::class, 'destroyCampaign'])->name('outreach.destroy');
            Route::post('outreach/{campaign}/prospects', [OutreachController::class, 'storeProspect'])->name('outreach.prospects.store');
            Route::post('outreach/prospects/{prospect}/status', [OutreachController::class, 'prospectStatus'])->name('outreach.prospects.status');
            Route::post('outreach/prospects/{prospect}/placement', [OutreachController::class, 'markPlacement'])->name('outreach.prospects.placement');
            Route::delete('outreach/prospects/{prospect}', [OutreachController::class, 'destroyProspect'])->name('outreach.prospects.destroy');
        });
    });
