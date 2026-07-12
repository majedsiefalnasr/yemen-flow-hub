# Workflow Engine

**Verified:** 2026-07-13, against `backend/app/Services/Workflow/`,
`backend/app/Providers/AppServiceProvider.php`, `backend/routes/api.php`,
and related enums/migrations directly — not carried over from the legacy
`docs/01-workflow-and-business-rules.md`, which predates this engine and
describes a fixed 18-value status vocabulary and an active Executive
Voting feature, both removed.

This is the single source of truth for Designer lifecycle, workflow
topology, publishing, and runtime transitions. For full authorization
mechanics, see [`03-permission-model.md`](03-permission-model.md). For
the 4-concept request-state model, see
[`05-request-state-model.md`](05-request-state-model.md). For schemas,
see [`06-database-and-models.md`](06-database-and-models.md). For
routes, see [`api-reference.md`](../api-reference.md). For extension
procedures, see [`engine/extension-guide.md`](../engine/extension-guide.md).
For the semantic-mapping ADR's dated rationale, see
[`decisions/semantic-mapping.md`](../decisions/semantic-mapping.md) —
this document does not duplicate its content.

---

## There is no monolithic Workflow Service

The engine is a set of focused services in `app/Services/Workflow/`, not
one centralized class:

| Service | Responsibility |
| --- | --- |
| `WorkflowDesignerService` | Author, validate, clone, publish, archive `WorkflowVersion`s and their stages/transitions/permissions |
| `WorkflowVersionValidator` | Orchestrates validate-before-publish (delegates to the rule pack below) |
| `WorkflowPublishRulePack` | The specific publish rules (reachability, executors, self-loops, field constraints, …) |
| `EngineTransitionService` | Executes transitions, draft saves, and draft abandonment on `EngineRequest`s |
| `StagePermissionResolver` | Resolves VIEW/EXECUTE access to a stage from `stage_permissions` |
| `EngineClaimService` | Claim/heartbeat/release lifecycle |
| `StageFieldRuleValidator` | Per-stage field enforcement (visible/editable/required) |
| `SemanticRegistry` / `SemanticResolver` | Semantic-role/tag resolution, semantic-first with code-alias fallback |
| `StageHookRegistry` | Effect/hook dispatch on stage entry/exit |
| `WorkflowActionService` | The global, version-independent action catalog `WorkflowTransition.action_id` references |
| `WorkflowGraphService` | Builds the `{nodes, edges}` stage graph for both the designer and per-request views |
| `RequestProjectionSync` | Projects selected `data` JSON fields onto indexed `engine_requests` columns |

There is no fixed, hardcoded state machine. Request lifecycles are
defined entirely by published `WorkflowVersion` data and interpreted by
these services at runtime.

---

## Workflow definitions and the version lifecycle

Entity chain: `WorkflowDefinition → WorkflowVersion → {WorkflowStage,
WorkflowTransition, FieldGroup→FieldDefinition}`, and `WorkflowStage →
{StagePermission, StageFieldRule→FieldDefinition}`.

`WorkflowVersion.state` is `App\Enums\WorkflowVersionState`:
`DRAFT`|`PUBLISHED`|`ARCHIVED`. State transitions are strictly one-way,
enforced by `WorkflowDesignerService::ensureValidStateTransition()`:

```php
$allowed = match ($to) {
    WorkflowVersionState::PUBLISHED => $from === WorkflowVersionState::DRAFT,
    WorkflowVersionState::ARCHIVED => $from === WorkflowVersionState::PUBLISHED,
    WorkflowVersionState::DRAFT => false,
};
```

`DRAFT → PUBLISHED` and `PUBLISHED → ARCHIVED` are the only allowed
transitions. Nothing can move back to `DRAFT` — a published version can
never be re-edited; it must be cloned into a new `DRAFT` instead.

### DRAFT editability, PUBLISHED/ARCHIVED immutability

`WorkflowVersionState::isEditable()` is true only for `DRAFT`, enforced
before every stage/transition/permission/field mutation
(`WorkflowDesignerService`'s `createStage`/`updateStage`/`createTransition`/
etc.). A `PUBLISHED` or `ARCHIVED` version's topology cannot be edited —
attempting to do so throws `WorkflowVersionImmutableException`.

### Cloning

Only `PUBLISHED` or `ARCHIVED` versions can be cloned, always into a
fresh `DRAFT`:

