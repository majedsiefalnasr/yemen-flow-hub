# Prototype Gap Analysis — Lovable vs Current Implementation

**Date:** 2026-05-18  
**Updated:** 2026-05-18 (UX spec pass — missing UI states fully documented in `docs/ux/missing-ui-states.md`)  
**Analyst:** Claude Code (automated analysis + manual screenshot verification)  
**Purpose:** Identify all UI/UX/logic/feature gaps between the stakeholder-approved Lovable prototype and the current production frontend, to drive a sprint toward production acceptance.

---

## Scope

| Item | Path |
|------|------|
| Reference prototype source | `lovable/src/` |
| Reference screenshots (80+) | `lovable/screenshots/` (all roles, fully reviewed) |
| UX design specifications | `docs/ux/missing-ui-states.md` (RequestWizard, SwiftUploadModal, EmptyState, Skeleton, Validation) |
| Current frontend | `frontend/app/` |
| Updated design authority | `DESIGN.md` (now reflects full screenshot analysis) |
| Current design tokens | `frontend/app/assets/css/main.css` |

---

## Track A: Design Token Gaps

### Critical

**A1 — Primary color mismatch**
- Prototype (approved): `#0066cc`
- Current implementation: `#0071e3`
- Scope: all CTAs, focus states, active nav, interactive highlights — the entire blue identity
- File: `frontend/app/assets/css/main.css:11`

**A2 — Background color mismatch**
- Prototype (approved): `#ffffff` (pure white)
- Current implementation: `#f5f5f7` (light gray)
- Scope: every page background — stakeholders see gray where they approved white
- File: `frontend/app/assets/css/main.css:6`

### Major

**A3 — Two of three fonts missing**
- Prototype uses a three-tier type system: **Cairo** (headlines/titles) + **Tajawal** (section headers) + IBM Plex Sans Arabic (body)
- Current loads only IBM Plex Sans Arabic + Inter; Cairo and Tajawal are not imported
- Impact: all page headers, section titles, and role labels render in the wrong typeface
- File: `frontend/app/assets/css/fonts.css` — no Cairo or Tajawal entries

**A4 — No elevation/shadow token system**
- Prototype defines three shadow levels: `sm` (1px/0.06), `md` (4px/0.1), `lg` (16px/0.12) with a `focus` variant
- Current CSS has no shadow token definitions; shadows are inline or absent
- Impact: cards, modals, hover states lack the approved depth hierarchy

**A5 — Surface-container palette absent**
- Prototype defines 8 surface-container variants (`surface-dim`, `surface-bright`, `surface-container-lowest` through `surface-container-highest`) for layered depth without secondary hues
- Current CSS has a single `--color-background` and `--color-surface` — no container variants
- Impact: nested cards, table rows, and hover states cannot match prototype appearance

**A6 — Semantic status color system divergence**
- Prototype: success text `#1b5e20` on bg `#f1f8f4` with border `#c8e6c9`; error text `#c62828` on bg `#ffcdd2`; info text `#0d47a1` on bg `#e3f2fd` — full text+bg+border triplets
- Current: `#34c759` (approved green), `#ff3b30` (rejected red), `#ff9f0a` (pending amber) — single accent colors, not semantic triplets
- Impact: status badges and alert banners don't match the approved palette

**A7 — Sidebar color tokens absent**
- Prototype defines a sidebar-specific palette: `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-accent`, `--sidebar-border`, `--sidebar-ring`
- Current AppSidebar uses main palette tokens, not sidebar-specific ones
- Impact: sidebar cannot be independently themed (required for dark mode)

### Minor

**A8 — Scrollbar styling**
- Prototype: rounded full (`border-radius: 9999px`) scrollbar thumb with oklch-based color
- Current: 3px border-radius on thumb — slight visual difference

**A9 — Radius naming mismatch**
- Button radius should be `lg` (16px) per prototype, not 12px (current)
- Cards use `lg` (16px), modals use `xl` (24px) — current uses flat 12px for both

---

## Track B: Component Fidelity Gaps

### Critical

