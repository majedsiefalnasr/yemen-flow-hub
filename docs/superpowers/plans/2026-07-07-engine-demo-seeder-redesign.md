# Engine Demo Seeder Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace legacy demo seeding with 56 deterministic anchors + 250 bulk engine requests on frozen Import Financing v1, with manifest parity, invariant validation, production hard blocks, and CI minimal/full modes.

**Architecture:** Layered seeders (`EngineRequestAnchorSeeder`, `EngineRequestBulkSeeder`, `EngineAuxiliaryDemoSeeder`) driven by `catalog/` + `SeederCatalog`, shared `EngineRequestScenarioBuilder`, post-build `EngineRequestAnchorInvariantValidator`, and `DemoSeedContext` for transition seeding without external side effects. Workflow v1 enforced via checked-in manifest — not gitignored `seed.ts`.

**Tech Stack:** Laravel 11, PHPUnit, Pinia/Nuxt unaffected (Playwright ref updates only), `EngineTransitionService`, `InvoiceKey`, `DocumentScanStatus`.

**Spec:** `docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md` (approved 2026-07-07)

## Global Constraints

- Anchors: **56**; bulk: **250**; full total: **306**; minimal mode: **56** anchors only.
- Reference format: `ENG-2026-{YBRD|TIIB}-{A|B}{SEQ}`; year **fixed 2026** (`SeederCatalog::DEMO_YEAR`).
- Normal invoices: `INV-YBRD-*` / `INV-TIIB-*`; only `A023` pair shares normalized invoice intentionally.
- No legacy `import_requests`, no voting-session data.
- **No** transition-built `CANCELLED`/`ABANDONED` anchors on v1; A027=`claim_released`, A028=`document_replaced`.
- `final_outcome` on `workflow_stages` only — Task 0 must verify terminal model before transition-built completed/rejected fixtures.
- Production: `LogicException` hard block — no bypass flag.
- `DEMO_SEED_SIZE=minimal` in `phpunit.xml`; full seed in dedicated CI job only.
- Commit format: `type(scope): description`; scope `testing` or `backend`; co-author line required; signed commits.
- Remove old seeders only after replacement verified (Task 14 gate).

---

### Task 0: Terminal outcome audit + manifest bootstrap

**Files:**
- Create: `backend/tests/Fixtures/import-financing-v1-manifest.php`
- Create: `backend/tests/Feature/Engine/ImportFinancingTerminalOutcomeAuditTest.php`
- Modify: `backend/database/seeders/ImportFinancingWorkflowSeeder.php` (only if audit requires reviewed data-fix)
- Docs: `docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md` (manifest outcome section if needed)

**Interfaces:**
- Produces: manifest array structure consumed by Task 3 parity test; documented terminal stage codes for anchor catalog.

- [x] **Step 1: Write audit test**

```php
// ImportFinancingTerminalOutcomeAuditTest.php
public function test_import_financing_v1_terminal_stages_match_publish_rules(): void
{
    $this->seed([GovernanceSeeder::class, ReferenceDataSeeder::class, WorkflowActionSeeder::class, ImportFinancingWorkflowSeeder::class]);
    $version = WorkflowDefinition::where('code', 'IMPORT_FINANCING')->firstOrFail()->versions()->firstOrFail();
    $finalStages = $version->stages()->where('is_final', true)->get();
    $transitions = $version->transitions()->with(['action', 'toStage'])->get();

    foreach ($transitions as $t) {
        if (! $t->toStage?->is_final) {
            continue;
        }
        $kind = $t->action->kind;
        $outcome = $t->toStage->final_outcome;
        if ($kind === WorkflowActionKind::REJECT) {
            $this->assertSame(FinalOutcome::REJECTED, $outcome, "transition {$t->id}");
        }
        if (in_array($kind, [WorkflowActionKind::APPROVE, WorkflowActionKind::CLOSE], true)) {
            $this->assertSame(FinalOutcome::COMPLETED, $outcome, "transition {$t->id}");
        }
    }
    // Record actual terminal stage codes + outcomes into manifest fixture (see Step 3)
}
```

- [x] **Step 2: Run audit**

Run: `cd backend && php artisan test tests/Feature/Engine/ImportFinancingTerminalOutcomeAuditTest.php -v`

Expected: PASS or FAIL exposing single-`CLOSED` outcome conflict.

- [x] **Step 3: If audit fails — apply reviewed v1 data-fix**

Split terminal stages in `ImportFinancingWorkflowSeeder` (immutable v1 **data-fix** only):

- `CLOSED_COMPLETED` (`is_final`, `final_outcome=COMPLETED`) ← `FINAL_APPROVE`
- `CLOSED_REJECTED` (`is_final`, `final_outcome=REJECTED`) ← `REJECT_FINAL`

