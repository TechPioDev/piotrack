<?php

namespace App\Http\Middleware;

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\PlanStanding;
use App\Models\Organization;
use App\Models\User;
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
     * What to tell someone whose workspace has no plan running - a trial that
     * ran out, a subscription that ended or is on hold.
     *
     * Without it the first sign was a refused page: most of the product is
     * switched off the moment a plan lapses, and nothing said so. A client
     * using the portal is never told; the agency's billing is not their affair.
     *
     * @return array{state: string, plan: string|null, ended_on: string|null, workspace: string, can_manage_billing: bool}|null
     */
    private function planNotice(User $user, Organization $organization, ?Role $role): ?array
    {
        if ($role === null || $role === Role::Client) {
            return null;
        }

        return [
            ...app(PlanStanding::class)->describe($organization, null),
            'workspace' => $organization->name,
            // Only someone who can change the plan is sent to do it.
            'can_manage_billing' => $user->can('billing.manage'),
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
        $inWorkspace = $user !== null && $currentOrganization !== null;
        // Looked up once: it names the plan and decides whether a notice is due.
        $subscription = $inWorkspace ? $currentOrganization->activeSubscription() : null;
        $role = $inWorkspace ? $user->roleIn($currentOrganization) : null;

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
                'permissions' => $inWorkspace ? $user->permissionsIn($currentOrganization) : [],
                'role' => $role?->value,
            ],
            // Plan feature entitlements for UX gating / upgrade prompts (the
            // backend `entitlement:` middleware is the security boundary).
            'entitlements' => $inWorkspace ? [
                'features' => app(Entitlements::class)->features($currentOrganization),
                'plan' => $subscription?->plan->code,
            ] : ['features' => [], 'plan' => null],
            // The workspace has no plan running: said on every page, so nobody
            // has to be refused one to find out (ENTL-009).
            'planNotice' => $inWorkspace && $subscription === null ? $this->planNotice($user, $currentOrganization, $role) : null,
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
                'error' => $request->session()->get('error'),
                'ai_result' => $request->session()->get('ai_result'),
                'play_result' => $request->session()->get('play_result'),
                'draft' => $request->session()->get('draft'),
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
