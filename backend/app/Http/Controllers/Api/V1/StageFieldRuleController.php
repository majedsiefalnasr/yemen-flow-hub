<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\SetStageFieldRuleRequest;
use App\Http\Resources\StageFieldRuleResource;
use App\Models\StageFieldRule;
use App\Models\WorkflowStage;
use App\Services\Workflow\FieldDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StageFieldRuleController extends Controller
{
    public function __construct(private readonly FieldDesignerService $designer) {}

    public function index(WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('viewAny', StageFieldRule::class);
        $rules = $workflowStage->stageFieldRules()->orderBy('field_id')->get();

        return response()->json(['data' => StageFieldRuleResource::collection($rules)->resolve()]);
    }

    public function store(SetStageFieldRuleRequest $request, WorkflowStage $workflowStage): JsonResponse
    {
        $this->authorize('create', StageFieldRule::class);

        try {
            $rule = $this->designer->setStageFieldRule($request->user(), $workflowStage, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new StageFieldRuleResource($rule))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, WorkflowStage $workflowStage, StageFieldRule $stageFieldRule): JsonResponse
    {
        $this->authorize('delete', $stageFieldRule);

        try {
            $this->designer->deleteStageFieldRule($request->user(), $stageFieldRule);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
