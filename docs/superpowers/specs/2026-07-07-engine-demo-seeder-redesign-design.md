# Engine Demo Seeder Redesign — Design Spec

**Date:** 2026-07-07  
**Status:** Approved (2026-07-07)  
**Scope:** Reseed workflow + request demo data for post–WP-14 engine runtime (no legacy `import_requests`, no voting sessions)

---

## Goal

Replace `EngineRequestDemoSeeder` with a **two-layer demo dataset**:

1. **Anchors** — **56** fixed references for manual QA, Playwright, and PHPUnit.
2. **Bulk** — **250** requests for role dashboards, analytics date spread, and queue volume.

**Total engine requests (full seed): 306.**

All request data runs on **Import Financing workflow v1**, aligned to the Lovable reference design and enforced in CI via a checked-in manifest.

**Non-goals:** Legacy `import_requests` seeding, voting-session tables, or the pre-engine 21-value `RequestStatus` enum on `engine_requests`.

---

## WP-2 runtime status model (authoritative)

Engine requests use three related concepts. **Do not conflate them.**

| Concept | Storage | Values | Meaning |
| --- | --- | --- | --- |
| **`current_stage`** | `engine_requests.current_stage_id` → `workflow_stages.code` | `CREATE`, `INTERNAL`, `SUPPORT`, `EXEC`, `FX`, `FX_CONFIRM`, `FINAL`, `CLOSED` | Where the request sits in the published workflow graph |
| **`runtime_status`** | `engine_requests.status` | `ACTIVE`, `CLOSED`, `REJECTED`, `CANCELLED`, `ABANDONED` | Engine lifecycle state (`EngineRequestStatus`) |
| **`final_outcome`** | `workflow_stages.final_outcome` on the **terminal stage row** (not on the transition or request) | `COMPLETED`, `REJECTED`, `CANCELLED`, `ABANDONED` (enum `FinalOutcome`) | Design-time semantics on a final stage; `EngineTransitionService::resolveStatusAfterTransition()` maps `toStage.final_outcome` → `runtime_status` when `toStage.is_final` |

**WP-2 mapping** (`FinalOutcome::toRequestStatus()` / `EngineRequestStatus::fromFinalOutcome()`):

| `final_outcome` (stage) | `runtime_status` (request) | Notes |
| --- | --- | --- |
| `COMPLETED` | `CLOSED` | Success terminal — **not** `COMPLETED` as runtime status |
| `REJECTED` | `REJECTED` | Terminal rejection |
| `CANCELLED` | `CANCELLED` | Operator/system cancel |
| `ABANDONED` | `ABANDONED` | Abandoned draft/pipeline |

**Active (non-terminal) requests:** `runtime_status = ACTIVE`; `final_outcome` is null on non-final stages.

### Terminal outcome model — v1 constraints (approved)

`final_outcome` lives on **`workflow_stages`**, not on transitions or requests. A **single** shared `CLOSED` stage **cannot** simultaneously represent `COMPLETED`, `REJECTED`, `CANCELLED`, and `ABANDONED` as distinct terminal semantics — the incoming transition does not change the stage row’s `final_outcome`.

**v1 Import Financing (frozen):** seed only terminal outcomes genuinely supported by the published graph and manifest. Do **not** synthesize incompatible history or request statuses.

**Deferred to v2:** transition-built anchors for `CANCELLED` and `ABANDONED` (requires separate terminal stages, transition/request outcome storage, or another explicit mapping mechanism — a workflow-model change, not a demo-seeder data-fix).

**Pre-implementation audit (required — Task 0):** Inspect WP-2 schema and `EngineTransitionService` before building terminal fixtures. Code today:

