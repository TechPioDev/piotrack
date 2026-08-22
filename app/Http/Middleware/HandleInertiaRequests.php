<?php

namespace App\Http\Middleware;

use App\Billing\Entitlements;
use App\Services\Platform\ImpersonationService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The active support-impersonation session, if any.
     *
     * @return array{active: bool, user: string|null, impersonator: string|null}|null
     */
    private function impersonationState(): ?array
    {
        $service = app(ImpersonationService::class);

        if (! $service->isImpersonating()) {
            return null;
        }

        $session = $service->active();

        return [
            'active' => true,
            'user' => $session?->user?->name,
            'impersonator' => $session?->impersonator?->name,
        ];
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $currentOrganization = app(CurrentOrganization::class)->get();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'currentOrganization' => $currentOrganization !== null ? [
                    'id' => $currentOrganization->id,
                    'name' => $currentOrganization->name,
                    'slug' => $currentOrganization->slug,
                ] : null,
                'organizations' => $user !== null
                    ? $user->activeOrganizations()->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                        ->map(fn ($org) => [
                            'id' => $org->id,
                            'name' => $org->name,
                            'slug' => $org->slug,
                            'role' => $org->getAttribute('pivot')?->role,
                        ])->all()
                    : [],
                // Permission keys the user holds in the current org — for UX
                // gating only; the backend Gate is the security boundary (RBAC-005).
                'permissions' => ($user !== null && $currentOrganization !== null)
                    ? $user->permissionsIn($currentOrganization)
                    : [],
                'role' => ($user !== null && $currentOrganization !== null)
                    ? $user->roleIn($currentOrganization)?->value
                    : null,
            ],
            // Plan feature entitlements for UX gating / upgrade prompts (the
            // backend `entitlement:` middleware is the security boundary).
            'entitlements' => ($user !== null && $currentOrganization !== null) ? [
                'features' => app(Entitlements::class)->features($currentOrganization),
                'plan' => $currentOrganization->activeSubscription()?->plan->code,
            ] : ['features' => [], 'plan' => null],
            'notifications' => [
                'unread' => $user !== null ? $user->unreadNotifications()->count() : 0,
            ],
            // Impersonation must never be invisible: the UI renders a
            // persistent banner whenever this is present (ADMIN-006).
            'impersonation' => $this->impersonationState(),
            // Session flash surfaced to the client. Without this, every
            // `back()->with('status', …)` confirmation in the app is invisible.
            'flash' => [
                'status' => $request->session()->get('status'),
                'ai_result' => $request->session()->get('ai_result'),
            ],
            // Enforcement redirects through password confirmation, which consumes
            // the flash message explaining why — so the reason is derived per
            // request instead of being passed along.
            'twoFactorRequired' => config('security.require_two_factor')
                && $user !== null
                && $user->two_factor_confirmed_at === null,
        ]);
    }
}
