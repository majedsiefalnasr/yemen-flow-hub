<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTraderRequest;
use App\Http\Requests\UpdateTraderRequest;
use App\Http\Resources\TraderResource;
use App\Models\Trader;
use App\Services\TraderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TraderController extends Controller
{
    public function __construct(private readonly TraderService $traderService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Trader::class);

        $request->validate([
            'tax_number' => ['nullable', 'string', 'max:255'],
            'trader_name' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $traders = $this->traderService->list($request->only(['tax_number', 'trader_name']), $request->integer('per_page', 20));

        return ApiResponse::success([
            'data' => TraderResource::collection($traders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $traders->currentPage(),
                'last_page' => $traders->lastPage(),
                'per_page' => $traders->perPage(),
                'total' => $traders->total(),
            ],
        ], 'Traders retrieved successfully.');
    }

    public function store(StoreTraderRequest $request)
    {
        $this->authorize('create', Trader::class);

        $trader = $this->traderService->create($request->validated());

        return ApiResponse::success(new TraderResource($trader), 'Trader created successfully.', 201);
    }

    public function show(Request $request, Trader $trader)
    {
        $this->authorize('view', $trader);

        return ApiResponse::success(
            new TraderResource($this->traderService->loadDetails($trader)),
            'Trader retrieved successfully.'
        );
    }

    public function update(UpdateTraderRequest $request, Trader $trader)
    {
        $this->authorize('update', $trader);

        $updated = $this->traderService->update($trader, $request->validated());

        return ApiResponse::success(new TraderResource($updated), 'Trader updated successfully.');
    }

    public function lookup(Request $request)
    {
        $this->authorize('viewAny', Trader::class);

        $request->validate([
            'tax_number' => ['required', 'string', 'max:255'],
        ]);

        $trader = $this->traderService->findByTaxNumber($request->string('tax_number')->toString());

        if (! $trader) {
            return ApiResponse::notFound('Trader not found.');
        }

        return ApiResponse::success(new TraderResource($trader), 'Trader retrieved successfully.');
    }
}
