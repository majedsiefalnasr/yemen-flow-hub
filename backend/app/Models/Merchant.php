<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bank_id',
        'name',
        'tax_number',
        'tax_card_expiry',
        'address',
        'phone',
        'status',
        'version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_card_expiry' => 'date',
            'version' => 'integer',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importRequests(): HasMany
    {
        return $this->hasMany(ImportRequest::class);
    }

    public function engineRequests(): HasMany
    {
        return $this->hasMany(EngineRequest::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(MerchantOwner::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(MerchantCompany::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $user->isBankUser() ? $query->where('bank_id', $user->bank_id) : $query;
    }

    public function hasActiveRequests(): bool
    {
        return $this->engineRequests()
            ->where('status', 'ACTIVE')
            ->exists();
    }

    public function hasAnyRequests(): bool
    {
        return $this->engineRequests()->exists();
    }
}
