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
    // An <img> the widget draws on the customer's site: every visitor fetches it,
    // so the allowance is generous and the response is cached by the browser.
    Route::get('logo', [PublicChatController::class, 'logo'])
        ->middleware('throttle:240,1')->name('logo');
    Route::post('events', [PublicChatController::class, 'event'])
        ->middleware('throttle:60,1')->name('event');
    Route::post('conversations', [PublicChatController::class, 'start'])
        ->middleware('throttle:20,1')->name('start');
    Route::post('conversations/{token}/messages', [PublicChatController::class, 'message'])
        ->middleware('throttle:60,1')->name('message');
    // Team replies are delivered by polling: there is no websocket server in
    // this stack, and the product runs on isolated networks. Allowance is
    // generous because an open chat polls every few seconds - and it has its
    // own counter: unnamed throttles share one per visitor IP, so without a
    // prefix a few minutes of polling would use up the allowance of the
    // routes below it.
    Route::get('conversations/{token}/poll', [PublicChatController::class, 'poll'])
        ->middleware('throttle:240,1,chat-poll')->name('poll');
    // How the chat went, once it has finished. Its own counter, and low: a
    // visitor rates a conversation once, twice if they change their mind.
    Route::post('conversations/{token}/rating', [PublicChatController::class, 'rate'])
        ->middleware('throttle:10,1,chat-rating')->name('rating');
    // A file the visitor attaches: tightly throttled on its own counter,
    // size-capped and scanned.
    Route::post('conversations/{token}/files', [PublicChatController::class, 'upload'])
        ->middleware('throttle:10,1,chat-files')->name('files');
    // A file from the conversation, for the visitor's own chat window (a picture
    // is drawn in the conversation, so reopening a chat loads each one again).
    Route::get('conversations/{token}/files/{message}', [PublicChatController::class, 'file'])
        ->whereNumber('message')
        ->middleware('throttle:120,1,chat-file-view')->name('file');
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
        Route::get('conversations/{conversation}/transcript', [ChatInboxController::class, 'transcript'])
            ->middleware('can:chat.view')->name('conversations.transcript');
        Route::post('conversations/{conversation}/reply', [ChatInboxController::class, 'reply'])
            ->middleware('can:chat.inbox.handle')->name('conversations.reply');
        Route::post('conversations/{conversation}/note', [ChatInboxController::class, 'note'])
            ->middleware('can:chat.inbox.handle')->name('conversations.note');
        // The answers a team types over and over, kept once and shared.
        Route::post('saved-replies', [ChatInboxController::class, 'storeSavedReply'])
            ->middleware('can:chat.inbox.handle')->name('saved-replies.store');
        Route::delete('saved-replies/{savedReply}', [ChatInboxController::class, 'destroySavedReply'])
            ->middleware('can:chat.inbox.handle')->name('saved-replies.destroy');
        Route::post('conversations/{conversation}/summarize', [ChatInboxController::class, 'summarize'])
            ->middleware('can:chat.inbox.handle')->name('conversations.summarize');
        Route::patch('conversations/{conversation}', [ChatInboxController::class, 'update'])
            ->middleware('can:chat.inbox.handle')->name('conversations.update');
        Route::get('conversations/{conversation}/poll', [ChatInboxController::class, 'poll'])
            ->middleware('can:chat.view')->name('conversations.poll');
        Route::get('conversations/{conversation}/files/{message}', [ChatInboxController::class, 'file'])
            ->middleware('can:chat.view')->name('conversations.files.show');

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
        Route::post('widgets/{widget}/logo', [ChatWidgetController::class, 'uploadLogo'])
            ->middleware('can:chat.widget.manage')->name('widgets.logo.store');
        Route::delete('widgets/{widget}/logo', [ChatWidgetController::class, 'removeLogo'])
            ->middleware('can:chat.widget.manage')->name('widgets.logo.destroy');
        Route::delete('widgets/{widget}', [ChatWidgetController::class, 'destroy'])
            ->middleware('can:chat.widget.manage')->name('widgets.destroy');
    });
