---
target: frontend/app/pages all role pages
total_score: 21
p0_count: 1
p1_count: 3
timestamp: 2026-06-01T10-38-35Z
slug: frontend-app-pages-all-role-pages
---
## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 1 | Live app currently shows a Nuxt compile overlay from `requests/[id]/edit.vue`; some older pages still use custom or silent loading/error paths. |
| 2 | Match System / Real World | 3 | Most role dashboards match operational queues, but legacy customs wording and raw `SLA` labels leak into Arabic workflows. |
| 3 | User Control and Freedom | 2 | Request detail has next/previous and shortcuts, but edit/new still use `window.confirm`; export and preset flows provide weak recovery. |
| 4 | Consistency and Standards | 2 | Shared `/requests` and dashboards use the modern component system, while reports, admin settings, print, organization, and preview pages retain raw controls/custom CSS. |
| 5 | Error Prevention | 2 | Role gates are strong, but the broken edit page blocks the correction path and some destructive/sensitive paths still lack shadcn confirmation patterns. |
| 6 | Recognition Rather Than Recall | 3 | Role-specific queues and status labels help, but Director FX, support claims, and admin risk/SLA concepts need stronger inline explanation. |
| 7 | Flexibility and Efficiency | 3 | Detail page has keyboard shortcut wiring and next/previous navigation; list filtering is URL-driven, but older report/admin pages are mouse-heavy. |
| 8 | Aesthetic and Minimalist Design | 2 | Best surfaces are restrained and task-first; older islands feel like a different app due raw buttons, custom cards, and dense ad hoc CSS. |
| 9 | Error Recovery | 2 | Many current components have retry actions, but exports, legacy reports, and custom print/preview paths are inconsistent. |
| 10 | Help and Documentation | 1 | High-stakes workflow moments, especially FX confirmation and irreversible decisions, lack enough contextual guidance for first-time operators. |
| **Total** | | **21/40** | **Needs remediation before broad UI polish** |

## Anti-Patterns Verdict

**LLM assessment:** This does not broadly look AI-generated. The role dashboard model is real product design: each role gets a distinct queue, status language, and action posture. The problem is drift. The polished shared surfaces sit beside older page islands that violate the repo's own shadcn-vue and design-token rules. That makes the application feel less governed than its workflow actually is.

**Deterministic scan:** The bundled Impeccable detector was attempted against `frontend/app/pages`, but the local skill bundle returned `Error: bundled detector not found.` Fallback source scans found raw `<button>`, `<input>`, `<select>`, raw `<table>`, `window.confirm`, custom skeletons, custom error banners, a gradient class, and legacy raw-color CSS patterns in page-level code.

**Visual overlays:** No reliable overlay is available. `playwright-cli` opened `http://localhost:3001/login`, but the live page rendered the Vite compile overlay instead of the login UI. The overlay reports an SFC parser error in `frontend/app/pages/requests/[id]/edit.vue` at `</script>`, caused by an unclosed `onMounted(async () => { ... })` block before `onBeforeRouteLeave`.

## Overall Impression

The product direction is correct: queue-first, role-scoped, restrained, and operational. The most important design issue is not color or spacing; it is product integrity. A compile-breaking request edit page blocks the correction workflow, and older surfaces still bypass the shared component system. Fix the broken flow first, then standardize the legacy page islands.

## What's Working

1. The role-surface matrix is a strong foundation. Navigation is derived from role authority instead of ad hoc visibility checks, which matches the least-privilege UI principle.
2. `/requests` is the best reference surface: role-aware KPI filters, URL-driven pagination, shadcn `DataTable`, export controls, and healthy empty states.
3. Most role dashboards are meaningfully different. Data Entry, Bank Reviewer, Support Committee, SWIFT Officer, Executive, Director, Bank Admin, and CBY Admin do not collapse into one generic dashboard.

## Priority Issues

**[P0] Request edit page currently breaks Nuxt compilation**

**Why it matters:** `frontend/app/pages/requests/[id]/edit.vue` is part of the correction path for Data Entry and Bank Admin fallback drafting. If the app renders the Vite overlay, every role page becomes impossible to validate live.

**Fix:** Close the first `onMounted(async () => { ... })` block before `formDirty.value = true` flows into `onBeforeRouteLeave`. Then rerun browser checks for login, dashboard, `/requests`, `/requests/:id`, `/requests/:id/edit`, `/customs`, reports, settings, and admin pages.