**B1 — Sidebar collapse not implemented**
- Prototype: sidebar toggles between 72px (collapsed, icon-only) and 280px (expanded, icon+label) with a chevron toggle button at the bottom
- Current: sidebar is fixed at 264px with no collapse state
- Screenshot evidence: `CBY_ADMIN/dashboard-sidebar-collapsed.png` shows the 72px icons-only state
- Confirmed: `frontend/app/layouts/default.vue:42` hardcodes `margin-inline-end: 264px`

**B2 — Header missing backdrop blur**
- Prototype: `sticky top-0 z-30 h-16 border-b bg-card/80 backdrop-blur-md`
- Current: `AppHeader.vue` has `position: sticky` but no backdrop-blur and no semi-transparent bg
- Impact: content bleeds through header on scroll

**B4 — VotingPanel session controls incomplete**
- Confirmed from screenshots (`EXECUTIVE_MEMBER/request-view-voting-open-cast-vote.png`):
  - Progress bar showing "50% تقدم الطلب في الدورة التنظيمية"
  - Tally: موافقة 0 | رفض 0 | امتناع 0
  - 6 named committee members with name, email (@cby.gov.ye), and individual vote status
  - Vote form: textarea (optional) + 3 buttons: موافق (green) | رافض (red) | ممتنع (gray)
  - Director controls (COMMITTEE_DIRECTOR only): open/close session buttons
- Current `useVoting.ts` (2.0KB) is minimal; full panel parity likely missing

**B6 — DocumentChecklist component MISSING**
- Prototype has `DocumentChecklist.tsx` showing required/optional documents per stage with upload status
- Current frontend has NO DocumentChecklist.vue — confirmed by grep

**B7 — RoleSwitcher in login: EXISTS ✓ — but no in-header version**
- Screenshot `login.png` confirms RoleSwitcher IS implemented in the login form (persona picker)
- The login form shows a dropdown listing all 7+ roles with user names and subroles
- However, the prototype also allows switching roles from within the authenticated session via the header; no in-app role switcher found in current frontend
- **Status**: login-page RoleSwitcher: CLOSED. In-app header RoleSwitcher: OPEN (needed for stakeholder QA)

### Major

**B8 — LockedBanner lacks variant system**
- Prototype: supports 3 variants (`locked` / `readonly` / `pending`) with appropriate icon (Lock / Eye / Clock) and contextual messages
- Current `LockedBanner.vue`: single hardcoded variant

**B9 — Icon system limited**
- Prototype uses Lucide React icons throughout
- Current frontend has only ~13 hardcoded SVG paths in `SidebarIcon.vue`; no Lucide Vue integration

**B10 — StatusBadge role-based filtering: IMPLEMENTED ✓ (CLOSED)**
- Confirmed: `frontend/app/constants/workflow.ts` has `DATA_ENTRY_STATUS_LABELS`, `getBusinessStatus()`, `DATA_ENTRY_REPRESENTATIVE_STATUS`

### Minor

**B11 — Modal overlay differs**
- Prototype: `modal-overlay` uses `rgba(12, 18, 26, 0.4)` with `backdrop-filter: blur(4px)`
- Current: shadcn-vue Dialog overlay may not have the blur backdrop

---

## Track C: Missing / Mismatched Pages

### BANK-ADMIN Role (Canonical)

**C0 — Historical note (role is now canonical)**

The prototype contains a complete BANK-ADMIN role (distinct from CBY_ADMIN) with its own full set of pages and features. This role is now part of the canonical `AGENTS.md` role enum and exists in both frontend and backend implementations.

BANK-ADMIN pages visible in screenshots:

| Route | Purpose | Screenshot evidence |
|-------|---------|---------------------|
| `/dashboard` (BANK-ADMIN view) | 5 KPI cards + line chart + quick actions + recent requests table | `BANK-ADMIN/dashboard.png` |
| `/requests` | Requests list with progress bars + workflow status | `BANK-ADMIN/requests-list.png` |
| `/requests/[id]` | Request detail (3 tabs: معلومات, وثائق, أطراف) | `BANK-ADMIN/request-view-info-tab.png` |
| `/merchants` (card grid) | Merchant management — card grid layout (not table) with add/edit/suspend | `BANK-ADMIN/merchants-list-cards.png` |
| `/merchants/new` | Add merchant modal | `BANK-ADMIN/merchants-add-modal.png` |
| `/merchants/[id]/edit` | Edit merchant modal | `BANK-ADMIN/merchants-edit-modal.png` |
| `/staff` | Bank staff management table with role/dept/status | `BANK-ADMIN/staff-list.png` |
| `/requests/new` | 4-step wizard: بيانات الطلب → بيانات المورد → الوثائق → المراجعة والإرسال | `BANK-ADMIN/new-request-step1-basic-info.png` |
| `/reports` | Reports with KPIs, charts, export | `BANK-ADMIN/reports.png` |
| `/notifications` | In-app notifications list | `BANK-ADMIN/notifications.png` |

