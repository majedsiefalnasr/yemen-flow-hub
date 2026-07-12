# Request State Model

**Verified:** 2026-07-12, against `backend/app/` and `frontend/app/`
directly. This is the single source of truth for how `EngineRequest`
state is represented — other documents link here instead of repeating
this model.

For the underlying schema, see
[`06-database-and-models.md`](06-database-and-models.md). For permission
mechanics that gate access to state transitions, see
[`03-permission-model.md`](03-permission-model.md).

---

## The old model is gone — do not recreate it

The old 22-value frontend `RequestStatus` enum has been **removed**.
Confirmed: no `RequestStatus` type or enum exists anywhere in
`frontend/app/**` — the only two hits for the string across the whole
frontend tree are `frontend/CLAUDE.md`'s prose describing what must not
be reintroduced, and an unrelated, still-live `EngineRequestStatus` type
(see the frontend-drift note below — different name, different scope).

Request state is **four independent concepts**, never one combined
static status enum:

1. `runtime_status`
2. `current_stage`
3. `current_stage.semantic_role`
4. `final_outcome`

Do not reintroduce a `RequestStatus`-style vocabulary that reconstructs
state from a label instead of reading these four fields directly. **Not
every one of the old 22 values has a current equivalent.** All _current_
request state must be represented by the four concepts below — but
several of the removed values were specific to the voting-session
feature itself (e.g. `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`,
`EXECUTIVE_VOTING_CLOSED`), and that feature was removed, not merely
renamed. Those values have **no live V1 equivalent to map to** — do not
attempt to reconstruct them from the four fields, and do not recreate
them as dead branches "just in case." Retired feature-specific values
must not be mapped or recreated; only genuinely still-live business
positions (e.g. "waiting for SWIFT," "with the Executive Committee")
have a current representation, expressed via `current_stage` and its
`semantic_role`.

---

## 1. `runtime_status`

**Persistence:** `EngineRequest.status`, a plain `string(20)` DB column
(`backend/database/migrations/2026_06_24_000001_create_engine_requests_table.php`,
`default('ACTIVE')`, **not nullable**). Not a database-level enum —
nothing in the schema itself restricts the column's values.

**Application constraint:** `App\Support\EngineRequestStatus` — a plain
class with string constants, **not** a native PHP `enum`, and **not**
under the `App\Enums\` namespace despite the naming convention every
other status-like concept in this codebase follows. This distinction
matters and has been a source of prior documentation error — cite it
exactly as `App\Support\EngineRequestStatus`, never
`App\Enums\EngineRequestStatus`.

**Exactly 5 values:** `ACTIVE`, `CLOSED`, `REJECTED`, `CANCELLED`,
`ABANDONED`.

**API serialization:** `EngineRequestResource` exposes this value
**twice** — as `status` (raw column alias, kept for frontend migration
compatibility) and as `runtime_status` (the canonical name), both
carrying the identical value. New frontend code should read
`runtime_status`, not `status`.

**Frontend typing drift (found during verification, not previously
documented):** `frontend/app/types/models.ts` declares
`export type EngineRequestStatus = 'ACTIVE' | 'CLOSED' | 'REJECTED'` — a
**different type from the removed `RequestStatus`**, but incomplete: it
is missing `'CANCELLED'` and `'ABANDONED'`, covering only 3 of the
backend's 5 live values. This is drift to fix in frontend code, not
something to document as intentional — a `CANCELLED` or `ABANDONED`
request currently has no correctly-typed frontend representation.

---

## 2. `current_stage`

**Persistence:** `EngineRequest.current_stage_id`, a non-nullable
`foreignId` → `workflow_stages` — every `EngineRequest` always occupies
some stage; there is no "no current stage" state.

**API serialization:** present in `EngineRequestResource`'s response
only when the `currentStage` relation is `whenLoaded()` (absent on
endpoints that don't eager-load it — this is a response-shape
availability gap, not a data-level nullability). When present, the
nested object is:

```json
{
  "id": 3,
  "code": "SUPPORT",
  "name": "...",
  "semantic_role": "SUPPORT_REVIEW",
  "is_initial": false,
  "is_final": false,
  "sla_duration_minutes": 120,
  "requires_claim": true
}
```

Workflow stages and their labels (`name`) are **Designer-defined**, not
a fixed frontend vocabulary. A request's "business status" as a human
would describe it — e.g. "waiting for SWIFT," "with the Executive
Committee" — is expressed by which `WorkflowStage` row it currently
occupies, resolved through the designer's own configuration, not through
any static string the frontend hardcodes.

---

## 3. `current_stage.semantic_role`

**Persistence:** `workflow_stages.semantic_role`, nullable `string(50)`,
cast to `App\Enums\StageSemanticRole` (nullable enum cast).

**Exactly 8 cases:** `INITIAL_ENTRY`, `BANK_REVIEW`, `SUPPORT_REVIEW`,
`SWIFT`, `EXECUTIVE_REVIEW`, `FINANCE_RESERVE`, `FX_CONFIRMATION`,
`FINAL`.

**Nullable, and this is load-bearing** for stages that predate the
semantic-role rollout (see the compatibility fallback below).

### `EXECUTIVE_REVIEW` on the backend — but real frontend contract drift

**Backend:** `App\Enums\StageSemanticRole::EXECUTIVE_REVIEW` is the
current, and only, case for the executive decision stage.
`EXECUTIVE_VOTING`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`,
and `WAITING_FOR_VOTING_OPEN` return zero matches anywhere in
`backend/app` — no alias, no dead case, no comment-only reference.

