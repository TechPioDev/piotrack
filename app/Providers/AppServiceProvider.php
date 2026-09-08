<?php

namespace App\Providers;

use App\Ai\AiProviderManager;
use App\Ai\Contracts\AiProvider;
use App\Analytics\CallProviderManager;
use App\Analytics\Contracts\CallProvider;
use App\Billing\Contracts\PaymentProvider;
use App\Billing\PaymentProviderManager;
use App\Calls\FixtureTranscriptionProvider;
use App\Calls\TranscriptionProvider;
use App\Content\ContentProviderManager;
use App\Content\Contracts\ReviewProvider;
use App\Content\Contracts\SocialListeningProvider;
use App\Content\Contracts\SocialProvider;
use App\Messaging\Contracts\MailProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\MessagingProviderManager;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Seo\Contracts\AiSearchProvider;
use App\Seo\Contracts\LinkDataProvider;
use App\Seo\Contracts\RankProvider;
use App\Seo\SeoProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the active payment provider (ADR-0003) wherever the
        // PaymentProvider contract is type-hinted.
        $this->app->bind(
            PaymentProvider::class,
            fn ($app) => $app->make(PaymentProviderManager::class)->driver(),
        );

        // Resolve the active marketing email/SMS drivers (ADR-0004) wherever the
        // MailProvider/SmsProvider contracts are type-hinted.
        $this->app->bind(
            MailProvider::class,
            fn ($app) => $app->make(MessagingProviderManager::class)->mail(),
        );
        $this->app->bind(
            SmsProvider::class,
            fn ($app) => $app->make(MessagingProviderManager::class)->sms(),
        );

        // Resolve the active SEO rank / AI-search drivers (ADR-0005).
        $this->app->bind(
            RankProvider::class,
            fn ($app) => $app->make(SeoProviderManager::class)->rank(),
        );
        $this->app->bind(
            AiSearchProvider::class,
            fn ($app) => $app->make(SeoProviderManager::class)->ai(),
        );
        $this->app->bind(
            LinkDataProvider::class,
            fn ($app) => $app->make(SeoProviderManager::class)->links(),
        );

        // Resolve the active social / review drivers (ADR-0007).
        $this->app->bind(
            SocialProvider::class,
            fn ($app) => $app->make(ContentProviderManager::class)->social(),
        );
        $this->app->bind(
            ReviewProvider::class,
            fn ($app) => $app->make(ContentProviderManager::class)->review(),
        );
        $this->app->bind(
            SocialListeningProvider::class,
            fn ($app) => $app->make(ContentProviderManager::class)->listening(),
        );

        // Resolve the active call-tracking driver (CALL).
        $this->app->bind(
            CallProvider::class,
            fn ($app) => $app->make(CallProviderManager::class)->driver(),
        );

        // Resolve the speech-to-text driver (CALL-004). Only the fixture ships;
        // a live driver is credentials + a class implementing the contract.
        $this->app->bind(
            TranscriptionProvider::class,
            fn () => match ((string) config('services.transcription.driver', 'fixture')) {
                default => new FixtureTranscriptionProvider,
            },
        );

        // Resolve the active language-model driver (ADR-0008). Feature code never
        // uses this directly — every call goes through AiGateway.
        $this->app->bind(
            AiProvider::class,
            fn ($app) => $app->make(AiProviderManager::class)->driver(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Which proxies may be believed (SEC-002). Set here rather than in
        // bootstrap/app.php because the middleware configuration closure runs
        // before the config is loaded. Defaults to trusting none: a direct
        // deployment that trusts every proxy lets any client spoof
        // X-Forwarded-For and defeat the per-IP login throttle.
        // The ?? guards a stale bootstrap/cache/config.php written before
        // config/security.php existed: at() is typed array|string, so a null
        // would TypeError on every request rather than fall back.
        TrustProxies::at(config('security.trusted_proxies') ?? []);

        // The URL scheme is deliberately NOT forced.
        //
        // Laravel already builds URLs from the scheme and host of the request in
        // hand, which is the only answer that is right for every way the app is
        // reached: https://piotrack.com generates https links, and the same
        // deployment on http://192.168.1.230:8080 generates http ones. Forcing
        // https globally — whether from the environment name or from APP_URL —
        // rewrites redirects on the plain-HTTP address to https on a port that
        // speaks HTTP, and the browser gets its connection closed with no error
        // page to explain it.
        //
        // Nothing is lost. Outside a request (queued mail, artisan) there is no
        // request to read, so URLs fall back to APP_URL, which carries its own
        // scheme. Behind a TLS-terminating proxy the scheme arrives in
        // X-Forwarded-Proto and is honoured once that proxy is trusted above.

        // Stable polymorphic aliases for CRM activity subjects (non-strict map;
        // unmapped models such as User fall back to their class name).
        Relation::morphMap([
            'contact' => Contact::class,
            'company' => Company::class,
            'lead' => Lead::class,
            'deal' => Deal::class,
        ]);

        // AUTH-003: platform password policy. Length over composition (NIST-aligned);
        // breached-password check only in production to keep tests/local offline.
        Password::defaults(function () {
            $rule = Password::min(12);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // RecordAuthEvents is wired by Laravel's listener auto-discovery
        // (app/Listeners, handle* methods) — do not ALSO subscribe it
        // manually, or every audit event is recorded twice.

        // Public API rate limit (API-003): 60 requests/minute per token, falling
        // back to the client IP for unauthenticated hits.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
