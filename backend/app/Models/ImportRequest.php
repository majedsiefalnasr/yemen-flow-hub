<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VotingSessionStatus;
use App\Exceptions\DirectStatusMutationException;
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
        'last_updated_by',
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
        'submitted_by',
        'bank_approved_at',
        'reviewed_by',
        'approved_by',
        'rejected_by',
        'resubmitted_by',
        'support_approved_at',
        'support_reviewed_by',
        'swift_uploaded_at',
        'swift_uploaded_by',
        'executive_decided_at',
        'customs_issued_at',
        'voting_opened_by',
        'voting_opened_at',
        'voting_closed_by',
        'voting_closed_at',
        'voting_session_status',
        'final_decision_at',
        'customs_declaration_id',
        'revision_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'current_owner_role' => UserRole::class,
            'currency' => Currency::class,
            'voting_session_status' => VotingSessionStatus::class,
            'amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'bank_approved_at' => 'datetime',
            'support_approved_at' => 'datetime',
            'swift_uploaded_at' => 'datetime',
            'executive_decided_at' => 'datetime',
            'customs_issued_at' => 'datetime',
            'voting_opened_at' => 'datetime',
            'voting_closed_at' => 'datetime',
            'final_decision_at' => 'datetime',
        ];
    }

    public function setAttribute($key, $value): static
    {
        if ($key === 'status' && !app()->bound('workflow.transition.active')) {
            throw new DirectStatusMutationException();
        }

        return parent::setAttribute($key, $value);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function resubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resubmitted_by');
    }

    public function supportReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_reviewed_by');
    }

    public function swiftUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'swift_uploaded_by');
    }

    public function votingOpenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voting_opened_by');
    }

    public function votingClosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voting_closed_by');
    }

    public function claimedByUser(): BelongsTo
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
            && $this->status?->isEditable() === true;
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
            $latest = self::withTrashed()
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
