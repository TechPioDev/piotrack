<?php

use App\Http\Controllers\Delivery\PortalController;
use App\Http\Controllers\Delivery\ProjectController;
use App\Http\Controllers\Delivery\SupportController;
use App\Http\Controllers\Strategy\PerformanceController;
use App\Http\Controllers\Strategy\StrategyController;
use App\Http\Controllers\Strategy\TrainingController;
use Illuminate\Support\Facades\Route;

/*
 * Service delivery (Stage 13): projects, support, strategy/brand/training and
 * the performance model. Tenant-scoped and permission-gated. No plan
 * entitlement — these belong to the service engagement, not a plan tier.
 */
Route::middleware(['auth', 'verified', 'organization'])->group(function () {

    // Projects (PROJ).
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->middleware('can:projects.view')->name('index');

        Route::middleware('can:projects.manage')->group(function () {
            Route::post('/', [ProjectController::class, 'store'])->name('store');
            Route::patch('{project}', [ProjectController::class, 'update'])->name('update');
            Route::post('{project}/members', [ProjectController::class, 'assign'])->name('members.store');
            Route::post('{project}/sprints', [ProjectController::class, 'storeSprint'])->name('sprints.store');
            Route::post('{project}/tasks', [ProjectController::class, 'storeTask'])->name('tasks.store');
            Route::patch('tasks/{task}', [ProjectController::class, 'updateTask'])->name('tasks.update');
            Route::post('{project}/deliverables', [ProjectController::class, 'storeDeliverable'])->name('deliverables.store');
            Route::post('deliverables/{deliverable}/submit', [ProjectController::class, 'submitDeliverable'])->name('deliverables.submit');
        });

        Route::middleware('can:projects.approve')->group(function () {
            Route::post('deliverables/{deliverable}/approve', [ProjectController::class, 'approveDeliverable'])->name('deliverables.approve');
            Route::post('deliverables/{deliverable}/reject', [ProjectController::class, 'rejectDeliverable'])->name('deliverables.reject');
        });
    });

    // Support (SUPP).
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [SupportController::class, 'index'])->middleware('can:support.view')->name('index');
        Route::middleware('can:support.manage')->group(function () {
            Route::post('tickets', [SupportController::class, 'store'])->name('tickets.store');
            Route::post('tickets/{ticket}/reply', [SupportController::class, 'reply'])->name('tickets.reply');
            Route::post('tickets/{ticket}/assign', [SupportController::class, 'assign'])->name('tickets.assign');
            Route::post('tickets/{ticket}/resolve', [SupportController::class, 'resolve'])->name('tickets.resolve');
            Route::post('articles', [SupportController::class, 'storeArticle'])->name('articles.store');
        });
    });

    // Strategy / brand / training / performance (STRAT, BRAND, TRAIN, METH, PERF).
    Route::prefix('strategy')->name('strategy.')->group(function () {
        Route::middleware('can:strategy.view')->group(function () {
            Route::get('/', [StrategyController::class, 'index'])->name('index');
            Route::get('brand', [StrategyController::class, 'brand'])->name('brand');
            Route::get('brand/style-guide.pdf', [StrategyController::class, 'styleGuide'])->name('brand.style-guide');
            Route::get('performance', [PerformanceController::class, 'index'])->name('performance');

            // Training courses (TRAIN): reading and personal completion.
            Route::get('training', [TrainingController::class, 'index'])->name('training.index');
            Route::get('training/{course}', [TrainingController::class, 'show'])->name('training.show');
            Route::post('training/lessons/{lesson}/complete', [TrainingController::class, 'complete'])->name('training.complete');
            Route::delete('training/lessons/{lesson}/complete', [TrainingController::class, 'uncomplete'])->name('training.uncomplete');
        });

        Route::middleware('can:strategy.manage')->group(function () {
            // Training authoring (TRAIN).
            Route::post('training', [TrainingController::class, 'store'])->name('training.store');
            Route::post('training/seed', [TrainingController::class, 'seed'])->name('training.seed');
            Route::patch('training/{course}', [TrainingController::class, 'update'])->name('training.update');
            Route::delete('training/{course}', [TrainingController::class, 'destroy'])->name('training.destroy');
            Route::post('training/{course}/lessons', [TrainingController::class, 'storeLesson'])->name('training.lessons.store');
            Route::delete('training/lessons/{lesson}', [TrainingController::class, 'destroyLesson'])->name('training.lessons.destroy');

            Route::post('plans', [StrategyController::class, 'storePlan'])->name('plans.store');
            Route::post('items', [StrategyController::class, 'storeItem'])->name('items.store');
            Route::patch('items/{item}', [StrategyController::class, 'updateItem'])->name('items.update');
            Route::delete('items/{item}', [StrategyController::class, 'destroyItem'])->name('items.destroy');
            Route::post('targets', [StrategyController::class, 'storeTarget'])->name('targets.store');
            Route::delete('targets/{target}', [StrategyController::class, 'destroyTarget'])->name('targets.destroy');
            Route::post('brand', [StrategyController::class, 'saveBrand'])->name('brand.save');
            Route::post('brand/assets', [StrategyController::class, 'storeAsset'])->name('brand.assets.store');
            Route::delete('brand/assets/{asset}', [StrategyController::class, 'destroyAsset'])->name('brand.assets.destroy');
            Route::post('engagements', [StrategyController::class, 'storeEngagement'])->name('engagements.store');
            Route::patch('engagements/{engagement}', [StrategyController::class, 'updateEngagement'])->name('engagements.update');
            Route::post('performance', [PerformanceController::class, 'store'])->name('performance.store');
            Route::post('performance/{agreement}/replace-lead', [PerformanceController::class, 'replaceLead'])->name('performance.replace-lead');
            Route::delete('performance/{agreement}', [PerformanceController::class, 'destroy'])->name('performance.destroy');
        });
    });

    /*
     * The client portal (PORTAL). Gated by `portal.access`, which only the
     * Client role holds by default. Everything served here is narrowed to
     * client-visible records by PortalService.
     */
    Route::prefix('portal')->name('portal.')->middleware('can:portal.access')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('projects', [PortalController::class, 'projects'])->name('projects');
        Route::get('support', [PortalController::class, 'support'])->name('support');
        Route::post('support/tickets', [PortalController::class, 'openTicket'])->name('support.tickets.store');
        Route::post('deliverables/{deliverable}/approve', [PortalController::class, 'approve'])->name('deliverables.approve');
        Route::post('deliverables/{deliverable}/reject', [PortalController::class, 'reject'])->name('deliverables.reject');
    });
});
