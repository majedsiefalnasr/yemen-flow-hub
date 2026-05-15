<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class BankController extends Controller
{
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

        return ApiResponse::success(BankResource::collection(Bank::query()->latest('id')->paginate(20)), 'Banks retrieved.');
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
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
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

        $bank->update($request->validated());

        return ApiResponse::success(new BankResource($bank->refresh()), 'Bank updated successfully.');
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
