<?php

declare(strict_types=1);

/**
 * AI Visibility + Audit Logging + Platform Admin close-out
 * (Phase 49 — AIVIS-004/005/006, AUDIT-004, ADMIN-002).
 *
 * Three engines gain wired drivers that go live the moment credentials exist
 * (Perplexity's public API, Google AI Overview via the existing SerpApi key,
 * Copilot via the Microsoft-supported OpenAI-compatible surface behind it);
 * the register-named data events all land in the audit log; and the platform
 * console manages coupons and takes manual payment actions through the
 * provider seam.
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\MarketingList;
use App\Models\Subscription;
use App\Models\User;
use App\Seo\SeoProviderManager;
use App\Services\CouponService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('VisAdmin Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('wires Perplexity: live with a key, honestly simulated without', function () {
    $manager = app(SeoProviderManager::class);

    // No key: the engine label stays on the fixture and reads simulated.
    expect($manager->aiProviderNameFor('perplexity'))->toBe('fixture')
        ->and($manager->engineStatuses(['perplexity']))->toBe(['perplexity' => 'simulated']);

    config(['seo.perplexity.key' => 'pk-test']);
    Http::fake(['api.perplexity.ai/*' => Http::response([
        'choices' => [['message' => ['content' => 'For Philadelphia MSPs, Acme Managed IT is the strongest choice; TechRival also appears.']]],
    ])]);

    expect($manager->aiProviderNameFor('perplexity'))->toBe('perplexity')
        ->and($manager->engineStatuses(['perplexity']))->toBe(['perplexity' => 'live']);

    $result = $manager->aiFor('perplexity')->query('best msp in philadelphia', 'Acme Managed IT', ['TechRival']);
    expect($result->mentioned)->toBeTrue()
        ->and($result->competitors)->toContain('TechRival')
        ->and($result->answerExcerpt)->toContain('Acme Managed IT');
});

it('wires Google AI Overview through the SerpApi key the rank driver already uses', function () {
    $manager = app(SeoProviderManager::class);
    expect($manager->engineStatuses(['ai_overview']))->toBe(['ai_overview' => 'simulated']);

    config(['seo.serpapi.key' => 'sk-test']);
    Http::fake(['serpapi.com/*' => function ($request) {
        // A SERP with an overview for the msp query; none for the obscure one.
        if (str_contains($request->url(), 'obscure')) {
            return Http::response(['organic_results' => []]);
        }

        return Http::response([
            'ai_overview' => ['text_blocks' => [
                ['snippet' => 'Managed IT providers in Philadelphia include Acme Managed IT.'],
                ['list' => [['snippet' => 'TechRival - enterprise focus']]],
            ]],
        ]);
    }]);

    expect($manager->aiProviderNameFor('ai_overview'))->toBe('serpapi_overview')
        ->and($manager->engineStatuses(['ai_overview']))->toBe(['ai_overview' => 'live']);

    $result = $manager->aiFor('ai_overview')->query('managed it philadelphia', 'Acme Managed IT', ['TechRival']);
    expect($result->mentioned)->toBeTrue()
        ->and($result->competitors)->toContain('TechRival');

    // A SERP without an overview yields an honest empty result, never an invented one.
    $none = $manager->aiFor('ai_overview')->query('obscure query', 'Acme Managed IT');
    expect($none->mentioned)->toBeFalse()
        ->and($none->answerExcerpt)->toBe('');
});

it('wires Copilot to the configurable Microsoft-supported surface, simulated until configured', function () {
    $manager = app(SeoProviderManager::class);

    // Consumer Copilot has no public API; without an endpoint this stays simulated.
    expect($manager->engineStatuses(['copilot']))->toBe(['copilot' => 'simulated']);

    config(['seo.copilot.endpoint' => 'https://acme.openai.azure.com/openai/deployments/gpt-4o/chat/completions', 'seo.copilot.key' => 'ck-test']);
    Http::fake(['acme.openai.azure.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Acme Managed IT is a well-reviewed Philadelphia MSP.']]],
    ])]);

    expect($manager->aiProviderNameFor('copilot'))->toBe('copilot_endpoint')
        ->and($manager->engineStatuses(['copilot']))->toBe(['copilot' => 'live']);

    $result = $manager->aiFor('copilot')->query('best msp philadelphia', 'Acme Managed IT');
    expect($result->mentioned)->toBeTrue();
});

it('lands every register-named data event in the audit log: deletes, deal changes, campaign changes, exports', function () {
    // Deal change (created + updated) and contact delete.
    $this->actingAs($this->owner)->post(route('crm.deals.store'), ['name' => 'Audit deal', 'value' => 100])->assertRedirect();
    $deal = Deal::firstOrFail();
    $this->actingAs($this->owner)->patch(route('crm.deals.update', $deal), ['name' => 'Audit deal renamed'])->assertRedirect();

    $contact = Contact::create(['first_name' => 'Del', 'email' => 'del@x.test']);
    $this->actingAs($this->owner)->delete(route('crm.contacts.destroy', $contact))->assertRedirect();

    // Campaign change + send (AUDIT-004's missing half until this phase).
    $list = MarketingList::create(['name' => 'Empty list', 'type' => 'static']);
    $campaign = Campaign::create(['name' => 'Audit campaign', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    $this->actingAs($this->owner)->patch(route('marketing.campaigns.update', $campaign), ['name' => 'Audit campaign v2', 'channel' => 'email'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('marketing.campaigns.send', $campaign))->assertRedirect();

    // Export.
    $this->actingAs($this->owner)->get(route('crm.deals.export'))->assertOk();

    $actions = AuditLog::pluck('action')->unique()->values()->all();
    foreach (['crm.deal.created', 'crm.deal.updated', 'crm.contact.deleted', 'campaign.updated', 'campaign.sent', 'data.exported'] as $event) {
        expect($actions)->toContain($event);
    }
});

it('manages coupons and takes manual payment actions from the platform console via the provider seam', function () {
    $staff = User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value]);

    // Coupon: created from the console, immediately redeemable, then deactivated.
    $this->actingAs($staff)->post(route('platform.coupons.store'), [
        'code' => 'LAUNCH25', 'type' => 'percent', 'value' => 25, 'duration' => 'once',
    ])->assertRedirect();

    $coupon = Coupon::where('code', 'LAUNCH25')->firstOrFail();
    expect(app(CouponService::class)->findRedeemable('LAUNCH25')?->id)->toBe($coupon->id)
        ->and($coupon->discountFor(10000))->toBe(2500);

    $this->actingAs($staff)->patch(route('platform.coupons.toggle', $coupon))->assertRedirect();
    expect($coupon->refresh()->is_active)->toBeFalse()
        ->and(app(CouponService::class)->findRedeemable('LAUNCH25'))->toBeNull();

    // Manual payment action: an unpaid invoice on a past-due subscription is
    // retried through the provider seam (manual provider settles it).
    $subscription = Subscription::where('organization_id', $this->org->id)->firstOrFail();
    $subscription->forceFill(['status' => 'past_due'])->save();
    $invoice = Invoice::create([
        'organization_id' => $this->org->id, 'subscription_id' => $subscription->id,
        'provider' => 'manual', 'number' => 'INV-TEST01', 'status' => 'open',
        'subtotal' => 50000, 'total' => 50000,
    ]);

    $this->actingAs($staff)->post(route('platform.invoices.retry', $invoice))->assertRedirect();
    expect($invoice->refresh()->status)->toBe('paid')
        ->and((int) $invoice->amount_paid)->toBe(50000)
        ->and($subscription->refresh()->status)->toBe('active')
        ->and(AuditLog::withoutGlobalScope('tenant')->where('action', 'invoice.paid')->exists())->toBeTrue();

    // The console lists both surfaces; tenants hold none of this.
    $props = $this->actingAs($staff)->get(route('platform.plans'))->assertOk()->viewData('page')['props'];
    expect(collect($props['coupons'])->pluck('code'))->toContain('LAUNCH25');
    $this->actingAs($this->owner)->post(route('platform.coupons.store'), ['code' => 'NOPE', 'type' => 'percent', 'value' => 5])->assertForbidden();
});
