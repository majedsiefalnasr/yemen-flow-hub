# Backend Test Baseline Remediation - Design Spec

**Date:** 2026-07-08  
**Status:** Approved (2026-07-08)  
**Branch:** `fix/backend-test-baseline-remediation`  
**Source report:** `docs/superpowers/2026-07-08-pre-existing-test-suite-failures.md`

---

## Goal

Restore a trustworthy backend PHPUnit baseline by investigating every current failure, clustering failures by root cause, and then planning targeted fixes. The implementation plan must not begin until the full current failure set is refreshed and every failure file has a remediation decision.

The starting report confirms four root causes:

1. Dead Trader tests remained after the Trader subsystem was deleted.
2. Engine permission fixtures omit `organization_id`, so `StagePermissionResolver` rejects otherwise valid users.
3. `DataScope` returns empty scoped data for users without `organization_id`.
4. Workflow publish fixtures omit `final_outcome` on final stages.

The design also requires fresh investigation of the previously uninvestigated failing files before implementation planning.

## Non-Goals

- No frontend changes unless fresh investigation proves a backend test depends on a frontend-generated contract.
- No production-code change just to satisfy stale tests.
- No broad quarantine to force a green suite.
- No package manager, hook, or CI policy redesign.

## Scope

The remediation scope is the backend test baseline on the current repo branch. The worker must start from the root repo and run backend commands from `backend/`.

Fresh investigation must cover the remaining failing clusters listed in the source report:

- Profile
- Governance
- Compliance
- Permission
- Settings
- Notifications
- Merchants
- Admin
- Audit
- Auth
- Report
- `PermissionServiceDerivedRequestsTest`

The final implementation plan should be root-cause based, not file based.

## Branching And Workflow

Work happens on `fix/backend-test-baseline-remediation`.

The accepted workflow is:

1. Confirm clean status from the root repo with `git -c core.fsmonitor=false status --short`.
2. Keep all commits in the root repository.
3. Commit this design spec before implementation planning.
4. Ask the user to review the committed spec.
5. Invoke `writing-plans` only after the user approves the spec.

Commit messages must follow the repo rules: conventional commit format, allowed scope, signed commit, and the required co-author line.

## Investigation Flow

The test runner output is the source of truth. The implementation plan must include an initial investigation phase that:

1. Runs `cd backend && php artisan test --compact`.
2. Captures the current failed test list and failure messages.
3. Compares the current list with `docs/superpowers/2026-07-08-pre-existing-test-suite-failures.md`.
4. Groups failures by shared root cause.
5. Records a remediation decision for every cluster before any fixes are applied.

Each cluster follows the same loop:

1. Reproduce with the smallest relevant PHPUnit file or filter.
2. Trace the failure to a fixture, assertion, service, policy, validator, deleted subsystem, or production regression.
3. Confirm whether current docs and architecture expect the failing behavior.
4. Choose the least invasive remediation.
5. Re-run the narrow file or filter.
6. Re-run broader backend verification only at cluster boundaries and final closeout.

## Remediation Policy

Default policy: fix tests and fixtures to match current product architecture.

Allowed outcomes:

| Outcome | Use When | Expected Action |
| --- | --- | --- |
| `obsolete surface` | The product surface was intentionally removed and no production code remains | Delete the stale test |
| `fixture drift` | Test setup predates current architecture | Update factories, helpers, seed data, or setup rows |
| `assertion drift` | API errors, enum names, policies, or validation outcomes intentionally changed | Update assertions to the current contract |
| `real regression` | Current architecture/docs expect the failing behavior to work | Fix production code |
| `flaky or environment-bound` | Failure is unstable, infrastructure-bound, or not safely fixable in this wave | Quarantine with reason, owner, and reactivation condition |

Quarantine is a last resort, not the default path.

## Known Cluster Expectations

The implementation plan should treat the already confirmed clusters as starting points, but still verify them against the refreshed failure list.

### Dead Trader Tests

Expected remediation: delete the stale Trader test files after confirming `backend/app` has no live `Trader` model/service/policy/request surface.

### Engine Organization Fixture Drift

Expected remediation: update Engine test fixtures so users have `organization_id` aligned with their bank or governance organization. Prefer shared fixture helpers where repeated setup makes omission likely to recur.

### DataScope Fixture Drift

Expected remediation: use the same organization-aware fixture repair as the Engine permission cluster. Do not loosen `DataScope` just to make old fixtures pass unless investigation proves the current data-scope contract is wrong.

### Workflow Final Outcome Fixture Drift

Expected remediation: update Workflow test fixtures that create final stages so they set a valid `final_outcome`, such as `FinalOutcome::COMPLETED`, unless the test intentionally validates missing final outcome behavior.

## Tooling And Impact Checks

Use the repo's verification ladder:

- Start with the smallest relevant test file or filter.
- Run format checks only for touched PHP files where supported.
- Run the backend default suite only for final closeout or after broad shared test-helper changes.

Use SocratiCode before modifying existing services/models:

- `codebase_symbol` before changing a known symbol.
- `codebase_impact` before refactoring, deleting, or changing shared behavior.
- `codebase_search` before adding helpers that may already exist.

Graphify output remains local only. Do not stage or commit `graphify-out/`.

## Deliverables For The Implementation Plan

The follow-up implementation plan must include:

1. A refreshed failure inventory from the current backend suite.
2. A root-cause cluster table covering all failed files.
3. A remediation decision for every cluster.
4. A focused verification matrix listing exact PHPUnit files or filters per cluster.
5. A final backend closeout command and reporting format.
6. A note distinguishing fixed failures, remaining known-red failures, deprecations, and environment issues.

## Expected Task Ordering

The implementation plan should order work by dependency:

1. Refresh and classify the full failure list.
2. Delete obsolete Trader tests if still confirmed.
3. Repair shared organization-aware fixture setup for Engine and DataScope failures.
4. Repair Workflow final-outcome fixture drift.
5. Address newly discovered clusters from Profile, Governance, Compliance, Permission, Notifications, Merchants, Admin, Audit, Auth, Report, and settings tests.
6. Quarantine only remaining proven flaky or intentionally deferred cases.
7. Run final focused and default backend verification.

## Success Criteria

- Every backend failure file from the refreshed suite has a documented root-cause cluster.
- Every cluster has a remediation decision before implementation begins.
- Obsolete tests are deleted only after confirming their production surface is gone.
- Current architecture is preserved; tests are updated to match it.
- Any quarantine includes a specific reason and reactivation condition.
- The final implementation plan is actionable without broad guessing.
