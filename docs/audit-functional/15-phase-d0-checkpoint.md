# Phase D0 Checkpoint — Dynamic Dashboard Architecture Migration

Evidence date: 2026-07-12. Phase D0 (D0.1–D0.7) complete and verified against the
recreated V2 dataset. Decision report: `14-dashboard-architecture-decision.md`
(approved, with the dashboard-family refinement).

---

## 0. Outcome

The per-role dashboard layer is replaced by **two dashboard families**, selected
by **capability**, never by role name:

- **Operational — `MyWorkDashboard.vue`**: the single dashboard for all six
  workflow-executor roles (Data Entry, Bank Reviewer, Support Committee, SWIFT
  Officer, Executive Member, Committee Director) **and any future dynamic executor
  role, automatically**.
- **Analytics & governance**: `SystemAdminDashboard` (the `CbyAdminDashboard.vue`
  component, `system_dashboard`-gated) and `BankAdminDashboard.vue`
  (`bank_analytics`-gated, bank-scoped charts retained).

Six bespoke executor dashboards and eleven of their tests were deleted (−4045
net lines); their behaviour is covered by `MyWorkDashboard` + the
`/dashboard/work` ID-parity tests.

---

## 1. Task ledger

| Task | Commit | Result |
| ---- | ------ | ------ |
| D0.1 Extract `UserActionableRequestQuery`; `/my-queue` delegates | `3cec2d67` | Behaviour-preserving; parity by IDs for all EXECUTE roles. |
| D0.2 `GET /api/dashboard/work` generic API | `8f1268d6` | actionable/claimed/tracking/sla; fixed a shallow-clone Request-bag mutation leak and a `withStageEntry()` SQLite count miscount. |
| D0.3 `MyWorkDashboard.vue` + store/composable | `9f3197cd` | Fixed layout, dynamic data; ErrorState for 403/404/429/500. |
| D0.4 SWIFT pilot + five-surface parity | `2ded15de` | Live V2: 3 FX requests, matching KPIs, 0 errors. |
| D0.5 All executor roles (Director last) → `MyWorkDashboard` | `1bbfc194` | dashboard.vue + index.vue; Director live-verified (6 FINAL, no voting UI). |
| D0.6 Capability-family routing + retire executor dashboards + voting/badge cleanup | `99bdfbfe` | Backend capability enforcement; nav badge from actionable; 6 components + 11 tests deleted. |
| D0.7 AGENTS.md architecture record | `56d4457d` | Two-family model, invariant, `/dashboard/work` contract, routing. |
| D0 dynamic-role proof | `05650774` | New role → actionable work from a stage grant, no code change. |

---

## 2. The shared actionable-work invariant

The actionable count, dashboard preview IDs, the `/workflows` nav badge, and
`/my-queue` all resolve through one contract —
`App\Services\Workflow\UserActionableRequestQuery` (ACTIVE requests on the user's
EXECUTE stages, `DataScope`-scoped) — and are equal **by record ID**. Counts use a
projection-free `countBranch()` because aggregating over `withStageEntry()`'s
`stage_entered_at` correlated-subquery select miscounts on SQLite.

`GET /api/dashboard/work` returns `actionable` / `claimed` / `tracking` / `sla` /
`recent_activity` / `metrics`; `actionable` is exactly the `/my-queue` record set.

---

## 3. Capability-family routing + backend enforcement

Frontend (`dashboard.vue`, `index.vue`), order: `system_dashboard` screen with
`VIEW` → `SystemAdminDashboard`; else `bank_analytics` screen with `VIEW` →
`BankAdminDashboard`; else → `MyWorkDashboard`. Two new screen capabilities seeded
(`system_admin`→`system_dashboard`, `bank_admin`→`bank_analytics`).

**Capability-naming contract (verified across all four layers — one canonical
form, no dotted literal stored anywhere):**

| Layer | System-admin | Bank-admin |
| ----- | ------------ | ---------- |
| Stored `screens.key` | `system_dashboard` | `bank_analytics` |
| `/auth/me` `screen_permissions` map | `['system_dashboard' => ['VIEW','MANAGE']]` | `['bank_analytics' => ['VIEW']]` |
| Frontend check (`useScreenPermissions().can`) | `can('system_dashboard','VIEW')` | `can('bank_analytics','VIEW')` |
| Backend check (`PermissionService::userHasCapability`) | `userHasCapability($user,'system_dashboard','VIEW')` | `userHasCapability($user,'bank_analytics','VIEW')` |

The check is **two arguments — screen key + capability verb**. The dotted
`system_dashboard.view` form appears only in prose as shorthand and is never a
stored value or a code literal.

