<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Exceptions\WorkflowVersionImmutableException;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\GuardsDesignerInput;
use App\Http\Requests\StoreFieldDefinitionRequest;
use App\Http\Requests\UpdateFieldDefinitionRequest;
use App\Http\Resources\FieldDefinitionResource;
use App\Models\FieldDefinition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use App\Services\Workflow\FieldDesignerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FieldDefinitionController extends Controller
{
    use GuardsDesignerInput;

    public function __construct(
        private readonly FieldDesignerService $designer,
        private readonly DynamicFieldOptionsResolver $options,
    ) {}

    public function index(WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('viewAny', FieldDefinition::class);
        $fields = $workflowVersion->fieldDefinitions()->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => FieldDefinitionResource::collection($fields)->resolve()]);
    }

    public function store(StoreFieldDefinitionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $this->authorize('create', FieldDefinition::class);

        try {
            $field = $this->designer->createField($request->user(), $workflowVersion, $request->validated());
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['key' => 'A field with this key already exists in this version.']);
        }

        return (new FieldDefinitionResource($field))->response()->setStatusCode(201);
    }

    public function update(UpdateFieldDefinitionRequest $request, WorkflowVersion $workflowVersion, FieldDefinition $fieldDefinition): JsonResponse
    {
        $this->authorize('update', $fieldDefinition);
        $attributes = collect($request->validated())->except(['version', 'key'])->all();

        try {
            $fieldDefinition = $this->designer->updateField($request->user(), $fieldDefinition, $attributes, $request->integer('version'));
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The field was modified by another user.', 409);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        }

        return (new FieldDefinitionResource($fieldDefinition))->response();
    }

    public function destroy(Request $request, WorkflowVersion $workflowVersion, FieldDefinition $fieldDefinition): JsonResponse
    {
        $this->authorize('delete', $fieldDefinition);

        try {
            $this->designer->deleteField($request->user(), $fieldDefinition);
        } catch (WorkflowVersionImmutableException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 409);
        } catch (WorkflowDesignProtectionException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Resolve the selectable options for a DYNAMIC_SELECT field (DI-5).
     */
    public function options(Request $request, WorkflowVersion $workflowVersion, FieldDefinition $fieldDefinition): JsonResponse
    {
        $this->authorize('view', $fieldDefinition);

        return response()->json(['data' => $this->options->resolve($fieldDefinition, $request->user())]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