Key BANK-ADMIN differentiators vs. DATA_ENTRY:
- Can see full workflow progress (not simplified statuses)
- Dashboard has a line chart ("حركة طلبات البنك الشهرية")
- Can manage merchants (card grid, not table)
- Can manage bank staff members
- Request detail has 3 full tabs (not simplified)
- 4-step wizard with step 4 having an acknowledgment checkbox ("إقرار وتعهد")

### Missing Pages (in prototype, absent from current frontend)

| Route | Lovable File | Purpose | Severity |
|-------|-------------|---------|----------|
| All BANK-ADMIN pages | (22 screenshots) | Full bank admin role (see C0 above) | **Critical** |
| `/admin/cby-staff` | `admin.cby-staff.tsx` | Manage CBY system users | Critical |
| `/admin/entities` | `admin.entities.tsx` | Add/edit registered banks and entities | Critical |
| `/admin/roles` | `admin.roles.tsx` | View and manage role definitions | Critical |
| `/customs/[id]/print` | `customs.$id.print.tsx` | Customs declaration print/preview | Major |
| `/settings` (OTP / MFA) | `settings.tsx` | 6-tab settings page (workflow/SMTP/notifications/security/general/demo-data) | Major |
| `/profile` | User profile page with avatar, stats, recent activity | Major |

**Note:** `/bank/users` from the previous analysis maps to the BANK-ADMIN `/staff` page — it is part of the C0 BANK-ADMIN scope.

### Mismatched Pages (exist but differ significantly)

**C1 — Login page**
- Prototype: two-column layout (form left 50% + branded hero right 50%), 2-step flow (credentials → OTP), `#0066cc` right panel with Arabic tagline, demo RoleSwitcher dropdown
- OTP step (`login-otp.png`): 6-cell digit input boxes, "تأكيد ودخول" button, "رجوع" link
- Current: single-column form, no hero section, no OTP/MFA step
- Severity: **Critical** — first page stakeholders see

**C2 — Dashboard**
- Prototype: role-specific dashboards with rich KPI strips; CBY_ADMIN and BANK-ADMIN include charts (line, donut, bar)
- Current: role-specific Vue components exist; visual and data parity against each of the 8 role dashboards unconfirmed
- Severity: **Major**

**C3 — Request detail page**
- Prototype: WorkflowProgress rail (right panel) + 3–4 content tabs (المعلومات / الوثائق / الأطراف / التصويت) + locked banner + voting panel with 6 committee members
- Current: tabs likely exist but parity against prototype tab content and layout unconfirmed
- Severity: **Critical**

**C4 — Settings page**
- Prototype: 6-tab settings (سير العمل / البريد / الإشعارات / الأمن / عام / بيانات العرض التوضيحي) — only CBY_ADMIN
- Current: `settings.vue` exists but likely has different tabs or subset
- Severity: **Major**

**C5 — Audit & Compliance page**
- Prototype: 3 tabs (سجل النشاط / الفواتير المكررة / مؤشرات المخاطر) + 4 KPI cards
- Current: `audit.vue` exists — parity of 3-tab structure unconfirmed
- Severity: **Major**

**C6 — Profile page**
- Prototype: two-column (edit form + avatar card with stats + recent activity list), "تغيير كلمة المرور" and MFA enable buttons
- Current: no profile page confirmed in the frontend
- Severity: **Major**

---

## Track D: UX / Logic Flow Gaps

### Critical

**D1 — 2-step login (OTP/MFA) not implemented**
- Prototype shows a confirmed second login step (`login-otp.png`): 6-cell OTP input, "تأكيد ودخول" button
- Footer of login page states "مصادقة متعددة العوامل (MFA) مفعّلة"
- Current: single-step credentials login, no OTP step
- Severity: **Critical** — MFA is a security requirement visible to stakeholders