The backend enforces the same capabilities independently: `DashboardStatsService`
gates the analytics branches on the capability (role **and** capability), so
revoking the capability removes analytics access and no workflow user can read
another family's analytics. `ProtectsSystemRecords` still guarantees the
`system_admin` role cannot be deleted, deactivated, or have its `code`/`is_system`
changed.

---

## 4. Required tests — all green

**Backend** (`DashboardWorkApiTest`, `DashboardFamilyCapabilityTest`,
`UserActionableRequestQueryTest`, `ScreenPermissionTest`):

1. Executor roles' actionable == `/my-queue` by IDs (Director/Support/SWIFT/Reviewer/Data Entry/Executive). ✓
2. **A new dynamic executor role receives actionable work from a stage-permission grant, no frontend change.** ✓
3. Bank Admin gated on `bank_analytics`; System Admin on `system_dashboard`. ✓
4. Bank Admin analytics restricted to its own bank (`DataScope`). ✓
5. Bank Admin has no actionable-work count via `/dashboard/work` (0 actionable). ✓
6. Revoking either analytics capability removes analytics access. ✓
7. A workflow user never receives analytics-family data. ✓
8. Tracking is VIEW-only, disjoint from actionable; no-active-role → empty; cross-bank/org excluded. ✓
9. Screen catalog is 16 (two new capabilities). ✓

**Frontend** (`DashboardPage.test.ts`, `MyWorkDashboard.test.ts`,
`useNavBadges.test.ts`): capability-family routing (system/bank/work incl. a
brand-new role with no capability → work), section fallbacks, and the `/workflows`
badge = actionable count with no voting/`/customs` remnants. ✓

Full runs: 129 backend dashboard/permission tests (545 assertions); 46 frontend
dashboard/workflow tests. Pint + ESLint clean; touched-file typecheck clean
(unrelated `reports/index.vue` baseline error remains, out of scope).

---

## 5. Live V2 browser verification

| User | Routes to | Evidence |
| ---- | --------- | -------- |
| CBY admin (`admin@cby.gov.ye`) | `SystemAdminDashboard` | "مؤشرات أداء النظام" region; **not** MyWorkDashboard (0 طابور مهامي). |
| Bank admin (`admin@ybrd.com.ye`) | `BankAdminDashboard` | إجمالي الطلبات 28, مُعتمد ومكتمل 2, monthly volume chart image; analytics intact. |
| Committee Director (`director@cby.gov.ye`) | `MyWorkDashboard` | طابور مهامي with 6 FINAL requests (A011–A013 ×2 banks); nav badge "طلبات التمويل 6" = the shared actionable count; **no voting UI**; 0 console errors. |
| SWIFT Officer (`swift@ybrd.com.ye`) | `MyWorkDashboard` | 3 FX requests (A007/A008/A019); 0 console errors (D0.4). |

Screenshots retained locally: `director-myworkdashboard-v2.png`,
`swift-myworkdashboard-v2.png` (not committed).

---

## 6. Deferred / follow-ups

- **Residual dashboard-stats cleanup (approved follow-up, does not block Phase D):**
  `/dashboard/stats` + the executor-specific `*Stats()` methods are temporary legacy
  compatibility. Execute in this order, only after each precondition holds:
  1. Inventory every remaining consumer of `/dashboard/stats` + the executor `*Stats()` methods.
  2. Confirm workflow dashboards and navigation no longer use the executor stats branches (they already read `/dashboard/work` + the shared actionable query).
  3. Remove the executor-specific stats methods (`dataEntryStats`, `bankReviewerStats`, `supportCommitteeStats`, `swiftOfficerStats`, `executiveMemberStats`, `committeeDirectorStats`) once the consumer count reaches zero.
  4. Preserve only the analytics-family contracts required by system administration (`cbyadminStats`) and bank analytics (`bankAdminStats`).
  5. Remove the backward-compatibility Director keys on `committeeDirectorStats()` once no consumer depends on them.
  6. Add negative tests proving workflow users cannot access analytics-family payloads (already present in `DashboardFamilyCapabilityTest`; extend to the cleaned endpoint).
- Backend voting-model deletion stays in the gated Phase F cleanup (only the voting
  **dashboard UI** was removed here).
- `metrics` / `recent_activity` sections are empty placeholders (Level 1); the
  metadata-driven widget catalog (Level 2) is a future enhancement.

**Phase D0 is complete and verified. Proceed to Phase D** (status-model /
semantic-role / presentation reconciliation, incl. the deferred B4 rename).
