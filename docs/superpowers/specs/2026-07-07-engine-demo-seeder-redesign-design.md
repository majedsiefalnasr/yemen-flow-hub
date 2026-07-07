# Engine Demo Seeder Redesign — Design Spec

**Date:** 2026-07-07  
**Status:** Draft — pending user review  
**Scope:** Reseed workflow + request demo data for post–WP-14 engine runtime (no legacy `import_requests`, no voting sessions)

---

## Goal

Replace the current `EngineRequestDemoSeeder` (40 stage-distributed requests with simplified statuses) with a **two-layer demo dataset**:

1. **Anchors** — ~50–60 fixed references for manual QA, Playwright, and PHPUnit (fast direct inserts).
2. **Bulk** — ~250 requests for role dashboards, analytics date spread, and queue volume.

All request data must run on the **Import Financing workflow v1** that mirrors the Lovable reference (`dynamic-workflow-engine/src/lib/workflow-engine/seed.ts`), adapted to Yemen Flow Hub field keys and governance bindings.

**Non-goals:** Reintroducing legacy `import_requests` seeding, voting-session tables, or the 21-value `RequestStatus` enum on `engine_requests` (engine uses `ACTIVE` / `CLOSED` / `REJECTED` + `current_stage_id`).

---

## Source-of-truth hierarchy

| Layer | Source | YFH consumer |
| --- | --- | --- |
| Workflow definition (stages, transitions, fields, groups, field rules) | `dynamic-workflow-engine/.../seed.ts` v1 | `ImportFinancingWorkflowSeeder` (+ parity test) |
| Governance org/team/role bindings | YFH `GovernanceSeeder` codes | `ImportFinancingWorkflowSeeder::seedStagePermissions()` |
| Sample instance payloads & stage distribution | `seed.ts` → `SAMPLE_REQUESTS` + `STAGE_PATH` | `EngineRequestAnchorSeeder` catalog |
| Rich scenario matrix (returns, claims, ops edges) | Port spirit of legacy `RequestScenarioBuilder` onto engine model | `catalog/engine-request-scenarios.php` + builders |
| Auxiliary hooks (docs, FX confirmations, notifications, exports) | YFH product needs | `EngineAuxiliaryDemoSeeder` wired to new anchor constants |

### Lovable → YFH field-key mapping (already shipped; must stay)

The reference prototype uses camelCase keys (`financeAmount`, `invoiceNumber`, `requestPercentage`). YFH canonical keys are snake_case semantic fields aligned with `FieldDefinition` and `engine_requests` columns:

| Lovable (`seed.ts`) | YFH (`ImportFinancingWorkflowSeeder`) |
| --- | --- |
| `financeAmount` | `amount` |
| `invoiceNumber` | `invoice_number` |
| `requestPercentage` | `request_percentage` |

Request `data` JSON and seeded `amount` / `invoice_number` / `request_percentage` columns must use YFH keys only.

### Workflow parity requirements

`ImportFinancingWorkflowSeeder` is **not rewritten from scratch**. It is audited and kept aligned with `seed.ts` via an automated parity test:

- **Definition:** `IMPORT_FINANCING`, name/description Arabic copy from reference.
- **Version:** single published v1.
- **Stages (8):** `CREATE`, `INTERNAL`, `SUPPORT`, `EXEC`, `FX`, `FX_CONFIRM`, `FINAL`, `CLOSED` — same order, `is_initial` / `is_final` flags.
- **Transitions (12):** exact from/to/action triples as `seed.ts` lines 151–164 (`APPROVE`, `REJECT`, `ADD_NOTES`, `REJECT_FINAL`, `FINAL_APPROVE`).
- **Field groups (4):** basic, invoice, shipping, docs — same labels.
- **Field definitions (~35):** same keys (with YFH snake_case mapping above), types, options, dynamic sources, reference table keys.
- **Field rules:** CREATE editable + `requiredOnCreate` set matches reference list; downstream stages read-only.
- **Stage permissions:** mapped from Lovable assignments (`org_bank`/`org_committee` teams and roles) to YFH governance codes (`commercial_banks`/`national_committee`, `entry`, `internal_review`, `support`, `executive`, `fx_ops`, `fx_confirmation`, `committee_manager`).

**YFH extensions on top of Lovable v1 (documented deltas, not drift):**

| Extension | Reason |
| --- | --- |
| `requires_claim = true` on `SUPPORT` stage | Support claim/heartbeat UX (engine feature; absent in Lovable localStorage demo) |
| `FX_CONFIRM` bank `VIEW` permission row | External FX confirmation read-only bank visibility (YFH product rule) |
| Semantic columns on `engine_requests` | `amount`, `invoice_number`, `request_percentage` populated from `data` |

Any future designer change to v1 must update **both** `seed.ts` parity manifest and `ImportFinancingWorkflowSeeder`, or the parity test fails.

---

## Architecture (recommended approach — layered seeders)

### New / changed files