**D2 — In-app role switcher for stakeholder QA**
- While login page has a RoleSwitcher (confirmed), stakeholders need to switch roles without logging out
- Prototype supports this via the authenticated header; current does not
- Severity: **Critical** for QA and demo acceptance

**D3 — Simplified status labels for Data Entry: IMPLEMENTED ✓ (CLOSED)**
- Confirmed in `frontend/app/constants/workflow.ts`

### Major

**D4 — Dark mode not implemented**
- Prototype has confirmed dark mode (`CBY_ADMIN/dark-mode.png`) — a full dark theme toggled from the header
- Current: no dark mode toggle or dark theme CSS
- Severity: **Major** — visible feature in the approved prototype

**D5 — Customs print flow**
- Prototype has `/customs/$id/print` with A4 preview, zoom controls, issuance confirmation dialog
- Current has `customs-preview.vue` — needs full parity check
- Note: SWIFT_OFFICER upload flow confirmed as inline modal on request detail (not a separate page)
- **UX SPECCED** → `docs/ux/missing-ui-states.md` Spec 2 (SwiftUploadModal — upload zone states, interaction flow, button states, toast)

**D6 — Permission-based nav item visibility**
- Prototype uses `can(role, permission)` from `lib/governance.ts` for fine-grained nav visibility
- Current nav uses role-based filtering only; permission-level granularity may be missing

**D7 — Support claim race condition UI**
- Prototype: `isClaimedByOther(req, userId)` renders a blocking "claimed by another user" banner
- Current: `useClaimLifecycle.ts` exists but full blocking UI parity unconfirmed

**D8 — Voting tie-break notice**
- Prototype: when `yesCount === noCount`, a "تعادل — يُرجَّح صوت المدير عند التعادل" notice appears
- Current: not confirmed in `useVoting.ts`

**D9 — Request creation wizard (4-step) for bank users**
- Prototype: all bank roles use a 4-step form wizard for creating requests (BANK-ADMIN confirmed in screenshots)
- Step 4 has an acknowledgment checkbox ("إقرار وتعهد") required before final submit
- Current: single-page form with no stepper for request creation
- Applies to: BANK-ADMIN, DATA_ENTRY (same wizard, merchant field read-only for DATA_ENTRY)
- **UX SPECCED** → `docs/ux/missing-ui-states.md` Spec 1 (RequestWizard — full 4-step spec with all fields, validation rules, stepper states, nav bar)

---

## Track E: Layout & AppShell Gaps

### Critical

**E1 — Admin routes absent from navigation**
- AppSidebar.vue nav array does not include BANK-ADMIN pages, `/admin/cby-staff`, `/admin/entities`, `/admin/roles`

**E2 — Sidebar width and collapse**
- Prototype: 280px expanded, 72px collapsed (icon-only)
- Current: 264px fixed, no collapse

### Major

**E3 — Max-width constraint**
- Prototype: `containerMaxWidth: 1600px`
- Current: `app-content { max-width: 1280px }` — 320px narrower

**E4 — Content padding not responsive**
- Prototype: 24px desktop / 16px tablet / 12px mobile
- Current: fixed 24px with no responsive reduction

---

## Missing Screenshots (gaps in visual reference material)

Items marked **SPECCED** have been designed from first principles using DESIGN.md and are fully documented in `docs/ux/missing-ui-states.md`. Remaining items still require Lovable dev server capture.

| Missing view | Role(s) | Resolution |
|-------------|---------|-----------|
| DATA_ENTRY request creation form | DATA_ENTRY | **SPECCED** — same 4-step wizard as BANK-ADMIN; merchant field read-only pre-filled from org. See `docs/ux/missing-ui-states.md` Spec 1. |
| SWIFT_OFFICER upload form detail | SWIFT_OFFICER | **SPECCED** — inline modal on request detail (not a separate page). See `docs/ux/missing-ui-states.md` Spec 2. |
| Form validation errors | Any | **SPECCED** — full error state system + Arabic message catalog (18 entries). See `docs/ux/missing-ui-states.md` Spec 3. |
| Request list / page empty states | All roles | **SPECCED** — 8 named variants (requests, filtered, notifications, queue, audit, merchants, staff, dropdown). See `docs/ux/missing-ui-states.md` Spec 4. |
| Skeleton loaders | All roles | **SPECCED** — 5 compositions with shimmer animation spec. See `docs/ux/missing-ui-states.md` Spec 5. |
| Error / access denied page | All | `COMMITTEE_DIRECTOR/role-access-denied.png` is sufficient reference — single screenshot covers the pattern. |
| SUPPORT_COMMITTEE request detail (full) | SUPPORT_COMMITTEE | Still needed — only partial views in screenshots. Capture from Lovable dev server. |
| Customs preview/print page | COMMITTEE_DIRECTOR | Still needed — no screenshot found. Capture from Lovable dev server before Sprint 6.7. |
| Notification settings (per-user) | Any | Still needed — not found in screenshots. Capture from Lovable dev server. |