- `resolveStatusAfterTransition()` reads `toStage.final_outcome` when `toStage.is_final`.
- `WorkflowPublishRulePack` requires REJECT transitions → `final_outcome = REJECTED` stage; APPROVE/CLOSE → `final_outcome = COMPLETED` stage.
- Migration `2026_07_06_000002` backfills a **single** final stage from incoming reject presence (cannot represent both outcomes on one stage under publish rules).
- `ImportFinancingWorkflowSeeder` seeds both `EXEC → CLOSED (REJECT_FINAL)` and `FINAL → CLOSED (FINAL_APPROVE)` into one `CLOSED` stage — **manifest + audit must confirm actual DB `final_outcome` and whether transition-built completed/rejected anchors are valid or require a reviewed v1 terminal-stage split before seeding.**

The parity manifest represents the **real implemented model**, not the conceptual table alone.

### Terminal scenario table — v1 anchors (manifest-backed)

Each row names all three columns. **Implement only rows validated by Task 0 audit.**

| Scenario key | `current_stage` | `runtime_status` | `final_outcome` (terminal stage) | Last transition |
| --- | --- | --- | --- | --- |
| `completed` | Terminal stage with `COMPLETED` outcome (e.g. `CLOSED` **only if** manifest says `final_outcome = COMPLETED`) | `CLOSED` | `COMPLETED` | `FINAL → …` / `FINAL_APPROVE` |
| `rejected` | Terminal stage with `REJECTED` outcome | `REJECTED` | `REJECTED` | `EXEC → …` / `REJECT_FINAL` |

**Not in v1 anchor catalog:** `cancelled`, `abandoned` as transition-built terminals. Constants `A027` / `A028` are **not** cancelled/abandoned until v2 or catalog-version migration.

**Abandon API note:** `POST /abandon` can set `runtime_status = ABANDONED` without a terminal stage hop (see `OutcomeSemanticsTest`). Bulk `abandoned_terminal` rows may use the abandon service in `DemoSeedContext`, not a fake `CLOSED` transition — document in manifest if used.

### Active scenario examples

| Scenario key | `current_stage` | `runtime_status` | `final_outcome` |
| --- | --- | --- | --- |
| `lovable_sample_create` | `CREATE` | `ACTIVE` | — |
| `returned_to_entry` | `CREATE` | `ACTIVE` | — |
| `claim_active` | `SUPPORT` | `ACTIVE` | — |
| `fx_confirm_active` | `FX_CONFIRM` | `ACTIVE` | — |

---

## Source-of-truth hierarchy

The Lovable file may be gitignored and unavailable in CI. Hierarchy:

1. **`dynamic-workflow-engine/src/lib/workflow-engine/seed.ts`** — external design/reference source (manual comparison when the manifest changes).
2. **`backend/tests/Fixtures/import-financing-v1-manifest.php`** — **repository-enforced CI contract** (frozen v1).
3. **`ImportFinancingWorkflowSeeder`** — must match the manifest.
4. **Manifest changes** require a reviewed manual diff against `seed.ts` (documented in PR/commit message).
5. **`ImportFinancingWorkflowParityTest`** verifies DB state against the **checked-in manifest**, not against an external file at runtime.

### Lovable → YFH field-key mapping

| Lovable (`seed.ts`) | YFH |
| --- | --- |
| `financeAmount` | `amount` |
| `invoiceNumber` | `invoice_number` |
| `requestPercentage` | `request_percentage` |

Request `data` JSON and projection columns use YFH keys only. Tests reject camelCase prototype keys.

### Published workflow v1 immutability

- **Import Financing v1 is immutable after publication.** Normal workflow evolution creates **v2+** with its own manifest and parity contract.
- The v1 manifest is a **frozen historical contract**, not a living designer export.
- `ImportFinancingWorkflowSeeder` may only change through an **explicit reviewed data-fix** when the original seed was invalid — not through routine designer edits.
- Future versions: `import-financing-v2-manifest.php`, separate seeder or versioned publish step, separate parity test.

**Documented YFH deltas on frozen v1** (not Lovable drift):

| Delta | Reason |
| --- | --- |
| `requires_claim = true` on `SUPPORT` | WP-5 stage-scoped claims |
| `FX_CONFIRM` bank `VIEW` permission | External FX confirmation bank visibility |
| Semantic projection columns | WP-4 / financing ledger |
| `invoice_number_normalized` via `InvoiceKey::normalize()` | WP-7 duplicate detection |

