# 12 — Gap Audit: Dynamic Engine Rules vs Yemen Flow Hub

**Date:** 2026-06-30. Audits the 11 reverse-engineered rule docs against the current
`backend/` (Laravel 11) and `frontend/` (Nuxt 4) codebase.

> **Headline.** The backend has **already ported the dynamic engine to near-full parity**
> with the `backend-handoff` spec — far beyond the prototype. Models, services,
> migrations, SLA runtime, two-table notifications, full audit fields, business-error
> codes, and concurrency locking are present. The remaining work is **frontend
> consumption depth + a handful of rule edge cases**, not a green-field port.

Legend: ✅ present & matches · 🟡 partial / verify · ❌ missing

---

## Backend coverage (verified by source)

| Rule area | Spec | Yemen Flow Hub | Evidence |
|---|---|---|---|
| Engine schema | definitions→versions→stages→actions→transitions→stage_permissions→fields→rules | ✅ all tables + models | `migrations/2026_06_23_*`, `app/Models/Workflow*`, `StagePermission`, `FieldDefinition` |
| Transition pipeline | lock→version→stage→EXECUTE→fields→comment→update→history→audit→notify | ✅ full | `EngineTransitionService.php:39-130` |
| Concurrency | `version` lock, `409 STALE` | ✅ | `EngineTransitionService.php:46,152` |
| `requires_comment` | `COMMENT_REQUIRED` | ✅ | `EngineTransitionService.php:60` |
| Stage field validation | on draft + transition | ✅ | `StageFieldRuleValidator`, called in transition |
| Stage permission AND/OR + EXECUTE⊇VIEW | matching algorithm | ✅ | `StagePermissionResolver.php` |
| my-queue derived | ACTIVE + EXECUTE + scope | ✅ | `EngineRequestController::myQueue`, `routes/api.php:169` |
| SLA columns | `sla_duration_minutes` | ✅ | `migrations/...stages:20`, `WorkflowStage` |
| SLA status + queue order | breached→nearing→oldest | ✅ | `EngineRequest.php:125-180` (`nearing` at ≤20% remaining) |
| SLA scheduled alerts | near/breach notifications | ✅ | `NotifySlaSignalsCommand.php` (with dedup) |
| Audit fields | actor_role_id, old/new_values, correlation_id, user_agent | ✅ | `migrations/2026_06_24_200001_add_engine_columns_to_audit_logs.php` |
| Audit append-only + events | | ✅ | `AuditService`, written inside transition txn |
| Notifications 2-table | notifications + recipients (read_at/archived_at, unique) | ✅ | `engine_notifications` + recipients `:27-33` |
| Notifications post-commit + audience resolve | | ✅ | `EngineNotificationDispatcher::afterTransition` |
| Version validate/publish | reject invalid config | ✅ | `WorkflowVersionValidator`, `WorkflowDesignerService` |
| Duplicate invoice = warning | | ✅ | `DuplicateInvoiceChecker`, `DuplicateDetectionService` |
| Merchant rules | bank immutable, active-requests, unique tax/CR | ✅ codes | `MerchantController.php:146-150` |
| Reference data | tables/values, protected | ✅ | `ReferenceTable/Value`, `ReferenceDataService` |
| Screen permissions + last-admin guard | | ✅ | `RoleScreenPermissionController.php:325` |
| Report exports | queued, scoped | ✅ | `ReportExport`, `ReportController` |
| Files private + authorized download | | ✅ | `DocumentService`, PDF-only (existing rule) |
| Dynamic field options | merchants / companies / reference | ✅ | `DynamicFieldOptionsResolver` |

**Backend verdict:** parity is effectively complete for phase-1 dynamic-engine scope.

---

## Frontend coverage

| Area | Yemen Flow Hub | Status |
|---|---|---|
| Engine request consumer | `useEngineRequests`, `useEngineRequestActions`, `useEngineRequestDocuments`, `useEngineRequestHistory` | ✅ exists |
| Dynamic form renderer | `DynamicForm.vue`, `DynamicFormField.vue`, `useDynamicFormSchema`, `useEngineFormSchema` | ✅ exists |
| Instance pages | `/workflows/instances/[id]` (per recent commits) | ✅ exists |