**Frontend: real, live contract drift, not fully clean.**
`frontend/app/types/models.ts`'s `StageSemanticRole` TypeScript type
still declares `'EXECUTIVE_VOTE'` as one of its 8 union members, and is
**missing `'EXECUTIVE_REVIEW'` entirely** — the type does not contain the
string the backend actually emits. A prior verification pass claimed
"zero matches" for voting-named semantic-role strings across both
`backend/app` and `frontend/app`; that claim was wrong for the frontend
half — it searched for `EXECUTIVE_VOTING` (with the `-ING` suffix) and
missed the frontend's actual residual spelling, `EXECUTIVE_VOTE`. This is
the same class of drift as the frontend `EngineRequestStatus` type
documented above (a live-but-incomplete/incorrect TypeScript type that no
longer matches its backend counterpart) — not corrected here (no
production code changed in this documentation pass), but recorded
explicitly rather than asserted clean.

Executive Voting the _feature_ remains out of V1 regardless of this
naming drift — these are two distinct facts. `EXECUTIVE_REVIEW` denotes
the stage where the Executive Committee reviews and decides on a request
(approve/reject); it does not imply a voting-session UI, vote-casting
mechanism, or multi-member tally exists — none of that does, on either
side of the stack. See
[`04-dashboard-architecture.md`](04-dashboard-architecture.md) and
[`api-reference.md`](../api-reference.md) for the full inventory of
voting-related cleanup debt that remains unreachable in source but does
not constitute a live feature.

---

## 4. `final_outcome`

**Persistence:** `workflow_stages.final_outcome`, nullable `string(20)`,
cast to `App\Enums\FinalOutcome` — a genuine PHP backed enum (invalid
strings in the DB throw on hydration; this is enforced, not merely
conventional).

**Exactly 4 cases:** `COMPLETED`, `REJECTED`, `CANCELLED`, `ABANDONED`.

**Lives on the terminal stage — by intended architecture and current
convention, not by a code-enforced guard.** The intended design, and the
current Import Financing V2 workflow's actual convention, is: a stage is
either mid-flow (carries a `semantic_role`, `final_outcome` stays null)
or terminal (carries a `final_outcome`, `semantic_role` stays null) — not
both on the same row. In Import Financing V2 specifically, the terminal
`CLOSED_*` stages keep `semantic_role` null and carry `final_outcome`
instead.

`StageSemanticRole::FINAL` is a separate case from this — it marks the
_operational_ `FINAL` stage in the stage graph (the stage a request
passes through on its way to completion), and is **not itself a terminal
outcome value**. A stage with `semantic_role: FINAL` does not
automatically carry a `final_outcome`; the two remain independently
nullable columns.

**No structural validation currently prevents a stage row from carrying
both `semantic_role` and `final_outcome` simultaneously.**
`StoreWorkflowStageRequest`/`UpdateWorkflowStageRequest` validate each
field independently (`semantic_role` against `Rule::enum(StageSemanticRole::class)`,
`final_outcome` against its own rule) with no cross-field check between
them, and no model-level or database-level constraint enforces mutual
exclusivity either. Do not document "never combined" as code-enforced
until such a guard actually exists — today it is a convention this
workflow version happens to follow, not an invariant the system
guarantees.

**API serialization is conditional, and "absent" is not the same as
"null."** `EngineRequestResource` exposes `final_outcome` only when the
current stage is loaded, non-null, **and** `is_final` is true. On a
non-final request, the `final_outcome` key is **absent from the JSON
response entirely** — it does not serialize as `"final_outcome": null`.
Frontend code checking for this field must check for key absence, not
just falsy/null, if it needs to distinguish "not yet terminal" from
"explicitly no outcome."

### `final_outcome` → `runtime_status` mapping (verified, not previously documented)

`FinalOutcome::toRequestStatus()` delegates to
`EngineRequestStatus::fromFinalOutcome()`:

