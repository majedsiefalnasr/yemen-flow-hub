<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReferenceDataProtectionException;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreReferenceTableRequest;
use App\Http\Requests\UpdateReferenceTableRequest;
use App\Http\Resources\ReferenceTableResource;
use App\Models\ReferenceTable;
use App\Services\ReferenceData\ReferenceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceTableController extends Controller
{
    private const SORT_COLUMNS = ['key', 'label', 'sort_order', 'is_active', 'created_at'];

    public function __construct(private readonly ReferenceDataService $referenceData) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReferenceTable::class);
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';
        $page = ReferenceTable::query()
            ->withExists('values')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('key', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('label', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => ReferenceTableResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(ReferenceTable $referenceTable): ReferenceTableResource
    {
        $this->authorize('view', $referenceTable);

        return new ReferenceTableResource($referenceTable);
    }

    public function store(StoreReferenceTableRequest $request): JsonResponse
    {
        $this->authorize('create', ReferenceTable::class);
        $referenceTable = $this->referenceData->createTable($request->user(), $request->validated());

        return (new ReferenceTableResource($referenceTable))->response()->setStatusCode(201);
    }

    public function update(UpdateReferenceTableRequest $request, ReferenceTable $referenceTable): JsonResponse
    {
        $this->authorize('update', $referenceTable);
        $expectedVersion = $request->integer('version');

        try {
            $referenceTable = $this->referenceData->updateTable(
                $request->user(),
                $referenceTable,
                array_filter([
                    'label' => $request->string('label')->toString(),
                    'sort_order' => $request->has('sort_order') ? $request->integer('sort_order') : null,
                ], fn ($value) => $value !== null),
                $expectedVersion,
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference table was modified by another user.', 409);
        }

        return (new ReferenceTableResource($referenceTable))->response();
    }

    public function activate(Request $request, ReferenceTable $referenceTable): JsonResponse|ReferenceTableResource
    {
        $this->authorize('update', $referenceTable);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $referenceTable = $this->referenceData->setTableActive(
                $request->user(),
                $referenceTable,
                true,
                $validated['version'],
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference table was modified by another user.', 409);
        }

        return new ReferenceTableResource($referenceTable);
    }

    public function deactivate(Request $request, ReferenceTable $referenceTable): JsonResponse|ReferenceTableResource
    {
        $this->authorize('update', $referenceTable);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $referenceTable = $this->referenceData->setTableActive(
                $request->user(),
                $referenceTable,
                false,
                $validated['version'],
            );
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference table was modified by another user.', 409);
        }

        return new ReferenceTableResource($referenceTable);
    }

    public function destroy(Request $request, ReferenceTable $referenceTable): JsonResponse
    {
        $this->authorize('delete', $referenceTable);
        try {
            $this->referenceData->deleteTable($request->user(), $referenceTable);
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
