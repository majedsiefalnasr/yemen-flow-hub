<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'role',
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
            'role' => UserRole::class,
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
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function team(): ?Team
    {
        return $this->relationLoaded('teams') ? $this->teams->first() : $this->teams()->first();
    }

    public function role(): ?Role
    {
        return $this->relationLoaded('roles') ? $this->roles->first() : $this->roles()->first();
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
        return $this->role === $role;
    }

    public function isBankUser(): bool
    {
        return $this->role?->isBankRole() ?? false;
    }

    public function isCbyUser(): bool
    {
        return $this->role?->isCbyRole() ?? false;
    }

    public function isSystemAdmin(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('code', 'system_admin');
        }

        return $this->roles()->where('code', 'system_admin')->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return app(PermissionService::class)->userCan($this, $slug);
    }
}
