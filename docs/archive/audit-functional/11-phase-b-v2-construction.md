# Phase B — V2 Construction Notes & B4 Type-Model Conflict

Evidence date: 2026-07-11. Records the V2 build mechanism and a type-model
conflict that pauses B4 per the approved execution rules.

---

## V2 build mechanism (no seeder/DB bypass of publication validation)

The V2 requirement forbids modifying published V1 in place and forbids any
seeder/direct-DB path that bypasses publication validation. The engine already
provides the exact path a designer user would use:

- `WorkflowDesignerService::cloneVersion($actor, $v1)` — deep-copies stages,
  field groups/fields, transitions, **stage permissions, and stage field rules**
  into a fresh **DRAFT** V2; the source V1 is untouched (`:120-258`).
- Designer mutation methods (`updateTransition`, `updateStagePermission`,
  `createFieldDefinition`, `createStageFieldRule`, `updateStage`, …) apply the
  B1–B4 corrections to the DRAFT.
- `WorkflowVersionController::publish` / the designer publish path runs
  `WorkflowVersionValidator` (which includes `WorkflowPublishRulePack`) and
  refuses to publish a version with errors, then archives the prior PUBLISHED V1.

**Approach (APPROVED):** a dedicated, idempotent **console command** (not a normal
application or demo seeder) that clones published V1 → DRAFT V2, applies
corrections through the designer service, and publishes through the validated
publish path. This is _using_ the designer API, not bypassing it — the validator
gates the publish exactly as for a human designer. Existing V1 requests keep
`workflow_version_id = V1` (version pinning); new requests pin V2 after
publication.

The command (`workflow:publish-import-financing-v2`):

- Is environment-restricted to local/staging/testing (production hard-blocked).
- Takes `--definition` and `--source-version` inputs.
- **Is dry-run by default and mutates nothing; publishing requires the explicit `--publish` flag** (safety default — see the incident record below).
- Prints target env, source/destination version, and intended mutations before executing.
- Refuses to modify an existing published V2 unexpectedly (idempotent — detects an already-published corrected version and no-ops).
- Uses designer services (`cloneVersion`, `updateTransition`, `updateStagePermission`, `createField`, `setStageFieldRule`, `publishVersion`), never raw DB writes or direct status updates.
- Preserves normal audit + publication behavior.
- Reports validation errors without partially publishing.
- Leaves V1 untouched if any step fails (transactional; DRAFT discarded on error).
- Ships with tests proving an invalid configuration cannot be published, the default invocation performs no mutation, and publishing requires the opt-in flag.

## Early-development publication incident + cleanup (recorded)

**Incident:** during command development, diagnostic `php artisan tinker` scripts
called `Artisan::call('workflow:publish-import-financing-v2')` **without a dry-run
guard against the real local dev DB**, which published IMPORT_FINANCING V2 (id=39)
and archived V1 (id=1) earlier than the intended Phase B checkpoint, and created
two throwaway `ENG-DBG-*` requests.

**Assessment (approved Option A — keep the valid V2):** the published V2 is the
correct, validated output of the approved designer-driven path (FINAL owned by
`committee_director`, all three SWIFT fields present, published by user 1 via the
real validated publish path). V1 archived through the normal lifecycle. The 48 V1
requests remained pinned and unchanged. No approved business/UAT data was migrated
or deleted. Reverting a correct validated V2 would add unnecessary DB manipulation.

**Cleanup performed (targeted, by exact ID):**

- Verified requests **2886257** (`ENG-DBG-6a524c6c11ca7`) and **2886258**
  (`ENG-DBG-6a524df35aeb5`): pinned to v39, `data_len=0` (no business data),
  created by throwaway users 62/63 during the diagnostic run, no claims, no
  `workflow_history`.
- Dependent records: **4** `engine_request_documents` (ids 59–62, debug SWIFT
  PDFs); **0** notification references; **0** workflow_history; **0** claims.
- Method: single DB transaction deleting the 4 documents then the 2 requests (no
  broad pattern delete).
- Post-cleanup verification: **0** `ENG-DBG-*` requests remain, **0** orphan
  documents, **0** requests on v39; **48** V1 ACTIVE intact; v39 still PUBLISHED;
  v1 still ARCHIVED. The publication audit trail was **preserved** (no audit rows
  edited or deleted).

**Process hardening (this commit):** the command now defaults to dry-run and only
persists with `--publish`; it prints the target/mutation summary first; production
stays hard-blocked. Two safety tests prove the default performs no mutation and
that publishing requires the explicit flag. No prior approved CLI contract existed
(the command is new this session), so the default change is a pure safety
improvement, not a contract break.

## Validator contract B1 must satisfy (verified from source)

`WorkflowVersionValidator` + `WorkflowPublishRulePack`:

- `CONFIRMATION_REQUIRED` — a transition whose action kind is REJECT/CLOSE/CUSTOM
  (or `is_destructive=true`) must have a non-empty `confirmation_message`
  (`WorkflowPublishRulePack:210,244`). The four reject transitions need this.
- `UNINTENTIONAL_SELF_LOOP` — `SUPPORT→SUPPORT` (from==to) must set
  `is_self_loop=true` (`:309`).
