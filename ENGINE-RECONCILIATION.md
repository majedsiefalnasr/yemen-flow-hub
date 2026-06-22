# Engine Reconciliation — keep vs retire (Epic 18 architecture spine)

**Date:** 2026-06-22
**Purpose:** Reconcile the `backend-handoff/` engine spec (written greenfield) with the **locked decision "replace the core, keep the infrastructure"**, grounded in the *actual* current Laravel schema. This is the spine every Epic 18 story references. Companion to `LOVABLE-AUDIT.md`.

> Grounded in real code: `backend/database/migrations/*`, `backend/app/Models/*`, `backend/app/Enums/*`, `backend/app/Services/Workflow/WorkflowService.php`, and Lovable `dynamic-workflow-engine/src/lib/workflow-engine/{types,seed}.ts`.

---

## A. KEEP — infrastructure that stays (engine builds on top)

| Area | Tables / code | Why it stays |
|---|---|---|
| Auth | `users`, `personal_access_tokens`, `sessions`, password-recovery cols, MFA (`totp_*`, `pin_*`, `phone_mfa`), `login_history` | Auth/MFA is infra, engine-agnostic. The engine only adds identity *structure* (org/team/role) on top — see §C. |
| Audit | `audit_logs`, `AuditService`, `AuditAction` | Engine writes transitions here (append-only). Keep; add engine event types. |
| Notifications | `notifications`, `notification_templates`, `notification_template_versions`, `email_deliveries`, Mail/Notifications services | Notification infra is reusable; engine emits workflow/SLA events into it (priority 14). |
| Settings | `system_settings`, `SystemSetting` | Platform config, engine-agnostic. |
| Entities | `banks`, `Bank` | Banks stay as **entities** under the "commercial banks" org (§C). `admin.entities` already maps. |
| RBAC primitive | `permissions`, `role_permissions` | **Repurpose** into the screen-permission catalog (priority 13), not delete. See §D-5. |
| Merchants/Traders | `merchants`, `traders`, `trader_owners`, `trader_companies` + services | Kept as data sources; engine `dynamic_select` fields read them. Terminology decision pending — §D-5. |

---

## B. RETIRE — the fixed workflow core the engine replaces

| Retire | Replaced by |
|---|---|
| `RequestStatus` enum (21 values) | dynamic `workflow_stages` (seeded 8-stage `IMPORT_FINANCING`) |
| `WorkflowService::transition()` (hardcoded paths) + `WorkflowAction` enum | `workflow_transitions` + `workflow_actions` resolved from config; new engine transition service |
| `request_stage_history` | `workflow_history` (instance-scoped, references stage IDs not enum) |
| `import_requests` (~30 typed domain columns) | `requests` (instances) + `field_definitions` + JSON `data` — **see big decision §D-2** |
| Hardcoded 8-value `users.role` semantics | org→team→role assignments (§C) |
| `WorkflowController` fixed endpoints | engine instance/transition endpoints (`workflows`, `workflows/instances/$id`) |
| **Executive voting (DI-3 LOCKED: removed)** — `request_votes`, `RequestVote`, Voting service, `eligible_voter_ids`, `voted_at`/auto-abstain, voting-rule versioning, `VoteType`/`VotingSessionStatus` enums | **No voting.** EXEC stage = single approver (committee manager / `rc_committee_manager`) via a normal designer `APPROVE`/`REJECT` action. Quorum concept dropped entirely. |
| `traders`, `trader_owners`, `trader_companies` + `TraderService` (Epic 17) — **DI-5 LOCKED** | **`merchants` / `merchant_companies` is canonical** (engine `dynamic_select` source). Trader tables retire or become a thin alias; reconcile Epic 17 trader PII/snapshot needs onto merchants. |

Keep the *exceptions* (`WorkflowLockedStateException`, `WorkflowImmutableStateException`) — still relevant (published-version immutability, terminal-stage locks).

---

## C. Identity mapping — the riskiest reconciliation

**Current:** `users.role` = single string (default `DATA_ENTRY`, 8 `UserRole` values); `users.bank_id` = FK; RBAC via `role_permissions` (role string → permission).

**Engine wants:** `organizationId` + `teamIds[]` + `roleIds[]` per user (many teams, many roles).

Mapping plan:

1. **Add** `organizations`, `teams`, `roles` (data), `user_teams`, `user_roles`. Seed from Lovable: 2 orgs, 7 teams, 8 role-codes.
2. **Org ≠ bank.** `org_bank` ("البنوك التجارية") is a category that *contains* all `banks` rows; `org_committee` ("اللجنة الوطنية") is the other org. So: keep `banks`; add `organizations`; bank-org users carry both `organizationId = org_bank` **and** `bank_id`. Committee users carry `organizationId = org_committee`, `bank_id = null`.
3. **Migrate** existing `users.role` (single) → one `user_roles` row + infer team from the role. Keep `users.role` column transitionally as a denormalized cache, or drop after cutover (recommend keep-then-drop).
4. **Decision DI-1:** does a user get **multiple** teams/roles now, or one-each at launch? `backend-handoff` "خارج المرحلة الأولى" explicitly defers multi-team/multi-role. **Recommend: model the join tables (many-to-many) but seed one-each; enforce single at UI until needed.** Schema-future-proof, behavior-simple.