```
backend/database/seeders/
  catalog/
    engine-request-scenarios.php      # declarative scenario matrix
    SeederCatalog.php                 # typed constants: anchor refs, counts, hook refs
  Support/
    EngineRequestScenarioBuilder.php  # engine-native builder (replaces RequestScenarioBuilder)
  EngineRequestAnchorSeeder.php       # direct-insert anchors
  EngineRequestBulkSeeder.php         # bulk plan executor
  EngineAuxiliaryDemoSeeder.php       # update hook refs
  ImportFinancingWorkflowSeeder.php   # parity fixes only if audit finds gaps
  DatabaseSeeder.php                  # wire new seeders

backend/tests/Feature/Engine/
  ImportFinancingWorkflowParityTest.php   # seed.ts manifest vs DB
  EngineDemoSeederTest.php                # rewritten for new catalog
```

### Removed (dead after WP-14)

- `EngineRequestDemoSeeder.php`
- `ImportRequestSeeder.php`
- `Support/RequestScenarioBuilder.php` (legacy `ImportRequest` model)

### `DatabaseSeeder` order

```
GovernanceSeeder
ScreenPermissionSeeder
ReferenceDataSeeder
WorkflowActionSeeder
ImportFinancingWorkflowSeeder      # workflow v1 — Lovable-aligned
BankSeeder
UserSeeder
MerchantSeeder
EngineRequestAnchorSeeder          # NEW
EngineRequestBulkSeeder            # NEW
AuditLogSeeder
SystemSettingsSeeder
NotificationTemplateSeeder
EngineAuxiliaryDemoSeeder
```

---

## Reference catalog (redesigned)

### Format

```
ENG-{YYYY}-{BANK}-{KIND}{SEQ}
```

| Part | Values | Example |
| --- | --- | --- |
| `YYYY` | `2026` | `2026` |
| `BANK` | `YBRD`, `CAC` | `YBRD` |
| `KIND` | `A` anchor, `B` bulk | `A` |
| `SEQ` | `001`–`999` | `001` |

`SeederCatalog` exposes named constants for auxiliary hooks, e.g.:

- `ANCHOR_SUBMITTED_NOTIFICATION` → `ENG-2026-YBRD-A001`
- `ANCHOR_SUPPORT_CLAIMED` → `ENG-2026-YBRD-A013`
- `ANCHOR_FX_CONFIRM` → `ENG-2026-YBRD-A017`
- `ANCHOR_COMPLETED_PRIMARY` / `ANCHOR_COMPLETED_SECONDARY` → FX confirmation customs rows
- `ANCHOR_REJECTED_EMAIL` → rejected notification anchor

### Anchor layer (~56 requests)

**Base:** Port Lovable `SAMPLE_REQUESTS` (17 rows) × 2 banks = 34 anchors with the same stage distribution, amounts, importers, and invoice numbers (YFH field keys).

**Additional anchors per bank (~11 each) for edge scenarios:**

| Scenario flag | Stage / status | Purpose |
| --- | --- | --- |
| `returned_to_entry` | `CREATE` / `ACTIVE` | `INTERNAL → CREATE` via `REJECT` (bank return-to-correction; v1 transition) |
| `returned_to_fx` | `FX` / `ACTIVE` | `FX_CONFIRM → FX` via `REJECT` (v1 transition) |
| `returned_to_fx_confirm` | `FX_CONFIRM` / `ACTIVE` | `FINAL → FX_CONFIRM` via `REJECT` (v1 transition) |
| `claim_active` | `SUPPORT` / `ACTIVE` | `claimed_by` + future `claim_expires_at` |
| `claim_expired` | `SUPPORT` / `ACTIVE` | expired claim columns |
| `duplicate_invoice` | any active | shares `invoice_number` with another anchor |
| `scan_pending` | `CREATE` or `INTERNAL` | document `scan_status = pending` |
| `scan_failed` | same | `scan_status = failed` |
| `exec_rejected` | `CLOSED` / `REJECTED` | `EXEC → CLOSED` via `REJECT_FINAL` — **only v1 terminal rejection path** (replaces legacy voting-reject scenarios) |
| `completed_closed` | `CLOSED` / `CLOSED` | `FINAL → CLOSED` via `FINAL_APPROVE` |

Anchors are created via **direct insert** of `engine_requests`, `workflow_history`, claim columns, and document rows where needed. History `action_code` values must match **published v1 transition action codes** (`APPROVE`, `REJECT`, `REJECT_FINAL`, `FINAL_APPROVE`, `ADD_NOTES` — no `RETURN` code; v1 uses `REJECT` for return-to-prior-stage hops).

### Bulk layer (~250 requests)

Declarative plan in `catalog/engine-request-scenarios.php` (ported from legacy `ImportRequestSeeder::$plan`, **engine-native**):

```php
// [scenario_key, count, days_ago_min, days_ago_max]
['draft_create',           24,   1,  14],
['submitted_internal',     20,   3,  21],
['support_claimed',        14,  14,  50],
['support_claim_expired',   4,  21,  60],
['exec_pending',           16,  45, 120],
['fx_active',              20,  21,  70],
['fx_confirm_active',      12,  30,  90],
['completed_closed',       12, 180, 365],
['rejected_terminal',       8,  90, 210],
// ...
```

