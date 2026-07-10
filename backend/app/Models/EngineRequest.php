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
        'sla_deadline_epoch',
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
            'sla_deadline_epoch' => 'integer',
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
     *
     * DB-001/DB-002 follow-up: orders directly on the raw, indexed
     * `engine_requests.sla_deadline_epoch` column (er_stage_sla_deadline) — NOT
     * through slaDeadlineEpochSql()'s COALESCE-wrapped expression. The load-run
     * harness (perf:load-scenario) proved that wrapping an otherwise-indexed
     * column in COALESCE(...) makes the ORDER BY non-sargable again: EXPLAIN
     * showed MySQL falling back to a full filesort even with the column and its
     * index in place, because the optimizer can't prove COALESCE(indexed_col,
     * fallback_expr) preserves the index's sort order. A raw
     * `ORDER BY sla_deadline_epoch` does use the index (confirmed via EXPLAIN:
     * `Using index condition`, no `Using filesort`).
     *
     * This trades the COALESCE safety net for a genuine ORDER BY: any row with
     * a null column (should only be true pre-backfill/edge-case rows — both
     * write paths, EngineRequestService::create() and
     * EngineTransitionService::execute(), always populate it when the stage has
     * an SLA) sorts NULL-first in MySQL ASC, i.e. as if maximally breached —
     * the safe-by-default direction for an operational queue: a row whose
     * deadline genuinely could not be computed surfaces at the top rather than
     * silently sinking to the bottom.
     *
     * The oldest-in-stage tiebreaker orders by the raw `stage_entered_at`
     * column (also indexed, er_stage_entered) instead of
     * UNIX_TIMESTAMP(COALESCE(...)) — same non-sargable-COALESCE problem as
     * the deadline clause: epoch conversion is monotonic, so sorting the raw
     * timestamp column produces the same relative order without defeating
     * the index. (First cut of this fix only touched the deadline clause and
     * left this tiebreaker wrapped — the load harness still showed a
     * filesort/no p95 improvement until this clause was fixed too.)
     */
    public function scopeOrderBySlaPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0 END')
            ->orderBy('engine_requests.sla_deadline_epoch', 'asc')
            ->orderBy('engine_requests.stage_entered_at', 'asc');
    }

    /**
     * Portable SQL expression for the current stage's SLA deadline as epoch
     * seconds, for WHERE-clause use (breach/nearing/ok filtering in
     * EngineRequestListQuery::applySlaStatusFilterInternal()) — NOT for
     * ORDER BY (see scopeOrderBySlaPriority()'s docblock for why).
     *
     * ARCH-002: the fast path reads the maintained `engine_requests.stage_entered_at`
     * column instead of a correlated max(created_at) subquery; DB-001/DB-002 follow-up
     * adds the maintained `sla_deadline_epoch` column as an even-faster first choice.
     * COALESCE falls back through both in turn for any row whose columns are not yet
     * populated, so a null column can never silently drop a breached request from
     * filtering. In the common case the maintained column is set and neither
     * fallback expression is evaluated.
     * Callers must have applied scopeWithStageEntry() for the current_stage join.
     */
    public static function slaDeadlineEpochSql(): string
    {
        $fallback = self::epochSql(self::stageEnteredAtSql())
            .' + (current_stage.sla_duration_minutes * 60)';

        return "COALESCE(engine_requests.sla_deadline_epoch, {$fallback})";
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