---

## D. Tensions the greenfield spec hides (must decide before stories)

### D-1. Backend-first per phase vs cross-phase identity dependency
Designer stage-permissions (phase 3) and request assignments reference org/team/role. So **governance (§C) must be schema-stable before the designer**. Already in our build order — just confirming the hard dependency.

### D-2. ⚠️ BIGGEST DECISION — `import_requests` typed columns vs pure-dynamic `data` JSON
The engine models a request as `instance.data: Record<string, unknown>` — fully dynamic, driven by `field_definitions`. But current `import_requests` has ~30 **typed** columns (invoice, shipping, currency, amounts, voting, support-claim, wizard, yer-equivalent…) that power **reporting, the financing ledger, customs/FX PDFs, and duplicate detection**.

Three options:
- **Pure dynamic** — drop typed columns, everything in JSON. Maximum flexibility, but breaks typed validation, SQL reporting, ledger aggregation, duplicate-detection indexes. High rework.
- **Hybrid (recommend)** — `requests` carries a JSON `data` for designer-defined fields **plus** a small set of promoted/typed columns for the seeded `IMPORT_FINANCING` fields that reporting/ledger/customs depend on. Engine still drives visibility/required via `stage_field_rules`; the promoted columns are a performance/integrity projection.
- **Defer** — phase-4 spike decides after governance+designer land.

This single decision shapes phases 4, 5, and the ledger/customs/duplicate services. **Needs an explicit call.**

### D-3. ✅ LOCKED — Executive voting is REMOVED
Decision: **no voting at all.** The multi-member quorum model (Epic 3/17) is dropped. The EXEC stage decision is a **single approval by the committee manager** (`rc_committee_manager` / مدير اللجنة التنفيذية) using a normal designer `APPROVE`/`REJECT` action — configurable later in the designer like any other stage. This keeps the engine single-actor (matches Lovable) and **removes** the largest net-new engine item. Retire `request_votes`, `RequestVote`, Voting service, `eligible_voter_ids`, auto-abstain, voting-rule versioning, and `VoteType`/`VotingSessionStatus` enums.

### D-4. Domain side-features bound to specific stages
Customs/FX-confirmation PDF generation, financing ledger updates, support-claim TTL, duplicate detection — today these fire on specific status transitions. In the engine they must bind to **stage entry/exit hooks or action side-effects**. Need a stage-hook mechanism (not in Lovable). Likely a `stage_hooks` / action-effect registry. Flag for phase 3/4.

### D-5. Terminology + RBAC repurpose
- **merchants vs traders:** Lovable `dynamic_select` sources are `merchants`/`merchant_companies`; current has both `merchants` and `traders/trader_*`. Decide canonical source the engine reads. (See `LOVABLE-AUDIT.md` §4 + [[national committee re-scope]].)
- **`permissions`/`role_permissions` → screen permissions:** current RBAC is role→permission; engine wants screen→capability→(org/team/role). Map existing permission slugs onto the screen catalog rather than a fresh table.

---

## E. Net-new engine work beyond Lovable (not free ports)

Lovable is a mock prototype; these have **no backend at all** and some have **no Lovable model either**:
1. All 14 backend phases (migrations/models/policies/resources/tests/OpenAPI) — Lovable is localStorage-only.
2. Stage entry/exit hooks for domain side-effects (D-4) — absent in Lovable.
3. Hybrid typed/JSON request storage (D-2 LOCKED) — Lovable is pure-JSON mock.
4. Identity migration of existing users (C-3).
5. Concurrency-safe transitions (pessimistic lock) — current `WorkflowService` has it; the engine version must keep parity.

(Voting is no longer net-new work — DI-3 removed it.)

---

## F. Open decisions to lock before `bmad-create-epics-and-stories`

| # | Decision | Status |
|---|---|---|
| DI-1 | Multi-team/role per user now, or one-each? | Proceeding on recommendation: join tables (M:N), seed one-each, UI single until needed |
| DI-2 | Request storage | ✅ **LOCKED: Hybrid** — typed projection for seeded `IMPORT_FINANCING` fields + JSON `data` for designer fields |
| DI-3 | Executive voting | ✅ **LOCKED: removed** — EXEC = single committee-manager approval via designer action; no quorum |
| DI-4 | Domain side-effects (customs/FX PDF, financing ledger) | Proceeding on recommendation: action-effect/stage-hook registry in phase 3/4 |
| DI-5 | Canonical merchant source | ✅ **LOCKED: `merchants`** — engine `dynamic_select` reads merchants/merchant_companies; trader tables retire/alias |

All blocking decisions locked. **Ready for `bmad-create-epics-and-stories` (Epic 18).** DI-1 and DI-4 proceed on the stated recommendation unless overridden.