Bulk builder **prefers `EngineTransitionService::execute()`** for forward paths (realistic invariants). Terminal edge states (expired claim, scan failed) may use direct column updates after a partial transition walk.

**No voting scenarios** (`executive_voting_open`, tie votes, session tables).

---

## `EngineRequestScenarioBuilder` responsibilities

Single engine-native builder shared by anchor + bulk:

| Method | Use |
| --- | --- |
| `buildAnchor(AnchorSpec $spec): EngineRequest` | Direct insert + synthetic history |
| `buildBulk(string $scenario, Bank $bank, Carbon $createdAt): EngineRequest` | Transition walk + flags |
| `applyClaimState(EngineRequest, ClaimState)` | Sets `claimed_by`, `claimed_at`, `claim_expires_at`, `claim_stage_id` |
| `applyDuplicatePair(...)` | Two requests, same `invoice_number`, different refs |
| `applyDocumentScanState(...)` | `engine_request_documents.scan_status` |
| `enrichRequestData(array $seedData, Merchant $merchant): array` | Same shape as current `EngineRequestDemoSeeder::requestData()` + Lovable `enrichRequestData()` defaults |

Uses `withUserRole()` / governance users from `UserSeeder` (post RM-3 pivot-only roles).

---

## Auxiliary demo data

`EngineAuxiliaryDemoSeeder` updated to import refs from `SeederCatalog` only:

| Hook | Anchor ref purpose |
| --- | --- |
| Commercial invoice PDF on every request | all anchors + bulk |
| Customs / FX confirmation PDFs | two `CLOSED`/`ACTIVE` FX_CONFIRM anchors |
| `EngineNotification` rows (4 types) | submitted, support, fx confirm, completed |
| `email_deliveries` | 3 rows tied to notification anchors |
| `report_exports` | 1 completed + 1 `truncated` + 1 `failed` (new anchors or bulk flags) |

`SystemSettingsSeeder` ensures `document_scan_enforced` and `duplicate_invoice_policy` are set so warning UI is reachable on seeded data.

---

## Testing & verification

### New: `ImportFinancingWorkflowParityTest`

Loads a **manifest** extracted from `seed.ts` (stages, transitions, field keys, required-on-create). After `ImportFinancingWorkflowSeeder`, asserts DB matches manifest (counts, codes, action codes, field keys). YFH extension columns (`requires_claim` on SUPPORT) asserted separately as documented deltas.

Manifest lives in `backend/tests/Fixtures/import-financing-v1-manifest.php` — maintained alongside seeder; comment points to `dynamic-workflow-engine/.../seed.ts` line ranges.

### Rewritten: `EngineDemoSeederTest`

Asserts:

- No legacy tables (`import_requests`, `request_votes`, …).
- Anchor count, bulk count, total history rows (ranges, not brittle per-row counts unless stable).
- Every scenario flag in catalog has ≥1 seeded request.
- `SeederCatalog` hook refs exist with expected stage/status.
- Field keys in `data` use YFH snake_case only.
- Duplicate-invoice pair detectable via `DuplicateInvoiceChecker`.
- SUPPORT claim anchors have expected `claimed_by` / expiry state.

### Manual QA checklist (post `migrate:fresh --seed`)

- Demo role switch: each role sees non-empty primary queue.
- Support claim banner: active vs expired anchors.
- Duplicate warning on flagged anchor edit/submit.
- FX confirmation queue: `FX_CONFIRM` anchors.
- Director EXEC action panel (no voting UI).
- Reports/analytics: bulk date spread populates charts.

---

## Migration / rollout

1. Implement parity test + fix any `ImportFinancingWorkflowSeeder` drift found.
2. Add catalog + builder + anchor/bulk seeders; remove dead legacy seeders.
3. Update `EngineAuxiliaryDemoSeeder` + `DatabaseSeeder`.
4. Rewrite `EngineDemoSeederTest`; run `php artisan test --filter=EngineDemo`.
5. Document anchor ref cheat-sheet in seeder catalog header comment (for QA).

**Idempotency:** Anchor/bulk seeders skip if `engine_requests` already exist (same pattern as today), unless `db:seed --force` flag is added later.

---

## Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| `seed.ts` changes outside repo (gitignored) | Manifest file is the CI contract; periodic manual diff against local `dynamic-workflow-engine` |
| Direct-insert history uses wrong action codes | Parity test on transitions + builder unit test for history hop codes |
| Slow bulk seed | Cap bulk at ~250; transition walk only for forward scenarios |
| Playwright tests hard-code old `ENG-2026-0020xx` refs | Grep and update in same PR |

---

## Approval checklist

- [x] Approach: layered seeders + declarative catalog (hybrid insert/transition)
- [x] Consumers: anchors + bulk (manual QA + automated tests)
- [x] Coverage: all seedable scenarios, **no voting**
- [x] Catalog: redesigned references (`ENG-2026-{BANK}-A###`)
- [x] Workflow: Lovable v1 `seed.ts` is definition source of truth, mapped to YFH governance + field keys
