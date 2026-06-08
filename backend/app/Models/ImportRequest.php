<?php

namespace App\Models;

use App\Enums\CoverageType;
use App\Enums\Currency;
use App\Enums\CurrencySource;
use App\Enums\Incoterm;
use App\Enums\InvoiceType;
use App\Enums\PaymentTermsMode;
use App\Enums\PortOfArrival;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Enums\VotingSessionStatus;
use App\Exceptions\DirectStatusMutationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ImportRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_number',
        'bank_id',
        'merchant_id',
        'trader_id',
        'created_by',
        'last_updated_by',
        'currency',
        'amount',
        'yer_equivalent',
        'quantity',
        'supplier_name',
        'goods_description',
        'port_of_entry',
        'notes',
        'goods_type',
        'payment_terms',
        'due_date',
        'invoice_number',
        'invoice_date',
        'origin_country',
        'arrival_port',
        'shipping_port',
        'customs_office',
        'bl_number',
        'request_type',
        'coverage_type',
        'currency_source',
        'payment_terms_mode',
        'request_percentage',
        'request_currency',
        'requested_amount',
        'invoice_type',
        'invoice_currency',
        'unit_of_measure',
        'total_invoice_amount',
        'commodity',
        'exporting_company_name',
        'exporting_company_location',
        'country_of_origin',
        'port_of_loading',
        'port_of_arrival',
        'incoterm',
        'final_destination',
        'shipping_date',
        'arrival_date',
        'trader_snapshot_name',
        'trader_snapshot_tax_number',
        'trader_snapshot_tax_card_expiry',
        'trader_snapshot_commercial_registration_number',
        'trader_snapshot_commercial_registration_expiry',
        'voting_rule_version',
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
        'eligible_voter_ids',
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
            'request_type' => RequestType::class,
            'coverage_type' => CoverageType::class,
            'currency_source' => CurrencySource::class,
            'payment_terms_mode' => PaymentTermsMode::class,
            'invoice_type' => InvoiceType::class,
            'port_of_arrival' => PortOfArrival::class,
            'incoterm' => Incoterm::class,
            'amount' => 'decimal:2',
            'yer_equivalent' => 'decimal:2',
            'request_percentage' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'total_invoice_amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'bank_approved_at' => 'datetime',
            'support_approved_at' => 'datetime',
            'swift_uploaded_at' => 'datetime',
            'executive_decided_at' => 'datetime',
            'customs_issued_at' => 'datetime',
            'voting_opened_at' => 'datetime',
            'eligible_voter_ids' => 'array',
            'voting_closed_at' => 'datetime',
            'final_decision_at' => 'datetime',
            'shipping_date' => 'date',
            'arrival_date' => 'date',
            'trader_snapshot_tax_card_expiry' => 'date',
            'trader_snapshot_commercial_registration_expiry' => 'date',
        ];
    }

    public function setAttribute($key, $value): static
    {
        if ($key === 'status' && ! app()->bound('workflow.transition.active')) {
            throw new DirectStatusMutationException;
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

    public function trader(): BelongsTo
    {
        return $this->belongsTo(Trader::class);
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

    public function issuedCustomsDeclaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'customs_declaration_id');
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
            if (empty($importRequest->reference_number)) {
                $importRequest->reference_number = self::nextReferenceNumber();
            }
        });
    }

    private static function referencePrefixForYear(string $year): string
    {
        return "YFH-{$year}-";
    }

    private static function nextReferenceNumber(): string
    {
        $year = now()->format('Y');

        $nextValue = DB::transaction(function () use ($year): int {
            $latestFromRequests = self::latestReferenceSequenceForYear($year);
            $now = now();

            if (! DB::table('import_request_reference_sequences')->where('year', $year)->exists()) {
                DB::table('import_request_reference_sequences')->insertOrIgnore([
                    'year' => $year,
                    'last_value' => $latestFromRequests,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $sequence = DB::table('import_request_reference_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextValue = max((int) ($sequence?->last_value ?? 0), $latestFromRequests) + 1;

            DB::table('import_request_reference_sequences')
                ->where('year', $year)
                ->update([
                    'last_value' => $nextValue,
                    'updated_at' => $now,
                ]);

            return $nextValue;
        }, 5);

        $prefix = self::referencePrefixForYear($year);

        return $prefix.str_pad((string) $nextValue, 6, '0', STR_PAD_LEFT);
    }

    private static function latestReferenceSequenceForYear(string $year): int
    {
        $prefix = self::referencePrefixForYear($year);

        $latest = self::withTrashed()
            ->where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        if ($latest === null) {
            return 0;
        }

        return (int) substr($latest, strlen($prefix));
    }
}
