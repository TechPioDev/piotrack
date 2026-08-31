<?php

use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Public\ContactMessageController;
use App\Http\Controllers\Public\EmailTrackingController;
use App\Http\Controllers\Public\MarketingSiteController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\PublicFormController;
use App\Http\Controllers\Public\PublicLandingPageController;
use App\Http\Controllers\Public\PublicSitePageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome')->withViewData([
        'metaTitle' => 'Piotrack — MSP Marketing & Growth Platform',
        'metaDescription' => 'The growth operating system for managed service providers: CRM, marketing automation, SEO and AI visibility tracking, booking, and revenue attribution in one platform.',
        'canonical' => url('/'),
    ]);
})->name('home');

// Product marketing pages (MSITE) — public, SEO meta rendered server-side.
Route::get('features', [MarketingSiteController::class, 'features'])->name('site.features');
Route::get('how-it-works', [MarketingSiteController::class, 'howItWorks'])->name('site.how');
Route::get('results', [MarketingSiteController::class, 'results'])->name('site.results');
Route::get('about', [MarketingSiteController::class, 'about'])->name('site.about');
Route::get('contact', [MarketingSiteController::class, 'contact'])->name('site.contact');
Route::get('faq', [MarketingSiteController::class, 'faq'])->name('site.faq');
Route::post('contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:10,1')->name('contact.submit');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('health', HealthController::class)->name('health');

// Public billing webhooks — verified by the provider driver, CSRF-exempt.
Route::post('webhooks/{provider}', [WebhookController::class, 'handle'])->name('billing.webhook');

/*
 * Public marketing surfaces (Stage 6) — unauthenticated. Tenant is resolved from
 * the form/page slug or the recipient token. POST endpoints are CSRF-exempt
 * (see bootstrap/app.php) and throttled.
 */
Route::get('f/{slug}', [PublicFormController::class, 'show'])->name('public.form.show');
Route::post('f/{slug}', [PublicFormController::class, 'submit'])->middleware('throttle:20,1')->name('public.form.submit');
Route::get('p/{slug}', [PublicLandingPageController::class, 'show'])->name('public.landing.show');
// Homepage newsletter signup (product-level, throttled).
Route::post('newsletter', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:10,1')->name('newsletter.subscribe');

// Visitor Intelligence tracker (VINT): keyed to a tenant, throttled, CSRF-exempt.
Route::get('t/{key}.js', [TrackingController::class, 'script'])->middleware('throttle:60,1')->name('public.track.script');
Route::post('t/{key}/e', [TrackingController::class, 'event'])->middleware('throttle:60,1')->name('public.track.event');

Route::get('e/o/{token}', [EmailTrackingController::class, 'open'])->name('public.track.open');
Route::get('e/c/{token}', [EmailTrackingController::class, 'click'])->name('public.track.click');
Route::get('e/u/{token}', [EmailTrackingController::class, 'unsubscribeShow'])->name('public.track.unsubscribe.show');
Route::post('e/u/{token}', [EmailTrackingController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('public.track.unsubscribe');

// Public appointment booking (Stage 10).
// Published website pages (Stage 15). Draft pages 404.
Route::get('s/{slug}', [PublicSitePageController::class, 'show'])->name('public.page.show');

Route::get('b/{slug}', [PublicBookingController::class, 'show'])->name('public.booking.show');
Route::post('b/{slug}', [PublicBookingController::class, 'book'])->middleware('throttle:20,1')->name('public.booking.book');

Route::middleware(['auth', 'verified'])->group(function () {
    // Organization lifecycle (no active organization required).
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    // Invitation acceptance (authenticated; token resolved across tenants).
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'accept'])
        ->middleware('throttle:10,1')->name('invitations.accept');

    // Tenant-scoped surfaces require an active organization.
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('search', SearchController::class)->name('search');
    });
});

require __DIR__.'/chat.php';
require __DIR__.'/crm.php';
require __DIR__.'/marketing.php';
require __DIR__.'/seo.php';
require __DIR__.'/advertising.php';
require __DIR__.'/content.php';
require __DIR__.'/sales.php';
require __DIR__.'/analytics.php';
require __DIR__.'/ai.php';
require __DIR__.'/delivery.php';
require __DIR__.'/web-platform.php';
require __DIR__.'/platform.php';
require __DIR__.'/tenant.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