```php
if (! in_array($source->state, [WorkflowVersionState::PUBLISHED, WorkflowVersionState::ARCHIVED], true)) {
    throw new WorkflowVersionImmutableException('Only PUBLISHED or ARCHIVED versions can be cloned to a new DRAFT.');
}
```

`deepCopyVersionConfig()` deep-copies stages, field groups, field
definitions, transitions, stage permissions, and stage field rules from
the source into the new `DRAFT`, remapping foreign keys since every
child row gets a fresh ID.

### Publishing supersedes the prior published version

`WorkflowDesignerService::publishVersion()`, inside one transaction:
locks the target version, validates it (see below), and on success
**archives the definition's current `PUBLISHED` version** (bumping its
`version` and setting `state: ARCHIVED`) before publishing the new one
(`state: PUBLISHED`, `published_at: now()`, `version` incremented). Both
mutations are audited (`AuditAction::GOVERNANCE_UPDATED`, `reason:
'superseded_by_publish'` and `'published'` respectively). On validation
failure, `state` is untouched — the exception is thrown before the
`update()` call.

---

## Designer-managed entities

All Designer-configurable via the Workflow Designer API, gated to
`DRAFT`-state versions:

- **Stages** — code, name, description, sort_order, is_initial, is_final,
  semantic_role, attached_effects, final_outcome, sla_duration_minutes,
  requires_claim, status.
- **Actions** — a global, version-independent catalog
  (`WorkflowActionService`), doc-commented "code is immutable; name and
  is_active are editable. System defaults and in-use actions are
  protected." `WorkflowTransition.action_id` references this catalog;
  `WorkflowActionService` is design-time only, never called from
  `EngineTransitionService`. `setActive()`/`delete()` block on protected
  or in-use actions (`isInUse()` checks `workflow_transitions.action_id`).
- **Transitions** — from-stage, action, to-stage, requires_comment,
  confirmation_message, is_default_submit, is_self_loop, transition_type,
  is_destructive.
- **Field groups and field definitions** — `FieldGroupController`/
  `FieldDefinitionController`/`StageFieldRuleController` (all inject
  `FieldDesignerService`).
- **Stage field rules** — per-stage visible/editable/required override
  on a field.
- **Stage permissions** — who can VIEW/EXECUTE a stage; see
  [`03-permission-model.md`](03-permission-model.md).
- **Semantic metadata** — `semantic_role` on stages, `semantic_tag` on
  fields, `attached_effects` on stages — see below.
- **Hooks/effects** — attachment only (which effect code a stage fires);
  the effect catalog itself is fixed, see below.

For the exact schema of every table above, see
[`06-database-and-models.md`](06-database-and-models.md). For which of
these are Designer-configurable versus fixed PHP vocabulary, see
[`engine/dynamic-vs-fixed.md`](../engine/dynamic-vs-fixed.md).

---

## Publish validation

`publishVersion()` calls **one** entry point,
`WorkflowVersionValidator::validate()`, which is a nested chain, not two
sequential top-level calls:

```php
$errors = $this->validator->validate($locked);
if ($errors !== []) {
    throw new WorkflowVersionValidationException($errors);
}
```

`WorkflowVersionValidator::validate()` runs its own inline checks
(exactly one initial stage, at least one final stage, final-outcome
consistency per stage, no duplicate stage codes/keys, transition
integrity, every non-final stage has an outgoing transition,
`DYNAMIC_SELECT` field source validity), then merges in
`WorkflowPublishRulePack::validate()`'s results as its final step, which
itself calls `SemanticResolver::publishErrors()`.

### `WorkflowPublishRulePack` rules (V-1..V-9)

- `validateReachability()` — BFS from the initial stage; flags **any**
  unreachable stage (`STAGE_UNREACHABLE`), regardless of its `status` —
  there is no exemption for unreachable `INACTIVE` stages specifically.
- `validateEffectiveExecutors()` — every non-final stage must have at
  least one active EXECUTE holder (`StagePermissionAudience::executeHolderIds()`)
  — a stage nobody can act on will not publish.
- `validateFinalStageOutgoing()`, `validateActionOutcomeConsistency()`,
  `validateSelfLoops()` (a transition with `from_stage_id === to_stage_id`
  must be explicitly flagged `is_self_loop`; cycles elsewhere in the
  graph are not restricted — see
  [`engine/dynamic-vs-fixed.md`](../engine/dynamic-vs-fixed.md)),
  `validateFieldRules()`, `validateFieldConstraints()`.