Update transitions; update manifest; **do not** add CANCELLED/ABANDONED stages.

- [x] **Step 4: Bootstrap manifest from actual DB**

Populate `import-financing-v1-manifest.php` with stages, transitions, fields, permissions, documented YFH deltas (`requires_claim` on SUPPORT, FX_CONFIRM bank VIEW). Comment: derived from `seed.ts` + audit; CI contract.

- [x] **Step 5: Commit**

```bash
git add backend/tests/Fixtures/import-financing-v1-manifest.php backend/tests/Feature/Engine/ImportFinancingTerminalOutcomeAuditTest.php
git commit -m "test(backend): audit import financing v1 terminal outcomes and add manifest"
```

---

### Task 1: Demo seed guard + config

**Files:**
- Create: `backend/database/seeders/Concerns/GuardsDemoSeedEnvironment.php`
- Modify: `backend/config/demo.php`
- Modify: `backend/phpunit.xml`
- Modify: `backend/.env.example`

**Interfaces:**
- Produces: `GuardsDemoSeedEnvironment::ensureDemoSeedAllowed(): void` — throws in production; checks `seed_demo_data` + allowed env.

- [x] **Step 1: Write failing test**

```php
// tests/Unit/Seeders/DemoSeedGuardTest.php
public function test_production_environment_blocks_demo_seed(): void
{
    $this->app['env'] = 'production';
    $this->expectException(LogicException::class);
    DemoSeedGuard::ensureDemoSeedAllowed();
}
```

- [x] **Step 2: Implement guard + config**

```php
// config/demo.php
'seed_demo_data' => env('DEMO_SEED_DATA', true),
'allowed_seed_environments' => ['local', 'staging', 'testing'],
'seed_size' => env('DEMO_SEED_SIZE', 'minimal'),
```

```php
trait GuardsDemoSeedEnvironment {
    protected function ensureDemoSeedAllowed(): void {
        if (app()->environment('production')) {
            throw new LogicException('Demo engine request seeders are forbidden in production.');
        }
        if (! config('demo.seed_demo_data')) { return; }
        if (! in_array(app()->environment(), config('demo.allowed_seed_environments'), true)) {
            throw new LogicException('Demo seeding is not allowed in this environment.');
        }
    }
}
```

- [x] **Step 3: phpunit.xml env**

```xml
<env name="DEMO_SEED_DATA" value="true"/>
<env name="DEMO_SEED_SIZE" value="minimal"/>
```

- [x] **Step 4: Run test; commit**

Run: `php artisan test tests/Unit/Seeders/DemoSeedGuardTest.php`

---

### Task 2: SeederCatalog + declarative catalogs

**Files:**
- Create: `backend/database/seeders/catalog/SeederCatalog.php`
- Create: `backend/database/seeders/catalog/anchor-catalog.php`
- Create: `backend/database/seeders/catalog/engine-request-scenarios.php`

**Interfaces:**
- Produces: `SeederCatalog::ANCHOR_COUNT = 56`, `BULK_COUNT = 250`, hook constants, `ANCHOR_SPEC_VERSION = 1`, `DEMO_YEAR = 2026`.
- Produces: `anchor-catalog.php` — 28 specs/bank including Lovable base A001–A017 + A018–A028 edge rows with explicit `path` arrays.
- Produces: `engine-request-scenarios.php` — matrix summing to **250** (per approved spec).

- [x] **Step 1: SeederCatalogIntegrityTest (failing)**

Assert unique constants, regex `^ENG-2026-(YBRD|TIIB)-[AB][0-9]{3}$`, bulk sum 250, anchor count 56.

- [x] **Step 2: Implement catalogs**

Lovable `SAMPLE_REQUESTS` mapped to A001–A017 with bank invoice prefixes. A027=`claim_released`, A028=`document_replaced`. Duplicate pair A023 cross-bank.

- [x] **Step 3: Run integrity test; commit**

---

### Task 3: Workflow parity test

**Files:**
- Create: `backend/tests/Feature/Engine/ImportFinancingWorkflowParityTest.php`

- [x] **Step 1: Parity test with diff output**

Compare DB after `ImportFinancingWorkflowSeeder` to manifest: stages (order, `is_initial`, `is_final`, `final_outcome`, `requires_claim` delta), transitions (action codes, `transition_type`, `is_default_submit`, destructive flags), fields (keys, types, semantic tags), field rules, stage permissions.

On failure: print structured diff (`missing`, `extra`, `changed`).

- [x] **Step 2: Run; commit**

---

