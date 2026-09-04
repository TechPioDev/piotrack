<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\BillingProfileController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\OnboardingSetupController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\FileController;
use App\Http\Controllers\Settings\FranchiseController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\InvitationController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\TeamController;
use Illuminate\Support\Facades\Route;

/*
 * Tenant-scoped organization management. Every route requires an authenticated,
 * verified user with an active organization; individual actions are gated by
 * RBAC permissions (RBAC-004). Route-model binding for {invitation} and {team}
 * is automatically scoped to the current tenant via BelongsToTenant.
 */
Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    // Organization profile & deletion.
    Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->middleware('can:organization.view')->name('organization.edit');
    Route::patch('settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware('can:organization.update')->name('organization.update');
    Route::delete('settings/organization', [OrganizationSettingsController::class, 'destroy'])
        ->middleware('can:organization.delete')->name('organization.destroy');

    // Guided setup wizard (ONBD-006..012). Setup decides taxonomy, goals,
    // scoring and competitors for the whole tenant, so it is an admin act.
    Route::prefix('onboarding')->middleware('can:organization.update')->group(function () {
        Route::get('setup', [OnboardingSetupController::class, 'show'])->name('onboarding.setup');
        Route::post('setup/business-profile', [OnboardingSetupController::class, 'businessProfile'])->name('onboarding.business');
        Route::post('setup/website', [OnboardingSetupController::class, 'website'])->name('onboarding.website');
        Route::post('setup/goals', [OnboardingSetupController::class, 'goals'])->name('onboarding.goals');
        Route::post('setup/icp', [OnboardingSetupController::class, 'icp'])->name('onboarding.icp');
        Route::post('setup/competitors', [OnboardingSetupController::class, 'competitors'])->name('onboarding.competitors');
        Route::post('setup/complete', [OnboardingSetupController::class, 'complete'])->name('onboarding.complete');
    });

    // Franchise hierarchy (MLOC-009).
    Route::get('settings/franchise', [FranchiseController::class, 'index'])
        ->middleware('can:organization.view')->name('franchise.index');
    Route::post('settings/franchise/link', [FranchiseController::class, 'link'])
        ->middleware('can:organization.update')->name('franchise.link');
    Route::delete('settings/franchise/{child}', [FranchiseController::class, 'unlink'])
        ->middleware('can:organization.update')->name('franchise.unlink');
    Route::post('settings/franchise/{child}/push-brand', [FranchiseController::class, 'pushBrand'])
        ->middleware('can:organization.update')->name('franchise.push');

    // Members.
    Route::get('settings/members', [MemberController::class, 'index'])
        ->middleware('can:members.view')->name('members.index');
    Route::patch('settings/members/{member}/role', [MemberController::class, 'updateRole'])
        ->middleware('can:members.update')->name('members.role');
    Route::patch('settings/members/{member}/deactivate', [MemberController::class, 'deactivate'])
        ->middleware('can:members.update')->name('members.deactivate');
    Route::patch('settings/members/{member}/reactivate', [MemberController::class, 'reactivate'])
        ->middleware('can:members.update')->name('members.reactivate');
    Route::delete('settings/members/{member}', [MemberController::class, 'destroy'])
        ->middleware('can:members.remove')->name('members.destroy');

    // Invitations.
    Route::post('settings/members/invitations', [InvitationController::class, 'store'])
        ->middleware('can:members.invite')->name('invitations.store');
    Route::post('settings/members/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
        ->middleware('can:members.invite')->name('invitations.resend');
    Route::delete('settings/members/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->middleware('can:members.invite')->name('invitations.destroy');

    // Teams (also gated by the `teams` feature entitlement).
    Route::middleware('entitlement:teams')->group(function () {
        Route::get('settings/teams', [TeamController::class, 'index'])
            ->middleware('can:teams.view')->name('teams.index');
        Route::post('settings/teams', [TeamController::class, 'store'])
            ->middleware('can:teams.manage')->name('teams.store');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])
            ->middleware('can:teams.manage')->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware('can:teams.manage')->name('teams.destroy');
        Route::post('settings/teams/{team}/members', [TeamController::class, 'addMember'])
            ->middleware('can:teams.manage')->name('teams.members.add');
        Route::delete('settings/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])
            ->middleware('can:teams.manage')->name('teams.members.remove');
    });

    // Audit log viewer (also gated by the `audit_log` feature entitlement).
    Route::get('settings/audit-log', [AuditLogController::class, 'index'])
        ->middleware(['can:audit.view', 'entitlement:audit_log'])->name('audit.index');

    // Files.
    Route::get('settings/files', [FileController::class, 'index'])
        ->middleware('can:files.view')->name('files.index');
    Route::get('settings/files/{file}/download', [FileController::class, 'download'])
        ->middleware('can:files.view')->name('files.download');
    Route::post('settings/files', [FileController::class, 'store'])
        ->middleware('can:files.manage')->name('files.store');
    Route::delete('settings/files/{file}', [FileController::class, 'destroy'])
        ->middleware('can:files.manage')->name('files.destroy');

    // Integrations / connectors (INTG). Viewing is read-only; connect/sync/
    // disconnect require integrations.manage.
    Route::get('settings/integrations', [IntegrationController::class, 'index'])
        ->middleware('can:integrations.view')->name('integrations.index');
    Route::middleware('can:integrations.manage')->group(function () {
        Route::post('settings/integrations/connect', [IntegrationController::class, 'connect'])
            ->name('integrations.connect');
        Route::post('settings/integrations/{integration}/disconnect', [IntegrationController::class, 'disconnect'])
            ->name('integrations.disconnect');
        Route::post('settings/integrations/{integration}/reconnect', [IntegrationController::class, 'reconnect'])
            ->name('integrations.reconnect');
        Route::post('settings/integrations/{integration}/sync', [IntegrationController::class, 'sync'])
            ->name('integrations.sync');
        Route::post('settings/integrations/{integration}/queue-sync', [IntegrationController::class, 'queueSync'])
            ->name('integrations.queue-sync');

        // Outbound webhooks (INTG-009) + generic OAuth2 connect (INTG-001).
        Route::post('settings/integrations/webhooks', [IntegrationController::class, 'storeWebhook'])
            ->name('integrations.webhooks.store');
        Route::delete('settings/integrations/webhooks/{webhook}', [IntegrationController::class, 'destroyWebhook'])
            ->name('integrations.webhooks.destroy');
        Route::post('settings/integrations/webhooks/{webhook}/test', [IntegrationController::class, 'testWebhook'])
            ->name('integrations.webhooks.test');
        Route::get('settings/integrations/oauth/{provider}', [IntegrationController::class, 'oauthRedirect'])
            ->name('integrations.oauth.redirect');
        Route::get('settings/integrations/oauth/{provider}/callback', [IntegrationController::class, 'oauthCallback'])
            ->name('integrations.oauth.callback');
    });

    // Billing & subscriptions.
    Route::get('billing', [BillingController::class, 'index'])
        ->middleware('can:billing.view')->name('billing.index');
    Route::get('billing/plans', [BillingController::class, 'plans'])
        ->middleware('can:billing.view')->name('billing.plans');
    Route::get('billing/invoices', [InvoiceController::class, 'index'])
        ->middleware('can:billing.view')->name('billing.invoices.index');
    Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('can:billing.view')->name('billing.invoices.show');

    Route::middleware('can:billing.manage')->group(function () {
        Route::get('billing/checkout', [CheckoutController::class, 'show'])->name('billing.checkout.show');
        Route::post('billing/checkout', [CheckoutController::class, 'store'])->name('billing.checkout.store');
        Route::patch('billing/subscription', [SubscriptionController::class, 'update'])->name('billing.subscription.update');
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('billing.subscription.cancel');
        Route::post('billing/subscription/resume', [SubscriptionController::class, 'resume'])->name('billing.subscription.resume');
        Route::patch('billing/profile', [BillingProfileController::class, 'update'])->name('billing.profile.update');
        // BILL-005/018: add-ons and provider-hosted payment-method management.
        Route::post('billing/addons', [SubscriptionController::class, 'addAddon'])->name('billing.addons.store');
        Route::delete('billing/addons', [SubscriptionController::class, 'removeAddon'])->name('billing.addons.destroy');
        Route::get('billing/payment-method', [SubscriptionController::class, 'paymentMethod'])->name('billing.payment-method');
    });
});
