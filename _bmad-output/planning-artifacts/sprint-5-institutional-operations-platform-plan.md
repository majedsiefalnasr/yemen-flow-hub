# Sprint 5 Plan: Institutional Operations Platform Foundation

Date: 2026-05-17
Status: Planned
BMAD Epic: Epic 5 — Post-MVP Institutional Operations Platform

## Sprint Goal

Move Yemen Flow Hub from MVP workflow completion into an institutional operational platform where governance, auditability, usability, discoverability, and operational efficiency are first-class concerns.

## Sprint Theme

Post-MVP platform hardening and operational UX.

The sprint must preserve all MVP workflow governance:

- No admin role may override workflow rules.
- No feature may bypass organization scoping.
- No prototype-only demo behavior may enter production.
- PDF remains the canonical legal customs document.
- Reporting remains operational and governance-focused.

## Planned Stories

### Story 5.1 — BANK_ADMIN Role, Hierarchical RBAC & Scoped Bank Administration

Add the dedicated `BANK_ADMIN` role and scoped bank administration model.

Primary scope:

- Add `BANK_ADMIN` to backend and frontend canonical role handling.
- Support hierarchical RBAC: global CBY roles, bank-scoped admin roles, bank operational roles.
- Allow `BANK_ADMIN` to manage users inside their own bank only.
- Allow create/deactivate/password reset for `DATA_ENTRY` and `BANK_REVIEWER` only.
- Allow bank profile metadata management for own bank.
- Add bank-level operational dashboard access.
- Audit log all bank-admin actions.
- Enforce bank scoping at policy, query, API, and UI layers.

Out of scope:

- Creating CBY users.
- Assigning CBY roles.
- Cross-bank access.
- Workflow governance overrides.
- Global audit log access.

### Story 5.2 — Profile, Settings & Navigation Completion

Add production-safe profile/settings pages and remove broken or premature navigation.

Primary scope:

- `/profile`: name/email, role display, bank affiliation, password change, sessions, activity summary.
- `/settings`: language, notification preferences, dashboard preferences, table density/page size, default filters.
- Admin settings: workflow timing values, upload limits, feature toggles, reporting controls.
- Only expose navigation routes that are fully implemented.

Out of scope:

- Prototype demo reset controls.
- Fake role switchers.
- Mock-only preferences.

### Story 5.3 — In-App Notifications Phase 1

Add scoped, production in-app notifications.

Primary scope:

- Notification center.
- Unread counters.
- Workflow assignment alerts.
- Review/claim alerts.
- Rejection alerts.
- Voting opened/closed alerts.
- Customs issued alerts.
- Account/admin action alerts.
- Notification preferences integration where available.

Out of scope:

- Websocket realtime delivery.
- Email/SMS.
- Cross-bank notification visibility.

### Story 5.4 — Global Search Phase 1

Add role-scoped global search.

Searchable entities:

- Request number.
- Importer/merchant.
- Supplier.
- Bank.
- Customs declaration number.
- Workflow status.
- SWIFT references where stored.
- Users for admin roles only.

Primary scope:

- Debounced async search.
- Role/org scoped backend search endpoint.
- Recent searches.
- Filter chips.
- Deep-link navigation.
- Keyboard shortcut support if accessible and low risk.

### Story 5.5 — Customs Print Preview Page

Add operational browser preview for customs declarations.

Primary scope:

- `/requests/{id}/customs-preview`.
- Print-optimized RTL layout.
- Same canonical data source as official PDF.
- Browser print support.
- "Download Official PDF" action.
- Read-only immutable preview.
- Watermark/status indicators where useful.

Constraint:

- Official PDF remains the canonical legal document.

### Story 5.6 — Advanced Operational Reporting

Add governance-focused reporting.

Primary modules:

- Workflow throughput.
- Pending queue aging.
- SLA delay indicators.
- Approval/rejection ratios.
- Support committee activity.
- Voting outcomes.
- Customs issuance metrics.
- Bank-specific request statistics.
- Importer/supplier activity.
- Approval success rates.
- Historical workflow volume.
- Voting participation and abstention metrics.
- Committee performance trend dashboards.

Technical scope:

- Date-range filters.
- Role-scoped visibility.
- Saved filters/presets.
- Export to Excel and PDF.
- Indexed, performant queries.
- Audit logging for exports.

Out of scope:

- External BI.
- Data warehouse.
- AI analytics.

### Story 5.7 — Approved Lovable Prototype Parity & Production UI Alignment

Add a final parity/signoff story to make the production Nuxt app match the accepted Lovable prototype as closely as possible while staying production-safe.

Primary scope:

- Map every `lovable/src/routes/*` screen to a production route, production equivalent, demo-only exclusion, or explicit deferral.
- Align production UI with Lovable visual intent using the current stack: Nuxt 4, Vue, TypeScript, Tailwind CSS, shadcn-vue-compatible patterns, and Pinia.
- Match page structure, RTL shell, sidebar/header, page headers, tables, cards, badges, forms, dialogs, tabs, notifications, search surfaces, print preview, reports, and empty/loading/error states as closely as practical.
- Produce a final parity checklist/report for stakeholder signoff.
- Verify key screens in desktop and <=600px responsive layouts where practical.

Constraints:

- `lovable/` is read-only reference material.
- Do not copy React/TanStack implementation code into Nuxt.
- Do not bring prototype mock state, role switcher, fake login picker, demo reset tools, or UI-only auth shortcuts into production.
- Where prototype UI conflicts with governance/security/docs, production rules win and the difference is documented.

## Recommended Delivery Order

1. Story 5.1 — `BANK_ADMIN` role and hierarchical RBAC.
2. Story 5.2 — profile/settings and navigation completion.
3. Story 5.3 — notifications Phase 1.
4. Story 5.4 — global search Phase 1.
5. Story 5.5 — customs print preview.
6. Story 5.6 — advanced operational reporting.
7. Story 5.7 — approved Lovable prototype parity and production UI alignment.

## BMAD Tracking

Sprint tracker updated in:

- `_bmad-output/implementation-artifacts/sprint-status.yaml`

Epic/story source updated in:

- `_bmad-output/planning-artifacts/epics.md`

## Next BMAD Action

Run create-story for:

`5-1-bank-admin-role-hierarchical-rbac-scoped-bank-administration`

This story should be implemented first because role hierarchy and bank-scoped admin permissions affect navigation, users, dashboards, settings, reporting, notifications, and search.