| `final_outcome` | → `runtime_status` |
| --------------- | ------------------ |
| `COMPLETED`     | `CLOSED`           |
| `REJECTED`      | `REJECTED`         |
| `CANCELLED`     | `CANCELLED`        |
| `ABANDONED`     | `ABANDONED`        |

**There is no `runtime_status: COMPLETED` value** — successful completion
is `runtime_status: CLOSED` with `final_outcome: COMPLETED`. This is a
genuinely easy mistake to make when reading the two vocabularies side by
side; the two enums do not share a 1:1 naming scheme by design.

### FX confirmation, not "customs declaration"

Where older docs or code say "customs declaration" for the Director's
final workflow step, current work must align to external FX confirmation
(`تأكيد مصارفة خارجية`). The `customs_declarations` DB table name is a
retained legacy-compatibility name (documented in
[`06-database-and-models.md`](06-database-and-models.md)) — that is a
storage detail, separate from the question of whether
`CUSTOMS_DECLARATION_ISSUED` exists as a live status/outcome value.

**It does not, on the backend — but frontend dead-code residue was
found.** The live `App\Enums\AuditAction` enum case is
`CUSTOMS_ISSUED`, not `CUSTOMS_DECLARATION_ISSUED`; no backend enum,
model, or emitted string contains the longer name (it survives only in
non-runtime surfaces: `backend/README.md`, AI-tooling config files, and
one legacy migration touching a pre-redesign status column). The
frontend, however, still has an **unreachable dead map entry**:
`frontend/app/pages/audit.vue`'s `ACTION_LABELS` object includes
`CUSTOMS_DECLARATION_ISSUED: 'إصدار تأكيد المصارفة الخارجية'` — a key
that can never match any `audit_logs.action` value the backend actually
writes, since the backend only ever writes `'CUSTOMS_ISSUED'`. The
lookup falls back to `action.replace(/_/g, ' ')` for unmapped codes, so
this is not a functional bug, only stale dead code — the same class of
issue as the residual voting keys documented next to it in the same
object (`VOTE_SUBMITTED`, `VOTING_SESSION_OPENED`,
`VOTING_SESSION_CLOSED` — none match the live `AuditAction` enum's
`VOTE_CAST` case or any voting-session case, because no voting-session
case exists). Recorded here as cleanup debt, not corrected in this
documentation pass.

---

## Compatibility fallback (temporary — has exit criteria, not yet satisfied)

`SemanticResolver::stageForRole()` and `EngineRequestReadModel::bucket()`
resolve a stage by `semantic_role` first, falling back to a hardcoded
stage-`code` match (`SemanticRegistry::stageCodeAliases()`) when
`semantic_role` is unset. This exists so requests on workflow versions
published before the semantic-role rollout (or any hand-built `DRAFT`
version) still resolve correctly.

**Exact mechanism, verified:**

- `SemanticRegistry::stageCodeAliases()` — a pure-code map, no DB table,
  mapping legacy stage codes to `StageSemanticRole` cases (e.g.
  `CREATE` → `INITIAL_ENTRY`, `FX` → `SWIFT`).
- `SemanticResolver::stageForRole()` — queries by `semantic_role` first;
  if null, falls back to querying by `code` via the alias map. Same
  pattern in `fieldForTag()` for `field_definitions.semantic_tag`.
- `EngineRequestReadModel::bucket()` — its `STAGE_BUCKETS` constant
  stores both `roles` and `codes` per bucket; the query does
  `whereIn('semantic_role', $roles) OR whereIn('code', $codes)` — an
  OR-fallback achieving the same net effect as the resolver's
  try-then-fallback pattern.

### Removal criteria — verified against current state, all still open

AGENTS.md defines the exit criteria for retiring this fallback. Checked
each against current source:

1. **Every occupiable stage on every ACTIVE-request-reachable workflow
   version has `semantic_role` set.** — **Cannot be confirmed by static
   analysis; requires a live database check.** No seeder or factory sets
   `semantic_role` at all (`grep` across `database/seeders/` and
   `database/factories/` for `semantic_role` returns zero hits), meaning
   any DB-seeded or factory-built stage defaults to `semantic_role: null`
   unless something else sets it explicitly (e.g.
   `PublishImportFinancingV2Command`, whose own comment documents that it
   depends on the fallback still working). Settling this criterion
   requires querying `workflow_stages` joined against `engine_requests`
   directly against a real database, not the codebase.
