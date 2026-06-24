# Dynamic Workflow Engine — Runtime UI Design (Workstream A + B)

Date: 2026-06-25
Status: approved by user, pending implementation plan
Builds on: `docs/DYNAMIC-ENGINE-AUDIT.md`, `docs/LOVABLE-PARITY-INVENTORY.md` (no re-audit performed — those findings are taken as ground truth)

## Problem

The dynamic workflow engine backend (`engine_requests`, `EngineTransitionService`, workflow designer, governance, screen permissions) is fully built and correct, per the 2026-06-25 code audit. But:

1. No frontend page or composable calls any `engine_requests` API — Workstream A gap.
2. No component renders a fillable, validated form from `field_definitions`/`field_groups`/`stage_field_rules` at runtime — Workstream B gap, the single largest missing piece.

Without both, `engine_requests` is unusable end-to-end even though the backend is ready. This spec designs both together since B is consumed by A's instance detail page.

## Scope

**In scope:**
- New `/workflows` route tree (queue, create, detail) — additive, does not touch `/requests` (legacy).
- Creating new instances against **any PUBLISHED workflow definition** (not hardcoded to one seed).
- Dynamic form rendering for all 9 `FieldType` values, including FILE fields with full upload/list/delete wiring.
- Screen-permission gating (new screen keys), consistent with `admin/orgs.vue` etc.
- Frontend trusts backend permission/validation entirely — no duplicated client-side authorization logic.

**Out of scope (explicitly deferred):**
- Legacy cutover / retiring `/requests`, `import_requests`, `users.role`-as-authority, voting (Workstream C/D — separate future specs).
- Merchant vs Trader reconciliation.
- Playwright/visual parity-evidence pass (defer to a follow-up once routes exist, matching existing project convention).
- Any backend changes — the backend is already correct and complete for this scope; this spec is frontend-only.

## Design

### 1. Instance queue + lifecycle pages (Workstream A)

| Page | Route | Purpose |
|---|---|---|
| Queue/list | `frontend/app/pages/workflows/index.vue` | Consumes `EngineRequestController::index` (all visible) and `myQueue` (دوري SLA-priority queue). Screen-gated: `screen: 'workflows'`. |
| Create | `frontend/app/pages/workflows/new.vue` | Lists PUBLISHED `workflow_definitions`, lets user pick one, `POST /engine-requests` creates an instance at the definition's first stage, redirects to detail. |
| Detail | `frontend/app/pages/workflows/instances/[id]/index.vue` | Stage stepper (dynamic, derived from the `graph` endpoint — NOT the fixed-enum `WorkflowProgress.vue`), embeds `DynamicForm` for the current stage (see §2), renders available actions exactly as returned by the API (no client-side permission re-derivation), history tab via `history` endpoint, autosave via `draft` PATCH. |

New composables (mirroring existing `useRequests.ts`/`useWorkflows.ts` conventions):
- `useEngineRequests.ts` — list, queue, create, show, draft.
- `useEngineRequestActions.ts` — `executeAction`, with 409 (stale `version`) handling: toast + refetch, no blind retry.
- `useEngineRequestHistory.ts` — `history` endpoint.
- `useEngineRequestDocuments.ts` — upload/list/download/delete against `engine_request_documents`.

New Pinia store `engineRequests.store.ts`, same shape/conventions as `requests.store.ts`.

**Permission model:** the frontend renders only what the API returns and never re-implements `StagePermissionResolver` logic. `myQueue` already filters to what's assigned to the user; `show` 403s if unauthorized; the action list on the detail response is the literal set of executable actions — the UI does not grey out or compute additional restrictions client-side.

### 2. Dynamic form renderer (Workstream B)

