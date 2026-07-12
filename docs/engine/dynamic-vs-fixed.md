# Dynamic vs. Fixed

"Dynamic workflow engine" does not mean "everything is configurable." It
means the **shape of a workflow** — its stages, transitions, fields, and
permission grants — is data, editable through the Workflow Designer
without a deploy. The **vocabulary** those shapes are built from — the
semantic roles, access levels, field types, effect codes, and runtime
statuses — is fixed PHP code. Confusing the two leads to either looking
for a UI that doesn't exist, or assuming a fixed enum can be extended by
editing a workflow version.

This doc draws that line explicitly. For how to actually perform each
kind of change, see [`extension-guide.md`](extension-guide.md).

---

## Dynamic — designer-configurable, no deploy

These live in the `WorkflowDefinition → WorkflowVersion → …` tables and
can be created, edited, validated, and published through the Workflow
Designer API, gated to `DRAFT`-state versions
(`App\Enums\WorkflowVersionState::isEditable()`):

- **Workflows and versions** — `WorkflowDefinition`, `WorkflowVersion`
  (with a `DRAFT → PUBLISHED → ARCHIVED` lifecycle).
- **Stages** — `WorkflowStage` rows: code, name, ordering, initial/final
  flags, SLA duration, claim requirement, which fixed `semantic_role` and
  which fixed effect codes it carries.
- **Transitions** — `WorkflowTransition` rows: which action moves a
  request from which stage to which stage, comment requirements,
  destructive/self-loop flags.
- **Stage permissions** — `StagePermission` rows: which
  org/team/role/user gets VIEW or EXECUTE on a given stage.
- **Fields and field rules** — `FieldDefinition` rows (as long as their
  `type` is one of the existing fixed `FieldType` cases) and
  `StageFieldRule` rows controlling per-stage visibility, editability,
  and requiredness.
- **Effect attachment** — which existing effect code a stage triggers on
  entry (`WorkflowStage.attached_effects`), not the effect's
  implementation.
- **Screen-permission grants** — reassigning `ScreenCapability` values to
  roles for **existing** screens.

All of this is validated before it can go live —
`WorkflowVersionValidator` and `WorkflowPublishRulePack` enforce DAG-ness,
stage reachability (BFS from the initial stage), that every non-final
stage has at least one active EXECUTE holder, and final-outcome
consistency. A workflow that would leave a stage unreachable or
unassignable fails publish rather than failing silently at runtime.

---

## Fixed — PHP code, requires a deploy

These are enums or hardcoded catalogs. There is no admin UI or API
endpoint to add a case to any of them; the Workflow Designer cannot touch
them:

| Concept            | Where                             | Notes                                                                                                                                                                                                                                             |
| ------------------ | --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Runtime status     | `App\Support\EngineRequestStatus` | `ACTIVE`, `CLOSED`, `REJECTED`, `CANCELLED`, `ABANDONED`. A plain class with string constants, not a native PHP `enum` — same fixed-vocabulary role either way.                                                                                   |
| Semantic role      | `App\Enums\StageSemanticRole`     | 8 cases: `INITIAL_ENTRY`, `BANK_REVIEW`, `SUPPORT_REVIEW`, `SWIFT`, `EXECUTIVE_REVIEW`, `FINANCE_RESERVE`, `FX_CONFIRMATION`, `FINAL`.                                                                                                            |
| Stage access level | `App\Enums\StageAccessLevel`      | `VIEW`, `EXECUTE` only — no separate `CLAIM` level (claim is a distinct mechanism, see the permission model doc).                                                                                                                                 |
| Screen capability  | `App\Enums\ScreenCapability`      | `VIEW`, `MANAGE`, `EXPORT`.                                                                                                                                                                                                                       |
| Field type         | `App\Enums\FieldType`             | 9 cases (`TEXT`, `NUMBER`, `DATE`, `SELECT`, `DYNAMIC_SELECT`, `TEXTAREA`, `FILE`, `CURRENCY`, `CHECKBOX`) — each needs a matching branch in the frontend's `DynamicFormField.vue`, so the renderer isn't purely data-driven off the enum either. |
| Effect code        | `App\Enums\WorkflowEffectCode`    | Currently `financing.reserve`, `fx.confirmation_pdf`. A new code needs an enum case, an effect class, and a `StageHookRegistry` registration.                                                                                                     |
| Screens            | `App\Models\Screen`               | `ScreenController` exposes only `index()` — no create/update/delete. New screens are migration + seeder + deploy.                                                                                                                                 |
| Role list          | `App\Enums\UserRole`              | The 8-role API-serialization enum (see the permission model doc) — not itself extended by workflow config.                                                                                                                                        |

Why fixed, not dynamic: these are the vocabulary the rest of the system's
_code_ branches on — dashboards, permission checks, effect dispatch, and
frontend renderers all pattern-match against these exact case lists.
Making them data-driven would mean every consumer needs a fallback for
"unknown case," which the codebase deliberately does not do (the one
partial exception, `semantic_role`'s null-fallback to a stage-code alias
table, is a scoped compatibility shim with its own removal criteria — see
AGENTS.md — not a general pattern to imitate).

---

## The one deliberate hybrid: `semantic_role` fallback

`App\Services\Workflow\SemanticResolver` resolves a stage by
`semantic_role` first. If a stage predates the semantic-role rollout (or
is a hand-built `DRAFT` version that hasn't set it), the resolver falls
back to `App\Services\Workflow\SemanticRegistry::stageCodeAliases()` — a
hardcoded map of legacy stage `code` values (e.g. `CREATE` →
`INITIAL_ENTRY`, `FX` → `SWIFT`) to semantic roles. This is why
`semantic_role` is nullable on `WorkflowStage` today, even though every
occupiable stage _should_ set it going forward (see the extension guide).
`EngineRequestReadModel::bucket()` implements the same idea slightly
differently: its `STAGE_BUCKETS` constant stores both `roles` and `codes`
per bucket and queries `whereIn('semantic_role', $roles) OR
whereIn('code', $codes)`.

This fallback exists for exactly one reason — old data — and is explicitly
temporary. AGENTS.md defines its removal criteria (every occupiable stage
on every ACTIVE-request-reachable workflow version has `semantic_role`
set; no consumer depends on the `codes` half; a regression test proves the
code-only path is dead). Do not extend this pattern to new fixed enums —
it is a migration aid, not a design template.

---

## Quick test: is this thing dynamic or fixed?

Ask: **"Can a Workflow Designer user create this by filling out a form,
without anyone touching PHP or Vue source?"**

- Yes → it's dynamic (a stage, a transition, a permission grant, a field,
  an effect _attachment_).
- No, because it's a closed list something else in the codebase branches
  on → it's fixed (a semantic role, an access level, a field _type_, an
  effect _code_, a screen).
