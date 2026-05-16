<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\VotingException;
use App\Http\Requests\WorkflowActionRequest;
use App\Http\Resources\ImportRequestResource;
use App\Models\ImportRequest;
use App\Services\Voting\VotingService;
use App\Services\Workflow\WorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly VotingService $votingService
    ) {
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/submit', tags: ['Workflow'], summary: 'Submit request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function submit(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'submit');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/bank-review', tags: ['Workflow'], summary: 'Bank begin review (SUBMITTED → BANK_REVIEW)', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function bankBeginReview(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'bank_begin_review');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/bank-approve', tags: ['Workflow'], summary: 'Bank approve request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function bankApprove(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'bank_approve');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/bank-reject', tags: ['Workflow'], summary: 'Bank reject request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function bankReject(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'bank_reject');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/return-to-entry', tags: ['Workflow'], summary: 'Return request to data entry', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function returnToEntry(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'return_to_entry');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/claim-support-review', tags: ['Workflow'], summary: 'Claim request for support review (atomic, 409 if already claimed)', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Claim acquired'), new OA\Response(response: 409, description: 'Already claimed by another user')])]
    public function claimSupportReview(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        return DB::transaction(function () use ($request, $importRequest) {
            $locked = ImportRequest::query()
                ->where('id', $importRequest->id)
                ->lockForUpdate()
                ->first();

            if ($locked->isClaimed()) {
                $holder = $locked->claimedByUser;
                return ApiResponse::error(
                    "هذا الطلب محجوز حالياً بواسطة {$holder?->name}. / This request is currently claimed by {$holder?->name}.",
                    [],
                    409
                );
            }

            $updated = $this->workflowService->transition(
                $locked,
                'support_claim',
                $request->user()
            );

            return ApiResponse::success(
                new ImportRequestResource($updated->load(['bank', 'merchant', 'claimedByUser'])),
                'Claim acquired.'
            );
        });
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-claim', tags: ['Workflow'], summary: 'Support claim request (legacy alias — use claim-support-review)', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportClaim(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->claimSupportReview($request, $importRequest);
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-release', tags: ['Workflow'], summary: 'Release support claim (legacy alias — use DELETE claim-support-review)', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Claim released')])]
    public function supportRelease(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->claimRelease($request, $importRequest);
    }

    #[OA\Delete(path: '/api/workflow/{importRequest}/claim-support-review', tags: ['Workflow'], summary: 'Release support claim', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Claim released'), new OA\Response(response: 403, description: 'Not the claim holder')])]
    public function claimRelease(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        $actor = $request->user();
        $isCbyadmin = $actor->role === UserRole::CBY_ADMIN;

        if (!$isCbyadmin && $importRequest->claimed_by !== $actor->id) {
            return ApiResponse::forbidden('لا يمكنك تحرير حجز لا تملكه. / You do not hold this claim.');
        }

        $metadata = $isCbyadmin ? ['auto_finalized' => true] : [];
        $updated = $this->workflowService->transition($importRequest, 'support_release', $actor, null, $metadata);

        return ApiResponse::success(
            new ImportRequestResource($updated->load(['bank', 'merchant', 'claimedByUser'])),
            'Claim released.'
        );
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/claim-support-review/heartbeat', tags: ['Workflow'], summary: 'Extend support claim TTL by 15 minutes', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Claim extended'), new OA\Response(response: 403, description: 'Not the claim holder')])]
    public function claimHeartbeat(Request $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        if ($importRequest->claimed_by !== $request->user()->id) {
            return ApiResponse::forbidden('You do not hold this claim.');
        }

        $ttlMinutes = (int) config('workflow.support_claim_ttl_minutes', 15);
        $expiresAt = now()->addMinutes($ttlMinutes);
        $cacheKey = "support_claim:{$importRequest->id}";

        app()->instance('workflow.transition.active', true);
        try {
            $importRequest->forceFill(['claim_expires_at' => $expiresAt])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Cache::put($cacheKey, $request->user()->id, $expiresAt);

        return ApiResponse::success(
            ['claimed_until' => $expiresAt->toISOString()],
            'Claim extended.'
        );
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-approve', tags: ['Workflow'], summary: 'Support approve request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportApprove(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'support_approve');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-reject', tags: ['Workflow'], summary: 'Support reject request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportReject(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'support_reject');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/bank-return-after-support-reject', tags: ['Workflow'], summary: 'BANK_REVIEWER returns a support-rejected request to DATA_ENTRY for editing', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function bankReturnAfterSupportReject(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'bank_return_after_support_reject');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/bank-finalize-rejection', tags: ['Workflow'], summary: 'BANK_REVIEWER finalizes a support-rejected request as terminal', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function bankFinalizeRejection(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'bank_finalize_rejection');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/finalize-decision', tags: ['Workflow'], summary: 'Finalize executive decision — tally-computed, tie-resolved by Director vote', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Decision finalized')])]
    public function finalizeDecision(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        $this->authorize('view', $importRequest);

        try {
            $updated = $this->votingService->finalize($importRequest, $request->user());
        } catch (VotingException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new ImportRequestResource($updated->load(['bank', 'merchant', 'claimedByUser'])),
            'Executive decision finalized.'
        );
    }

    private function run(WorkflowActionRequest $request, ImportRequest $importRequest, string $action)
    {
        $this->authorize('view', $importRequest);

        $updated = $this->workflowService->transition(
            $importRequest,
            $action,
            $request->user(),
            $request->input('reason')
        );

        return ApiResponse::success(new ImportRequestResource($updated->load(['bank', 'merchant', 'claimedByUser'])), 'Workflow transition executed.');
    }
}
