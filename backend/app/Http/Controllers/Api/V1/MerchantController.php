<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreMerchantRequest;
use App\Http\Requests\UpdateMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Merchant::class);
        $user = $request->user();
        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $query = Merchant::query()
            ->with('bank', 'owners', 'companies')
            ->withCount('engineRequests')
            ->forUser($user)
            ->when($user->isBankUser(), fn ($q) => $q, fn ($q) => $q->when(
                $request->filled('bank_id'),
                fn ($nested) => $nested->where('bank_id', $request->integer('bank_id'))
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('tax_number'), fn ($q) => $q->where('tax_number', $request->string('tax_number')->toString()))
            ->when($request->filled('sector_id'), fn ($q) => $q->whereHas(
                'companies',
                fn ($nested) => $nested->where('sector_reference_value_id', $request->integer('sector_id'))
            ))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%"));
            })
            ->latest('id');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => MerchantResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Merchant $merchant): MerchantResource
    {
        $this->guardMerchantScope($request->user(), $merchant);
        $this->authorize('view', $merchant);

        return new MerchantResource($merchant->loadCount('engineRequests')->load('bank', 'owners', 'companies'));
    }

    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $this->authorize('create', Merchant::class);
        $user = $request->user();
        $bankId = $user->isBankUser() ? $user->bank_id : $request->integer('bank_id');

        if (! $user->isBankUser() && ! $request->filled('bank_id')) {
            throw ValidationException::withMessages(['bank_id' => 'The bank_id field is required.']);
        }

        if (Merchant::withTrashed()->where('tax_number', $request->string('tax_number')->toString())->exists()) {
            return $this->businessError('MERCHANT_TAX_NUMBER_EXISTS', 'A merchant with this tax number already exists.', 409, ['tax_number' => 'Already in use.']);
        }

        if ($duplicateCr = $this->findDuplicateCommercialRegistration($request->input('companies', []))) {
            return $this->businessError('COMMERCIAL_REGISTRATION_EXISTS', 'A company with this commercial registration number already exists.', 409, ['commercial_registration_number' => $duplicateCr]);
        }

        $merchant = DB::transaction(function () use ($request, $bankId): Merchant {
            $merchant = Merchant::query()->create([
                'bank_id' => $bankId,
                'name' => $request->string('name')->toString(),
                'tax_number' => $request->string('tax_number')->toString(),
                'tax_card_expiry' => $request->input('tax_card_expiry'),
                'address' => $request->input('address'),
                'phone' => $request->input('phone'),
                'status' => $request->input('status', 'ACTIVE'),
                'version' => 1,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->input('owners', []) as $owner) {
                $merchant->owners()->create($owner);
            }

            foreach ($request->input('companies', []) as $company) {
                $merchant->companies()->create($company);
            }

            $this->auditService->log(AuditAction::GOVERNANCE_CREATED, $request->user(), $merchant, [
                'after' => $merchant->only(['bank_id', 'name', 'tax_number', 'status', 'version']),
            ]);

            return $merchant->refresh();
        });

        return (new MerchantResource($merchant->loadCount('engineRequests')->load('bank', 'owners', 'companies')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): JsonResponse
    {
        $this->guardMerchantScope($request->user(), $merchant);
        $this->authorize('update', $merchant);
        $user = $request->user();
        $expectedVersion = (int) $request->integer('version');

        if ($request->filled('tax_number') && Merchant::withTrashed()
            ->where('tax_number', $request->string('tax_number')->toString())
            ->whereKeyNot($merchant->id)
            ->exists()) {
            return $this->businessError('MERCHANT_TAX_NUMBER_EXISTS', 'A merchant with this tax number already exists.', 409, ['tax_number' => 'Already in use.']);
        }

        if ($duplicateCr = $this->findDuplicateCommercialRegistration($request->input('companies', []), $merchant)) {
            return $this->businessError('COMMERCIAL_REGISTRATION_EXISTS', 'A company with this commercial registration number already exists.', 409, ['commercial_registration_number' => $duplicateCr]);
        }

        if ($request->filled('bank_id')
            && (int) $request->integer('bank_id') !== $merchant->bank_id
            && $merchant->hasAnyRequests()) {
            return $this->businessError('MERCHANT_BANK_IMMUTABLE', 'This merchant already has requests; its bank cannot be changed.', 409);
        }

        if ($request->input('status') === 'SUSPENDED' && $merchant->status !== 'SUSPENDED' && $merchant->hasActiveRequests()) {
            return $this->businessError('MERCHANT_HAS_ACTIVE_REQUESTS', 'This merchant has active requests and cannot be suspended.', 409);
        }

        try {
            DB::transaction(function () use ($request, $merchant, $expectedVersion, $user): void {
                $locked = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
                if ($expectedVersion !== (int) $locked->version) {
                    throw new StaleResourceException;
                }

                $before = $locked->only(['bank_id', 'name', 'tax_number', 'status', 'version']);

                $locked->update([
                    'bank_id' => $user->isBankUser() ? $locked->bank_id : ($request->filled('bank_id') ? $request->integer('bank_id') : $locked->bank_id),
                    'name' => $request->input('name', $locked->name),
                    'tax_number' => $request->input('tax_number', $locked->tax_number),
                    'tax_card_expiry' => $request->has('tax_card_expiry') ? $request->input('tax_card_expiry') : $locked->tax_card_expiry,
                    'address' => $request->has('address') ? $request->input('address') : $locked->address,
                    'phone' => $request->has('phone') ? $request->input('phone') : $locked->phone,
                    'status' => $request->input('status', $locked->status),
                    'version' => $locked->version + 1,
                ]);

                if ($request->has('owners')) {
                    $locked->owners()->delete();
                    foreach ($request->input('owners', []) as $owner) {
                        $locked->owners()->create($owner);
                    }
                }

                if ($request->has('companies')) {
                    $locked->companies()->delete();
                    foreach ($request->input('companies', []) as $company) {
                        $locked->companies()->create($company);
                    }
                }

                $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $locked, [
                    'before' => $before,
                    'after' => $locked->only(['bank_id', 'name', 'tax_number', 'status', 'version']),
                ]);
            });
        } catch (StaleResourceException) {
            return $this->businessError('STALE_RESOURCE', 'The merchant was modified by another user.', 409);
        }

        return (new MerchantResource($merchant->refresh()->loadCount('engineRequests')->load('bank', 'owners', 'companies')))->response();
    }

    public function destroy(Request $request, Merchant $merchant): JsonResponse
    {
        $this->guardMerchantScope($request->user(), $merchant);
        $this->authorize('delete', $merchant);

        $merchant->delete();
        $this->auditService->log(AuditAction::GOVERNANCE_UPDATED, $request->user(), $merchant, [
            'after' => ['deleted_at' => $merchant->deleted_at?->toIso8601String()],
        ]);

        return response()->json(null, 204);
    }

    /**
     * Block out-of-scope access without leaking existence: a bank user reaching
     * another bank's merchant gets 404 + MERCHANT_OUT_OF_SCOPE, never a 403 oracle.
     */
    private function guardMerchantScope(User $user, Merchant $merchant): void
    {
        if ($user->isBankUser() && $user->bank_id !== $merchant->bank_id) {
            throw new HttpResponseException(
                $this->businessError('MERCHANT_OUT_OF_SCOPE', 'Merchant not found.', 404)
            );
        }
    }

    private function findDuplicateCommercialRegistration(array $companies, ?Merchant $merchant = null): ?string
    {
        $seen = [];

        foreach ($companies as $company) {
            $number = $company['commercial_registration_number'] ?? null;
            if ($number === null || $number === '') {
                continue;
            }

            // Catch duplicates within the same payload before they reach the DB
            // unique index (which would surface as an unhandled 500, not a 409).
            if (isset($seen[$number])) {
                return $number;
            }
            $seen[$number] = true;

            $exists = MerchantCompany::query()
                ->where('commercial_registration_number', $number)
                ->when($merchant, fn ($q) => $q->where('merchant_id', '!=', $merchant->id))
                ->exists();

            if ($exists) {
                return $number;
            }
        }

        return null;
    }

    private function businessError(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => request()->header('X-Request-ID'),
            ],
        ], $status);
    }
}
