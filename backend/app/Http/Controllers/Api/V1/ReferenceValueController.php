<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReferenceDataProtectionException;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreReferenceValueRequest;
use App\Http\Requests\UpdateReferenceValueRequest;
use App\Http\Resources\ReferenceValueResource;
use App\Models\ReferenceValue;
use App\Services\ReferenceData\ReferenceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceValueController extends Controller
{
    private const SORT_COLUMNS = ['key', 'label', 'sort_order', 'is_active', 'created_at'];

    public function __construct(private readonly ReferenceDataService $referenceData) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReferenceValue::class);
        $validated = $request->validate([
            'reference_table_id' => ['sometimes', 'integer', 'exists:reference_tables,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';
        $page = ReferenceValue::query()
            ->withExists('merchantCompanies')
            ->when($request->filled('reference_table_id'), fn ($q) => $q->where('reference_table_id', $request->integer('reference_table_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('key', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('label', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => ReferenceValueResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(ReferenceValue $referenceValue): ReferenceValueResource
    {
        $this->authorize('view', $referenceValue);

        return new ReferenceValueResource($referenceValue);
    }

    public function store(StoreReferenceValueRequest $request): JsonResponse
    {
        $this->authorize('create', ReferenceValue::class);
        $referenceValue = $this->referenceData->createValue($request->user(), $request->validated());

        return (new ReferenceValueResource($referenceValue))->response()->setStatusCode(201);
    }

    public function update(UpdateReferenceValueRequest $request, ReferenceValue $referenceValue): JsonResponse
    {
        $this->authorize('update', $referenceValue);
        $expectedVersion = $request->integer('version');

        try {
            $referenceValue = $this->referenceData->updateValue(
                $request->user(),
                $referenceValue,
                array_filter([
                    'label' => $request->string('label')->toString(),
                    'sort_order' => $request->has('sort_order') ? $request->integer('sort_order') : null,
                ], fn ($value) => $value !== null),
                $expectedVersion,
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference value was modified by another user.', 409);
        }

        return (new ReferenceValueResource($referenceValue))->response();
    }

    public function activate(Request $request, ReferenceValue $referenceValue): JsonResponse|ReferenceValueResource
    {
        $this->authorize('update', $referenceValue);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $referenceValue = $this->referenceData->setValueActive(
                $request->user(),
                $referenceValue,
                true,
                $validated['version'],
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference value was modified by another user.', 409);
        }

        return new ReferenceValueResource($referenceValue);
    }

    public function deactivate(Request $request, ReferenceValue $referenceValue): JsonResponse|ReferenceValueResource
    {
        $this->authorize('update', $referenceValue);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $referenceValue = $this->referenceData->setValueActive(
                $request->user(),
                $referenceValue,
                false,
                $validated['version'],
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference value was modified by another user.', 409);
        }

        return new ReferenceValueResource($referenceValue);
    }

    public function destroy(Request $request, ReferenceValue $referenceValue): JsonResponse
    {
        $this->authorize('delete', $referenceValue);
        try {
            $this->referenceData->deleteValue($request->user(), $referenceValue);
        } catch (ReferenceDataProtectionException $exception) {
            return $this->error($exception->errorCode, $exception->getMessage(), 422);
        }

        return response()->json(null, 204);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
