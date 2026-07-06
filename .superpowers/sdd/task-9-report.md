# Task 9 Report — Financing advisory scope (S-7)

## Status: DONE

## Commit
`feat(backend): scope financing advisory by DataScope (WP-7 S-7)`

## Changes
- **Modified `FinancingController::utilization`**:
    - Resolved `DataScopeContext` for the authenticated user.
    - Implemented cross-bank probe denial: users in `BANKING_SECTOR` are denied (403) if they query a merchant tax number that belongs to another bank.
    - Allowed NC users with `audit` VIEW capability to access the advisory endpoint system-wide.
    - Passed the `DataScopeContext` to the financing ledger service.
- **Modified `EngineFinancingLedger`**:
    - Added optional `DataScopeContext $scope` parameter to `usedPercent`, `remainingPercent`, and internal calculation methods.
    - Applied `DataScope::applyTo` to the financing query in `sumEligiblePercent` when a scope is provided.
- **Updated Tests**:
    - Created `FinancingDataScopeTest.php` to verify probe denial, NC system-wide access, and own-bank access.
    - Fixed `FinancingUtilizationEndpointTest.php` and `EngineFinancingCapacityTest.php` to include organization classification and `organization_id` on test users, ensuring compatibility with the new `DataScope` enforcement.
    - Updated mocks in existing tests to match the new `EngineFinancingLedger` method signatures.

## Verification Results
- `tests/Feature/Financing/FinancingDataScopeTest.php`: 5 tests, 8 assertions, ALL PASS.
- `tests/Feature/Financing/FinancingUtilizationEndpointTest.php`: 6 tests, 15 assertions, ALL PASS.
- `tests/Feature/Engine/EngineFinancingCapacityTest.php`: 6 tests, 26 assertions, ALL PASS.
- All tests passed with zero regressions.
- Pint code style check: PASSED (fixed minor issues).

## DataScope Enforcement
The two-layer visibility model is now enforced on the financing advisory endpoint:
1. **Capability Layer**: User must have `requests` CREATE or `audit` VIEW capability.
2. **Data Layer**: `DataScope` bounds the query to the user's organization (or system-wide for NC).
3. **Probe Denial**: Explicit check prevents bank users from probing merchants outside their institution.