---

## Architecture (layered seeders — approved)

### New / changed files

```
backend/database/seeders/
  catalog/
    engine-request-scenarios.php       # bulk matrix (sums to 250)
    anchor-catalog.php                 # 56 anchor specs with history paths
    SeederCatalog.php                  # constants, counts, hook refs, fixed year
  Support/
    EngineRequestScenarioBuilder.php
    EngineRequestAnchorInvariantValidator.php
    DemoSeedContext.php                # scoped fakes / side-effect policy
  EngineRequestAnchorSeeder.php
  EngineRequestBulkSeeder.php
  DemoSystemSettingsSeeder.php         # env-gated demo settings only
  EngineAuxiliaryDemoSeeder.php
  ImportFinancingWorkflowSeeder.php    # data-fix only if manifest audit requires
  DatabaseSeeder.php

backend/tests/Fixtures/
  import-financing-v1-manifest.php

backend/tests/Feature/Engine/
  ImportFinancingWorkflowParityTest.php
  EngineDemoSeederTest.php
  SeederCatalogIntegrityTest.php
  EngineRequestAnchorInvariantTest.php
  EngineRequestAnchorHistoryTest.php
  DemoSeedIdempotencyTest.php
  DemoSeedScopeTest.php
  DemoSeedDocumentScanTest.php
  DemoSeedDuplicateInvoiceTest.php
```

### Removed (only after replacement verified — see § Removal timing)

- `EngineRequestDemoSeeder.php`
- `ImportRequestSeeder.php`
- `Support/RequestScenarioBuilder.php`

### `DatabaseSeeder` order

```
GovernanceSeeder
ScreenPermissionSeeder
ReferenceDataSeeder
WorkflowActionSeeder
ImportFinancingWorkflowSeeder
BankSeeder
UserSeeder
MerchantSeeder
── demo gate (config demo.seed_demo_data + allowed environment) ──
EngineRequestAnchorSeeder
EngineRequestBulkSeeder          # skipped when DEMO_SEED_SIZE=minimal
AuditLogSeeder
SystemSettingsSeeder           # production-safe defaults
DemoSystemSettingsSeeder       # local/testing/staging demo overrides only
NotificationTemplateSeeder
EngineAuxiliaryDemoSeeder
```

---

## Reference catalog

### Format (fixed demo year)

```
ENG-{YYYY}-{BANK}-{KIND}{SEQ}
```

| Part | Rule |
| --- | --- |
| `YYYY` | **Fixed `2026`** — demo catalog version; never derived from current date (`SeederCatalog::DEMO_YEAR`) |
| `BANK` | `YBRD`, `TIIB` |
| `KIND` | `A` = anchor, `B` = bulk |
| `SEQ` | `001`–`999` zero-padded |

Bulk row **timestamps** may be relative to seeding date; **anchor references never change**.

### Exact counts

| Layer | Count |
| --- | --- |
| Anchors | **56** (28 per bank) |
| Bulk | **250** |
| **Total** | **306** |

`DEMO_SEED_SIZE=minimal` seeds **56 anchors only** (0 bulk). Anchor refs unchanged in all modes.

### Anchor composition (56 = 34 + 22)

**Base (34):** Lovable `SAMPLE_REQUESTS` (17) × 2 banks, with bank-specific invoice transformation (§ Duplicate prevention).

**Additional per bank (11 × 2 = 22):**

