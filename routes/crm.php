<?php

use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactExportController;
use App\Http\Controllers\Crm\ContactImportController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\EntityExportController;
use App\Http\Controllers\Crm\EntityImportController;
use App\Http\Controllers\Crm\LeadController;
use Illuminate\Support\Facades\Route;

/*
 * CRM module. Every route requires an authenticated, verified user with an
 * active organization on a plan that includes the `crm` feature; individual
 * actions are gated by crm.* permissions. Route-model binding is tenant-scoped
 * via BelongsToTenant on each model.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:crm'])
    ->prefix('crm')->name('crm.')->group(function () {

        // Contacts — static routes before {contact} binding.
        Route::get('contacts', [ContactController::class, 'index'])->middleware('can:crm.contact.read')->name('contacts.index');
        Route::get('contacts/export', ContactExportController::class)->middleware('can:crm.contact.read')->name('contacts.export');
        Route::get('contacts/import', [ContactImportController::class, 'create'])->middleware('can:crm.import')->name('contacts.import');
        Route::post('contacts/import/preview', [ContactImportController::class, 'preview'])->middleware('can:crm.import')->name('contacts.import.preview');
        Route::post('contacts/import', [ContactImportController::class, 'store'])->middleware('can:crm.import')->name('contacts.import.store');
        Route::post('contacts', [ContactController::class, 'store'])->middleware('can:crm.contact.create')->name('contacts.store');
        // Bulk actions (CRMT): delete inside additionally re-checks crm.contact.delete.
        Route::post('contacts/bulk', [ContactController::class, 'bulk'])->middleware('can:crm.contact.update')->name('contacts.bulk');
        // Saved views are personal; reading rights are enough to manage your own.
        Route::post('contacts/views', [ContactController::class, 'storeView'])->middleware('can:crm.contact.read')->name('contacts.views.store');
        Route::delete('contacts/views/{view}', [ContactController::class, 'destroyView'])->middleware('can:crm.contact.read')->name('contacts.views.destroy');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->middleware('can:crm.contact.read')->name('contacts.show');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:crm.contact.update')->name('contacts.update');
        // VID-016: personalized sales video over the real dispatcher.
        Route::post('contacts/{contact}/video-message', [ContactController::class, 'videoMessage'])->middleware('can:crm.contact.update')->name('contacts.video-message');
        // CRM-027: enrichment through the provider seam (set-once fills).
        Route::post('contacts/{contact}/enrich', [ContactController::class, 'enrich'])->middleware('can:crm.contact.update')->name('contacts.enrich');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->middleware('can:crm.contact.delete')->name('contacts.destroy');

        // Import/export breadth (IMEX): companies, leads, deals on the shared
        // wizard and streamed-export patterns. {entity} is allow-listed.
        Route::get('companies/export-csv', EntityExportController::class)
            ->defaults('entity', 'companies')->middleware('can:crm.company.read')->name('companies.export');
        Route::get('leads/export-csv', EntityExportController::class)
            ->defaults('entity', 'leads')->middleware('can:crm.lead.read')->name('leads.export');
        Route::get('deals/export-csv', EntityExportController::class)
            ->defaults('entity', 'deals')->middleware('can:crm.deal.read')->name('deals.export');
        Route::get('{entity}/import', [EntityImportController::class, 'create'])
            ->whereIn('entity', ['companies', 'leads', 'deals', 'keywords', 'competitors'])
            ->middleware('can:crm.import')->name('entity.import');
        Route::post('{entity}/import/preview', [EntityImportController::class, 'preview'])
            ->whereIn('entity', ['companies', 'leads', 'deals', 'keywords', 'competitors'])
            ->middleware('can:crm.import')->name('entity.import.preview');
        Route::post('{entity}/import', [EntityImportController::class, 'store'])
            ->whereIn('entity', ['companies', 'leads', 'deals', 'keywords', 'competitors'])
            ->middleware('can:crm.import')->name('entity.import.store');

        // Companies.
        Route::get('companies', [CompanyController::class, 'index'])->middleware('can:crm.company.read')->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->middleware('can:crm.company.create')->name('companies.store');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->middleware('can:crm.company.read')->name('companies.show');
        Route::patch('companies/{company}', [CompanyController::class, 'update'])->middleware('can:crm.company.update')->name('companies.update');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('can:crm.company.delete')->name('companies.destroy');

        // Leads.
        Route::get('leads', [LeadController::class, 'index'])->middleware('can:crm.lead.read')->name('leads.index');
        Route::post('leads', [LeadController::class, 'store'])->middleware('can:crm.lead.create')->name('leads.store');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->middleware('can:crm.lead.update')->name('leads.update');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->middleware('can:crm.lead.delete')->name('leads.destroy');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('can:crm.lead.update')->name('leads.convert');

        // Deals.
        Route::get('deals', [DealController::class, 'index'])->middleware('can:crm.deal.read')->name('deals.index');
        Route::post('deals', [DealController::class, 'store'])->middleware('can:crm.deal.create')->name('deals.store');
        Route::get('deals/{deal}', [DealController::class, 'show'])->middleware('can:crm.deal.read')->name('deals.show');
        Route::patch('deals/{deal}', [DealController::class, 'update'])->middleware('can:crm.deal.update')->name('deals.update');
        Route::patch('deals/{deal}/stage', [DealController::class, 'moveStage'])->middleware('can:crm.deal.update')->name('deals.stage');
        Route::delete('deals/{deal}', [DealController::class, 'destroy'])->middleware('can:crm.deal.delete')->name('deals.destroy');

        // Activities (polymorphic timeline).
        Route::post('activities', [ActivityController::class, 'store'])->middleware('can:crm.activity.manage')->name('activities.store');
        Route::patch('activities/{activity}/complete', [ActivityController::class, 'complete'])->middleware('can:crm.activity.manage')->name('activities.complete');
        // PORTAL-014: toggle whether a meeting note is visible in the client portal.
        Route::patch('activities/{activity}/visibility', [ActivityController::class, 'visibility'])->middleware('can:crm.activity.manage')->name('activities.visibility');
        Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->middleware('can:crm.activity.manage')->name('activities.destroy');
    });
