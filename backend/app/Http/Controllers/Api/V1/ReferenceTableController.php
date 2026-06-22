<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreReferenceTableRequest;
use App\Http\Requests\UpdateReferenceTableRequest;
use App\Http\Resources\ReferenceTableResource;
use App\Models\ReferenceTable;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceTableController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReferenceTable::class);
        $page = ReferenceTable::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('key', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('label', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

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
        $referenceTable = DB::transaction(function () use ($request): ReferenceTable {
            $referenceTable = ReferenceTable::query()->create($request->validated())->refresh();
            $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $referenceTable, ['after' => $referenceTable->toArray()]);

            return $referenceTable;
        });

        return (new ReferenceTableResource($referenceTable))->response()->setStatusCode(201);
    }

    public function update(UpdateReferenceTableRequest $request, ReferenceTable $referenceTable): JsonResponse
    {
        $this->authorize('update', $referenceTable);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $referenceTable, $expectedVersion): void {
                $locked = ReferenceTable::query()->lockForUpdate()->findOrFail($referenceTable->getKey());
                if ($expectedVersion !== $locked->version) {
                    throw new StaleResourceException;
                }
                $before = $locked->toArray();
                $locked->update([
                    'label' => $request->string('label')->toString(),
                    'sort_order' => $request->integer('sort_order', $locked->sort_order),
                    'version' => $locked->version + 1,
                ]);
                $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $locked, ['before' => $before, 'after' => $locked->toArray()]);
            });
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The reference table was modified by another user.', 409);
        }

        return (new ReferenceTableResource($referenceTable->refresh()))->response();
    }

    public function activate(Request $request, ReferenceTable $referenceTable): ReferenceTableResource
    {
        $this->authorize('update', $referenceTable);
        $this->setActive($request, $referenceTable, true);

        return new ReferenceTableResource($referenceTable->refresh());
    }

    public function deactivate(Request $request, ReferenceTable $referenceTable): JsonResponse|ReferenceTableResource
    {
        $this->authorize('update', $referenceTable);
        if ($referenceTable->isProtected()) {
            return $this->error('REFERENCE_TABLE_PROTECTED', 'System reference tables cannot be deactivated.', 422);
        }
        $this->setActive($request, $referenceTable, false);

        return new ReferenceTableResource($referenceTable->refresh());
    }

    public function destroy(Request $request, ReferenceTable $referenceTable): JsonResponse
    {
        $this->authorize('delete', $referenceTable);
        if ($referenceTable->isProtected() || $referenceTable->isInUse()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $request->user(), $referenceTable, [
                'reason' => 'reference_table_protected_or_in_use',
            ]);

            return $this->error('REFERENCE_TABLE_PROTECTED', 'Reference table cannot be deleted while it has values or is a system table.', 422);
        }
        $referenceTable->delete();

        return response()->json(null, 204);
    }

    private function setActive(Request $request, ReferenceTable $referenceTable, bool $active): void
    {
        $before = $referenceTable->only(['is_active', 'version']);
        $referenceTable->update(['is_active' => $active, 'version' => $referenceTable->version + 1]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $referenceTable, ['before' => $before, 'after' => $referenceTable->only(['is_active', 'version'])]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