| Seq (per bank) | Scenario key | `current_stage` | `runtime_status` | `final_outcome` |
| --- | --- | --- | --- | --- |
| A018 | `returned_to_entry` | `CREATE` | `ACTIVE` | — |
| A019 | `returned_to_fx` | `FX` | `ACTIVE` | — |
| A020 | `returned_to_fx_confirm` | `FX_CONFIRM` | `ACTIVE` | — |
| A021 | `claim_active` | `SUPPORT` | `ACTIVE` | — |
| A022 | `claim_expired` | `SUPPORT` | `ACTIVE` | — |
| A023 | `duplicate_invoice` | `INTERNAL` | `ACTIVE` | — (intentional shared normalized invoice) |
| A024 | `scan_pending` | `INTERNAL` | `ACTIVE` | — |
| A025 | `scan_failed` | `INTERNAL` | `ACTIVE` | — |
| A026 | `scan_infected` | `INTERNAL` | `ACTIVE` | — |
| A027 | `claim_released` | `SUPPORT` | `ACTIVE` | — (claim columns cleared after release/transition) |
| A028 | `document_replaced` | `INTERNAL` | `ACTIVE` | — (superseded + active commercial-invoice doc versions) |

Base Lovable rows already include **`completed`** (`CLOSED`/`CLOSED`/`COMPLETED`) and **`rejected`** (`CLOSED`/`REJECTED`/`REJECTED`) per bank. **`scan_clean`** is covered by default commercial-invoice document on `ENG-2026-{BANK}-A001` (and bulk rows).

**Cross-bank duplicate pair:** `ENG-2026-YBRD-A023` and `ENG-2026-TIIB-A023` share `invoice_number` / `invoice_number_normalized` intentionally.

### Bank-specific invoice transformation (normal samples only)

| Bank | `invoice_number` pattern | Example |
| --- | --- | --- |
| YBRD | `INV-YBRD-{nnnnn}` | `INV-YBRD-10000` (from Lovable index 0) |
| TIIB | `INV-TIIB-{nnnnn}` | `INV-TIIB-10000` |

Only `duplicate_invoice` anchors share a normalized key (e.g. `INV-DUP-SEED-001`). All other anchors must not trigger `DuplicateInvoiceChecker` warnings.

### `SeederCatalog` hook constants (examples)

| Constant | Reference |
| --- | --- |
| `ANCHOR_SUBMITTED_NOTIFICATION` | `ENG-2026-YBRD-A001` |
| `ANCHOR_SUPPORT_CLAIM_ACTIVE` | `ENG-2026-YBRD-A021` |
| `ANCHOR_FX_CONFIRM_PANEL` | `ENG-2026-YBRD-A017` (base Lovable FX_CONFIRM sample) |
| `ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY` | `ENG-2026-YBRD-A016` (base CLOSED/COMPLETED) |
| `ANCHOR_FX_CONFIRM_COMPLETED_SECONDARY` | `ENG-2026-TIIB-A016` |
| `ANCHOR_REJECTED_NOTIFICATION` | `ENG-2026-YBRD-A017` base rejected row |
| `ANCHOR_SCAN_PENDING` | `ENG-2026-YBRD-A024` |
| `ANCHOR_DUPLICATE_YBRD` / `ANCHOR_DUPLICATE_TIIB` | `A023` pair |

Terminology: **External FX Confirmation** document/deliverable — not “customs” in user-facing seed docs.

---

## Anchor history construction

Each anchor spec in `anchor-catalog.php` defines an **ordered path**:

```php
// Example: returned_to_entry (YBRD A018)
'path' => [
    ['CREATE', null, 'CREATE'],           // creation row
    ['CREATE', 'INTERNAL', 'APPROVE'],
    ['INTERNAL', 'CREATE', 'REJECT'],     // v1 return — not RETURN
],
'current_stage' => 'CREATE',
'runtime_status' => 'ACTIVE',
```

Rules:

- Timestamps strictly monotonic along the path.
- Every hop’s `(from_stage, to_stage, action_code)` must match a published v1 transition.
- No synthetic `RETURN` action code.
- Returned scenarios include **full** forward-and-return history, not only the latest row.
- Terminal hop must match `final_outcome` → `runtime_status` mapping.
- Builder tests required for: `INTERNAL→CREATE/REJECT`, `FX_CONFIRM→FX/REJECT`, `FINAL→FX_CONFIRM/REJECT`, `EXEC→CLOSED/REJECT_FINAL`, `FINAL→CLOSED/FINAL_APPROVE`.

---

## Bulk layer (exactly 250)