**Suggested command:** `impeccable harden frontend/app/pages/requests/[id]/edit.vue`

**[P1] Legacy page islands bypass the shared design system**

**Why it matters:** Reports, admin settings, print/preview, and organization surfaces use raw controls or custom classes while core workflow pages use shadcn-vue. Operators will read that as uneven reliability, especially in an audit-sensitive CBY platform.

**Fix:** Migrate raw controls to shadcn components and shared patterns: `Button`, `Input`, `Select`, `Table/DataTable`, `Alert`, `Skeleton`, `Empty`, `AlertDialog`. Start with `reports/index.vue`, `admin/settings.vue`, `organization.vue`, `requests/[id]/customs-preview.vue`, `requests/[id]/print.vue`, and `audit.vue`.

**Suggested command:** `impeccable polish legacy role pages`

**[P1] External FX confirmation still carries legacy customs shape**

**Why it matters:** The Director workflow has been renamed to external FX confirmation, but the route and data model still surface `customs` names and `customs_declaration` concepts. That creates cognitive friction at the exact handoff that must feel formal and unambiguous.

**Fix:** Keep compatibility aliases internally if needed, but rename user-facing labels, cards, errors, print preview titles, and route affordances around `تأكيد مصارفة خارجية`. Add a numbered Director instruction strip for download, print/sign, scan, upload/complete.

**Suggested command:** `impeccable clarify external FX confirmation pages`

**[P1] High-stakes actions need stronger inline guidance**

**Why it matters:** Support claim ownership, irreversible bank rejection, executive voting finality, Director close/finalize, and FX confirmation are consequential. Current role pages often depend on button labels and status badges rather than explaining the consequence at the point of action.

**Fix:** Add compact, role-specific action panels and confirmation copy. Use `AlertDialog` for irreversible or audit-heavy actions. Place instruction text only at decision points, not as general page prose.

**Suggested command:** `impeccable harden workflow action states`

**[P2] Error and loading behavior is inconsistent across roles**

**Why it matters:** Some pages have excellent `Skeleton`, `Alert`, and retry paths; others have custom `.error-banner`, silent catches, or raw state messages. This hurts trust when network/API errors happen during regulatory work.

**Fix:** Standardize page state primitives. Replace custom error banners with `Alert variant="destructive"` plus retry, custom skeleton divs with `Skeleton`, and silent `catch {}` with actionable toast or inline alert.

**Suggested command:** `impeccable audit frontend/app/pages`

## Persona Red Flags

**Faisal (Bank Reviewer, power user):** The modern request detail patterns help him move quickly, but the broken edit page blocks returned-request correction, and raw report/admin surfaces do not support the same keyboard and component vocabulary as `/requests`.

**Yusra (Committee Director, first-time FX operator):** The FX surface still reads partly as customs issuance. She needs a numbered external-FX process, clearer document ownership, and stronger finalization guidance before signing or completing a request.

**Samia (CBY Admin, governance operator):** The CBY admin dashboard is moving in the right direction, but raw `SLA` terminology and mixed old/new admin settings controls make governance pages feel less authoritative than the role's responsibility requires.

## Minor Observations

- `frontend/app/pages/reports/index.vue` still uses raw `<button>` and `<input>` controls in the main filter/export path.
- `frontend/app/pages/admin/settings.vue` uses raw `<button>`, `<input>`, and `<select>` controls plus custom `.error-banner`.
- `frontend/app/pages/requests/[id]/edit.vue` uses `window.confirm`, which violates the shadcn confirmation rule for navigational risk.
- `frontend/app/components/dashboard/ExecutiveDashboard.vue` still uses hand-rolled `animate-pulse` skeleton divs.
- `frontend/app/pages/audit.vue` contains a raw table for the log diff table.
- `frontend/app/pages/admin/cby-staff.vue` uses `bg-gradient-hero`, conflicting with the no-gradient product register rule.
- Request detail still exposes `SLA` in English acronym form inside Arabic UI.

## Questions to Consider

1. Should the next pass be a **blocking fix pass** or a **design-system migration pass**? Options: fix the compile blocker first, migrate legacy pages first, or do both in one hardening story.
2. For the FX flow, should we keep `/customs` as a hidden compatibility route while changing visible copy, or rename the route/nav surface in this sprint?
3. Do you want the role critique converted into one backlog per role, or one shared-component-first backlog that fixes the recurring issues once?