- `validateStageActivity()` — blocks publish if: the initial stage is
  `INACTIVE`; any final stage is `INACTIVE`; any transition references an
  inactive stage; or any *reachable* non-final stage is `INACTIVE`.

### Semantic-mapping publish gate: `SEMANTIC_MAPPING_MISSING` blocks, `SEMANTIC_DASHBOARD_ROLE_GAP` does not

`SemanticResolver::publishErrors()` (merged into the rule pack, above) is
**publish-blocking**: it fires `SEMANTIC_MAPPING_MISSING` when a stage's
`attached_effects` requires a semantic tag that no field on the version
declares. `SemanticResolver::publishWarnings()` is a **separate,
non-throwing entry point**, reachable only via
`WorkflowVersionValidator::warnings()` → `WorkflowDesignerService::validationWarnings()`
— never called from `publishVersion()`. It fires
`SEMANTIC_DASHBOARD_ROLE_GAP` when no stage declares a semantic role a
dashboard bucket expects; this is a warning surfaced to the designer, not
a publish blocker.

---

## Runtime execution: `EngineTransitionService::execute()`

Called on every workflow action (`POST /api/v1/engine-requests/{id}/actions`
— there is no per-action fixed route family; grep for `submit` in
`routes/api.php` returns nothing). Inside one `DB::transaction()`, in
this exact order:

1. **Pessimistic lock** — `EngineRequest::lockForUpdate()->findOrFail()`.
2. **Runtime status check** — `isActive()` → `REQUEST_CLOSED` (403) if
   not.
3. **Optimistic version check** — `$request->version !== $version` →
   `REQUEST_STALE` (409).
4. **Transition lookup** — the transition must exist and originate from
   the request's current stage → `TRANSITION_NOT_AVAILABLE` (422).
5. **Permission** — `StagePermissionResolver::userCanAccessStage(..., EXECUTE)`
   on the from-stage → `STAGE_EXECUTION_FORBIDDEN` (403).
6. **Claim** — only if `fromStage->requires_claim`: the caller must
   currently hold the claim → `CLAIM_NOT_HELD` (403).
7. **Comment requirement** — if `transition->requires_comment` and the
   comment is blank → `COMMENT_REQUIRED` (422).
8. **Field validation** — `StageFieldRuleValidator::validateStage(...,
   enforceRequired: true, ...)` — required-field enforcement is **on**
   for transitions → `STAGE_FIELDS_INVALID` (422) with a per-field error
   array on failure. (Contrast with `saveDraft()` below, which passes
   `enforceRequired: false`.)
9. **Status resolution** — `resolveStatusAfterTransition($transition->toStage)`
   (see below).
10. **Mutation** — `data`, `current_stage_id`, `stage_entered_at`,
    `sla_deadline_epoch`, `status`, `version` (+1) are all written in one
    `forceFill()->save()`.
11. **Projection sync** — `RequestProjectionSync::sync()`.
12. **Claim release on exit** — if the from-stage required a claim and
    one was held, it's released for the stage change.
13. **`workflow_history` write** — `from_stage_id`, `to_stage_id`,
    `action_code`, `performed_by`, `comments`, a fresh `correlation_id`
    UUID, `created_at` (same timestamp as the stage-entry projection).
14. **Audit write** — `AuditAction::STATUS_TRANSITION`, sharing the same
    `correlation_id`, with before/after field diffs.
15. **Hooks/effects** — `StageHookRegistry::fireExit()` then
    `fireEntry()`, still inside the transaction. Domain exceptions
    (`EngineException`, `FinancingLimitExceededException`,
    `FinancingLockTimeoutException`, `CustomsException`) propagate as-is
    with their own error envelope; any other `\Throwable` is wrapped as
    `EngineException('...', 'STAGE_HOOK_FAILED', 422)` and logged via
    `OperationalAlertLogger::failure()`.
16. **Notification dispatch** — `NotificationDispatcher::afterTransition()`,
    still inside the transaction.

A failure at any step rolls back the entire transition atomically —
nothing above step 10 is partially applied.

### `saveDraft()` — not gated by a fixed "editable states" list

`saveDraft()` (`PATCH /api/v1/engine-requests/{id}/draft`) uses the
**same** `isActive()` + EXECUTE-permission + claim-held gate as
`execute()` — **not** a hardcoded whitelist of stage names. Any stage the
caller holds EXECUTE and claim on is "editable" via `saveDraft()`; there
is no separate `editable_states` concept in code. It calls
`StageFieldRuleValidator::validateStage(..., enforceRequired: false,
...)` — deliberately lenient on required fields, since a draft save is
not a transition.