---

## Summary Table

| # | Track | Severity | Gap | Status |
|---|-------|----------|-----|--------|
| A1 | Design Tokens | **Critical** | Primary color: `#0066cc` vs `#0071e3` | Open |
| A2 | Design Tokens | **Critical** | Background: `#ffffff` vs `#f5f5f7` | Open |
| A3 | Design Tokens | **Major** | Cairo + Tajawal fonts missing | Open |
| A4 | Design Tokens | **Major** | No shadow/elevation token system | Open |
| A5 | Design Tokens | **Major** | Surface-container palette absent | Open |
| A6 | Design Tokens | **Major** | Semantic status color triplets missing | Open |
| A7 | Design Tokens | **Major** | Sidebar-specific color tokens absent | Open |
| A9 | Design Tokens | **Minor** | Button radius: 16px vs 12px | Open |
| B1 | Components | **Critical** | Sidebar collapse (72px ↔ 280px) missing | Open |
| B2 | Components | **Critical** | Header lacks backdrop-blur | Open |
| B4 | Components | **Critical** | VotingPanel: 6 members, tally, director controls | Open |
| B6 | Components | **Critical** | DocumentChecklist component MISSING | Open |
| B7-login | Components | **Closed** | Login RoleSwitcher | **CLOSED** |
| B7-app | Components | **Critical** | In-app role switcher for QA/demo | Open |
| B8 | Components | **Major** | LockedBanner missing 3 variants | Open |
| B9 | Components | **Major** | Icon system: need Lucide Vue | Open |
| B10 | Components | **Closed** | StatusBadge role filtering | **CLOSED** |
| C0 | Pages | **Critical** | BANK-ADMIN role entirely missing (10 pages) | Open |
| C1 | Pages | **Critical** | Login: two-column + OTP step missing | Open |
| C2 | Pages | **Major** | Dashboard visual parity per role | Open |
| C3 | Pages | **Critical** | Request detail: rail + tabs + voting panel parity | Open |
| C4 | Pages | **Major** | Settings: 6-tab structure | Open |
| C5 | Pages | **Major** | Audit: 3-tab structure + KPI cards | Open |
| C6 | Pages | **Major** | Profile page missing | Open |
| C7 | Pages | **Critical** | `/admin/cby-staff` MISSING | Open |
| C8 | Pages | **Critical** | `/admin/entities` MISSING | Open |
| C9 | Pages | **Critical** | `/admin/roles` MISSING | Open |
| C10 | Pages | **Major** | `/customs/[id]/print` — dedicated print page | Open |
| D1 | UX/Logic | **Critical** | OTP/MFA login step not implemented | Open |
| D2 | UX/Logic | **Critical** | In-app role switcher for stakeholder QA | Open |
| D3 | UX/Logic | **Closed** | DATA_ENTRY simplified status labels | **CLOSED** |
| D4 | UX/Logic | **Major** | Dark mode not implemented | Open |
| D5 | UX/Logic | **Major** | Customs print flow parity | Open |
| D6 | UX/Logic | **Major** | Permission-based nav item visibility | Open |
| D7 | UX/Logic | **Major** | Support claim blocking UI parity | Open |
| D8 | UX/Logic | **Major** | Voting tie-break notice | Open |
| D9 | UX/Logic | **Major** | 4-step request creation wizard | Open |
| E1 | Layout | **Critical** | Admin + BANK-ADMIN routes absent from sidebar nav | Open |
| E2 | Layout | **Critical** | Sidebar 264px fixed vs 280/72px collapsible | Open |
| E3 | Layout | **Major** | Max-width 1280px vs 1600px | Open |
| E4 | Layout | **Minor** | Responsive padding not reducing on tablet/mobile | Open |

