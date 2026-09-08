<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Coupon;
use App\Models\FeatureFlag;
use App\Models\ImpersonationSession;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\PlatformAdminService;
use App\Services\SubscriptionService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform staff console (ADMIN-001…005). Reachable only by platform staff
 * holding `admin.platform`; every query here crosses tenants deliberately.
 */
class PlatformController extends Controller
{
    public function __construct(private PlatformAdminService $platform) {}

    public function dashboard(): Response
    {
        return Inertia::render('platform/dashboard', [
            'overview' => $this->platform->overview(),
            'tenants' => $this->platform->tenants(),
            // Recent support access, so impersonation is reviewable at a glance.
            'impersonations' => ImpersonationSession::with(['impersonator:id,name', 'user:id,name'])
                ->latest('id')->limit(25)->get()
                ->map(fn (ImpersonationSession $s) => [
                    'id' => $s->id,
                    'impersonator' => $s->impersonator?->name,
                    'user' => $s->user?->name,
                    'reason' => $s->reason,
                    'started_at' => $s->started_at->toIso8601String(),
                    'ended_at' => $s->ended_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * ENTL-002: the plan × entitlement matrix — every feature and limit each
     * plan grants, editable in place. Tenants pick up changes on their next
     * request (entitlements resolve per request).
     */
    public function plans(): Response
    {
        return Inertia::render('platform/plans', [
            'plans' => Plan::with('entitlements')->orderBy('sort_order')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'is_active' => $p->is_active,
                'entitlements' => $p->entitlements->map(fn (PlanEntitlement $e) => [
                    'key' => $e->key, 'kind' => $e->kind, 'bool_value' => $e->bool_value, 'int_value' => $e->int_value,
                ])->all(),
            ]),
            // ADMIN-002: coupon management + manual payment actions.
            'coupons' => Coupon::orderBy('code')->get()->map(fn (Coupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'value' => $c->value,
                'duration' => $c->duration,
                'max_redemptions' => $c->max_redemptions,
                'times_redeemed' => $c->times_redeemed,
                'expires_at' => $c->expires_at?->toDateString(),
                'is_active' => $c->is_active,
            ]),
            'unpaid_invoices' => Invoice::with('organization:id,name')->where('status', '!=', 'paid')
                ->latest('id')->limit(25)->get()
                ->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'organization' => $i->organization?->name,
                    'total' => $i->total,
                    'status' => $i->status,
                    'due_at' => $i->due_at?->toDateString(),
                ]),
        ]);
    }

    /** ADMIN-002: create a coupon (platform-global; redemption is Stage-3 tested). */
    public function storeCoupon(Request $request, AuditLogger $audit): RedirectResponse
    {
        $coupon = Coupon::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('coupons', 'code')],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1'],
            'duration' => ['nullable', Rule::in(['once', 'forever'])],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]) + ['is_active' => true]);

        $audit->log('platform.coupon.created', context: ['code' => $coupon->code], resourceType: 'coupon', resourceId: (string) $coupon->id);

        return back()->with('status', __('Coupon created.'));
    }

    /** ADMIN-002: deactivate / reactivate a coupon. */
    public function toggleCoupon(Coupon $coupon, AuditLogger $audit): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $audit->log('platform.coupon.toggled', context: ['code' => $coupon->code, 'is_active' => $coupon->is_active], resourceType: 'coupon', resourceId: (string) $coupon->id);

        return back()->with('status', $coupon->is_active ? __('Coupon reactivated.') : __('Coupon deactivated.'));
    }

    /** ADMIN-002: manual payment action — retry an unpaid invoice via the provider seam. */
    public function retryInvoice(Invoice $invoice, SubscriptionService $subscriptions): RedirectResponse
    {
        return $subscriptions->retryInvoice($invoice)
            ? back()->with('status', __('Invoice :number collected.', ['number' => $invoice->number]))
            : back()->with('status', __('Payment for :number failed again - see the audit log.', ['number' => $invoice->number]));
    }

    /** ENTL-002: upsert one cell of the matrix. */
    public function savePlanEntitlement(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['feature', 'limit'])],
            'bool_value' => ['nullable', 'boolean'],
            // null = unlimited for limits.
            'int_value' => ['nullable', 'integer', 'min:0'],
        ]);

        $plan->entitlements()->updateOrCreate(
            ['key' => $data['key']],
            [
                'kind' => $data['kind'],
                'bool_value' => $data['kind'] === 'feature' ? (bool) ($data['bool_value'] ?? false) : null,
                'int_value' => $data['kind'] === 'limit' ? ($data['int_value'] ?? null) : null,
            ],
        );

        return back()->with('status', __(':plan — :key saved.', ['plan' => $plan->name, 'key' => $data['key']]));
    }

    public function flags(): Response
    {
        return Inertia::render('platform/flags', [
            'flags' => FeatureFlag::orderBy('key')->get()->map(fn (FeatureFlag $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'description' => $f->description,
                'is_enabled' => $f->is_enabled,
                'is_kill_switch' => $f->is_kill_switch,
                'rollout' => $f->rollout,
            ]),
        ]);
    }

    public function saveFlag(Request $request, FeatureFlagService $flags): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
            'is_kill_switch' => ['boolean'],
            'rollout' => ['nullable', 'array'],
            'rollout.percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rollout.organizations' => ['nullable', 'array'],
        ]);

        $flags->upsert($data['key'], [
            'description' => $data['description'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'is_kill_switch' => (bool) ($data['is_kill_switch'] ?? false),
            'rollout' => $data['rollout'] ?? null,
        ]);

        return back()->with('status', __('Feature flag saved.'));
    }

    public function announcements(): Response
    {
        return Inertia::render('platform/announcements', [
            'announcements' => Announcement::latest('id')->get()->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'audience' => $a->audience,
                'type' => $a->type,
                'published_at' => $a->published_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['announcement', 'release_note'])],
            'publish' => ['boolean'],
        ]);

        Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'type' => $data['type'],
            'published_at' => ($data['publish'] ?? false) ? now() : null,
        ]);

        return back()->with('status', __('Announcement saved.'));
    }
}