- `ACTION_OUTCOME_MISMATCH` — a REJECT-kind transition targeting a **final** stage
  must target one with `final_outcome=REJECTED`; APPROVE/CLOSE targeting a final
  stage must target `COMPLETED` (`:223,231`). `INTERNAL→CREATE` (REJECT to a
  non-final stage) is exempt — the check skips non-final targets.
- `INITIAL_SUBMIT_AMBIGUOUS` — only relevant if the initial stage has >1 outgoing;
  CREATE has one, so no `is_default_submit` needed.

Note: `requires_comment` (runtime reason capture) is separate from
`confirmation_message` (publish-validation copy). The M1 contract wants **both** on
consequential rejects — `requires_comment=true` for the runtime reason gate and
`confirmation_message` for the publish gate.

## B4 — TYPE-MODEL CONFLICT (pausing B4 for decision)

**Per the execution rule "if the semantic-role type model cannot represent the
approved mapping safely, stop and report," B4 is paused.**

The M1 §6 approved semantic values are **not representable as a simple rename** of
the live enum without a breaking, wider refactor:

| M1 §6 approved value        | Live `StageSemanticRole` value | Conflict                               |
| --------------------------- | ------------------------------ | -------------------------------------- |
| REQUEST_CREATION            | `INITIAL_ENTRY`                | rename                                 |
| BANK_INTERNAL_REVIEW        | `BANK_REVIEW`                  | rename                                 |
| SUPPORT_COMMITTEE_REVIEW    | `SUPPORT_REVIEW`               | rename                                 |
| EXECUTIVE_REVIEW            | `EXECUTIVE_VOTE`               | rename (and must drop the voting name) |
| SWIFT_DOCUMENT_HANDLING     | `SWIFT`                        | rename                                 |
| FX_CONFIRMATION             | `FX_CONFIRMATION`              | match                                  |
| DIRECTOR_FINAL_CONFIRMATION | `FINAL`                        | rename                                 |
| COMPLETED (terminal)        | _(no case)_                    | **new — no terminal semantic exists**  |
| REJECTED (terminal)         | _(no case)_                    | **new — no terminal semantic exists**  |

Two structural problems:

1. **The enum values are hard-referenced in three code sites** —
   `SemanticRegistry::stageCodeAliases()` (`:40-47`),
   `SemanticRegistry::dashboardRoles()` (`:89-96`), and `EngineRequestReadModel`
   (literal `EXECUTIVE_VOTE`/`SWIFT`/etc.). A rename is a coordinated change across
   the registry + read model, not a one-line enum edit.
2. **Terminal semantics have no home in this enum.** `StageSemanticRole` models
   _active_ workflow phases; terminal outcome is a separate `FinalOutcome` enum
   (`COMPLETED`/`REJECTED`/`CANCELLED`/`ABANDONED`). Forcing `COMPLETED`/`REJECTED`
   into `StageSemanticRole` mixes two concerns.

**Mitigating facts (lower the urgency):**

- `semantic_role` at publish is a **warning, not an error** (`SemanticResolver::publishWarnings` → `SEMANTIC_DASHBOARD_ROLE_GAP`). So B4 is **not required for V2 to publish** — B1–B3 can ship V2 with `semantic_role` unset, and the runtime already resolves roles via the `stageCodeAliases()` fallback.
- This means B4 can be split cleanly from V2 publication.

### B4 DECISION (APPROVED): Option 3 — defer B4 to Phase D

**B4 marked:** _Deferred to Phase D due to a confirmed cross-layer type-model
dependency; V2 publication remains safe through the temporary stage-code
compatibility fallback._

Rationale (approved): `semantic_role` is a publish **warning**, not an error;
runtime continues through the existing stage-code compatibility fallback
(`SemanticRegistry::stageCodeAliases()`); renaming the enum touches the semantic
registry, dashboards, read models, API contracts, frontend state types, and
legacy presentation logic — that cross-layer work belongs to Phase D's full
status/presentation-model reconciliation. Terminal outcomes stay represented by
`FinalOutcome`, never forced into `StageSemanticRole`.

**Phase D B4 contract (to execute at the start of Phase D):**

- Replace legacy active-stage semantic names (e.g. `EXECUTIVE_VOTE`) with the approved non-voting meanings (e.g. `EXECUTIVE_REVIEW`).
- Coordinate across `StageSemanticRole`, `SemanticRegistry`, `EngineRequestReadModel`, dashboards, API resources, frontend types, and tests.
- Keep active-stage semantics separate from terminal outcomes.
- Represent terminal completion through `FinalOutcome` (COMPLETED / REJECTED / CANCELLED / ABANDONED).
- Add `semantic_role` and request-level `final_outcome` to the API contract (D1).
- Use a temporary compatibility adapter for V1 stages lacking semantic metadata.
- Define and test explicit exit criteria for removing literal stage-code fallbacks.
- **Do not add terminal `COMPLETED`/`REJECTED` cases to `StageSemanticRole`.**

**Phase B therefore ships B1–B3 only. V2 publishes validator-clean without B4;
`semantic_role` stays unset on V2 stages (runtime resolves via the alias fallback).**
