<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class BankController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    #[OA\Get(
        path: '/api/banks',
        tags: ['Banks'],
        summary: 'List banks',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Banks retrieved')]
    )]
    public function index()
    {
        $this->authorize('viewAny', Bank::class);
        $actor = request()->user();

        $perPage = max(1, min(request()->integer('per_page', 20), 200));
        $banks = Bank::query()
            ->when($actor->isBankUser(), fn ($q) => $q->where('id', $actor->bank_id))
            ->when(request()->filled('search'), function ($q) {
                $s = request('search');
                $q->where(fn ($x) => $x->where('name_ar', 'like', "%{$s}%")
                    ->orWhere('name_en', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%"));
            })
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'data' => BankResource::collection($banks->getCollection())->resolve(),
            'meta' => [
                'current_page' => $banks->currentPage(),
                'last_page'    => $banks->lastPage(),
                'per_page'     => $banks->perPage(),
                'total'        => $banks->total(),
            ],
        ], 'Banks retrieved.');
    }

    #[OA\Post(
        path: '/api/banks',
        tags: ['Banks'],
        summary: 'Create bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Bank display name (Arabic, MVP single-language)'),
                    new OA\Property(property: 'code', type: 'string', maxLength: 20),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Bank created'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(StoreBankRequest $request)
    {
        $this->authorize('create', Bank::class);

        $bank = Bank::query()->create($request->validated());

        return ApiResponse::success(new BankResource($bank), 'Bank created successfully.', 201);
    }

    #[OA\Get(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Get bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank retrieved')]
    )]
    public function show(Bank $bank)
    {
        $this->authorize('view', $bank);

        return ApiResponse::success(new BankResource($bank), 'Bank retrieved.');
    }

    #[OA\Put(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Update bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank updated')]
    )]
    public function update(UpdateBankRequest $request, Bank $bank)
    {
        $this->authorize('update', $bank);

        $before = $bank->only(['name', 'code', 'is_active']);
        $bank->update($request->validated());
        $bank->refresh();
        $after = $bank->only(['name', 'code', 'is_active']);
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($v, $k) => array_key_exists($k, $before) && $before[$k] !== $v,
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->auditService->log(AuditAction::BANK_UPDATED, $request->user(), $bank, [
            'bank_id' => $bank->id,
            'before' => array_intersect_key($before, array_flip($changedKeys)),
            'after'  => array_intersect_key($after, array_flip($changedKeys)),
        ]);

        return ApiResponse::success(new BankResource($bank), 'Bank updated successfully.');
    }

    #[OA\Delete(
        path: '/api/banks/{id}',
        tags: ['Banks'],
        summary: 'Delete bank',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Bank deleted')]
    )]
    public function destroy(Bank $bank)
    {
        $this->authorize('delete', $bank);

        $bank->delete();

        return ApiResponse::success((object) [], 'Bank deleted successfully.');
    }
}
