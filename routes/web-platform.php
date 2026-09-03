<?php

use App\Http\Controllers\Web\SiteController;
use Illuminate\Support\Facades\Route;

/*
 * Website platform, service lines, verticals and locations (Stage 15).
 * Gated by the existing `marketing` entitlement — a customer paying for
 * marketing expects their site and landing pages included — plus web.* permissions.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:marketing'])
    ->prefix('website')->name('web.')->group(function () {

        Route::middleware('can:web.view')->group(function () {
            Route::get('/', [SiteController::class, 'index'])->name('pages.index');
            Route::get('taxonomy', [SiteController::class, 'taxonomy'])->name('taxonomy.index');
        });

        Route::middleware('can:web.pages.manage')->group(function () {
            Route::post('pages', [SiteController::class, 'store'])->name('pages.store');
            Route::post('pages/from-template', [SiteController::class, 'storeFromTemplate'])->name('pages.template');
            Route::patch('pages/{page}', [SiteController::class, 'update'])->name('pages.update');
            Route::delete('pages/{page}', [SiteController::class, 'destroy'])->name('pages.destroy');
            Route::post('pages/{page}/sections', [SiteController::class, 'storeSection'])->name('sections.store');
            Route::patch('sections/{section}', [SiteController::class, 'updateSection'])->name('sections.update');
            Route::delete('sections/{section}', [SiteController::class, 'destroySection'])->name('sections.destroy');
            Route::post('pages/{page}/sections/reorder', [SiteController::class, 'reorderSections'])->name('sections.reorder');
            Route::post('pages/{page}/publish', [SiteController::class, 'publish'])->name('pages.publish');
            Route::post('pages/{page}/unpublish', [SiteController::class, 'unpublish'])->name('pages.unpublish');
            Route::post('navigation', [SiteController::class, 'storeNavigation'])->name('navigation.store');
            Route::delete('navigation/{item}', [SiteController::class, 'destroyNavigation'])->name('navigation.destroy');
        });

        Route::middleware('can:web.taxonomy.manage')->group(function () {
            Route::post('taxonomy/provision', [SiteController::class, 'provisionTaxonomy'])->name('taxonomy.provision');
            Route::post('locations', [SiteController::class, 'storeLocation'])->name('locations.store');
        });
    });
