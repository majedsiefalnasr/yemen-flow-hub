---
title: PRD — Dynamic Workflow Engine (Epic 18)
status: draft
created: 2026-06-22
owner: MAJED
absorbs:
  - dynamic-workflow-engine/docs/backend-handoff/* (engine functional + API contract)
  - LOVABLE-AUDIT.md (scope, build order)
  - ENGINE-RECONCILIATION.md (see companion architecture doc)
note: >
  Durable, self-contained. Absorbs the throwaway `dynamic-workflow-engine/` reference
  app, `LOVABLE-AUDIT.md`, and `ENGINE-RECONCILIATION.md` so those can be deleted
  without losing requirements. Companion: architecture-dynamic-workflow-engine.md.
---

# PRD — Dynamic Workflow Engine (Epic 18)

## 1. Summary & Intent

The PM has mandated replacing Yemen Flow Hub's **hardcoded** import-financing workflow (21-status enum, 8 fixed roles, fixed `WorkflowService` transitions) with a **metadata-driven Dynamic Workflow Engine**. Administrators design workflows at runtime — stages, actions, transitions, permissions, fields, field groups, and routing labels are configuration, not code. A "request" becomes a **workflow instance** of a published workflow version.

This is a **clean start**: existing requests are test data; no instance migration. The engine ships seeded with the National Committee import-financing workflow so day-one behavior is concrete, but everything is reconfigurable through the designer.

**Stack stays Nuxt 4 / Vue** (frontend) + **Laravel 11 / MySQL / Redis** (backend). The reference React app in `dynamic-workflow-engine/` is UX + contract guidance only; do not port its code.

## 2. Goals

- Replace the fixed workflow core with a configurable engine without losing shipped operational features (auth, MFA, audit, notifications, reporting, ledger, customs).
- Let admins model org→team→role identity, design/version/publish workflows, and define dynamic forms.
- Keep every view queue-scoped and role/permission-scoped; backend remains the only authority.
- Preserve Arabic-first RTL, audit-sensitivity, and least-privilege posture.

## 3. Non-Goals (Phase 1)

From `backend-handoff` "خارج المرحلة الأولى":
- Multiple teams/roles per user (schema supports M:N; UI enforces one-each).
- Migrating requests between workflow versions.
- Saved custom reports.
- SMS/email as live notification channels (in-platform only).
- Distributed file storage (single server, private local disk).
- Multi-server deployment.
- **Executive voting / quorum is removed entirely** (was Epic 3/17) — the executive decision is a single approval by the committee manager via a normal designer action.

## 4. Personas / Actors

National Committee admins, commercial-bank staff (intake, internal review, FX/SWIFT, bank admin), support-committee reviewers, the committee manager (executive decision), FX-confirmation staff, and system administrators. Identity is org→team→role; no fixed role codes gate screens (see FR-PERM).

## 5. Build Order (priority = dependency order)

0. Engine foundation · 1. Governance · 2. Merchants · 2.5 Reference-data foundation · 3. Workflow designer · 4. Requests + queue · 5. Audit + reports · 6. Reference-data UI / screen-permissions / notifications.

> Adjustment vs raw priority list: reference-data **foundation** is pulled before the designer because `DYNAMIC_SELECT`/reference fields depend on it; its admin **screen** stays in Phase 6.

---

## 6. Functional Requirements

### FR-AUTH — Auth & session (reuse existing infra)
- FR-AUTH1: JWT auth — `POST /auth/login`, `/auth/mfa/verify`, `/auth/refresh`, `/auth/logout`, `GET /auth/me`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/change-password`. Access token short-lived (Bearer); refresh token in HttpOnly Secure SameSite cookie; blacklist on logout. No tokens in localStorage.
- FR-AUTH2: TOTP MFA in phase 1 (reuse current MFA infra).
- FR-AUTH3: Deactivating a user or changing sensitive permissions invalidates all their sessions.
- FR-AUTH4: Rate limit login, MFA, and password reset.
- FR-AUTH5: `/auth/me` returns user + `organization` + `team` + `role` + `bank|null` + computed screen permissions + relevant general capabilities.

### FR-GOV — Governance (orgs, teams, roles, banks, users)
- FR-GOV1 Organizations: independent org tier. Protected defaults `commercial_banks`, `national_committee`, `system_administration`. Default orgs cannot be deleted, cannot be deactivated while in use, display-name editable only (code immutable). Fields: `code`, `name`, `is_system`, `is_active`. CRUD + activate/deactivate.
- FR-GOV2 Teams: a team belongs to exactly one org, never directly to a bank, carries no role_code. A user belongs to one team. Cannot delete/deactivate a team with users. Fields: `organization_id`, `code`, `name`, `is_system`, `is_active`.
- FR-GOV3 Roles: a role belongs to one org. A user holds exactly one role, which must match the user's org. Default roles protected; any role assigned to a user cannot be deleted/deactivated. Screen permissions attach to the role. Fields: `organization_id`, `code`, `name`, `is_system`, `is_active`.
- FR-GOV4 Banks: independent resource under the `commercial_banks` org via `organization_id`. A bank user maps to one bank; a merchant maps to one bank. Cannot delete/deactivate a bank referenced by users/merchants/requests; bank org immutable after use. Fields: `organization_id`, `code`, `name`, `license_number`, `swift_code`, `status`.
- FR-GOV5 Users: always one org, one team, one role; one bank **iff** org = `commercial_banks` (else none/null). Validation: team & role must belong to the user's org; bank must belong to the commercial-banks org; `bank_id` required for bank org, null otherwise. No hard delete; deactivating a user with active work requires reassignment/closure; deactivation invalidates JWT. Fields: `organization_id`, `team_id`, `role_id`, `bank_id`, `name`, `email`, `phone`, `password`, `is_active`, `mfa_enabled`. Extra ops: `reset-password`, `reset-mfa`.
- FR-GOV6: Create/update forms return nested relation objects (organization/team/role/bank) so the UI never rebuilds names from IDs.

### FR-MERCH — Merchants (canonical merchant module; DI-5)
- FR-MERCH1: A merchant belongs to one bank and has basic data, multiple owners/shareholders, multiple related companies, and multiple requests.
- FR-MERCH2: `bank_id` required. Tax number unique system-wide. Related-company commercial-registration number unique system-wide.
- FR-MERCH3: Bank users see/manage only their bank's merchants; global-scope users see all with a bank filter.
- FR-MERCH4: Soft delete only. Cannot suspend a merchant with active requests. Bank immutable after the merchant's first request. Every change audited.
- FR-MERCH5 Merchant fields: `bank_id`, `name`, `tax_number`, `tax_card_expiry`, `address`, `phone`, `status` (`ACTIVE|SUSPENDED`).
- FR-MERCH6 Owners: `name`, `ownership_percentage` (0–100; UI surfaces ≥25% but DB stores all). Companies: `name`, `commercial_registration_number`, `commercial_registration_expiry`, `sector_reference_value_id`, `is_active`.
- FR-MERCH7: Create/update accepts nested `owners` and `companies` in one transaction.
- FR-MERCH8: List filters: `search`, `bank_id`, `status`, `sector_id`, `tax_number`. Business errors: `MERCHANT_TAX_NUMBER_EXISTS`, `COMMERCIAL_REGISTRATION_EXISTS`, `MERCHANT_HAS_ACTIVE_REQUESTS`, `MERCHANT_BANK_IMMUTABLE`, `MERCHANT_OUT_OF_SCOPE`.

### FR-REF — Reference data
- FR-REF1: Default tables `sector_activity`, `arrival_port`, `origin_country`. `reference_tables` (`key`, `label`, `sort_order`, `is_active`, `is_system`) + `reference_values` (`reference_table_id`, `key`, `label`, `sort_order`, `is_active`, `is_system`).
- FR-REF2: Keys immutable & unique. Requests store value ID/key, never label. A table used by a published version cannot be deleted; a value used by a request cannot be deleted (deactivate preserves history). Defaults delete-protected.
- FR-REF3: CRUD + activate/deactivate for tables and values.

### FR-WD — Workflow designer
- FR-WD1 Definitions/versions: `workflow_definitions` = process type; `workflow_versions` = runnable frozen config. Version states `DRAFT|PUBLISHED|ARCHIVED`. Edit only DRAFT; publish is final; later edits start by cloning a new version. A request keeps its original version to the end; new requests use the active published version; no cross-version migration (phase 1).
- FR-WD2 Stages: fields `code`, `name`, `description`, `sort_order`, `is_initial`, `is_final`, `sla_duration_minutes`, `status`. Exactly one initial stage; at least one final stage; code unique within version; cannot delete a stage bound to a transition/request; every non-final stage needs ≥1 outgoing transition with ≥1 executor before publish.
- FR-WD3 Actions: central reusable catalog — `code` (unique, immutable), `name` (editable), `kind` (`DRAFT|APPROVE|REJECT|RETURN|CLOSE|INFO|CUSTOM`), `is_active`, `is_system`. Cannot delete/deactivate an action used in a transition. `DRAFT` save does not have to change stage.
- FR-WD4 Transitions: `from_stage_id`, `action_id`, `to_stage_id`, `requires_comment`, `confirmation_message`. No duplicate action from the same stage; self-stage transitions allowed; execution validates current stage, permission, and stage fields, in one transaction (request + history + audit + notifications).
- FR-WD5 Stage permissions: unified `stage_permissions` — `stage_id`, `organization_id`, `team_id`, `role_id`, `user_id`, `access_level` (`VIEW|EXECUTE`), `display_label`. Set fields within a row = AND; different rows = OR; `EXECUTE` implies `VIEW`; `user_id` optional for exceptions. The "دوري" queue and request permissions derive from this table; no parallel routing source.
- FR-WD6 Fields & groups: field types `TEXT, NUMBER, DATE, SELECT, DYNAMIC_SELECT, TEXTAREA, FILE, CURRENCY, CHECKBOX`. `field_groups` organize fields as ordered tabs. Field settings: `key`, `label`, `type`, `placeholder`, `help_text`, `default_value`, `min_value`, `max_value`, `min_length`, `max_length`, `regex_pattern`, `options`, `reference_table_id`, `dynamic_source`, `allowed_file_types`, `max_file_size`, `multiple`, `is_system`. `DYNAMIC_SELECT` sources: merchants / merchant_companies / reference_data. Key unique within version & immutable after use; default fields delete-protected; a field used by a request is not deleted (changes via new version); files stored as independent records.
- FR-WD7 Per-stage field rules: `stage_field_rules` (`stage_id`, `field_id`, `is_visible`, `is_editable`, `is_required`). Backend enforces on draft save and on transition.
- FR-WD8 Process graph: generated from real stages/transitions; API returns `nodes` + `edges` showing branches, returns, finals; visibility derives from `stage_permissions`; `display_label` gives contextual naming without a separate routing source.
- FR-WD9 Validate before publish: `POST /workflow-versions/{id}/validate` returns displayable errors (bad initial stage, no final, non-final stage without transition/executor, transition to invalid resource, duplicate codes/keys, invalid field source). `publish` rejects any invalid config.

### FR-REQ — Requests & "دوري" queue
- FR-REQ1: A request is an instance of a published version — `workflow_version_id`, `current_stage_id`, `reference` (backend-generated unique), `status` (`ACTIVE|CLOSED|REJECTED`), `created_by`, `bank_id`, `merchant_id`, `data` (dynamic non-file fields), `version` (concurrency).
- FR-REQ2 Create: allowed if user has `EXECUTE` on the initial stage; bank & merchant from user scope and authorized selection; data validated against initial-stage field rules; create writes first `workflow_history` + `audit_logs`.
- FR-REQ3 List: shows requests where the user has `VIEW` or `EXECUTE` on the current stage, with org/bank scope. Filters: `workflow_id`, `workflow_version_id`, `stage_id`, `bank_id`, `merchant_id`, `status`, `created_from/to`, `sla_status`, `search`.
- FR-REQ4 "دوري" queue: `GET /requests/my-queue` returns only `ACTIVE` requests whose current stage grants the user `EXECUTE` (matching org/team/role/user/bank). No separate tasks table — derived from `current_stage_id` + `stage_permissions`. Default sort: SLA-breached → nearest-to-breach → oldest in stage.
- FR-REQ5 Execute action: `POST /requests/{id}/actions` with `transition_id`, `comment`, `data`, `version`. In one transaction: lock request → check version → check current stage → check `EXECUTE` → validate fields & comment → update data/stage/status → append `workflow_history` → append `audit_logs` → queue notification jobs after commit. Request leaves prior executor's queue and appears for the new stage's authorized users.
- FR-REQ6 Draft: `PATCH /requests/{id}/draft` — only for `EXECUTE` holders on the current stage; validates editable fields; required fields enforced only on the leaving action unless a rule says otherwise.
- FR-REQ7 Documents: `POST/GET/DELETE /requests/{id}/documents/...`; each doc tied to request + field + user + stage; deletable before field lock / leaving the stage.
- FR-REQ8 History & graph: `GET /requests/{id}/history` (request movement) and `GET /requests/{id}/graph` (nodes/edges with executed/current/possible path).
- FR-REQ9 Duplicate check: backend checks invoice number; result is a compliance warning, not an automatic block (unless a business rule is added). Business errors: `REQUEST_STALE`, `TRANSITION_NOT_AVAILABLE`, `STAGE_EXECUTION_FORBIDDEN`, `STAGE_FIELDS_INVALID`, `COMMENT_REQUIRED`, `REQUEST_CLOSED`, `MERCHANT_OUT_OF_SCOPE`.

### FR-AUD — Audit & compliance
- FR-AUD1: `audit_logs` append-only (no app edit/delete). Fields: `actor_user_id`, `actor_role_id`, `event_code`, `entity_type`, `entity_id`, `request_id`, `workflow_instance_id`, `old_values`, `new_values`, `metadata`, `ip_address`, `user_agent`, `correlation_id`, `created_at`.
- FR-AUD2: Log login/logout/failed attempts; admin resource create/update/deactivate; permission changes; workflow clone/validate/publish; request create/draft/actions; document upload/download/delete; exports. `workflow_history` stays a specialized request-path log linked to audit where possible.
- FR-AUD3: APIs `GET /audit-logs`, `/audit-logs/{id}`, `/audit-logs/export`, `/compliance/duplicate-invoices`. Filters: user, role, event, entity, request, date, IP, correlation_id.
- FR-AUD4 Compliance (phase 1): invoice-number duplicate detection; expired-document detection from recorded data; SLA-breach display. No speculative fraud indicators.

### FR-RPT — Reports & analytics
- FR-RPT1: Every report applies user scope & permissions. Aggregated APIs: `/reports/summary`, `/requests-over-time`, `/by-workflow-stage`, `/by-bank`, `/by-merchant`, `/by-sector`, `/by-currency`, `/stage-duration`, `/sla`, `/team-performance`.
- FR-RPT2: Shared filters: date, workflow, version, bank, org, stage, status, currency.
- FR-RPT3: Export `POST /reports/exports`, `GET /reports/exports/{id}`, `/download`; large exports are queued jobs using the same filters as the screen.
- FR-RPT4 Privacy: individual-performance reports need a separate permission; defaults show team/role performance; never return data outside the user's bank/org scope.

### FR-PERM — Screen permissions
- FR-PERM1: Central catalog of every screen and its capabilities (`VIEW, CREATE, UPDATE, DELETE, EXPORT, MANAGE`). `screen_permissions` links role → screen → capability. Screens include: organizations, teams, roles, banks, users, merchants, workflow_designer, requests, reports, audit, reference_data, screen_permissions, notifications, settings.
- FR-PERM2: Neither frontend nor backend may gate screens on hardcoded role codes. Request view/execute permission derives from `stage_permissions`; request export / general management may come from screen permissions.
- FR-PERM3: The default system administrator holds all permissions; cannot remove permission-management from the last active system admin.
- FR-PERM4: APIs `GET /screens`, `GET /roles/{id}/screen-permissions`, `PUT /roles/{id}/screen-permissions`, `GET /auth/me/permissions`.

### FR-NOTIF — Notifications
- FR-NOTIF1: `notifications` (`type`, `severity`, `title`, `body`, `entity_type`, `entity_id`, `action_url`, `created_at`) + `notification_recipients` (`notification_id`, `user_id`, `read_at`, `archived_at`).
- FR-NOTIF2: Events — request reaching an executable stage; approve/reject/return; SLA near/breached; invoice duplicate / compliance issue; workflow version published; sensitive permission change.
- FR-NOTIF3: In-platform channel only (phase 1). Audience resolves to actual users at send time; a user reads/archives only their own copy; shared notification not deleted; creation via queued job after a successful transaction.
- FR-NOTIF4: APIs `GET /notifications`, `/notifications/unread-count`, `POST /notifications/{id}/read|unread|archive`, `/notifications/read-all`.

---

## 7. Non-Functional Requirements

- NFR1 Platform: Laravel ^11, PHP ^8.2, MySQL 8+, Redis (queue/cache/rate-limit), Swagger (l5-swagger ^11), JWT (jwt-auth). Frontend Nuxt 4/Vue/TS, Tailwind v4, shadcn-vue, Pinia, VeeValidate+Zod.
- NFR2 API: base `/api/v1`, JSON snake_case, every resource returns `id`, `created_at`, `updated_at`, `version`. Lists: `page`, `per_page` (default 25, max 100), `search`, `sort`, `direction`, with `data`+`meta`. Error envelope `{ error: { code, message, fields, request_id } }`. HTTP codes: 401/403/404/409/422/429 per `00-api-and-auth`.
- NFR3 Concurrency: every editable record carries `version`; sensitive updates send current `version`; mismatch → `409 STALE_RESOURCE`. Request transitions run in a DB transaction with row lock; no two transitions for the same version.
- NFR4 Files: Laravel local private disk outside `public`; DB stores metadata + path only; multipart upload; authorized download endpoint (no public URLs); backend validates type/size/extension; PDF-only for documents; backups cover MySQL + files dir.
- NFR5 Storage/indexes (Hybrid, DI-2): non-file values in `requests.data` JSON validated from field definitions, storing reference IDs/keys not labels, **plus explicit columns** for fields used in filter/scope/reports: bank, merchant, status, stage, reference, amount, currency, invoice_number. Core reports never scan unindexed JSON. Indexes ≥: `requests(status,current_stage_id,updated_at)`, `requests(bank_id,status)`, `requests(workflow_version_id,current_stage_id)`, `requests(invoice_number)`, `workflow_history(request_id,created_at)`, `audit_logs(entity_type,entity_id,created_at)`, `audit_logs(actor_user_id,created_at)`, `notification_recipients(user_id,read_at,archived_at)`.
- NFR6 Security: org/bank scope enforced at query level (mandatory); permission from screen_permissions + stage_permissions, never role codes; all workflow transitions transactional; audit every action; failed auth logged with null user; rate limits on auth.
- NFR7 Ops: Nginx + PHP-FPM, Supervisor queue workers, Cron (Laravel Scheduler every minute), single production server with persistent storage.
- NFR8 UX: Arabic-first RTL default; desktop-first (≤600px degradation); queue-first dashboards; status badges color + icon; WCAG 2.2 AA; no analytics theatrics.
- NFR9 Definition of done per phase (backend-handoff): migration + protected default seed; Form Requests + Policies/Gates; unified API Resources; feature tests (success/failure/permission); Swagger updated; matching UI screen wired; screen proven free of localStorage/mock for its scope.

---

## 8. Default Seed (adopted as-is — DI-5/seed decision)

Seed the National Committee import-financing workflow as the active published version:
- Orgs: `commercial_banks` (البنوك التجارية), `national_committee` (اللجنة الوطنية لتمويل الواردات), `system_administration`.
- Teams (7): entry, internal-review, FX ops, bank admin (under banks org); support, executive, FX-confirmation (under committee org).
- Roles (8): intake/ENTRY, internal reviewer/REVIEWER, FX/SWIFT, support, committee-manager (executive decision), FX-confirm, system admin — codes finalized at implementation against governance defaults.
- Workflow `IMPORT_FINANCING` — 8 stages: CREATE → INTERNAL → SUPPORT → EXEC → FX → FX_CONFIRM → FINAL → CLOSED.
- Actions: SAVE_DRAFT, APPROVE, REJECT, RETURN, CLOSE, MORE_INFO, ADD_NOTES, UPLOAD_DOCS, FINAL_APPROVE (designer-editable).
- EXEC stage decision = single committee-manager APPROVE/REJECT (no voting).

## 9. Out-of-engine domain features to re-bind (see architecture DI-4)

Customs/FX-confirmation PDF, financing ledger updates, support-claim TTL, duplicate detection bind to stage entry/exit hooks / action effects rather than fixed status transitions. Hybrid typed columns keep ledger/customs/reports working.

## 10. Open items carried to architecture

DI-1 (M:N identity, seed one-each, UI single) and DI-4 (stage-hook mechanism design) are resolved in the companion architecture doc. DI-2 (Hybrid), DI-3 (voting removed), DI-5 (merchants canonical) are locked here and there.