Matrix in `engine-request-scenarios.php` — **must sum to 250**:

| Scenario key | Count | Days ago min | Days ago max |
| --- | ---: | ---: | ---: |
| `create_active` | 24 | 1 | 14 |
| `internal_active` | 20 | 3 | 21 |
| `returned_to_entry` | 8 | 7 | 35 |
| `support_active` | 24 | 14 | 50 |
| `support_claim_active` | 14 | 14 | 50 |
| `support_claim_expired` | 4 | 21 | 60 |
| `support_returned` | 6 | 21 | 60 |
| `exec_active` | 16 | 45 | 120 |
| `fx_active` | 20 | 21 | 70 |
| `fx_confirm_active` | 12 | 30 | 90 |
| `final_active` | 8 | 35 | 100 |
| `completed_closed` | 20 | 180 | 365 |
| `rejected_terminal` | 12 | 90 | 210 |
| `claim_released` | 6 | 14 | 45 |
| `document_replaced` | 6 | 10 | 40 |
| `abandoned_via_api` | 6 | 5 | 30 |
| `scan_pending` | 8 | 5 | 30 |
| `scan_failed` | 4 | 10 | 40 |
| `scope_cross_bank_mask` | 4 | 30 | 90 |
| `analytics_volume` | 28 | 90 | 365 |
| **Total** | **250** | | |

Bulk references: `ENG-2026-{BANK}-B001` … `B125` per bank (125 × 2 = 250), assigned deterministically by scenario iteration order documented in the seeder.

---

## Direct-insert anchors vs transition bulk

### Direct-insert anchors

- **Purpose:** Deterministic QA/test fixtures with stable refs.
- **Do not prove** the state is reachable via live runtime alone.
- Every anchor **must pass** `EngineRequestAnchorInvariantValidator` after build.
- Selected critical anchors (e.g. `completed`, `rejected`) **also** have transition-built twins in tests-only or bulk rows to prove reachability.

### Transition bulk

- Prefer `EngineTransitionService::execute()` inside `DemoSeedContext` for forward paths.
- Post-transition flags (expired claim, scan failed) applied only after valid stage reached.

### Invariant validator (minimum checks)

- Pinned workflow version exists and matches `IMPORT_FINANCING` v1.
- `current_stage_id` belongs to that version.
- `runtime_status` compatible with stage / terminal `final_outcome`.
- Claim fields: active only when `requires_claim` on current stage; `claim_stage_id === current_stage_id` when claimed; terminal requests have claim columns cleared.
- `data` keys ⊆ published field definitions; no camelCase prototype keys.
- `amount`, `invoice_number`, `invoice_number_normalized`, `request_percentage` match `data` / `InvoiceKey` / `RequestProjectionSync` rules.
- History forms a connected path; action codes exist on published workflow.
- Terminal history hop compatible with `final_outcome`.
- Documents: correct `request_id`, `field_id`; scan state logically valid; superseded docs not counted as current evidence.

**Tests:** valid anchors pass; intentionally invalid synthetic anchors fail validation.

---

## Demo seed execution context (side effects)

Bulk transition seeding runs inside **`DemoSeedContext`** (scoped service binding, not scattered `runningInConsole()` in core services):

| Side effect | Policy during demo seed |
| --- | --- |
| DB writes / workflow history / financing ledger locks | **Enabled** (internal invariants) |
| Mail | **Suppressed** — `Mail::fake()` or null transport in context |
| Queue jobs (scan, email, notifications) | **Faked or sync-disabled** — `Queue::fake()` except explicitly documented jobs run inline for scan state |
| External HTTP / webhooks | **Suppressed** |
| Audit logs for transitions | **Enabled** (optional: tag `seeded=true` in metadata if supported) |
| Notification rows | **Created by auxiliary seeder**, not mass transition fan-out |

`migrate:fresh --seed` in local/testing **must never send real email**. Production never runs demo request seeders (§ Environment safety).

No `if (seeding)` branches inside `EngineTransitionService` or related core services.

---