### Task 4: Anchor invariant validator

**Files:**
- Create: `backend/database/seeders/Support/EngineRequestAnchorInvariantValidator.php`
- Create: `backend/tests/Unit/Seeders/EngineRequestAnchorInvariantTest.php`

**Interfaces:**
- Produces: `EngineRequestAnchorInvariantValidator::validate(EngineRequest $request): void` — throws `InvalidArgumentException` on violation.

- [x] **Step 1: Failing tests** for each rule in spec § Invariant validator + invalid claim states.

- [x] **Step 2: Implement validator**

- [x] **Step 3: History path tests** (`EngineRequestAnchorHistoryTest.php`) for five required v1 paths.

- [x] **Step 4: Run tests; commit**

---

### Task 5: DemoSeedContext

**Files:**
- Create: `backend/database/seeders/Support/DemoSeedContext.php`
- Create: `backend/tests/Unit/Seeders/DemoSeedContextTest.php`

**Interfaces:**
- Produces: `DemoSeedContext::run(callable $callback): mixed` — wraps `Mail::fake()`, `Queue::fake()`, optional `Notification::fake()`; asserts zero mail sent and zero jobs dispatched after callback.

- [x] **Step 1: Test transition seed emits no mail/jobs**

- [x] **Step 2: Implement context binding** (no `if (seeding)` in `EngineTransitionService`).

- [x] **Step 3: Commit**

---

### Task 6: EngineRequestScenarioBuilder

**Files:**
- Create: `backend/database/seeders/Support/EngineRequestScenarioBuilder.php`

**Interfaces:**
- Consumes: `anchor-catalog.php`, `engine-request-scenarios.php`, `DemoSeedContext`, `EngineRequestAnchorInvariantValidator`, `InvoiceKey`, `RequestProjectionSync` patterns.
- Produces: `buildAnchor(array $spec): EngineRequest`, `buildBulk(string $scenario, Bank $bank, Carbon $at): EngineRequest`, helpers `applyClaimState`, `applyDocumentScanState`, `applyDuplicatePair`, `enrichRequestData`.

- [x] **Step 1: Unit tests** for invoice transform, enrichment, history builder monotonic timestamps.

- [x] **Step 2: Implement builder** — one DB transaction per anchor/bulk row; seed-owned doc/history cleanup on `ANCHOR_SPEC_VERSION` bump.

- [x] **Step 3: Terminal rows** use manifest terminal stage codes from Task 0 only.

- [x] **Step 4: `abandoned_via_api` bulk** via abandon service inside `DemoSeedContext`, not fake CLOSED transition.

- [x] **Step 5: Commit**

---

### Task 7: EngineRequestAnchorSeeder

**Files:**
- Create: `backend/database/seeders/EngineRequestAnchorSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (wire behind guard)

**Interfaces:**
- Consumes: `GuardsDemoSeedEnvironment`, `EngineRequestScenarioBuilder`, `SeederCatalog`.

- [x] **Step 1: EngineDemoSeederTest skeleton** — assert 56 anchors after minimal seed, exact refs exist.

- [x] **Step 2: Implement seeder** — `updateOrCreate` by `reference`; log `Seeding 56 demo anchors…`.

- [x] **Step 3: Run test; commit**

---

### Task 8: EngineRequestBulkSeeder

**Files:**
- Create: `backend/database/seeders/EngineRequestBulkSeeder.php`

- [x] **Step 1: Skip when `config('demo.seed_size') === 'minimal'`**

- [x] **Step 2: Seed 250 rows** with deterministic refs `ENG-2026-{BANK}-B001…B125`; `seed_batch = demo-bulk-v1` metadata.

- [x] **Step 3: Full-seed test** (separate file or group `@group full-seed`) asserting exact 306 total.

- [x] **Step 4: Commit**

---

### Task 9: DemoSystemSettingsSeeder + auxiliary rewrite

**Files:**
- Create: `backend/database/seeders/DemoSystemSettingsSeeder.php`
- Modify: `backend/database/seeders/EngineAuxiliaryDemoSeeder.php`

- [x] **Step 1: DemoSystemSettingsSeeder** — `document_scan_enforced`, `duplicate_invoice_policy=warn`; uses `GuardsDemoSeedEnvironment`.

- [x] **Step 2: Rewrite auxiliary seeder** — External FX Confirmation terminology; natural keys; upsert notifications, email_deliveries, report_exports (completed/truncated/failed), FX docs with superseded version on `document_replaced` anchor.

- [x] **Step 3: Tests** for auxiliary idempotency + hook resolution.

- [x] **Step 4: Commit**

---

### Task 10: Focused feature tests

**Files:**
- Create: `backend/tests/Feature/Engine/DemoSeedIdempotencyTest.php`
- Create: `backend/tests/Feature/Engine/DemoSeedScopeTest.php`
- Create: `backend/tests/Feature/Engine/DemoSeedDocumentScanTest.php`
- Create: `backend/tests/Feature/Engine/DemoSeedDuplicateInvoiceTest.php`
- Modify: `backend/tests/Feature/Engine/EngineDemoSeederTest.php`

- [x] **Idempotency:** clean seed, second run, partial anchors, non-demo request present, missing auxiliary backfill.

- [x] **Scope:** bank isolation, committee capability, cross-bank duplicate masking.

- [x] **Scan:** clean downloadable; pending/failed/infected blocked when enforcement on; superseded doc excluded from required evidence.

- [x] **Duplicate:** normal samples no warning; A023 pair warns; cross-bank masking for bank user.

- [x] **Projection:** reject camelCase keys; assert `InvoiceKey` normalization.

- [x] **Permission:** EXEC action panel via stage permission; negative director without EXECUTE.

- [x] **Commit**

---

### Task 11: CI full-seed job

**Deferred (2026-07-08):** No `.github/workflows/` exists anywhere in this repo yet — there is no baseline CI pipeline to add a full-seed job to. Building one from scratch (PHP/MySQL/Redis service setup, base test job, path filters) is a separate, larger undertaking than "add a job." The full-seed test group (`EngineRequestBulkSeederTest`'s `#[Group('full-seed')]` tests) already exists and passes locally with `DEMO_SEED_SIZE=full`; wiring it into CI is tracked as follow-up work once base CI exists.

