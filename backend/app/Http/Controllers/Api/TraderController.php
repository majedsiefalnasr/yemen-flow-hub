<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTraderRequest;
use App\Http\Requests\UpdateTraderRequest;
use App\Http\Resources\TraderResource;
use App\Models\Trader;
use App\Services\TraderService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        try {
            $trader = $this->traderService->create($request->validated());
        } catch (QueryException $exception) {
            // Concurrent create that raced past the unique validation: surface a
            // clean 422 instead of a raw 500 (code-review 17-B).
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'tax_number' => 'A trader with this tax number already exists.',
                ]);
            }

            throw $exception;
        }

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

    private function isUniqueViolation(QueryException $exception): bool
    {
        // SQLSTATE 23000 = integrity constraint violation (unique/duplicate key)
        // across MySQL and SQLite.
        return (string) ($exception->errorInfo[0] ?? '') === '23000';
    }
}
