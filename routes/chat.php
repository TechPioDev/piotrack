<?php

use App\Http\Controllers\Chat\ChatAnalyticsController;
use App\Http\Controllers\Chat\ChatFlowController;
use App\Http\Controllers\Chat\ChatInboxController;
use App\Http\Controllers\Chat\ChatPresenceController;
use App\Http\Controllers\Chat\ChatWidgetController;
use App\Http\Controllers\Public\PublicChatController;
use Illuminate\Support\Facades\Route;

/*
 * Website Chat / Conversations module.
 *
 * Public widget API first: unauthenticated + cross-origin (the widget runs on the
 * customer's own site). The tenant is resolved from the widget public key inside
 * the controller; CSRF is exempted for wc/* in bootstrap/app.php; CORS for wc/*
 * comes from config/cors.php. Throttled like the other public capture routes.
 */
Route::prefix('wc/{publicKey}')->name('public.chat.')->group(function () {
    Route::get('config', [PublicChatController::class, 'config'])
        ->middleware('throttle:60,1')->name('config');
    Route::post('events', [PublicChatController::class, 'event'])
        ->middleware('throttle:60,1')->name('event');
    Route::post('conversations', [PublicChatController::class, 'start'])
        ->middleware('throttle:20,1')->name('start');
    Route::post('conversations/{token}/messages', [PublicChatController::class, 'message'])
        ->middleware('throttle:60,1')->name('message');
    // Live chat is delivered by polling: there is no websocket server in this
    // stack, and the product runs on isolated networks. Allowance is generous
    // because an open chat polls every few seconds.
    Route::get('conversations/{token}/poll', [PublicChatController::class, 'poll'])
        ->middleware('throttle:240,1')->name('poll');
});

/*
 * Authenticated admin area. Same stack as every module: tenant context, plan
 * entitlement on the group, per-route permission checks.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:chat'])
    ->prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatInboxController::class, 'index'])
            ->middleware('can:chat.view')->name('inbox');
        Route::get('conversations/{conversation}', [ChatInboxController::class, 'show'])
            ->middleware('can:chat.view')->name('conversations.show');
        Route::post('conversations/{conversation}/reply', [ChatInboxController::class, 'reply'])
            ->middleware('can:chat.inbox.handle')->name('conversations.reply');
        Route::post('conversations/{conversation}/note', [ChatInboxController::class, 'note'])
            ->middleware('can:chat.inbox.handle')->name('conversations.note');
        Route::post('conversations/{conversation}/summarize', [ChatInboxController::class, 'summarize'])
            ->middleware('can:chat.inbox.handle')->name('conversations.summarize');
        Route::patch('conversations/{conversation}', [ChatInboxController::class, 'update'])
            ->middleware('can:chat.inbox.handle')->name('conversations.update');
        Route::get('conversations/{conversation}/poll', [ChatInboxController::class, 'poll'])
            ->middleware('can:chat.view')->name('conversations.poll');

        // An agent's own availability. Anyone who can work the inbox may set it.
        Route::post('presence', [ChatPresenceController::class, 'update'])
            ->middleware('can:chat.inbox.handle')->name('presence.update');
        Route::post('presence/heartbeat', [ChatPresenceController::class, 'heartbeat'])
            ->middleware('can:chat.inbox.handle')->name('presence.heartbeat');

        Route::get('widgets', [ChatWidgetController::class, 'index'])
            ->middleware('can:chat.view')->name('widgets.index');
        Route::get('analytics', ChatAnalyticsController::class)
            ->middleware('can:chat.view')->name('analytics');
        Route::get('widgets/{widget}/settings', [ChatWidgetController::class, 'edit'])
            ->middleware('can:chat.widget.manage')->name('widgets.edit');

        // Flow builder. Everything here shapes what visitors see, so it is
        // management-only — including the preview, which writes a conversation row.
        Route::middleware('can:chat.widget.manage')->group(function () {
            Route::get('widgets/{widget}/flow', [ChatFlowController::class, 'edit'])->name('flow.edit');
            Route::put('widgets/{widget}/flow', [ChatFlowController::class, 'update'])->name('flow.update');
            Route::post('widgets/{widget}/flow/validate', [ChatFlowController::class, 'validateFlow'])->name('flow.validate');
            Route::post('widgets/{widget}/flow/template', [ChatFlowController::class, 'applyTemplate'])->name('flow.template');
            Route::post('widgets/{widget}/flow/test', [ChatFlowController::class, 'test'])->name('flow.test');
        });
        Route::post('widgets', [ChatWidgetController::class, 'store'])
            ->middleware('can:chat.widget.manage')->name('widgets.store');
        Route::patch('widgets/{widget}', [ChatWidgetController::class, 'update'])
            ->middleware('can:chat.widget.manage')->name('widgets.update');
        Route::delete('widgets/{widget}', [ChatWidgetController::class, 'destroy'])
            ->middleware('can:chat.widget.manage')->name('widgets.destroy');
    });