**Component:** `frontend/app/components/workflow/DynamicForm.vue`
Props: `stageId`, `fieldGroups` (resolved `field_groups` + `field_definitions` + `stage_field_rules` for the current stage), `modelValue` (the instance's `data` JSON), `mode: 'edit' | 'readonly'`.

**Runtime Zod schema builder** — a new function `buildDynamicSchema(fieldDefinitions, stageFieldRules)`, living in `frontend/app/composables/useDynamicFormSchema.ts` (or `lib/dynamicForm/` if it grows beyond one function):
- Maps each `FieldType` (`TEXT`/`NUMBER`/`DATE`/`SELECT`/`DYNAMIC_SELECT`/`TEXTAREA`/`FILE`/`CURRENCY`/`CHECKBOX`) to the matching Zod primitive.
- Applies constraints: `min_value`/`max_value` (NUMBER/CURRENCY), `min_length`/`max_length`/`regex_pattern` (TEXT/TEXTAREA), `is_required`.
- `stage_field_rules.is_required` for the current stage overrides the field-level `is_required` default.
- Built once per stage load, fed into VeeValidate's `toTypedSchema` — same integration pattern `RequestFormTabs.vue` already uses, the only difference is the schema is generated instead of hand-written.

**Field renderer** — `frontend/app/components/workflow/DynamicFormField.vue`, a `<component :is>` dispatch per `FieldType`, wrapping existing shadcn-vue primitives (mandatory per AGENTS.md — no raw HTML):
- TEXT/NUMBER/CURRENCY → `Input` (with appropriate `type`/formatting for CURRENCY).
- TEXTAREA → `Textarea`.
- DATE → existing DatePicker/Calendar component.
- SELECT → `Select`, options from `field_definitions.options` JSON.
- CHECKBOX → `Checkbox`.
- All wrapped in `Field`/`FieldGroup`/`Label` per the project's mandatory form-field convention.

**DYNAMIC_SELECT** — `dynamic_source` (`MERCHANTS` / `MERCHANT_COMPANIES` / `REFERENCE_DATA`) selects which existing composable supplies `Select` options: `useMerchants` for the first two, the reference-table endpoint (via `reference_table_id`) for the third. Dependent-option lookups (e.g. merchant company list filtered by selected merchant) reuse the existing lookup pattern already used elsewhere in the app, just pointed at the new data source.

**FILE fields** — full upload support, not deferred:
- Render an upload widget respecting `allowed_file_types`, `max_file_size`, `multiple`.
- POST to the existing `engine_request_documents` upload endpoint, scoped to `field_id` + `stage_id`.
- List/download/delete via the same endpoint family.
- Reuse the visual pattern from `DocumentChecklist.vue` where it fits without forcing it.

**Visibility/editability semantics:**
- `stage_field_rules.is_visible = false` → field is omitted entirely from the rendered form (not hidden-but-present).
- `stage_field_rules.is_editable = false` → field renders read-only (disabled input), still visible.
- This mirrors backend enforcement so the UI can't visually suggest an action the server will reject — but the server (`StageFieldRuleValidator`) remains the actual authority; this is UX correctness, not a security boundary.

### 3. Error handling

| Condition | Handling |
|---|---|
| 409 on `executeAction`/`draft` (stale `version`) | Toast: "تم تحديث الطلب من مستخدم آخر" + refetch instance. No automatic retry. |
| 403 from `StagePermissionResolver` | Standard `forbidden.vue` / inline banner, consistent with existing `LockedBanner` pattern. |
| 422 from `StageFieldRuleValidator` | Map field-level errors onto the dynamic form via VeeValidate `setErrors`, same mechanism static forms already use. |

### 4. Testing

- Vitest unit tests for `buildDynamicSchema` — one case per `FieldType`, valid and invalid per constraint (min/max, length, regex, required).
- Vitest mount tests for `DynamicForm.vue` / `DynamicFormField.vue` per field type, including DYNAMIC_SELECT option loading and FILE upload interaction.
- Vitest tests for the new composables (`useEngineRequests`, `useEngineRequestActions`, etc.) with mocked `$fetch`, including the 409 conflict path.
- No Playwright/visual-parity pass in this spec — deferred to a follow-up once the routes exist and have real content to screenshot, per existing project convention (see Story 9.1's parity-evidence gate).

## Open risks / notes for the implementation plan

- `useWorkflowFields`/stage-field-rule read composables may need a new "resolved fields for stage X" endpoint or client-side join if the backend only exposes raw `field_definitions`/`stage_field_rules` separately — verify exact API shape during planning, not assumed here.
- The stage stepper for dynamic instances is new UI, not a reuse of `WorkflowProgress.vue` (which is fixed-enum-only) — budget real design time for it, not just wiring.
- DYNAMIC_SELECT dependent-option behavior (Lovable's tax/name merchant lookup) should be confirmed against the current `useMerchants` composable's actual filtering capability before assuming a 1:1 swap.
