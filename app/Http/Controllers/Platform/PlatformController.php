<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\FeatureFlag;
use App\Models\ImpersonationSession;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\PlatformAdminService;
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
        ]);
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