### `abandonDraft()` — gated specifically by `is_initial`, not "any non-final stage"

`abandonDraft()` (`POST /api/v1/engine-requests/{id}/abandon`) only
succeeds when the request's current stage has `is_initial: true`:

```php
if ($stage === null || ! $stage->is_initial) {
    throw EngineException::abandonNotAvailable();
}
```

It sets `status: EngineRequestStatus::ABANDONED` directly — it does
**not** call `resolveStatusAfterTransition()` or reference `FinalOutcome`
at all — and clears every claim field (`claimed_by`, `claimed_at`,
`claim_expires_at`, `claim_stage_id`). It writes a `workflow_history` row
with `action_code: 'ABANDON'` and `to_stage_id: null`, and logs
`AuditAction::REQUEST_ABANDONED`.

---

## Terminal-stage handling: `FinalOutcome` → runtime-status mapping

`resolveStatusAfterTransition()`, the exact mechanism:

```php
private function resolveStatusAfterTransition(WorkflowStage $toStage): string
{
    if (! $toStage->is_final) {
        return EngineRequestStatus::ACTIVE;
    }
    if ($toStage->final_outcome === null) {
        Log::warning('Final stage reached with null final_outcome; defaulting to CLOSED.', [...]);
        return EngineRequestStatus::CLOSED;
    }
    return $toStage->final_outcome->toRequestStatus();
}
```

A final stage with a null `final_outcome` does **not** fail the
transition — it logs a warning and defaults `runtime_status` to `CLOSED`.
Treat this as an edge case worth avoiding at Designer time (always set
`final_outcome` on final stages, which `WorkflowVersionValidator` already
requires at publish via `FINAL_STAGE_NO_OUTCOME`), not as a runtime
error path to handle defensively.

For the full `FinalOutcome`→`runtime_status` mapping table (including why
`final_outcome: COMPLETED` maps to `runtime_status: CLOSED`, not a
nonexistent `runtime_status: COMPLETED`), see
[`05-request-state-model.md`](05-request-state-model.md) — not
duplicated here.

---

## Claim lifecycle

Claim/heartbeat/release: `EngineClaimService`. TTL: the **live** value is
the admin-configurable `support_claim_ttl` setting
(`AdminSettingsService`, default 15 minutes, 5–60 range), read via
`EngineClaimService::ttlMinutes()`.
`config('workflow.support_claim_ttl_minutes')` (`backend/config/workflow.php`)
exists but is **not** read by the claim service — both default to 15
minutes today, but they are two different settings; the config key is
unused. See [`03-permission-model.md`](03-permission-model.md) §4 for the
full claim-permission interplay (claim check runs only after the EXECUTE
permission check passes, never before).

---

## Semantic roles, field tags, and the compatibility fallback

`App\Enums\StageSemanticRole` (8 fixed cases: `INITIAL_ENTRY`,
`BANK_REVIEW`, `SUPPORT_REVIEW`, `SWIFT`, `EXECUTIVE_REVIEW`,
`FINANCE_RESERVE`, `FX_CONFIRMATION`, `FINAL`) and field-level
`semantic_tag` are the explicit, first-choice resolution path.
`SemanticRegistry::stageCodeAliases()` is a pure-code map (no DB table)
of legacy stage codes to semantic roles, used only as a fallback.
`SemanticResolver::stageForRole()`/`fieldForTag()` resolve by
semantic_role/semantic_tag first, falling back to the code-alias map when
null. `EngineRequestReadModel::bucket()` implements the same idea as an
OR condition (`whereIn('semantic_role', $roles) OR whereIn('code',
$codes)`).

`StageHookRegistry::fireEntry()` follows the same priority: if a stage's
`attached_effects` is non-empty, only effect-keyed hooks fire (the
current, explicit path); the code-keyed hooks bound to
`config('engine_hooks.*_stage')` are reached only when `attached_effects`
is empty — a legacy bootstrap fallback for workflows that predate
`attached_effects`.

**This fallback is temporary, with exit criteria not yet satisfied — see
[`05-request-state-model.md`](05-request-state-model.md) for the
complete criteria list and current status against each.** Not duplicated
here.

---

## Separation of duties: convention, not a code guard

