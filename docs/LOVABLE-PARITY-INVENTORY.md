# Lovable (`dynamic-workflow-engine/`) vs Yemen Flow Hub — Page/Feature Inventory

Date: 2026-06-24
Scope: page-by-page and feature-by-feature inventory diff only (not architecture/contract audit — that already exists in `LOVABLE-AUDIT.md` / `ENGINE-RECONCILIATION.md` / Epic 18 PRD).

**Headline finding:** this comparison was expected to show Lovable = engine-only prototype, ours = legacy fixed workflow + auth/settings Lovable never had. That is no longer the full picture. The backend already has full migrations + controllers for the dynamic engine (workflow_definitions/versions/stages/transitions/actions, organizations/teams/roles, screens/screen_permissions, traders, reference_tables — all dated 2026-06-22 to 2026-06-24), and the frontend already has a working `admin/workflows.vue` designer, trader pages, and a screen-permission layer. The legacy fixed-enum stack (`RequestStatus`, `users.role` column, `import_requests`) still runs in parallel, uncut. This is a **mid-migration project**, not a pre-migration one. Project memory describing Epic 18 as "NOT started" is stale as of this audit.

---

## 1. Present in Lovable, present in ours (parity / already ported)

| Feature | Lovable evidence | Ours evidence | Notes |
|---|---|---|---|
| Workflow designer (مصمم سير العمل) | `src/routes/admin.workflows.tsx`, 7 tabs, `lib/workflow-engine/engine.ts` | `frontend/app/pages/admin/workflows.vue` + `WorkflowStageEditor/TransitionEditor/FieldDesigner/ProcessGraph/PublishPanel/ActionsCatalog.vue`, `useWorkflows()` | Real backend behind ours (`WorkflowDefinitionController` etc.) vs Lovable's localStorage-only |
| Organizations admin | `admin.orgs.tsx` | `admin/orgs.vue`, `useOrganizations` | Backend: `organizations` table + `OrganizationController` |
| Teams admin | `admin.teams.tsx` | `admin/teams.vue`, `useTeams` | Backend: `teams` table + `TeamController` |
| Roles admin (dynamic) | `admin.roles.tsx` | `admin/roles.vue`, `useGovernanceRoles` | Backend: `roles` table + `RoleController`, but `users.role` legacy column still present too |
| Screen permissions admin | `admin.screen-permissions.tsx`, `ScreenGuard.tsx` | `admin/screen-permissions.vue`, `ScreenGuard.vue`, `useScreenPermissions` | Backend: `screens`/`screen_permissions` tables + `RoleScreenPermissionController`/`ScreenController` |
| Reference data (البيانات الأساسية) admin | `admin.reference-data.tsx` | `admin/reference-data.vue`, `useReferenceData` | Backend: `reference_tables`/`reference_values` + controllers |
| Merchant/Trader basic data | `merchants.tsx` (تجار) | `traders/index.vue` + `new/[id]/edit.vue`, `traders.ts` store, **plus legacy parallel `merchants.vue`** | Backend: `traders`/`trader_owners`/`trader_companies` tables. Ours runs Trader (new) and Merchant (legacy) in parallel — Lovable only has Merchant/تجار, no separate Trader concept |
| Audit & compliance | `audit.tsx` | `audit.vue`, `useAudit`/`useCompliance` | Both real; ours has richer compliance composable |
| Reports/analytics | `reports.tsx` | `reports.vue` AND `reports/index.vue` (duplicate, unreconciled) | Ours has 4 chart components (Line/Pie/CurrencyBar/Heatmap); Lovable's charts are simpler |
| Notifications inbox | `notifications.tsx` | `notifications.vue`, `useNotifications`, `notifications.store.ts` | Ours has real email/in-app delivery; Lovable is mock-seeded only |
| Profile page | `profile.tsx` | `settings/index.vue` (acts as profile) | Ours folds profile into settings tab rather than a separate route |
| CBY/committee staff admin | `admin.cby-staff.tsx` | `admin/cby-staff.vue` | Both real, ours backend-wired |
| Bank users admin | `bank.users.tsx` | `bank/users.vue` | Both real |
| Entities admin | `admin.entities.tsx` | `admin/entities.vue` | Ours maps to banks via `screen` middleware (`requiredScreen: 'banks'`) |

---

## 2. Present in Lovable, missing or incomplete in ours

| Feature | Lovable evidence | Status in ours | Gap |
|---|---|---|---|
| Dynamic workflow **instance** queue replacing fixed requests | `workflows.index.tsx` (`/workflows`), `workflows.instances.$id.tsx` — list + detail driven entirely by `workflow-engine` metadata, no fixed status enum | We still run `requests/index.vue` + `requests/[id]/*.vue` against the **legacy `RequestStatus` enum** (`import_requests` table). New `engine_requests` table exists in backend but **no frontend pages consume it yet** | Biggest real gap: the dynamic *request/instance* UI (queue + detail rendering `DynamicForm` from stage field metadata) is not built on our side. We have the designer (produces metadata) but not the consumer (renders instances from that metadata) |
| Dynamic form renderer from field metadata | `components/workflow/DynamicForm.tsx` — renders arbitrary stage fields from `field_definitions`/`stage_field_rules` at runtime | No equivalent component found in `frontend/app/components/` | Needed once engine_requests UI is built |
| Org-process stepper (dynamic stage routing visualization) | `components/workflow/OrgProcessStepper.tsx` | We have `WorkflowProgress.vue`/`RequestProgress.vue` but those are keyed to the **fixed 22-status enum**, not to dynamic `workflow_stages` | Needs a dynamic-stage version |
| Role switcher (demo) | `components/workflow/RoleSwitcher.tsx` | `layout/` has a role switcher component per earlier story memory (Story 6.7) | Likely already covered — verify it's wired to the new dynamic role model, not just the fixed 8-role enum |
| Single-approval EXEC stage (no voting) | Lovable engine has no voting concept at all — `applyAction` does plain APPROVE/REJECT per stage | We still have a full **voting subsystem** (`VotingPanel.vue`, `voting.store.ts`, `useVoting`, executive voting dashboards) | Per locked decision DI-3 in memory, voting is supposed to be **removed entirely** under Epic 18 — not yet done; this is an intentional future removal, not a missing feature to add |

