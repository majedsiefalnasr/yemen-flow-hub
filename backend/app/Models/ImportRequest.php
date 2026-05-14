<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_number',
        'bank_id',
        'merchant_id',
        'created_by',
        'currency',
        'amount',
        'supplier_name',
        'goods_description',
        'port_of_entry',
        'notes',
        'status',
        'current_owner_role',
        'claimed_by',
        'claimed_at',
        'claim_expires_at',
        'submitted_at',
        'bank_approved_at',
        'support_approved_at',
        'swift_uploaded_at',
        'executive_decided_at',
        'customs_issued_at',
        'revision_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'current_owner_role' => UserRole::class,
            'amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'bank_approved_at' => 'datetime',
            'support_approved_at' => 'datetime',
            'swift_uploaded_at' => 'datetime',
            'executive_decided_at' => 'datetime',
            'customs_issued_at' => 'datetime',
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

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(RequestStageHistory::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class, 'request_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RequestVote::class, 'request_id');
    }

    public function customsDeclaration(): HasOne
    {
        return $this->hasOne(CustomsDeclaration::class, 'request_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isBankUser()) {
            return $query->where('bank_id', $user->bank_id);
        }

        return $query;
    }

    public function scopeStatus(Builder $query, RequestStatus|array $status): Builder
    {
        if (is_array($status)) {
            $values = array_map(fn ($item) => $item instanceof RequestStatus ? $item->value : (string) $item, $status);
            return $query->whereIn('status', $values);
        }

        return $query->where('status', $status->value);
    }

    public function isEditable(): bool
    {
        return $this->current_owner_role === UserRole::DATA_ENTRY
            && in_array($this->status, [
                RequestStatus::DRAFT,
                RequestStatus::RETURNED_TO_DATA_ENTRY,
            ], true);
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by !== null
            && $this->claim_expires_at !== null
            && $this->claim_expires_at->isFuture();
    }

    public function isClaimedBy(User $user): bool
    {
        return $this->isClaimed() && $this->claimed_by === $user->id;
    }

    public function isClaimExpired(): bool
    {
        return $this->claimed_by !== null
            && ($this->claim_expires_at === null || $this->claim_expires_at->isPast());
    }

    protected static function booted(): void
    {
        static::creating(function (self $importRequest): void {
            if (!empty($importRequest->reference_number)) {
                return;
            }

            $year = now()->format('Y');
            $prefix = "YFH-{$year}-";
            $latest = self::query()
                ->where('reference_number', 'like', $prefix.'%')
                ->latest('id')
                ->value('reference_number');

            $next = 1;
            if ($latest) {
                $parts = explode('-', $latest);
                $next = ((int) ($parts[2] ?? 0)) + 1;
            }

            $importRequest->reference_number = $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
