<?php

namespace App\Http\Controllers\Api;

use App\Models\Merchant;
use App\Services\Authorization\DataScope;
use App\Services\Authorization\PermissionService;
use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class FinancingController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function utilization(Request $request, EngineFinancingLedger $financingLedgerService)
    {
        abort_unless(
            $request->user() && (
                $this->permissionService->userHasCapability($request->user(), 'requests', 'CREATE') ||
                $this->permissionService->userHasCapability($request->user(), 'audit', 'VIEW')
            ),
            403
        );

        $validated = $request->validate([
            'tax_number' => ['required', 'string', 'max:255'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'exclude_request_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $excludeRequestId = $validated['exclude_request_id'] ?? null;
        $taxNumber = $validated['tax_number'];
        $invoiceNumber = $validated['invoice_number'];

        $scope = DataScope::forUser($request->user());

        // Cross-bank probe denial (S-7)
        if (! $scope->systemWide) {
            if ($scope->ownBankId === null) {
                abort(403, 'Access denied.');
            }

            $merchant = Merchant::where('tax_number', EngineFinancingLedger::normalizeKey($taxNumber))->first();
            if ($merchant && $merchant->bank_id !== $scope->ownBankId) {
                abort(403, 'Cross-bank probe denied.');
            }
        }

        $usedPercent = $financingLedgerService->usedPercent($taxNumber, $invoiceNumber, $excludeRequestId, $scope);
        $remainingPercent = $financingLedgerService->remainingPercent($taxNumber, $invoiceNumber, $excludeRequestId, $scope);

        return ApiResponse::success([
            'used_percent' => $usedPercent,
            'remaining_percent' => $remainingPercent,
            'blocked' => $remainingPercent <= 0,
        ], 'Financing utilization retrieved successfully.');
    }
}
