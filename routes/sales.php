<?php

use App\Http\Controllers\Sales\AccountController;
use App\Http\Controllers\Sales\AlertController;
use App\Http\Controllers\Sales\BookingController;
use App\Http\Controllers\Sales\EnablementController;
use App\Http\Controllers\Sales\IntentController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Sales\ScoringController;
use App\Http\Controllers\Sales\VisitorController;
use Illuminate\Support\Facades\Route;

/*
 * Sales (Stage 10). Requires an authenticated, verified user with an active
 * organization on a plan that includes the `sales` feature; actions are gated
 * by sales.* permissions. Route-model binding is tenant-scoped.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:sales'])
    ->prefix('sales')->name('sales.')->group(function () {

        Route::get('/', SalesDashboardController::class)->middleware('can:sales.view')->name('dashboard');

        // Lead scoring.
        Route::get('scoring', [ScoringController::class, 'index'])->middleware('can:sales.view')->name('scoring.index');
        Route::middleware('can:sales.scoring.manage')->group(function () {
            Route::post('scoring', [ScoringController::class, 'store'])->name('scoring.store');
            Route::patch('scoring/{rule}', [ScoringController::class, 'update'])->name('scoring.update');
            Route::delete('scoring/{rule}', [ScoringController::class, 'destroy'])->name('scoring.destroy');
            Route::post('scoring/recompute', [ScoringController::class, 'recompute'])->name('scoring.recompute');
        });

        // Visitor Intelligence (VINT).
        Route::get('visitors', [VisitorController::class, 'index'])->middleware('can:sales.view')->name('visitors.index');

        // Buyer intent.
        Route::get('intent', [IntentController::class, 'index'])->middleware('can:sales.view')->name('intent.index');
        Route::post('intent', [IntentController::class, 'store'])->middleware('can:sales.scoring.manage')->name('intent.store');

        // Alerts.
        Route::get('alerts', [AlertController::class, 'index'])->middleware('can:sales.view')->name('alerts.index');
        Route::post('alerts/{alert}/read', [AlertController::class, 'markRead'])->middleware('can:sales.view')->name('alerts.read');
        Route::middleware('can:sales.alerts.manage')->group(function () {
            Route::post('alerts', [AlertController::class, 'store'])->name('alerts.store');
            Route::put('alerts/channels', [AlertController::class, 'updateChannels'])->name('alerts.channels');
            Route::delete('alerts/{rule}', [AlertController::class, 'destroy'])->name('alerts.destroy');
        });

        // Booking.
        Route::get('booking', [BookingController::class, 'index'])->middleware('can:sales.view')->name('booking.index');
        Route::middleware('can:sales.booking.manage')->group(function () {
            Route::post('booking', [BookingController::class, 'storePage'])->name('booking.store');
            Route::post('booking/{bookingPage}/publish', [BookingController::class, 'publishPage'])->name('booking.publish');
            Route::delete('booking/{bookingPage}', [BookingController::class, 'destroyPage'])->name('booking.destroy');
            Route::post('booking/appointments/{booking}/status', [BookingController::class, 'bookingStatus'])->name('booking.status');
        });

        // Enablement.
        Route::get('enablement', [EnablementController::class, 'index'])->middleware('can:sales.view')->name('enablement.index');
        Route::middleware('can:sales.enablement.manage')->group(function () {
            Route::post('enablement/assets', [EnablementController::class, 'storeAsset'])->name('enablement.assets.store');
            // ENAB-008/014: the ROI calculator + proposal generation.
            Route::post('enablement/roi', [EnablementController::class, 'roi'])->name('enablement.roi');
            Route::post('enablement/proposal', [EnablementController::class, 'generateProposal'])->name('enablement.proposal');
            Route::delete('enablement/assets/{asset}', [EnablementController::class, 'destroyAsset'])->name('enablement.assets.destroy');
            Route::post('enablement/plays', [EnablementController::class, 'storePlay'])->name('enablement.plays.store');
            Route::delete('enablement/plays/{play}', [EnablementController::class, 'destroyPlay'])->name('enablement.plays.destroy');
        });

        // ABM accounts.
        Route::get('accounts', [AccountController::class, 'index'])->middleware('can:sales.view')->name('accounts.index');
        Route::get('accounts/{account}/report', [AccountController::class, 'report'])->middleware('can:sales.view')->name('accounts.report');
        Route::middleware('can:sales.accounts.manage')->group(function () {
            Route::post('accounts/sync-list', [AccountController::class, 'syncList'])->name('accounts.sync-list');
            // ABM-007/011/012/016/019.
            Route::patch('accounts/contacts/{contact}/manager', [AccountController::class, 'setManager'])->name('accounts.manager');
            Route::post('accounts/{account}/content', [AccountController::class, 'attachContent'])->name('accounts.content.attach');
            Route::get('accounts/linkedin-export', [AccountController::class, 'linkedinExport'])->name('accounts.linkedin-export');
            Route::post('accounts/{account}/play', [AccountController::class, 'runPlay'])->name('accounts.play');
            Route::post('accounts/{account}/page', [AccountController::class, 'createPage'])->name('accounts.page.create');
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::post('accounts/{account}/rescore', [AccountController::class, 'rescore'])->name('accounts.rescore');
            Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        });
    });
