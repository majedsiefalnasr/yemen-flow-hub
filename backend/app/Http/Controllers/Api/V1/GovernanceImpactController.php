<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GovernanceReferenceEntityType;
use App\Http\Controllers\Api\Controller;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\Role;
use App\Models\Team;
use App\Services\Workflow\PublishedWorkflowReferenceGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GovernanceImpactController extends Controller
{
    public function __construct(private readonly PublishedWorkflowReferenceGuard $guard) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::enum(GovernanceReferenceEntityType::class)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'action' => ['sometimes', Rule::in(['delete', 'deactivate'])],
        ]);

        $entityType = GovernanceReferenceEntityType::from($validated['entity_type']);
        $entityId = (int) $validated['entity_id'];
        $this->authorizeImpact($entityType, $entityId);

        $impact = $this->guard->impact($entityType, $entityId);

        if ($entityType === GovernanceReferenceEntityType::REFERENCE_TABLE) {
            $impact['bank_context'] = null;
        }

        if ($request->input('action') === 'deactivate' && $entityType === GovernanceReferenceEntityType::ORGANIZATION) {
            $impact['draft_only_warning'] = $impact['referenced_by_draft_only'];
        }

        return response()->json(['data' => $impact]);
    }

    /**
     * Bank lifecycle impact is not workflow-permission based; expose usage separately.
     */
    public function bank(Bank $bank): JsonResponse
    {
        $this->authorize('view', $bank);

        $inFlight = $bank->engineRequests()->where('status', 'ACTIVE')->count();
        $closedHistory = $bank->engineRequests()->where('status', '!=', 'ACTIVE')->count();

        return response()->json([
            'data' => [
                'entity_type' => 'bank',
                'entity_id' => $bank->id,
                'referenced_by_published' => false,
                'would_break_executor' => false,
                'usage' => [
                    'users' => $bank->users()->count(),
                    'merchants' => $bank->merchants()->withTrashed()->count(),
                    'engine_requests_total' => $bank->engineRequests()->count(),
                    'engine_requests_in_flight' => $inFlight,
                    'engine_requests_closed' => $closedHistory,
                ],
                'warnings' => $inFlight > 0
                    ? ["Bank has {$inFlight} in-flight request(s); suspension is allowed but new activity will be blocked."]
                    : [],
                'can_suspend' => true,
                'can_delete' => ! $this->bankIsUsedForDelete($bank),
            ],
        ]);
    }

    private function authorizeImpact(GovernanceReferenceEntityType $entityType, int $entityId): void
    {
        $model = match ($entityType) {
            GovernanceReferenceEntityType::ORGANIZATION => Organization::query()->findOrFail($entityId),
            GovernanceReferenceEntityType::TEAM => Team::query()->findOrFail($entityId),
            GovernanceReferenceEntityType::ROLE => Role::query()->findOrFail($entityId),
            GovernanceReferenceEntityType::REFERENCE_TABLE => ReferenceTable::query()->findOrFail($entityId),
            GovernanceReferenceEntityType::REFERENCE_VALUE => ReferenceValue::query()->findOrFail($entityId),
            GovernanceReferenceEntityType::USER => abort(404),
        };

        $policy = match ($entityType) {
            GovernanceReferenceEntityType::ORGANIZATION => Organization::class,
            GovernanceReferenceEntityType::TEAM => Team::class,
            GovernanceReferenceEntityType::ROLE => Role::class,
            GovernanceReferenceEntityType::REFERENCE_TABLE => ReferenceTable::class,
            GovernanceReferenceEntityType::REFERENCE_VALUE => ReferenceValue::class,
            GovernanceReferenceEntityType::USER => null,
        };

        if ($policy !== null) {
            $this->authorize('view', $model);
        }
    }

    private function bankIsUsedForDelete(Bank $bank): bool
    {
        return $bank->users()->exists()
            || $bank->merchants()->withTrashed()->exists()
            || $bank->engineRequests()->exists();
    }
}
