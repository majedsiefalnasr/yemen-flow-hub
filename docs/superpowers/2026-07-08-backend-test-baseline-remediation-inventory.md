# Backend Test Baseline Remediation Inventory - 2026-07-08

## Full Suite Snapshot

- Command: `cd backend && php artisan test --compact`
- Result before remediation: `75 failed, 53 passed, 1084 deprecated`
- Duration before remediation: `683.35s`

## Cluster Decisions

| Cluster | Files | Decision | Verification |
| --- | --- | --- | --- |
| Obsolete Trader tests | `tests/Unit/**/Trader*Test.php` | Delete stale tests; production surface removed | `rg -l "Trader|StoreTraderRequest|UpdateTraderRequest|TraderPolicy|TraderService" backend/app backend/tests` |
| Organization classification fixture drift | Governance and PermissionService tests | Add required `classification`/`organization_id` fixture data | Targeted governance and permission tests |
| Pivot-role migration drift | Profile, Settings, Audit, Report | Use governance role pivots and remove stale `users.role` SQL fallback | Targeted profile/settings/audit/report tests |
| Auth/notification contract drift | Auth and SecurityEmailRedaction tests | Fake queue, send required role, assert stable issuance | Targeted auth/notification tests |
| Bank/merchant lifecycle drift | BankLifecycleGuard and MerchantIntegrity tests | Repair fixtures; then fix real guard regression if still failing | Targeted lifecycle tests |
| SLA/report/capacity projection drift | Compliance, Engine read-model/report, OutcomeSemantics | Populate org-scoped users, stage history, merchant/projection columns | Targeted compliance/engine/workflow tests |
| Workflow validation/retention drift | Workflow lifecycle/stage/delete tests | Add `final_outcome`; align delete assertions to retention rules | Targeted workflow tests |
| Workflow option/graph drift | FieldDefinition and WorkflowGraph tests | Use current merchant scope fields; align graph label assertion | Targeted workflow tests |
| Environment-bound Redis queue | Auth MFA email path | Keep test-local queue fakes; do not require Redis for default test suite | Targeted auth tests |
