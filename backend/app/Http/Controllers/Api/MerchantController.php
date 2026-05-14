<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreMerchantRequest;
use App\Http\Requests\UpdateMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Support\ApiResponse;
use OpenApi\Attributes as OA;

class MerchantController extends Controller
{
    #[OA\Get(path: '/api/merchants', tags: ['التجار / Merchants'], summary: 'List merchants', responses: [new OA\Response(response: 200, description: 'Merchants retrieved')])]
    public function index()
    {
        $this->authorize('viewAny', Merchant::class);

        $query = Merchant::query()
            ->with('bank')
            ->forUser(request()->user())
            ->when(request()->filled('bank_id') && !request()->user()->isBankUser(), fn ($q) => $q->where('bank_id', request('bank_id')))
            ->when(request()->has('is_active'), fn ($q) => $q->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOL)))
            ->when(request()->filled('search'), function ($q) {
                $s = request('search');
                $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('commercial_register', 'like', "%{$s}%")->orWhere('tax_number', 'like', "%{$s}%"));
            })
            ->latest('id');

        return ApiResponse::success(MerchantResource::collection($query->paginate(20)), 'Merchants retrieved.');
    }

    #[OA\Post(
        path: '/api/merchants',
        tags: ['التجار / Merchants'],
        summary: 'Create merchant',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'bank_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'commercial_register', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'tax_number', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'national_id', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'owner_name', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Merchant created')]
    )]
    public function store(StoreMerchantRequest $request)
    {
        $this->authorize('create', Merchant::class);

        $payload = $request->validated();
        if (request()->user()->isBankUser()) {
            $payload['bank_id'] = request()->user()->bank_id;
        } elseif (empty($payload['bank_id'])) {
            return ApiResponse::validationError(['bank_id' => ['bank_id is required for CBY users.']]);
        }
        $payload['created_by'] = request()->user()->id;

        $merchant = Merchant::query()->create($payload);
        return ApiResponse::success(new MerchantResource($merchant->load('bank')), 'Merchant created successfully.', 201);
    }

    #[OA\Get(path: '/api/merchants/{id}', tags: ['التجار / Merchants'], summary: 'Get merchant', responses: [new OA\Response(response: 200, description: 'Merchant retrieved')])]
    public function show(Merchant $merchant)
    {
        $this->authorize('view', $merchant);
        return ApiResponse::success(new MerchantResource($merchant->load('bank')), 'Merchant retrieved.');
    }

    #[OA\Put(path: '/api/merchants/{id}', tags: ['التجار / Merchants'], summary: 'Update merchant', responses: [new OA\Response(response: 200, description: 'Merchant updated')])]
    public function update(UpdateMerchantRequest $request, Merchant $merchant)
    {
        $this->authorize('update', $merchant);
        $payload = $request->validated();
        if (request()->user()->isBankUser()) {
            $payload['bank_id'] = request()->user()->bank_id;
        }
        $merchant->update($payload);
        return ApiResponse::success(new MerchantResource($merchant->refresh()->load('bank')), 'Merchant updated successfully.');
    }

    #[OA\Delete(path: '/api/merchants/{id}', tags: ['التجار / Merchants'], summary: 'Delete merchant', responses: [new OA\Response(response: 200, description: 'Merchant deleted')])]
    public function destroy(Merchant $merchant)
    {
        $this->authorize('delete', $merchant);
        $merchant->delete();
        return ApiResponse::success((object) [], 'Merchant deleted successfully.');
    }
}
