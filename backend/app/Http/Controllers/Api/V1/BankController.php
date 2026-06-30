<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\BankOrganizationImmutableException;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Models\Organization;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Bank::class);
        $page = Bank::query()->with('organization')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n
                ->where('code', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('name', 'like', '%'.$request->string('search')->toString().'%')
                ->orWhere('swift_code', 'like', '%'.$request->string('search')->toString().'%')))
            ->orderBy(
                in_array($request->input('sort'), ['code', 'name', 'created_at'], true) ? $request->input('sort') : 'created_at',
                $request->input('direction') === 'asc' ? 'asc' : 'desc'
            )
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => BankResource::collection($page->items())->resolve(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Bank $bank): BankResource
    {
        $this->authorize('view', $bank);

        return new BankResource($bank->load('organization'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Bank::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:banks,code'],
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50', 'unique:banks,swift_code'],
            'status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])],
        ]);
        $data['organization_id'] = Organization::query()->where('code', 'commercial_banks')->value('id');
        $data['is_active'] = $data['status'] === 'ACTIVE';
        $bank = Bank::query()->create($data)->refresh();
        $this->auditService->log(AuditAction::BANK_CREATED, $request->user(), $bank, ['after' => $bank->toArray()]);

        return (new BankResource($bank->load('organization')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Bank $bank): JsonResponse
    {
        $this->authorize('update', $bank);
        $data = $request->validate([
            'organization_id' => ['sometimes', 'integer'],
            'code' => ['required', 'string', 'max:50', Rule::unique('banks', 'code')->ignore($bank)],
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50', Rule::unique('banks', 'swift_code')->ignore($bank)],
            'status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])],
            'version' => ['required', 'integer'],
        ]);
        $expectedVersion = $request->integer('version');

        try {
            DB::transaction(function () use ($request, $bank, $data, $expectedVersion): void {
                $locked = Bank::query()->lockForUpdate()->findOrFail($bank->getKey());
                if ($expectedVersion !== $locked->version) {
                    throw new StaleResourceException;
                }
                if (isset($data['organization_id']) && $data['organization_id'] !== $locked->organization_id && $this->isUsed($locked)) {
                    throw new BankOrganizationImmutableException;
                }
                unset($data['organization_id']);
                $data['is_active'] = $data['status'] === 'ACTIVE';
                $data['version'] = $locked->version + 1;
                $before = $locked->toArray();
                $locked->update($data);
                $this->auditService->log(AuditAction::BANK_UPDATED, $request->user(), $locked, ['before' => $before, 'after' => $locked->toArray()]);
            });
        } catch (StaleResourceException) {
            return $this->error('STALE_RESOURCE', 'The bank was modified by another user.', 409);
        } catch (BankOrganizationImmutableException) {
            return $this->error('BANK_ORGANIZATION_IMMUTABLE', 'Bank organization cannot change after use.', 422);
        }

        return (new BankResource($bank->refresh()->load('organization')))->response();
    }

    public function deactivate(Request $request, Bank $bank): JsonResponse|BankResource
    {
        $this->authorize('update', $bank);
        if ($this->isUsed($bank)) {
            return $this->error('BANK_IN_USE', 'Bank cannot be deactivated while referenced.', 422);
        }
        $bank->update(['status' => 'SUSPENDED', 'is_active' => false, 'version' => $bank->version + 1]);
        $this->auditService->log(AuditAction::BANK_UPDATED, $request->user(), $bank);

        return new BankResource($bank->refresh()->load('organization'));
    }

    public function activate(Request $request, Bank $bank): BankResource
    {
        $this->authorize('update', $bank);
        $bank->update(['status' => 'ACTIVE', 'is_active' => true, 'version' => $bank->version + 1]);
        $this->auditService->log(AuditAction::BANK_UPDATED, $request->user(), $bank);

        return new BankResource($bank->refresh()->load('organization'));
    }

    public function destroy(Bank $bank): JsonResponse
    {
        $this->authorize('delete', $bank);
        if ($this->isUsed($bank)) {
            return $this->error('BANK_IN_USE', 'Bank cannot be deleted while referenced.', 422);
        }
        $bank->delete();

        return response()->json(null, 204);
    }

    private function isUsed(Bank $bank): bool
    {
        // Count soft-deleted merchants too: a hard-deleted bank would leave their
        // bank_id FK dangling if such a merchant were later restored.
        return $bank->users()->exists()
            || $bank->merchants()->withTrashed()->exists()
            || $bank->importRequests()->exists();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) [], 'request_id' => request()->header('X-Request-ID')]], $status);
    }
}
