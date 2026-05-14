<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WorkflowActionRequest;
use App\Http\Resources\ImportRequestResource;
use App\Models\ImportRequest;
use App\Services\Workflow\WorkflowService;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowService $workflowService)
    {
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/submit', tags: ['Workflow'], summary: 'Submit request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function submit(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'submit');
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

    #[OA\Post(path: '/api/workflow/{importRequest}/support-approve', tags: ['Workflow'], summary: 'Support approve request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportApprove(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'support_approve');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-claim', tags: ['Workflow'], summary: 'Support claim request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportClaim(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('request.claim')) {
            return ApiResponse::forbidden();
        }

        return $this->run($request, $importRequest, 'support_claim');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-release', tags: ['Workflow'], summary: 'Support release claim', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportRelease(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('request.claim')) {
            return ApiResponse::forbidden();
        }

        return $this->run($request, $importRequest, 'support_release');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/support-reject', tags: ['Workflow'], summary: 'Support reject request', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function supportReject(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        return $this->run($request, $importRequest, 'support_reject');
    }

    #[OA\Post(path: '/api/workflow/{importRequest}/finalize-decision', tags: ['Workflow'], summary: 'Finalize executive decision', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'decision', type: 'string', enum: ['approve', 'reject']), new OA\Property(property: 'reason', type: 'string', maxLength: 2000)])), responses: [new OA\Response(response: 200, description: 'Transition applied')])]
    public function finalizeDecision(WorkflowActionRequest $request, ImportRequest $importRequest)
    {
        $action = (string) $request->input('decision', 'approve') === 'reject'
            ? 'finalize_rejected'
            : 'finalize_approved';

        return $this->run($request, $importRequest, $action);
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

        return ApiResponse::success(new ImportRequestResource($updated->load(['bank', 'merchant', 'claimedBy'])), 'Workflow transition executed.');
    }
}
