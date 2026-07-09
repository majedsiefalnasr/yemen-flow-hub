# ARCH-007 — FX-confirmation PDF generation moved outside the transition lock

## What changed

`app/Services/Workflow/Effects/CustomsFxPdfEffect.php` (`fx.confirmation_pdf` hook, fired by `EngineTransitionService::execute()`'s `fireEntry()` — inside `DB::transaction()` + `EngineRequest::lockForUpdate()`):

- **Kept inside the lock (unchanged, fast, must-abort-capable):** `snapshot()` — the semantic-field-mapping resolution (`resolveFieldValue()` for amount/currency/supplier/goods/port + stage-entry timestamps). This is cheap (in-memory reads off the already-loaded `$request` plus a few small queries) and must be able to throw `SemanticMappingUnresolvedException` to abort the transition atomically — a genuinely unresolvable workflow config is a validation failure, not a rendering failure, and must still roll back the whole state change.
- **Moved to run after commit (was the actual problem):** the DomPDF render (`CustomsDeclarationGenerator::generate()` → `Pdf::loadView()->output()`), a second, unrelated MySQL named lock (`GET_LOCK('customs_declaration_number')`, up to 10s wait) inside that generator, the disk write (`Storage::put()`), and the `CustomsDeclaration` row creation + audit log. These now run inside a `DB::afterCommit()` closure registered from within the hook — by the time this closure fires, `EngineTransitionService::execute()`'s transaction has already committed and the `engine_requests` row lock has already released.

## Why this design (not a queued job)

The finding's own recommendation named two options: render before the lock, or split via an after-commit path/job — with the hard constraint "must keep the guarantee that a committed FX confirmation always has its document" and AGENTS.md's "wrap FX confirmation generation/completion in a single database transaction."

- **Render-before-lock was rejected**: `snapshot()` needs `$request`'s state as it exists *after* the transition's stage/data fields are set (bank, workflow version, and `firstEnteredAt()` reads against `workflow_history` rows written earlier in the same transition) — that data doesn't exist yet before the lock is acquired.
- **A queued/async job was rejected**: a queue failure (worker down, job exception) would leave a committed transition with a permanently missing document and no path back to abort it — the exact gap the finding's own guarantee forbids. `DB::afterCommit()` keeps the render synchronous within the same HTTP request/response cycle (confirmed empirically — see Test evidence) while still being outside the transaction/lock span, so there is no user-visible window where the transition succeeded but the response returned before the document was ready.
- **A "pending" declaration row + later update was rejected**: `CustomsDeclaration::booted()` enforces immutability — only `signed_fx_doc_*`/`metadata` columns may ever be updated after creation (`MUTABLE_SIGNED_DOC_COLUMNS`). Creating a row with `pdf_path = null` and updating it later would throw `LogicException` by design. So the declaration row is still created exactly once, fully formed, matching today's behavior — just from inside the after-commit callback instead of inline.

## What happens if the post-commit render fails

The transition has already committed (correctly — a PDF rendering bug should not block workflow progress that already passed validation). The failure is caught and logged via `OperationalAlertLogger::failure('fx_confirmation_pdf_render', ...)` — the same fail-visible, alertable pattern established by QUEUE-001 for the document scan job, rather than being silently swallowed. `EngineFxConfirmationController::downloadCustomsDeclaration()` already 404s cleanly (`pdf_path !== null && Storage::exists(...)`) rather than crashing if a declaration is ever missing its PDF, so this failure mode was already handled defensively on the read side before this fix.

## Test evidence

`tests/Feature/Engine/EngineDomainHooksTest.php` — 2 new tests, plus the 1 pre-existing test re-verified unchanged:

1. `test_customs_pdf_generated_on_fx_stage_entry` (pre-existing, unmodified) — still passes: the declaration and PDF file both exist immediately after the HTTP response, proving the render still completes synchronously within the same request/response cycle even though it now runs after commit.
2. `test_customs_pdf_render_runs_outside_the_transition_lock` (new) — mocks `CustomsDeclarationGenerator::generate()` to capture `DB::transactionLevel()` at call time, and asserts it equals the transaction level observed *before* the transition's HTTP request started. Confirmed red on unmodified code first (captured level 2, i.e. nested one level inside `EngineTransitionService::execute()`'s transaction) before the fix, green after.
3. `test_unresolvable_semantic_mapping_still_rolls_back_the_transition` (new) — nulls `data.amount` on the FX-stage transition (the only mutation surface, since `RequestProjectionSync::sync()` re-derives the `engine_requests.amount` column from `data.amount` on every transition) and asserts the transition still returns 422 and rolls back to the prior stage with no `CustomsDeclaration` row created — proving the abort-capable half of the hook still runs inside the lock/transaction exactly as before.

```
EngineDomainHooksTest: 7 tests (2 new), 32 assertions
```

## Regression check

```
EngineDomainHooksTest, EngineAuxiliaryDemoSeederTest, EngineCustomsSignedFxTest,
PivotRoleGroupPoliciesTest, SwiftCustomsSearchScopeTest, CustomsDeclarationPolicyTest: 40 tests, 106 assertions — all pass
```

Covers: the demo seeder's FX-confirmation seeding path, signed-FX-doc upload/download/replace, declaration immutability guards, cross-bank/role authorization on customs declarations, and customs search scoping — none of which changed behavior.

## Residual

- `financing.reserve` (`FinancingLedgerEffect`) was not touched — it is a ledger write that genuinely needs the transaction's atomicity (it's a financial reservation, not a rendering side-effect), and was not named by this finding.
- No load test was run to quantify the actual lock-hold-time reduction under concurrency (the finding's roadmap tier is "threshold-gated (concurrent transition rate on FX stages)" — this fix satisfies the qualitative gate: PDF CPU/IO time is verifiably zero-length inside the lock now, not benchmarked at a specific throughput).
