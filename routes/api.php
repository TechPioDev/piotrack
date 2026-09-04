<?php

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\SetApiOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * Public API v1 (API-001..005). Every request is token-authenticated
 * (Sanctum), scoped to a tenant via X-Organization-Id, gated by the `api`
 * plan feature, rate limited, and de-duplicated by Idempotency-Key on unsafe
 * methods. Individual endpoints add `can:` permission checks. Responses use
 * the `{data, meta}` / `{data}` envelope and carry X-Request-Id.
 */
Route::middleware([
    'auth:sanctum',
    SetApiOrganization::class,
    'entitlement:api',
    'throttle:api',
    Idempotency::class,
])->prefix('v1')->name('api.v1.')->group(function () {

    Route::get('contacts', [ContactController::class, 'index'])
        ->middleware('can:crm.contact.read')->name('contacts.index');
    Route::post('contacts', [ContactController::class, 'store'])
        ->middleware('can:crm.contact.create')->name('contacts.store');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])
        ->middleware('can:crm.contact.read')->name('contacts.show');
    Route::patch('contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('can:crm.contact.update')->name('contacts.update');

    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('can:crm.company.read')->name('companies.index');
    Route::post('companies', [CompanyController::class, 'store'])
        ->middleware('can:crm.company.create')->name('companies.store');
    Route::get('companies/{company}', [CompanyController::class, 'show'])
        ->middleware('can:crm.company.read')->name('companies.show');
    Route::patch('companies/{company}', [CompanyController::class, 'update'])
        ->middleware('can:crm.company.update')->name('companies.update');

    Route::get('deals', [DealController::class, 'index'])
        ->middleware('can:crm.deal.read')->name('deals.index');
    Route::post('deals', [DealController::class, 'store'])
        ->middleware('can:crm.deal.create')->name('deals.store');
    Route::get('deals/{deal}', [DealController::class, 'show'])
        ->middleware('can:crm.deal.read')->name('deals.show');
    Route::patch('deals/{deal}', [DealController::class, 'update'])
        ->middleware('can:crm.deal.update')->name('deals.update');
});