## Idempotency (reference-based)

**Forbidden:** skip all seeding when any `engine_requests` row exists.

| Seeder | Strategy |
| --- | --- |
| **Anchors** | `updateOrCreate` keyed by `reference`; rebuild history/documents for that ref when spec version changes (`SeederCatalog::ANCHOR_SPEC_VERSION`) |
| **Bulk** | `updateOrCreate` keyed by deterministic `reference`; `seed_batch = 'demo-bulk-v1'` column or metadata for identification |
| **Auxiliary** | Natural keys: `(request_id, field_id, original_name)` for documents; `(recipient_id, type, anchor_ref)` for notifications; export `uuid` deterministic from anchor ref |
| **Non-demo rows** | Do not block demo seeding; demo rows identifiable by reference pattern `ENG-2026-*` |

**Rerun behavior:** Second `db:seed` updates demo rows in place; no duplicate references; missing auxiliary rows backfilled.

**ANCHOR_SPEC_VERSION rebuild:** Transactional; delete/replace **seed-owned** history and documents only (match `seed_batch` / deterministic doc keys). Never delete unrelated audit or document rows on the request.

**Tests:** clean seed; second seed run; partial anchor set; pre-existing non-demo request; missing auxiliary after anchors exist.

---

## Transaction boundaries

- Each **anchor** created in one DB transaction (request + history + docs + claim state).
- Each **bulk** request created in one DB transaction.
- **Not** one giant transaction for all 306 rows.
- Auxiliary rows for an anchor run in the same transaction as that anchor when possible.

---

## Semantic projection & merchant ownership

Every seeded request:

- `data.amount` ↔ `engine_requests.amount`
- `data.invoice_number` ↔ `invoice_number` ↔ `InvoiceKey::normalize()` → `invoice_number_normalized`
- `data.request_percentage` ↔ `request_percentage`
- Merchant on request belongs to request `bank_id` (except cross-bank duplicate scenario uses bank-local merchants with shared invoice key).
- Dynamic merchant options remain bank-scoped for bank users.

Tests reject `financeAmount`, `invoiceNumber`, `requestPercentage` in JSON.

---

## WP-5 claim scenarios

| State | Anchor / bulk | Rules |
| --- | --- | --- |
| Active | A021, bulk `support_claim_active` | `requires_claim` stage; holder with SUPPORT EXECUTE; future `claim_expires_at`; `claim_stage_id = current_stage_id` |
| Expired | A022, bulk `support_claim_expired` | `claim_expires_at` in past |
| Released | bulk `claim_released` | claim columns null after transition away or explicit release |
| Invalid | unit tests only | wrong stage, wrong `claim_stage_id`, terminal with claim set — validator rejects |

Terminal anchors (`completed`, `rejected`): **claim fields cleared**. `abandoned_via_api` bulk rows: claim cleared per abandon side effects.

---

## Document scan states

| `scan_status` | Seeded on | Enforcement-on behavior to test |
| --- | --- | --- |
| `clean` | A001 default doc, selected bulk | Download allowed |
| `pending` | A024, bulk | Download blocked |
| `failed` | A025, bulk | Download blocked |
| `infected` | A026 | Download blocked / quarantined |

Tests (with `document_scan_enforced=true` via `DemoSystemSettingsSeeder`):

- Clean downloadable; pending/failed/infected not.
- Required FILE evidence satisfied only by active **clean** current version.
- Superseded clean document does not satisfy required evidence.

When enforcement off: schema-ready rows still seeded; download tests run under enforcement-on configuration only.

---

## Auxiliary demo data (`EngineAuxiliaryDemoSeeder`)

Uses **External FX Confirmation** terminology.

| Asset | Natural key | Lifecycle states |
| --- | --- | --- |
| Commercial invoice PDF | `(request_id, field_id=docCommercialInvoice, version=active)` | clean / pending / failed / infected |
| FX confirmation deliverable | `(engine_request_id, declaration_number)` | primary + secondary anchors; active signed doc + superseded prior version on one anchor |
| `EngineNotification` | `(recipient_id, type, SeederCatalog ref)` | read, unread, archived variants |
| `email_deliveries` | deterministic id per notification hook | sent, failed |
| `report_exports` | deterministic uuid from hook ref | completed, truncated, failed |

