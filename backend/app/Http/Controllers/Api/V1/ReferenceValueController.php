<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreReferenceValueRequest;
use App\Http\Requests\UpdateReferenceValueRequest;
use App\Http\Resources\ReferenceValueResource;
use App\Models\ReferenceValue;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceValueController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReferenceValue::class);
        $page = ReferenceValue::query()
            ->when($request->filled('reference_table_id'), fn ($q) => $q->where('reference_table_id', $request->integer('reference_table_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('key', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('label', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

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
        $referenceValue = DB::transaction(function () use ($request): ReferenceValue {
            $referenceValue = ReferenceValue::query()->create($request->validated())->refresh();
            $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $referenceValue, ['after' => $referenceValue->toArray()]);

            return $referenceValue;
        });

        return (new ReferenceValueResource($referenceValue))->response()->setStatusCode(201);
    }

    public function update(UpdateReferenceValueRequest $request, ReferenceValue $referenceValue): JsonResponse
    {
        $this->authorize('update', $referenceValue);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $referenceValue, $expectedVersion): void {
                $locked = ReferenceValue::query()->lockForUpdate()->findOrFail($referenceValue->getKey());
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
            return $this->error('STALE_RESOURCE', 'The reference value was modified by another user.', 409);
        }

        return (new ReferenceValueResource($referenceValue->refresh()))->response();
    }

    public function activate(Request $request, ReferenceValue $referenceValue): ReferenceValueResource
    {
        $this->authorize('update', $referenceValue);
        $this->setActive($request, $referenceValue, true);

        return new ReferenceValueResource($referenceValue->refresh());
    }

    public function deactivate(Request $request, ReferenceValue $referenceValue): JsonResponse|ReferenceValueResource
    {
        $this->authorize('update', $referenceValue);
        if ($referenceValue->isProtected()) {
            return $this->error('REFERENCE_VALUE_PROTECTED', 'System reference values cannot be deactivated.', 422);
        }
        $this->setActive($request, $referenceValue, false);

        return new ReferenceValueResource($referenceValue->refresh());
    }

    public function destroy(Request $request, ReferenceValue $referenceValue): JsonResponse
    {
        $this->authorize('delete', $referenceValue);
        if ($referenceValue->isProtected() || $referenceValue->isInUse()) {
            $this->auditService->log(AuditAction::AUTHORIZATION_FAILURE, $request->user(), $referenceValue, [
                'reason' => 'reference_value_protected_or_in_use',
            ]);

            return $this->error('REFERENCE_VALUE_PROTECTED', 'Reference value cannot be deleted while in use or is a system value.', 422);
        }
        $referenceValue->delete();

        return response()->json(null, 204);
    }

    private function setActive(Request $request, ReferenceValue $referenceValue, bool $active): void
    {
        $before = $referenceValue->only(['is_active', 'version']);
        $referenceValue->update(['is_active' => $active, 'version' => $referenceValue->version + 1]);
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $referenceValue, ['before' => $before, 'after' => $referenceValue->only(['is_active', 'version'])]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
