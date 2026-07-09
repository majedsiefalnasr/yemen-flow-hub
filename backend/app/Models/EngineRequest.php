<?php

namespace App\Models;

use App\DTOs\Authorization\DataScopeContext;
use App\Services\Authorization\DataScope;
use App\Support\EngineRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EngineRequest extends Model
{
    protected $table = 'engine_requests';

    protected $fillable = [
        'workflow_version_id',
        'current_stage_id',
        'stage_entered_at',
        'reference',
        'status',
        'created_by',
        'claimed_by',
        'claimed_at',
        'claim_expires_at',
        'claim_stage_id',
        'bank_id',
        'merchant_id',
        'data',
        'version',
        'amount',
        'currency',
        'invoice_number',
        'invoice_number_normalized',
        'request_percentage',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'version' => 'integer',
            'amount' => 'decimal:2',
            'request_percentage' => 'decimal:2',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'stage_entered_at' => 'datetime',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by !== null
            && $this->claim_expires_at !== null
            && $this->claim_expires_at->isFuture();
    }

    public function claimIsExpired(): bool
    {
        return $this->claim_expires_at !== null && $this->claim_expires_at->isPast();
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(WorkflowHistoryEntry::class, 'request_id')->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EngineRequestDocument::class, 'request_id');
    }

    public function customsDeclaration(): HasOne
    {
        return $this->hasOne(CustomsDeclaration::class, 'engine_request_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isClosed(): bool
    {
        return EngineRequestStatus::isTerminal((string) $this->status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('engine_requests.status', 'ACTIVE');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $scope = DataScope::forUser($user);

        if ($user->isSystemAdmin()) {
            $scope = new DataScopeContext(systemWide: true);
        }

        return DataScope::applyTo($query, $scope, 'engine_requests.bank_id');
    }

    /**
     * Exposes the current stage's SLA window (via the current_stage join) alongside
     * the indexed `stage_entered_at` projection column, so SLA status can be derived,
     * filtered, and ordered at the query level.
     *
     * ARCH-002: `stage_entered_at` is now a maintained column (set on create and on
     * every transition to equal the latest matching workflow_history.created_at), so
     * it arrives via `engine_requests.*` — no correlated max(created_at) subquery is
     * evaluated per row in the projection, ORDER BY, or WHERE anymore.
     */
    public function scopeWithStageEntry(Builder $query): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('engine_requests.*');
        }

        return $query
            ->leftJoin('workflow_stages as current_stage', 'current_stage.id', '=', 'engine_requests.current_stage_id')
            // Project the maintained column, falling back to the history subquery for
            // any row whose column is not yet populated, so the sla_status accessor
            // reads the same value the deadline SQL uses.
            ->selectRaw(self::stageEnteredAtSql().' as stage_entered_at')
            ->addSelect('current_stage.sla_duration_minutes as current_stage_sla_minutes');
    }

    /**
     * SQL for the request's current-stage entry time: the maintained
     * `stage_entered_at` projection column, falling back to the correlated
     * max(created_at) history subquery when the column is null (ARCH-002). Single
     * source of truth for the projection alias, the SLA deadline, and the ordering
     * tiebreaker so all three agree on which timestamp a row entered its stage.
     */
    public static function stageEnteredAtSql(): string
    {
        return 'COALESCE(engine_requests.stage_entered_at, '
            .'(select max(created_at) from workflow_history '
            .'where workflow_history.request_id = engine_requests.id '
            .'and workflow_history.to_stage_id = engine_requests.current_stage_id))';
    }

    /**
     * Default دوري priority order: SLA-breached first, then nearest-to-breach, then
     * oldest-in-stage. Requires scopeWithStageEntry to have been applied.
     */
    public function scopeOrderBySlaPriority(Builder $query): Builder
    {
        $deadline = self::slaDeadlineEpochSql();

        return $query
            ->orderByRaw('CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw("{$deadline} ASC")
            ->orderByRaw(self::epochSql(self::stageEnteredAtSql()).' ASC');
    }

    /**
     * Portable SQL expression for the current stage's SLA deadline as epoch seconds:
     * stage-entry time + (sla_duration_minutes * 60). Works on both MySQL and SQLite
     * so query-level SLA ordering/filtering is consistent between prod and tests.
     *
     * ARCH-002: the fast path reads the indexed `engine_requests.stage_entered_at`
     * projection column (maintained on create/transition) instead of a correlated
     * max(created_at) subquery. To stay correct for any row whose column is not yet
     * populated (pre-backfill edge rows, or history written outside the maintained
     * paths), it falls back to the original subquery via COALESCE — so a null column
     * can never silently drop a breached request from SLA ordering/filtering. In the
     * common case the column is set and the subquery is never evaluated.
     * Callers must have applied scopeWithStageEntry() for the current_stage join.
     */
    public static function slaDeadlineEpochSql(): string
    {
        return self::epochSql(self::stageEnteredAtSql())
            .' + (current_stage.sla_duration_minutes * 60)';
    }

    public static function nowEpochSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s','now') AS INTEGER)"
            : 'UNIX_TIMESTAMP()';
    }

    private static function epochSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', {$column}) AS INTEGER)"
            : "UNIX_TIMESTAMP({$column})";
    }

    public function getSlaStatusAttribute(): ?string
    {
        $slaMinutes = $this->current_stage_sla_minutes
            ?? ($this->relationLoaded('currentStage') ? $this->currentStage?->sla_duration_minutes : null);
        $enteredAt = $this->stage_entered_at;

        if ($slaMinutes === null || $enteredAt === null) {
            return null;
        }

        $deadline = Carbon::parse($enteredAt)->addMinutes((int) $slaMinutes);
        $remaining = now()->diffInMinutes($deadline, false);

        return match (true) {
            $remaining < 0 => 'breached',
            $remaining <= max(1, (int) $slaMinutes * 0.2) => 'nearing',
            default => 'ok',
        };
    }
}