---

## 3. Present in ours, missing in Lovable (the part the user explicitly called out)

| Feature | Our evidence | Confirmed absent in Lovable |
|---|---|---|
| Full real login flow (credential check + TOTP MFA + lockout + recovery) | `pages/login.vue` (real auth, 429 lockout, `setupTotp`/`verifyTotpSetup`), `mfa-setup.vue`, `reset-password.vue`, `change-temporary-password.vue` | Lovable's `login.tsx` is demo-only: pick a `DEMO_USERS` entry, no real credential verification, OTP step is cosmetic |
| User/system settings (real, backend-backed) | `pages/organization.vue` (branding/mail/security/network), `settings/index.vue` (profile/MFA/theme/notifications), `admin/settings.vue`, `admin/email-templates/*` | Lovable's `settings.tsx` is a single tabbed page with no real persistence beyond localStorage, and includes a "reset demo data" action — not a real settings system |
| Document upload + checksum validation | `requests/` document checklist components, PDF-only validation, sha256 checksum (Story 2.2) | No file/document upload concept in Lovable at all |
| SWIFT officer flow | `requests/[id]/swift.vue`, SWIFT upload form component | No SWIFT concept in Lovable (dead redirect stub `requests.$id.swift.tsx` only) |
| Customs/FX confirmation issuance (PDF generation, DB transaction + advisory lock) | `requests/[id]/customs-preview.vue`, `customs/[id]/print.vue`, backend `DomPDF` generation | Lovable's `customs.tsx` and `customs.$id.print.tsx` are dead redirect stubs only |
| Audit trail with real stage history persistence | `request_stage_history`/`audit_logs` tables, `AuditTimeline.vue`, `WorkflowTimeline.vue` | Lovable's audit log is mock data only |
| Financing ledger (global, derived) | `useFinancingLedger`, ledger composable (National Committee Epic D) | No ledger concept in Lovable |
| Email template management | `admin/email-templates/index.vue`, `[type].vue` | Not present in Lovable |
| Inactivity timeout / forced logout | `useInactivityTimer`, `InactivityBanner.vue` | Not present in Lovable |
| Print layouts | `requests/[id]/print.vue`, `customs/[id]/print.vue` | Not present in Lovable |
| Dark mode | confirmed app-wide dark mode support (Story 6.7) | Not checked in Lovable, likely absent — Lovable is a UI/concept prototype, not styled for theming completeness |

---

## 4. Internal inconsistencies surfaced by this audit (not Lovable-related, but found while scanning)

- `pages/reports.vue` and `pages/reports/index.vue` coexist — unclear which is canonical; one is likely dead and should be removed.
- `pages/merchants.vue` (legacy `Merchant`) and `pages/traders/*` (new `Trader`) coexist as parallel entities. Per memory DI-5, `merchants` should be canonical for the dynamic engine, but the new National Committee Trader module (separate, real PII/snapshot model) was built first — these two efforts haven't been reconciled yet.
- Backend: `import_requests` (legacy `RequestStatus` enum) and `engine_requests` (dynamic `workflow_stages`-based) tables both exist; no frontend currently reads/writes `engine_requests`.
- `users.role` legacy enum column and new `roles`/`user_roles`/`user_teams` tables coexist; cutover not done.
- Voting subsystem still fully present in frontend despite locked decision DI-3 (voting removed entirely under the dynamic engine).

---

## 5. What this means for Epic 18 planning

Project memory (`project_dynamic_workflow_engine.md`) states Epic 18 work is "NOT started." That is **incorrect as of this audit** — schema, controllers, and several admin UI pages for the dynamic engine (governance, screen permissions, reference data, workflow designer, traders) already exist and appear to predate or overlap with this memory's "not started" note. The actual remaining gap, based on this inventory, is narrower than previously assumed:

1. **No dynamic request/instance UI** — the `engine_requests` table and `workflow_stages` exist, but nothing renders an instance queue or a `DynamicForm` against stage field metadata. This is the single largest missing piece, directly analogous to Lovable's `/workflows` + `/workflows/instances/:id` + `DynamicForm.tsx`.
2. **Legacy/engine cutover not done** — `import_requests`/`RequestStatus`/`users.role` column still live alongside the new tables.
3. **Voting not yet removed** (DI-3).
4. **Merchant/Trader reconciliation** not yet done (DI-5).

The BMAD framework (planning artifacts, sprint-status.yaml, story files) has since been removed from this repo — there is no longer a BMAD-tracked story backlog to reconcile against. Treat this inventory doc as the current source of truth for what's built vs. missing, and re-plan remaining Epic 18 work (story breakdown, sequencing) using whatever planning approach replaces BMAD going forward.