Idempotent upsert on rerun. No duplicate auxiliary rows.

---

## DataScope demo coverage (WP-7)

Seeded dataset must exercise:

- Both `commercial_banks` orgs (`YBRD`, `TIIB`) have requests.
- Bank users see only their bank’s requests.
- `NATIONAL_COMMITTEE` users see system-wide only via capabilities — add demo users proving positive and negative cases.
- `OTHER`/null-org users without capabilities: no broad visibility.
- Cross-bank duplicate: bank user sees warning without other bank’s identifying details (masking).
- FX/SWIFT committee users on `national_committee`, not null-bank shortcuts.

---

## Demo system settings (not production)

`DemoSystemSettingsSeeder` runs only when `demo.seed_demo_data` is true in `local`, `staging`, `testing`:

- `document_scan_enforced` — enable for scan tests / QA
- `duplicate_invoice_policy` — `warn` (or product default)

`SystemSettingsSeeder` keeps production-safe defaults; **does not** override operator production configuration.

---

## Environment safety

| Rule | Detail |
| --- | --- |
| **Production hard block (non-bypassable)** | All demo seeders throw `LogicException('Demo engine request seeders are forbidden in production.')` when `app()->environment('production')` — even if `DEMO_SEED_DATA=true`, operator invokes seeder directly, or allowed environments misconfigured. **No force flag.** |
| Demo request seeders | Additionally gated by `config('demo.seed_demo_data')` **and** `app()->environment()` ∈ `config('demo.allowed_seed_environments')` (default: `local`, `staging`, `testing`) |
| `DatabaseSeeder` | Does **not** register demo seeders in production execution path |
| `DemoSystemSettingsSeeder` | Same production hard block as anchor/bulk/auxiliary seeders |
| Demo role switch | Remains local/staging-only per WP-14 |
| External I/O | No real email, external APIs, or production storage mutation |
| Logging | `Seeding demo engine requests (56 anchors, N bulk)…` clearly emitted |

Shared guard (trait or `DemoSeedGuard::ensureNotProduction()`), called at start of every demo seeder `run()`.

`config/demo.php` additions:

```php
'seed_demo_data' => env('DEMO_SEED_DATA', true), // .env.example: false for production templates
'allowed_seed_environments' => ['local', 'staging', 'testing'],
'seed_size' => env('DEMO_SEED_SIZE', 'minimal'), // minimal | full
```

**PHPUnit / normal CI:** `DEMO_SEED_DATA=true`, `DEMO_SEED_SIZE=minimal` (56 anchors).  
**Dedicated full-seed CI job:** `DEMO_SEED_SIZE=full` (306 requests).

---

## Performance acceptance criteria

| Mode | Contents | Target |
| --- | --- | --- |
| `DEMO_SEED_SIZE=minimal` | 56 anchors | ≤ 30s local `migrate:fresh --seed` anchor path; **default normal CI** |
| `DEMO_SEED_SIZE=full` | 56 + 250 | ≤ 120s local; **dedicated full-seed CI job only** |
| Queue | — | 0 real jobs dispatched (faked); **tests assert** zero dispatched jobs |
| Mail | — | 0 messages sent; **tests assert** zero deliveries |

Bulk count configurable only via `DEMO_SEED_SIZE`; anchor set **immutable**.

### CI strategy (approved)

| Job | `DEMO_SEED_SIZE` | Runs |
| --- | --- | --- |
| Normal PHPUnit pipeline | `minimal` | Anchor, parity, invariant, permission, claim, document, auxiliary, idempotency tests |
| Dedicated full-seed job | `full` | Exact bulk count, scenario distribution, dashboard/pagination/analytics volume, seed performance |

Full-seed job is **required** on changes touching: demo seeders, workflow manifest, dashboard/list stats, DataScope queries, pagination, analytics, scenario builder.

