<?php

use App\Http\Controllers\Platform\AiSettingsController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\PlatformController;
use Illuminate\Support\Facades\Route;

/*
 * Platform staff console (Stage 13, ADMIN). Deliberately outside the tenant
 * middleware: these surfaces span every organization and are reachable only by
 * platform staff holding `admin.platform` (platform Super Admins bypass the
 * Gate entirely, per ADR-0002).
 */
Route::middleware(['auth', 'verified'])->prefix('platform')->name('platform.')->group(function () {
    Route::middleware('can:admin.platform')->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('flags', [PlatformController::class, 'flags'])->name('flags');
        Route::post('flags', [PlatformController::class, 'saveFlag'])->name('flags.save');
        Route::get('announcements', [PlatformController::class, 'announcements'])->name('announcements');
        Route::post('announcements', [PlatformController::class, 'storeAnnouncement'])->name('announcements.store');

        // AI provider + credentials. Platform-scoped: tenants buy credits, the
        // operator owns the provider account those credits draw on.
        Route::get('ai', [AiSettingsController::class, 'edit'])->name('ai');
        Route::post('ai', [AiSettingsController::class, 'update'])->name('ai.save');
        Route::post('ai/test', [AiSettingsController::class, 'test'])->name('ai.test');
    });

    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])
        ->middleware('can:admin.impersonate')->name('impersonate.start');
});

/*
 * Stopping impersonation is intentionally NOT permission-gated: once inside an
 * impersonated session the acting user holds the target's permissions, so
 * requiring an admin permission here would trap them in the session.
 */
Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop');
