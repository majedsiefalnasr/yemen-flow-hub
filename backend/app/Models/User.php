<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Exceptions\UnmappedRoleException;
use App\Support\RoleCodes;
use App\Support\UserRoleMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'organization_id',
        'locale',
        'phone',
        'password',
        'must_change_password',
        'temporary_password_set_at',
        'password_changed_at',
        'pin_code_hash',
        'pin_enabled',
        'bank_id',
        'is_active',
        'mfa_enabled',
        'totp_secret',
        'totp_enabled',
        'totp_recovery_codes',
        'last_login_at',
        'user_preferences',
        'avatar_variant',
        'version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_recovery_codes',
        'pin_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'temporary_password_set_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'mfa_enabled' => 'boolean',
            'totp_enabled' => 'boolean',
            'totp_recovery_codes' => 'array',
            'pin_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'user_preferences' => 'array',
            'version' => 'integer',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'user_teams')->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->wherePivot('is_active', true);
    }

    public function team(): ?Team
    {
        return $this->relationLoaded('teams') ? $this->teams->first() : $this->teams()->first();
    }

    /**
     * The user's single active role: an active pivot on an active role record.
     * Null when there is no such role — inactive/historical pivots are never
     * returned, so no fallback to the first (possibly inactive) role (M3).
     */
    public function role(): ?Role
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->first(
                fn (Role $role): bool => (bool) $role->pivot?->is_active && (bool) $role->is_active,
            );
        }

        return $this->activeRoles()->where('roles.is_active', true)->first();
    }

    public function asUserRole(): ?UserRole
    {
        $code = $this->role()?->code;

        if ($code === null) {
            return null;
        }

        try {
            return UserRoleMapper::toUserRole($code);
        } catch (UnmappedRoleException) {
            return null;
        }
    }

    public function scopeWithActiveRoleCode(Builder $query, string $roleCode): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roleQuery) => $roleQuery
                ->where('roles.code', $roleCode)
                ->where('user_roles.is_active', true),
        );
    }

    public function scopeWithUserRole(Builder $query, UserRole $userRole): Builder
    {
        return $query->withActiveRoleCode(UserRoleMapper::roleCodeFor($userRole));
    }

    public function scopeWithoutUserRole(Builder $query, UserRole $userRole): Builder
    {
        $roleCode = UserRoleMapper::roleCodeFor($userRole);

        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roleQuery) => $roleQuery
                ->where('roles.code', $roleCode)
                ->where('user_roles.is_active', true),
        );
    }

    /**
     * Assign exactly one active pivot role; prior active rows are deactivated (audited via caller).
     */
    public function assignActiveRole(int $roleId): void
    {
        $alreadyActive = $this->roles()
            ->where('roles.id', $roleId)
            ->wherePivot('is_active', true)
            ->exists();

        if ($alreadyActive) {
            return;
        }

        $this->roles()
            ->wherePivot('is_active', true)
            ->get()
            ->each(fn (Role $role) => $this->roles()->updateExistingPivot($role->id, ['is_active' => false]));

        if ($this->roles()->where('roles.id', $roleId)->exists()) {
            $this->roles()->updateExistingPivot($roleId, ['is_active' => true]);
        } else {
            $this->roles()->attach($roleId, ['is_active' => true]);
        }
    }

    public function assertSingleActiveRole(): void
    {
        if ($this->roles()->wherePivot('is_active', true)->count() > 1) {
            abort(response()->json([
                'error' => [
                    'code' => 'MULTIPLE_ROLES_NOT_ALLOWED',
                    'message' => 'A user may hold only one active role.',
                    'fields' => (object) [],
                    'request_id' => request()->header('X-Request-ID'),
                ],
            ], 422));
        }
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->asUserRole() === $role;
    }

    public function isBankUser(): bool
    {
        return $this->asUserRole()?->isBankRole() ?? false;
    }

    public function isCbyUser(): bool
    {
        return $this->asUserRole()?->isCbyRole() ?? false;
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRoleCode(RoleCodes::SYSTEM_ADMIN);
    }

    public function hasRoleCode(string $code): bool
    {
        return $this->activeRoleCodes()->contains($code);
    }

    public function hasAnyRoleCode(array $codes): bool
    {
        return $this->activeRoleCodes()->intersect($codes)->isNotEmpty();
    }

    /**
     * Codes of the user's active role(s): only pivot rows with
     * `user_roles.is_active = true` whose `roles.is_active = true` count.
     * Historical/inactive pivots never contribute to authorization, and a
     * user with no active role has no role-derived permissions (M3 / RBAC-001).
     *
     * The loaded-relationship and DB-query branches return identical results.
     */
    private function activeRoleCodes(): Collection
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles
                ->filter(fn (Role $role): bool => (bool) $role->pivot?->is_active && (bool) $role->is_active)
                ->pluck('code');
        }

        return $this->roles()
            ->wherePivot('is_active', true)
            ->where('roles.is_active', true)
            ->pluck('code');
    }

    public function inOrganization(string $code): bool
    {
        if ($this->relationLoaded('organization')) {
            return $this->organization?->code === $code;
        }

        return $this->organization()->where('code', $code)->exists();
    }
}