---

## Parity test coverage (`ImportFinancingWorkflowParityTest`)

Assert DB vs manifest:

- Stage codes, order, `is_initial`, `is_final`, `final_outcome`, active flag, `requires_claim` delta
- Transitions: from/to, action codes, `transition_type`, `is_default_submit`, destructive confirmation flags
- Field groups order; field keys, types, options, semantic tags
- Required-on-create; downstream visibility/editability
- Stage permissions → current governance org/team/role codes
- Documented YFH deltas only

Failures print a **structured diff** (missing/extra/changed keys).

---

## Seed catalog integrity test (`SeederCatalogIntegrityTest`)

- All anchor constants unique.
- References match regex `^ENG-2026-(YBRD|TIIB)-[AB][0-9]{3}$`.
- Auxiliary hooks resolve to existing requests.
- Bank code in reference matches request `bank_id`.
- No duplicate seq per bank/kind namespace.
- Every scenario key in bulk matrix has a builder handler.
- Bulk matrix sums to **250**.

---

## Removal timing (old seeders)

Delete `EngineRequestDemoSeeder`, `ImportRequestSeeder`, legacy `RequestScenarioBuilder` **only after**:

1. New seeders implemented.
2. New tests pass.
3. `DatabaseSeeder` migrated.
4. Playwright/PHPUnit refs updated.
5. `rg` confirms zero remaining references.

---

## Manual QA checklist

- Demo role switch: each role primary queue non-empty (full seed).
- Support claim banner: active (A021) vs expired (A022).
- Duplicate warning on `A023` pair only; normal samples clean.
- External FX Confirmation panel: `FX_CONFIRM` anchors.
- **Authorized EXEC/FINAL-stage executor** (per stage permission) sees action panel — not merely role code.
- **Negative:** user with `COMMITTEE_DIRECTOR` role but without stage EXECUTE permission does not see execute actions.
- Reports/analytics: bulk date spread populates charts.

---

## Migration / rollout

1. Add manifest + parity test; data-fix seeder if needed.
2. Implement catalog, validator, `DemoSeedContext`, anchor/bulk seeders.
3. Implement `DemoSystemSettingsSeeder` + `DatabaseSeeder` gate.
4. Update auxiliary seeder + tests.
5. Update Playwright/PHPUnit references.
6. Remove old seeders after verification gate.

---

## Approval checklist

- [x] Layered seeders + declarative catalog (architecture approved)
- [x] WP-2 status / `final_outcome` alignment documented; Task 0 audit required before terminal fixtures
- [x] v1 immutability documented
- [x] Manifest is CI contract (not gitignored `seed.ts`)
- [x] Direct-insert invariant validation specified
- [x] Transition side-effect suppression via `DemoSeedContext`; tests assert zero mail/jobs
- [x] Reference-based idempotency specified; seed-owned rebuild only
- [x] Cross-bank duplicate prevention documented
- [x] Scan enforcement scenarios + runtime download tests
- [x] External FX Confirmation terminology
- [x] Permission-based QA wording (not role-hardcoded)
- [x] Exact counts: 56 / 250 / 306
- [x] Production hard block + config gate
- [x] Anchor refs + auxiliary hooks in `SeederCatalog`; A027/A028 = claim_released / document_replaced
- [x] CANCELLED/ABANDONED transition anchors deferred to v2
- [x] CI: minimal default + dedicated full-seed job
- [x] Old seeder removal gated on verification

---

## Resolved decisions (2026-07-07)

| # | Decision |
| --- | --- |
| 1 | **Defer** transition-built `CANCELLED` / `ABANDONED` anchors to **v2**. Replace A027/A028 with `claim_released` and `document_replaced`. Task 0 audits completed/rejected terminal model. |
| 2 | Normal CI uses `DEMO_SEED_SIZE=minimal`; separate **required** full-seed job for bulk/dashboard coverage. |
| 3 | **Hard production block** in code (`LogicException`); config gate for non-production only; no bypass flag. |