Legacy documentation claimed a code-enforced rule preventing a request's
creator from reviewing/rejecting their own request (including a specific
`bank_reject_terminal` guard). **No such guard exists in current code.**
Exhaustive search across `backend/app/` for SOD/self-reject/same-user
logic found zero matches; `bank_reject_terminal` does not appear
anywhere. `StagePermissionResolver` grants EXECUTE purely from
`stage_permissions` rows matched against
`{organization_id, team_ids, role_ids, user_id}` — there is no comparison
against `EngineRequest.created_by` anywhere in
`EngineTransitionService::execute()` or any permission-resolution path.

Separation of duties today is enforced **only by convention** — how a
Designer scopes `stage_permissions` grants — not by any policy, guard, or
query filter. Do not assume this is code-enforced when designing new
workflows; if SOD matters for a given workflow, it must be achieved
through careful `stage_permissions` scoping, since nothing in the engine
itself prevents a self-approval otherwise.

---

## Designer-defined topology versus fixed semantic contracts

Stage names, transitions, field definitions, and permission grants are
entirely Designer-defined data — there is no fixed frontend vocabulary
for "what stages exist" or "what a stage is called." The semantic roles
(`StageSemanticRole`'s 8 cases), field types (`FieldType`'s 9 cases),
action kinds (`WorkflowActionKind`: `DRAFT`, `APPROVE`, `REJECT`,
`RETURN`, `CLOSE`, `INFO`, `CUSTOM`), transition types
(`WorkflowTransitionType`: `FORWARD`, `RETURN`, `REJECT`, `CLOSE`,
`CUSTOM`), and effect codes are fixed PHP enums a Designer selects from,
not vocabulary a Designer can extend. See
[`engine/dynamic-vs-fixed.md`](../engine/dynamic-vs-fixed.md) for the
full dynamic-vs-fixed line and [`engine/extension-guide.md`](../engine/extension-guide.md)
for how to safely extend either category.

---

## The per-request stage graph

`WorkflowGraphService::build()` produces a `{nodes, edges}` graph derived
purely from a version's stages and transitions — "no graph table," per
its own doc comment. Two callers:

- `GET /api/v1/workflow-versions/{v}/graph` — the raw designer graph.
- `GET /api/v1/engine-requests/{id}/graph` — the same graph, augmented
  per-request: each node gets `state: 'current'|'executed'|'possible'`
  (derived from `workflow_history`), each edge gets
  `state: 'executed'|'possible'`, plus `execute_stage_ids` from
  `StagePermissionResolver::accessibleStageIds()` for the caller.

---

## What this document removes from the legacy source

The following legacy content is **not** carried forward, and must not be
reintroduced:

- The fixed 18-value status diagram/vocabulary (`DRAFT` →
  `CUSTOMS_DECLARATION_ISSUED` → `COMPLETED`, etc.) — replaced entirely
  by the 4-concept model in
  [`05-request-state-model.md`](05-request-state-model.md).
- Executive Voting stage/session/tally behavior as active engine
  functionality — Executive Voting is **out of V1**. This does not mean
  zero related code exists; see [`api-reference.md`](../api-reference.md)'s
  "Executive Voting (out of V1 — no live routes)" section for the
  verified backend/frontend cleanup-debt inventory (dead enum cases,
  dead frontend types, unreachable notification templates). None of that
  is active engine behavior, and none of it is described here as such.
- A dedicated "Voting Service" or any centralized fixed-workflow
  language — the engine is the set of focused services listed at the top
  of this document, not one monolithic service.
- Fixed per-role workflow paths ("Owner: Bank SWIFT Officer," etc.) —
  stage ownership is expressed entirely through `stage_permissions`
  grants, Designer-configured, not a hardcoded per-stage role table.
- Stale route families or static transition endpoints (`/api/workflow/{id}/submit`,
  `.../bank-approve`, etc.) — every transition goes through the one
  generic `POST /api/v1/engine-requests/{id}/actions` endpoint.
- Any claim that `current_status` (a column that does not exist — see
  [`05-request-state-model.md`](05-request-state-model.md) for the real
  `status`/`current_stage_id` split) or a frontend `RequestStatus` enum
  (removed) drives the engine.
- Customs-declaration terminology for the current Director/FX-confirmation
  workflow — align to external FX confirmation
  (`تأكيد مصارفة خارجية`); `customs_declarations` remains only as a
  legacy-compatibility table name, documented in
  [`06-database-and-models.md`](06-database-and-models.md).
