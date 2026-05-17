<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'password',
        'role',
        'bank_id',
        'is_active',
        'last_login_at',
        'user_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'user_preferences' => 'array',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RequestVote::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
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

    public function hasPermission(string $slug): bool
    {
        return app(PermissionService::class)->userCan($this, $slug);
    }
}
