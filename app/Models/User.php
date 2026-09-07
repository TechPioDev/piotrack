<?php

namespace App\Models;

use App\Authorization\Permission;
use App\Authorization\Role;
use App\Authorization\RolePermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user has completed two-factor enrollment.
     */
    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    // ---------------------------------------------------------------------
    // Tenancy
    // ---------------------------------------------------------------------

    /**
     * @return BelongsToMany<Organization, $this, OrganizationUser>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Active memberships only (excludes deactivated).
     *
     * @return BelongsToMany<Organization, $this, OrganizationUser>
     */
    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('status', 'active');
    }

    /**
     * Policy/terms acceptances recorded for this person (PRIV-001).
     *
     * @return HasMany<PolicyAcceptance, $this>
     */
    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Membership regardless of status (includes deactivated members) — used for
     * member management, where deactivated members must remain addressable.
     */
    public function isMemberOf(Organization $organization): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->exists();
    }

    /**
     * The user's organization role in the given organization, or null if not
     * an active member.
     */
    public function roleIn(Organization $organization): ?Role
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('status', 'active')
            ->first();

        $role = $membership?->getAttribute('pivot')?->role;

        return $role !== null ? Role::tryFrom($role) : null;
    }

    // ---------------------------------------------------------------------
    // Authorization (RBAC — ADR-0002)
    // ---------------------------------------------------------------------

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Whether the user wants a given channel for a category. Defaults to on;
     * only an explicit disabled preference turns a channel off.
     */
    public function wantsChannel(string $category, string $channel): bool
    {
        $preference = $this->notificationPreferences
            ->first(fn (NotificationPreference $p) => $p->category === $category && $p->channel === $channel);

        return $preference === null || $preference->enabled;
    }

    /**
     * SMS notifications are OPT-IN (NOTIF-003) — unlike email's opt-out,
     * nobody gets surprise texts: only an explicit enabled preference (and a
     * phone number on file) turns the channel on.
     */
    public function smsOptedIn(string $category): bool
    {
        if ($this->phone === null || $this->phone === '') {
            return false;
        }

        $preference = $this->notificationPreferences
            ->first(fn (NotificationPreference $p) => $p->category === $category && $p->channel === 'sms');

        return $preference !== null && $preference->enabled;
    }

    public function platformRole(): ?Role
    {
        return $this->platform_role !== null ? Role::tryFrom($this->platform_role) : null;
    }

    public function isPlatformSuperAdmin(): bool
    {
        return $this->platformRole() === Role::PlatformSuperAdmin;
    }

    /**
     * The permission keys the user holds in the given organization.
     * Memoized per organization for the lifetime of the request to avoid
     * repeated membership lookups across multiple gate checks.
     *
     * @var array<int, list<string>>
     */
    private array $permissionCache = [];

    /**
     * @return list<string>
     */
    public function permissionsIn(Organization $organization): array
    {
        return $this->permissionCache[$organization->id] ??= $this->resolvePermissionsIn($organization);
    }

    /**
     * @return list<string>
     */
    private function resolvePermissionsIn(Organization $organization): array
    {
        if ($this->isPlatformSuperAdmin()) {
            return Permission::values();
        }

        $role = $this->roleIn($organization);

        return $role !== null ? RolePermissions::for($role->value) : [];
    }
}