> The memory note "frontend has ZERO engine_requests consumer / NO dynamic form
> renderer" is **stale** — both now exist. The frontend gap is now about *depth and
> rule fidelity*, not absence.

---

## Candidate gaps to confirm with the user (the ask-answer list)

These are the items where the spec carries a rule the codebase may not yet fully honour,
or where a deliberate product decision is needed. **Phase 3 resolves each via
ask-answer.**

### G1 — Reference values: store id/key, not label
Spec: request stores reference **value id/key, not the label**
(`06-...:32`, `07-data-model.md:120`). Prototype stored labels. **Verify** the
production `engine_requests.data` persists reference ids/keys; if it stores labels,
reporting/relabeling breaks.

### G2 — Stage permission `display_label` (contextual per-audience label)
Spec replaces `StageRoutingRule` with `stage_permissions.display_label`
(`03-workflow-designer.md:97,153`). **Verify** the UI shows the audience-specific stage
label (e.g. "المراجعة الداخلية بالبنك" vs the raw stage name) from `display_label`.

### G3 — Field designer richness
Spec field config includes `regex_pattern`, `min/max_value`, `min/max_length`,
`default_value`, `allowed_file_types`, `max_file_size`, `multiple`
(`03-workflow-designer.md:115-125`). **Verify** the field designer + validator support
the full set, not just type/options.

### G4 — "Save draft does not require required fields" semantics
Spec: required only enforced on the **leaving** action, not on draft
(`04-requests-and-queue.md:90`). **Verify** the draft path skips required-field
enforcement.

### G5 — Workflow graph endpoint (executed/current/possible paths)
Spec: `GET /requests/{id}/graph` returns nodes+edges with executed + current + possible
path highlighting (`04-requests-and-queue.md:106`). `WorkflowGraphService` exists —
**verify** it marks the three path states for the request detail view.

### G6 — `actor_role_id` is a snapshot of role-at-action-time
Audit must record the role **at the time of action**, not current
(`05-audit-and-reports.md:11`). Column exists — **verify** it is written from the
acting role, never back-filled from the user's current role.

### G7 — Notification `type` enum + `severity` are explicit & complete
Spec lists trigger events (`07 §2`). **Verify** the production notification `type`
catalog covers all of: stage-reachable, approve/reject/return, SLA near/breach,
duplicate/compliance, version-published, sensitive-permission-change — and that
`severity` is set per type.

### G8 — Compliance endpoints
Spec: `GET /compliance/duplicate-invoices` and expired-document detection
(`05-audit-and-reports.md:41,52`). **Verify** both exist (duplicate checker is present;
**expired-document detection from recorded data** may be missing).

### G9 — "No automatic retry on 401/403/409/422" in the FE API client
Spec FE rule (`09-frontend-integration.md:13`). **Verify** the Nuxt API layer disables
auto-retry for these and maps `422.fields` → form, shows `409` business message,
reloads on `STALE_RESOURCE`.

### G10 — Designer publish immutability guard at deploy/CI
Spec + deploy rule: a published version is never mutated; new behaviour = new version
(`README.md:14`, [11 §5](11-deployment-rules.md)). **Verify** there is a hard guard
(policy/test) preventing edits to a `PUBLISHED` version, plus `clone → DRAFT`.

### G11 — Legacy vs dynamic coexistence / cutover
The repo still has the **legacy** fixed workflow (`ImportRequest`, `CustomsDeclaration`,
`RequestVote`, `WorkflowService`) alongside the **dynamic** engine (`EngineRequest`,
`EngineTransitionService`). The spec assumes the dynamic engine **replaces** the legacy
path. **Decision needed:** cutover strategy, feature flag, and whether new requests
route to the engine. (This is the largest open item and matches the memory's
"Workstream C = legacy cutover".)

---

## Suggested Phase-3 ordering

1. **G11** (cutover decision) — gates everything else.
2. **G1, G6** (data-integrity: reference ids, role snapshot) — cheap, high-risk if
   wrong.
3. **G2, G3, G4, G5** (designer + request fidelity).
4. **G7, G8** (notifications + compliance completeness).
5. **G9, G10** (FE client discipline + publish immutability guard).

Each becomes one ask-answer round: I confirm current behaviour in code, propose the
change, you approve, I implement into the production codebase.