**Files:**
- Create or modify: `.github/workflows/backend-tests.yml` (or project CI config)

- [ ] **Step 1: Normal job** keeps `DEMO_SEED_SIZE=minimal`.

- [ ] **Step 2: Add `backend-full-seed` job** with `DEMO_SEED_SIZE=full`; runs full-seed test group + dashboard stats smoke tests.

- [ ] **Step 3: Path filter** — required on seeder/manifest/dashboard changes.

- [ ] **Step 4: Commit**

---

### Task 12: Playwright + frontend reference updates

**Investigated (2026-07-08), no-op:** No `e2e/` directory exists. Real Playwright specs (`frontend/tests/e2e/`, `frontend/tests/visual/`) have zero hardcoded `ENG-2026-*` references. The two frontend unit-test files that do contain `ENG-2026-000NNN` strings (`useEngineRequests.test.ts`, `workflows-instance-detail.test.ts`) use them as arbitrary mocked-API-response fixtures, unrelated to the real seeded backend — nothing to rewire. Nothing changed.

**Files:**
- Grep/update: `frontend/` and `e2e/` for `ENG-2026-0020`

- [x] **Step 1: Replace hard-coded old refs** with `SeederCatalog` equivalents documented in plan README comment.

- [x] **Step 2: Run focused Playwright if present; commit**

---

### Task 13: Remove legacy seeders (verification gate)

**Files:**
- Delete: `EngineRequestDemoSeeder.php`, `ImportRequestSeeder.php`, `Support/RequestScenarioBuilder.php`
- Modify: `DatabaseSeeder.php`

**Gate — all must pass before delete:**

```bash
rg 'EngineRequestDemoSeeder|ImportRequestSeeder|RequestScenarioBuilder' backend frontend
php artisan test --filter=DemoSeed
php artisan test tests/Feature/Engine/ImportFinancingWorkflowParityTest.php
```

- [x] **Step 1: Verify gate**

- [x] **Step 2: Delete files; commit**

```bash
git commit -m "chore(backend): remove legacy import request demo seeders"
```

---

### Task 14: graphify + docs

- [x] Run `graphify update .` after code changes (local only, do not commit `graphify-out/`).

- [x] Update spec status if Task 0 changed terminal stage codes.

---

## Plan self-review (spec coverage)

| Spec section | Task |
| --- | --- |
| WP-2 status model | 0, 4, 6 |
| v1 immutability + manifest | 0, 3 |
| 56/250/306 counts | 2, 7, 8 |
| A027/A028 replacements | 2 |
| Production hard block | 1 |
| DemoSeedContext | 5, 6 |
| Idempotency | 6, 7, 10 |
| Invoice bank prefixes | 2, 6, 10 |
| Scan states | 6, 9, 10 |
| FX terminology | 9 |
| DataScope | 10 |
| CI minimal/full | 1, 8, 11 |
| Old seeder removal gate | 13 |

**Open implementation dependency:** Task 0 outcome — if v1 terminal stages require split, complete data-fix before Tasks 6–8 terminal anchors.