**Open critical:** 17  
**Open major:** 16  
**Open minor:** 3  
**Closed:** 4

---

## Recommended Sprint Order

### Sprint 6.1 — Design Foundation *(~4 hours)*
> Every page fixed at once; unblocks all visual comparisons

1. Primary color `#0066cc`, background `#ffffff`
2. Load Cairo + Tajawal from Google Fonts; apply tier hierarchy
3. Add full elevation token system (`shadow-sm/md/lg/focus`)
4. Add surface-container palette (8 variants)
5. Add semantic status color triplets (text + bg + border)
6. Button radius 16px; modal radius 24px
7. Max-width 1600px

### Sprint 6.2 — AppShell & Layout Parity *(~6 hours)*
> Core structural UX PM sees first

8. Sidebar collapse/expand toggle (72px ↔ 280px), `localStorage` persistence
9. Header backdrop-blur (`bg-surface/80 backdrop-blur-md`)
10. Sidebar-specific color tokens, responsive padding
11. Add BANK-ADMIN and missing CBY_ADMIN routes to sidebar nav (role-gated)

### Sprint 6.3 — BANK-ADMIN Role *(~16 hours)*
> Entirely new role — 10 pages, cannot be skipped

12. Register BANK-ADMIN role in canonical enum (backend + frontend)
13. BANK-ADMIN dashboard (5 KPIs, line chart, quick actions, recent requests table)
14. BANK-ADMIN requests list (progress bars)
15. BANK-ADMIN request detail (3 tabs: المعلومات / الوثائق / الأطراف)
16. Merchant management: card grid layout, add/edit/suspend modals
17. Staff management: table with add/edit modals
18. 4-step request creation wizard (stepper component + acknowledgment checkbox step 4)
19. BANK-ADMIN-scoped reports and notifications pages

### Sprint 6.4 — Login & Auth *(~4 hours)*
> First stakeholder touchpoint

20. Login redesign: two-column layout (form + blue hero panel)
21. OTP step: 6-cell digit inputs, "تأكيد ودخول" flow
22. Wire MFA to backend OTP endpoint

### Sprint 6.5 — Missing CBY Admin Pages *(~10 hours)*

23. `/admin/cby-staff` — CBY system user CRUD
24. `/admin/entities` — Banks/entities CRUD
25. `/admin/roles` — Role definitions viewer
26. Settings page: 6-tab structure (سير العمل / البريد / الإشعارات / الأمن / عام / بيانات العرض)
27. Profile page: avatar + stats + recent activity + MFA toggle

### Sprint 6.6 — Request Detail & Voting Parity *(~8 hours)*

28. WorkflowTimeline visual comparison + pixel parity with prototype
29. DocumentChecklist.vue — stage-aware required/optional docs
30. VotingPanel: 6 named members, tally, director open/close, tie-break notice
31. AuditTimeline: confirm 3-tab structure (activity/duplicates/risk) on audit page

### Sprint 6.7 — Polish & Dark Mode *(~8 hours)*

32. Dark mode CSS variables + toggle in header
33. Lucide Vue (`lucide-vue-next`) — replace SidebarIcon, add icons throughout
34. LockedBanner 3-variant system
35. Modal overlay blur
36. Customs print page (`/customs/[id]/print`)
37. In-app RoleSwitcher for stakeholder demo mode

---

## Notes for Sprint Planning

- **BANK-ADMIN is a full-size role**, not a minor addition. It requires a backend schema change (adding role to enum) and 10+ new frontend pages. Plan accordingly in Sprint 6.3.
- **OTP login** requires backend coordination — the OTP endpoint and delivery mechanism must be designed before Sprint 6.4 frontend work.
- **Sprints 6.1–6.2** are independent and can be done in any order. Sprint 6.1 (token fixes) should land first so that all subsequent visual work happens on the correct token baseline.
- **Dark mode** (Sprint 6.7) can be deferred to post-launch if timeline is tight, but must be acknowledged to stakeholders.

---

*Report generated from analysis of `lovable/src/` + all 80+ screenshots in `lovable/screenshots/` vs `frontend/app/`. File paths relative to repo root.*
