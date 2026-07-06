<?php

namespace App\Support;

use App\Models\EngineRequest;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Shared read projection for engine requests.
 *
 * G11-P2 migrates Class-B shared read surfaces (dashboards, search, audit,
 * profile, financing, model guards) off the legacy ImportRequest reads and
 * onto EngineRequest. Centralising the role scope, stage/status bucket
 * predicates, and resource normalisation here keeps those surfaces aligned to
 * one mapping instead of re-deriving the retired 21-status enum.
 *
 * Operational buckets are expressed against the engine's stage codes
 * (currentStage.code) and lifecycle status (ACTIVE / CLOSED / REJECTED / CANCELLED / ABANDONED),
 * never the legacy enum.
 */
final class EngineRequestReadModel
{
    /**
     * Operational stage buckets keyed by the current stage code(s) they cover.
     * Mirrors the default IMPORT_FINANCING seed stage chain.
     */
    private const STAGE_BUCKETS = [
        'draft' => ['CREATE'],
        'pending_bank_review' => ['INTERNAL'],
        'at_cby' => ['SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL'],
        'support_queue' => ['SUPPORT'],
        'swift_queue' => ['FX'],
        'executive_queue' => ['EXEC'],
        'fx_confirmation_queue' => ['FX_CONFIRM'],
        'fx_confirmation_pending' => ['FX_CONFIRM'],
    ];

    /**
     * Lifecycle-status buckets. Voting was removed by DI-3, so there are no
     * vote-derived buckets here.
     */
    private const STATUS_BUCKETS = [
        'active' => 'ACTIVE',
        'in_progress' => 'ACTIVE',
        'approved_or_completed' => 'CLOSED',
        'completed' => 'CLOSED',
        'rejected' => 'REJECTED',
    ];

    /**
     * Base scoped query for shared reads: bank users see only their bank's
     * requests, CBY roles (bank_id null) see all. Relations needed by every
     * shared surface are eager-loaded once.
     */
    public static function queryFor(User $user): Builder
    {
        return EngineRequest::query()
            ->select('engine_requests.*')
            ->with(['bank', 'merchant', 'creator', 'currentStage', 'claimedBy'])
            ->forUser($user);
    }

    /**
     * Returns a constraint closure for the named bucket, usable in ->where().
     * Stage buckets filter on current_stage_id via the stage code; status
     * buckets filter on engine_requests.status. Unknown buckets match nothing.
     */
    public static function bucket(string $name): Closure
    {
        if (isset(self::STAGE_BUCKETS[$name])) {
            $stageCodes = self::STAGE_BUCKETS[$name];

            return static function (Builder $query) use ($stageCodes): void {
                $query->whereHas('currentStage', static function (Builder $stage) use ($stageCodes): void {
                    $stage->whereIn('workflow_stages.code', $stageCodes);
                });
            };
        }

        if (isset(self::STATUS_BUCKETS[$name])) {
            $status = self::STATUS_BUCKETS[$name];

            return static function (Builder $query) use ($status): void {
                $query->where('engine_requests.status', $status);
            };
        }

        return static function (Builder $query): void {
            $query->whereRaw('1 = 0');
        };
    }

    /**
     * Normalises a collection of engine requests into the shared list shape.
     * Both `reference` and `reference_number` are exposed so legacy consumers
     * keying off `reference_number` keep working during coexistence.
     *
     * @param  iterable<EngineRequest>  $requests
     * @return array<int, array<string, mixed>>
     */
    public static function resourceCollection(iterable $requests): array
    {
        $items = [];

        foreach ($requests as $request) {
            $items[] = [
                'id' => $request->id,
                'reference' => $request->reference,
                'reference_number' => $request->reference,
                'status' => $request->status,
                'stage_code' => $request->currentStage?->code,
                'stage_name' => $request->currentStage?->name,
                'bank_id' => $request->bank_id,
                'bank_name' => $request->bank?->name,
                'merchant_id' => $request->merchant_id,
                'merchant_name' => $request->merchant?->name,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'created_by' => $request->created_by,
                'created_by_name' => $request->creator?->name,
                'created_at' => $request->created_at,
            ];
        }

        return $items;
    }

    /**
     * Resolves a human reference for an engine request subject. Used by audit
     * and search where the subject may have been deleted: a present request
     * yields its reference, a bare id falls back to a synthesised `ENG-{id}`,
     * and a fully absent subject yields null.
     */
    public static function reference(?EngineRequest $request, ?int $fallbackId = null): ?string
    {
        if ($request !== null) {
            return $request->reference;
        }

        if ($fallbackId !== null) {
            return 'ENG-'.$fallbackId;
        }

        return null;
    }
}
