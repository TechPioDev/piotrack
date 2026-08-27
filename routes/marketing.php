<?php

use App\Http\Controllers\Marketing\CampaignController;
use App\Http\Controllers\Marketing\FormController;
use App\Http\Controllers\Marketing\FunnelController;
use App\Http\Controllers\Marketing\LandingPageController;
use App\Http\Controllers\Marketing\ListController;
use App\Http\Controllers\Marketing\MarketingDashboardController;
use App\Http\Controllers\Marketing\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
 * Marketing platform (Stage 6). Every route requires an authenticated, verified
 * user with an active organization on a plan that includes the `marketing`
 * feature; individual actions are gated by marketing.* permissions. Workflow
 * automation additionally requires the `automation` feature. Route-model
 * binding is tenant-scoped via BelongsToTenant.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:marketing'])
    ->prefix('marketing')->name('marketing.')->group(function () {

        Route::get('/', MarketingDashboardController::class)->middleware('can:marketing.view')->name('dashboard');

        // Lists / segments.
        Route::get('lists', [ListController::class, 'index'])->middleware('can:marketing.view')->name('lists.index');
        Route::post('lists', [ListController::class, 'store'])->middleware('can:marketing.lists.manage')->name('lists.store');
        Route::get('lists/{list}', [ListController::class, 'show'])->middleware('can:marketing.view')->name('lists.show');
        Route::patch('lists/{list}', [ListController::class, 'update'])->middleware('can:marketing.lists.manage')->name('lists.update');
        Route::delete('lists/{list}', [ListController::class, 'destroy'])->middleware('can:marketing.lists.manage')->name('lists.destroy');
        Route::post('lists/{list}/contacts', [ListController::class, 'addContact'])->middleware('can:marketing.lists.manage')->name('lists.contacts.add');
        Route::delete('lists/{list}/contacts/{contact}', [ListController::class, 'removeContact'])->middleware('can:marketing.lists.manage')->name('lists.contacts.remove');

        // Forms.
        Route::get('forms', [FormController::class, 'index'])->middleware('can:marketing.view')->name('forms.index');
        Route::post('forms', [FormController::class, 'store'])->middleware('can:marketing.forms.manage')->name('forms.store');
        Route::patch('forms/{form}', [FormController::class, 'update'])->middleware('can:marketing.forms.manage')->name('forms.update');
        Route::post('forms/{form}/publish', [FormController::class, 'publish'])->middleware('can:marketing.forms.manage')->name('forms.publish');
        Route::delete('forms/{form}', [FormController::class, 'destroy'])->middleware('can:marketing.forms.manage')->name('forms.destroy');

        // Landing pages.
        Route::get('landing-pages', [LandingPageController::class, 'index'])->middleware('can:marketing.view')->name('landing-pages.index');
        Route::post('landing-pages', [LandingPageController::class, 'store'])->middleware('can:marketing.forms.manage')->name('landing-pages.store');
        Route::patch('landing-pages/{landingPage}', [LandingPageController::class, 'update'])->middleware('can:marketing.forms.manage')->name('landing-pages.update');
        Route::post('landing-pages/{landingPage}/publish', [LandingPageController::class, 'publish'])->middleware('can:marketing.forms.manage')->name('landing-pages.publish');
        Route::delete('landing-pages/{landingPage}', [LandingPageController::class, 'destroy'])->middleware('can:marketing.forms.manage')->name('landing-pages.destroy');

        // Campaigns.
        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('can:marketing.view')->name('campaigns.index');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('can:marketing.campaigns.manage')->name('campaigns.store');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('can:marketing.view')->name('campaigns.show');
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('can:marketing.campaigns.manage')->name('campaigns.update');
        Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->middleware('can:marketing.campaigns.send')->name('campaigns.send');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('can:marketing.campaigns.manage')->name('campaigns.destroy');

        // Funnels.
        Route::get('funnels', [FunnelController::class, 'index'])->middleware('can:marketing.funnels.view')->name('funnels.index');
        Route::post('funnels', [FunnelController::class, 'store'])->middleware('can:marketing.campaigns.manage')->name('funnels.store');
        Route::delete('funnels/{funnel}', [FunnelController::class, 'destroy'])->middleware('can:marketing.campaigns.manage')->name('funnels.destroy');
        // Funnel Builder (FUNL): the funnel as an executable, measured map.
        Route::get('funnels/{funnel}', [FunnelController::class, 'show'])->middleware('can:marketing.funnels.view')->name('funnels.show');
        Route::post('funnels/{funnel}/stages/{stage}/assets', [FunnelController::class, 'attachAsset'])->middleware('can:marketing.campaigns.manage')->name('funnels.assets.attach');
        Route::delete('funnels/{funnel}/stages/{stage}/assets/{asset}', [FunnelController::class, 'detachAsset'])->middleware('can:marketing.campaigns.manage')->name('funnels.assets.detach');

        // Automation / workflows (also gated by the `automation` feature).
        Route::middleware('entitlement:automation')->group(function () {
            Route::get('automation', [WorkflowController::class, 'index'])->middleware('can:marketing.view')->name('automation.index');
            Route::post('automation', [WorkflowController::class, 'store'])->middleware('can:marketing.automation.manage')->name('automation.store');
            Route::get('automation/{workflow}', [WorkflowController::class, 'show'])->middleware('can:marketing.view')->name('automation.show');
            Route::patch('automation/{workflow}', [WorkflowController::class, 'update'])->middleware('can:marketing.automation.manage')->name('automation.update');
            Route::post('automation/{workflow}/toggle', [WorkflowController::class, 'toggle'])->middleware('can:marketing.automation.manage')->name('automation.toggle');
            Route::post('automation/{workflow}/steps', [WorkflowController::class, 'addStep'])->middleware('can:marketing.automation.manage')->name('automation.steps.add');
            Route::delete('automation/{workflow}/steps/{step}', [WorkflowController::class, 'deleteStep'])->middleware('can:marketing.automation.manage')->name('automation.steps.delete');
            Route::delete('automation/{workflow}', [WorkflowController::class, 'destroy'])->middleware('can:marketing.automation.manage')->name('automation.destroy');
        });
    });