2. **No consumer relies on the `codes` half of the fallback.** — **Two
   separate claims here; only one is confirmed by static analysis.**
   Confirmed: there is no roles-only consumer path anywhere in source.
   `EngineRequestReadModel::bucket()` is called from roughly 40 call
   sites across `ProfileController` and `DashboardStatsService`; every
   one of them goes through the single `bucket()` method whose SQL always
   includes the `code IN (...)` half — there is no call-site variant that
   omits it. `SemanticResolver::stageForRole()` is likewise called only
   through its explicit-then-alias-fallback path (from
   `publishWarnings()` and `CustomsFxPdfEffect`), with no roles-only
   alternative anywhere. **This proves the implementation still executes
   a query containing both conditions at every call site — it does not
   prove that any live record's result actually depends on the `codes`
   half firing.** Whether the `codes` half is ever the one that matches a
   real row (as opposed to `semantic_role` always matching first, with
   `codes` present in the SQL but never the decisive clause) requires a
   live database query, runtime telemetry, or a targeted regression test
   — none of which this document has performed. Removing the fallback
   today would additionally require changing the shared `bucket()`/
   `stageForRole()` implementation itself, since no alternate code path
   exists to fall back to. **Do not mark this criterion "confirmed not
   met" from call-site count alone** — the call-site count proves the
   _code path_ is still exercised; it does not prove _runtime data
   dependence_, which remains unconfirmed either way.
3. **A regression test proves the code-only path is dead.** — Not
   verified to exist; no such test was found during this pass.
4. **No archived version with ACTIVE requests still depends on it.** —
   Requires the same live-database check as criterion 1.

**None of the four criteria are confirmed satisfied. Do not remove this
fallback.** There is also currently no logging, metric, or telemetry
anywhere that fires when the fallback branch is actually taken (checked
`SemanticResolver.php` and `EngineRequestReadModel.php` for any `Log::`
call — none exists) — if a future effort wants to measure how often the
fallback fires before removing it, that instrumentation does not exist
yet and would need to be added first.

---

## `WorkflowVersion.state` and `WorkflowStage.status` are distinct concepts — not `runtime_status`

Two more state-like fields exist in the schema that are easy to conflate
with `runtime_status` but govern entirely different things:

- **`WorkflowVersion.state`** (`DRAFT`\|`PUBLISHED`\|`ARCHIVED`) — a real
  backed PHP enum (`App\Enums\WorkflowVersionState`), governs whether a
  _workflow version_ can still be edited in the designer
  (`isEditable()` is true only for `DRAFT`). This has nothing to do with
  any individual request's runtime status.
- **`WorkflowStage.status`** (`ACTIVE`\|`INACTIVE`) — **a plain
  validated string column, not a PHP enum cast** (unlike `semantic_role`
  and `final_outcome`, which are). Enforced only at the Form Request
  validation layer (`in:ACTIVE,INACTIVE`) when creating/updating a stage,
  not by a database-level or model-level cast.

### Valid and invalid combinations (verified, not invented)

`WorkflowPublishRulePack::validateReachability()` rejects **every**
unreachable stage at publish time, regardless of its `status`
(`STAGE_UNREACHABLE` error) — there is no exemption for unreachable
`INACTIVE` stages specifically. Separately,
`WorkflowPublishRulePack::validateStageActivity()` blocks publishing if:
the initial stage is `INACTIVE`; any final stage is `INACTIVE`; any
transition references an inactive stage; or any _reachable_ non-final
stage is `INACTIVE`. Combined with `WorkflowVersionState::isEditable()`
gating all stage edits to `DRAFT` only:

- **Valid:** through the supported publish path, a `PUBLISHED` version's
  stages are always `status: ACTIVE` at the moment of publish (an
  unreachable stage is rejected outright by `validateReachability()`
  before `validateStageActivity()`'s reachable-only check would even
  apply) — and stay `ACTIVE`, since stages become immutable once
  published. An `ACTIVE`-runtime-status `EngineRequest` sitting on a
  stage of a `PUBLISHED` version that was published through this path
  will therefore find that stage `status: ACTIVE`.
- **Not possible through the supported publish path:** an unreachable
  `INACTIVE` stage surviving into a `PUBLISHED` version.
  `validateReachability()` rejects any unreachable stage outright, so
  this combination cannot arise from a version that went through
  `POST .../publish`.
- **Precise scope of what's actually guaranteed:** the current validated
  publish/edit path prevents an `ACTIVE`-runtime-status `EngineRequest`
  from occupying an `INACTIVE` stage **for any `WorkflowVersion` that was
  published through the supported `POST .../publish` flow and never
  modified outside it.** This is not the same claim as "the database
  guarantees this combination cannot exist." No database constraint,
  model-level cast, or trigger independently enforces stage/request
  status consistency — the guarantee comes entirely from application-layer
  validation at the moment of publish. A row modified directly (a manual
  DB update, a data migration, a legacy pre-validator-era version, or any
  write path that bypasses `WorkflowDesignerService`/`WorkflowPublishRulePack`)
  is not covered by this guarantee. Do not claim the combination is
  "universally impossible at the database level" — state precisely that
  the _validated publish/edit path_ prevents it for newly published
  versions, and that nothing independent of that path enforces it for
  externally modified or legacy data.
