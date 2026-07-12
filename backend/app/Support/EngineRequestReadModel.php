<?php

namespace App\Support;

use App\Enums\StageSemanticRole;
use App\Models\EngineRequest;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Shared read projection for engine requests.
 *
 * Operational buckets prefer workflow_stages.semantic_role (WP-4) with a
 * stage-code fallback for unmigrated versions.
 */
final class EngineRequestReadModel
{
    /**
     * @var array<string, array{roles: list<StageSemanticRole>, codes: list<string>}>
     */
    private const STAGE_BUCKETS = [
        'draft' => ['roles' => [StageSemanticRole::INITIAL_ENTRY], 'codes' => ['CREATE']],
        'pending_bank_review' => ['roles' => [StageSemanticRole::BANK_REVIEW], 'codes' => ['INTERNAL']],
        'at_cby' => [
            'roles' => [
                StageSemanticRole::SUPPORT_REVIEW,
                StageSemanticRole::EXECUTIVE_REVIEW,
                StageSemanticRole::SWIFT,
                StageSemanticRole::FX_CONFIRMATION,
                StageSemanticRole::FINAL,
            ],
            'codes' => ['SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL'],
        ],
        'support_queue' => ['roles' => [StageSemanticRole::SUPPORT_REVIEW], 'codes' => ['SUPPORT']],
        'swift_queue' => ['roles' => [StageSemanticRole::SWIFT], 'codes' => ['FX']],
        'executive_queue' => ['roles' => [StageSemanticRole::EXECUTIVE_REVIEW], 'codes' => ['EXEC']],
        'fx_confirmation_queue' => ['roles' => [StageSemanticRole::FX_CONFIRMATION], 'codes' => ['FX_CONFIRM']],
        'fx_confirmation_pending' => ['roles' => [StageSemanticRole::FX_CONFIRMATION], 'codes' => ['FX_CONFIRM']],
        // The Committee Director's own actionable queue: the final-confirmation
        // stage they execute (UI-FX-001). Distinct from fx_confirmation_pending,
        // which is the national FX team's FX_CONFIRM stage.
        'director_final_queue' => ['roles' => [StageSemanticRole::FINAL], 'codes' => ['FINAL']],
    ];

    private const STATUS_BUCKETS = [
        'active' => 'ACTIVE',
        'in_progress' => 'ACTIVE',
        'approved_or_completed' => 'CLOSED',
        'completed' => 'CLOSED',
        'rejected' => 'REJECTED',
    ];

    public static function queryFor(User $user): Builder
    {
        return EngineRequest::query()
            ->select('engine_requests.*')
            ->with(['bank', 'merchant', 'creator', 'currentStage', 'claimedBy'])
            ->forUser($user);
    }

    public static function bucket(string $name): Closure
    {
        if (isset(self::STAGE_BUCKETS[$name])) {
            $roles = array_map(static fn (StageSemanticRole $role): string => $role->value, self::STAGE_BUCKETS[$name]['roles']);
            $codes = self::STAGE_BUCKETS[$name]['codes'];

            return static function (Builder $query) use ($roles, $codes): void {
                $query->whereHas('currentStage', static function (Builder $stage) use ($roles, $codes): void {
                    $stage->where(function (Builder $inner) use ($roles, $codes): void {
                        $inner->whereIn('semantic_role', $roles)
                            ->orWhereIn('workflow_stages.code', $codes);
                    });
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
