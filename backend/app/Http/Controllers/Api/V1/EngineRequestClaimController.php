<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Http\JsonResponse;

class EngineRequestClaimController extends Controller
{
    public function __construct(private EngineClaimService $claimService) {}

    public function claim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->claim($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }

    public function heartbeatClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->heartbeat($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }

    public function releaseClaim(EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('execute', $engineRequest);

        $updated = $this->claimService->release($engineRequest, request()->user());
        $updated->load(['currentStage', 'claimedBy']);

        return response()->json([
            'success' => true,
            'data' => new EngineRequestResource($updated),
        ]);
    }
}
