# Yemen Flow Hub — Design System

> **Two-layer design system:**
> - This file (`DESIGN.md`) = **What** — token values, spacing philosophy, component specs, role dashboard layouts. The design authority for all tools.
> - `frontend/DESIGN.md` = **How** — Tailwind/Vue code patterns that implement this. Frontend AI reads both; this file wins on any conflict.
>
> **Source of truth:** This document is generated from and kept in sync with the shipped frontend code, primarily `frontend/app/assets/css/main.css` (token definitions) and `frontend/app/components/ui/` (shadcn-vue "new-york" component layer). Where a value here disagrees with `main.css`, `main.css` is correct and this file should be corrected.

## Overview

Yemen Flow Hub is a government banking workflow platform for the Central Bank of Yemen (CBY). The design system is institutional and Arabic-first: **clarity through restraint, confidence through density, authority through structure**.

The design is **RTL-first, workflow-centric, and desktop-focused** with responsive degradation at ≤ 600px. Unlike consumer product design, every surface prioritizes *operational density*: each screen is a queue or a form. There are no gradients, no glassmorphism, no decorative chrome, only what the transaction requires.

**Platform aesthetic:** Institutional clarity built on the shadcn-vue **new-york / neutral** base with Tailwind CSS v4. A single, themeable brand accent (default `#0066cc`, set by CBY Admin). A four-font Arabic-first system (IBM Plex Sans Arabic headings, sections, and body, Inter for Latin). Compact controls (32px default button height) tuned for operators processing dozens of requests. One subtle card shadow. Generous gaps between sections. The system rests on five foundations:

1. **Color:** A themeable brand accent plus a dual semantic system — muted institutional status colors (`--success`, `--warning`) and brighter iOS-style severity colors (`--severity-green/amber/red`), all OKLCH, all with automatic dark-mode derivations.
2. **Typography:** Four loaded families. IBM Plex Sans Arabic (headings, sections, and body) + Inter (Latin), Arabic-first fallback stack.
3. **Spacing:** 8px base unit; `gap-6` between dashboard sections; `p-4` compact cards / `p-6` full-page cards.
4. **Shapes:** One radius scale derived from `--radius: 0.625rem` (10px). `rounded-lg` = 10px on buttons and standard cards. No pill buttons; badges use `rounded-4xl`.
5. **Elevation:** One shadow at rest (`shadow`), one on hover (`shadow-md`). No stacked shadows.

**Key Characteristics:**
- Themeable: CBY Admin sets `--brand-color-base`; primary, ring, and links track it.
- Four theme modes: light, dark, high-contrast, dark.high-contrast.
- Compact density mode (`data-density='compact'`) shrinks tables, inputs, buttons, and cards.
- Dark mode is derived, not hand-tuned: severity/status colors are computed from light base values via `color-mix` in OKLCH.

---

## 1 — Color Palette

All colors are CSS custom properties defined in `frontend/app/assets/css/main.css`. **No hex value appears inline in component code, and raw Tailwind color scales (`text-gray-*`, `bg-blue-500`, `text-red-600`) are forbidden.** Always use the semantic token or its Tailwind utility (`text-foreground`, `bg-primary`, `text-[var(--severity-red)]`).

### Core shadcn-vue Surfaces (OKLCH, neutral base)

| Token | Light (OKLCH) | Dark (OKLCH) | Usage |
|---|---|---|---|
| `--background` | `oklch(1 0 0)` | `oklch(0.145 0 0)` | Page canvas |
| `--foreground` | `oklch(0.145 0 0)` | `oklch(0.985 0 0)` | Primary text (near-black, not pure) |
| `--card` / `--popover` | `oklch(1 0 0)` | `oklch(0.205 0 0)` | Card / popover surface |
| `--muted` | `oklch(0.97 0 0)` | `oklch(0.269 0 0)` | Alternate rows, subtle fills |
| `--muted-foreground` | `oklch(0.556 0 0)` | `oklch(0.708 0 0)` | Secondary text, disabled |
| `--secondary` | `oklch(0.97 0 0)` | `oklch(0.269 0 0)` | Secondary button surface |
| `--accent` | `oklch(0.97 0 0)` | `oklch(0.269 0 0)` | Hover highlight on ghost/menu items |
| `--border` / `--input` | `oklch(0.922 0 0)` | `oklch(1 0 0 / 10–15%)` | Dividers, card/input borders |
| `--ring` | tracks `--brand-color` | tracks `--brand-color` | Focus outline |
| `--destructive` | `oklch(0.577 0.245 27.3)` | `oklch(0.704 0.191 22.2)` | Destructive actions and errors |

### Brand Accent (themeable)

| Token | Value | Usage |
|---|---|---|
| `--brand-color-base` | `#0066cc` (default) | Source of truth, set by CBY Admin in settings |
| `--brand-color` | `var(--brand-color-base)`; dark = `color-mix(... 60%, white 40%)` | What the app consumes |
| `--primary` | tracks `--brand-color` | All primary CTAs, active nav, links |
| `--primary-foreground` | `oklch(0.985 0 0)` (white) | Text on primary |
| `--ring` | tracks `--brand-color` | Focus rings stay branded |

**The Themeable Brand Rule.** The brand accent is never hardcoded. CBY Admin sets `--brand-color-base` once; `--primary`, `--ring`, and links derive from it, and dark mode lifts its lightness automatically via `color-mix`. Never write `#0066cc` (or any literal accent) in a component, the only exception is fixed theme-preview thumbnails in settings.

### Dual Semantic System

The app ships **two parallel status vocabularies**. Pick by context.

**Institutional status (muted, formal)** — for workflow status text and badges:

| Token | Light | Dark (derived) | Usage |
|---|---|---|---|
| `--success` | `#1b5e20` | `color-mix(... 55%, white)` | Approved, completed |
| `--warning` | `#f57f17` | `color-mix(... 60%, white)` | At-risk, pending |
| `--info` / `--swift` | `#32ade6` | `color-mix(... 70%, white)` | SWIFT, informational |
| `--locked` | `#8e8e93` | `color-mix(... 60%, white)` | Immutable / locked states |
| `--voting` | `#5856d6` | `color-mix(... 60%, white)` | Executive voting sessions |

**Severity (brighter, iOS-derived)** — for action-required banners, severity emphasis, alert accents:

| Token | Light | Dark (derived) | Usage |
|---|---|---|---|
| `--severity-green` | `#34c759` | `color-mix(... 70%, white)` | Strong success emphasis |
| `--severity-amber` | `#ff9f0a` | `color-mix(... 70%, white)` | Action-required banners |
| `--severity-red` | `#ff3b30` | `color-mix(... 70%, white)` | High-severity error accents |

**Semantic UI aliases (triplets)** — text / surface tint / border, all OKLCH, each with a dark override:

| Role | Text | Surface tint | Border |
|---|---|---|---|
| Error | `--color-text-error` | `--color-surface-error` | `--color-border-error` |
| Success | `--color-text-success` | `--color-surface-success` | `--color-border-success` |
| Warning | `--color-text-warning` | `--color-surface-warning` | `--color-border-warning` |
| Subtle | `--color-text-subtle` (= muted-foreground) | `--color-surface-subtle` (= muted) | — |

### Sidebar Tokens

| Token | Light | Dark | Usage |
|---|---|---|---|
| `--sidebar` | `oklch(0.985 0 0)` | `oklch(0.205 0 0)` | Sidebar background |
| `--sidebar-foreground` | `oklch(0.145 0 0)` | `oklch(0.985 0 0)` | Sidebar text |
| `--sidebar-primary` | tracks neutral primary | `oklch(0.488 0.243 264)` | Active nav indicator |
| `--sidebar-accent` | `oklch(0.97 0 0)` | `oklch(0.269 0 0)` | Hover state |
| `--sidebar-border` | `oklch(0.922 0 0)` | `oklch(1 0 0 / 10%)` | Sidebar divider |

### Chart Tokens

Five chart colors (`--chart-1` … `--chart-5`) with distinct light/dark OKLCH values, used only on BANK_ADMIN and CBY_ADMIN analytics surfaces.

### Rules

- **No decoration via color.** Every colored element carries semantic meaning.
- **No color-only status.** Every status badge combines color + text + icon (`DESIGN.md §24`).
- **Border rule:** `--border` for passive dividers; `--ring` (brand) for active focus.
- **The Derived-Dark Rule.** Never hand-author a dark-mode hex for status/severity colors. Set the light base; dark mode is computed via `color-mix` in OKLCH so hue is preserved and lightness lifts. If you find yourself writing a `.dark` override for a severity color, you are duplicating what `color-mix` already does.

---

## 2 — Typography

Four font families load via Google Fonts (`display=swap`), imported at the top of `main.css`:

- **IBM Plex Sans Arabic** — page headlines and section headings (`font-heading`; auto-applied to `h1`–`h6`) subheadings, navigation labels (`font-section`) body copy, form fields, table content (`font-sans`, default)
- **Inter** — Latin strings and numbers in LTR context (via the fallback stack)

Fallback stack (Arabic-first): `'IBM Plex Sans Arabic', 'Inter', system-ui, -apple-system, sans-serif`.

### Hierarchy

Product UI uses a fixed rem scale, not fluid clamps. Density favors compact sizes; emphasis comes from weight and color, not large type.

| Role | Font | Size | Weight | Usage |
|---|---|---|---|---|
| Page headline | IBM Plex Sans Arabic | `text-2xl`–`text-3xl` | 600–700 | Dashboard / page title |
| Section heading | IBM Plex Sans Arabic | `text-lg`–`text-xl` | 600 | Card titles, form section headers |
| Subheading / nav | IBM Plex Sans Arabic | `text-sm` | 500–600 | Sidebar items, secondary headers |
| Body (default) | IBM Plex Sans Arabic | `text-sm` (14px) | 400 | Dashboard tables, dense UI |
| Body (form) | IBM Plex Sans Arabic | `text-base` (16px) | 400 | Form content, reading surfaces |
| Label / button | IBM Plex Sans Arabic | `text-sm` | 500 (`font-medium`) | Button text, form labels |
| Caption / badge | IBM Plex Sans Arabic | `text-xs` (12px) | 400–500 | Captions, badge text, table meta |

### Rules

- **The Three-Weight Rule.** Body ladder is 400 / 500 / 600. Avoid `font-bold` (700) on body text; reserve 700 for headings only.
- **No italics.** Emphasis uses weight (`font-medium`, `font-semibold`) or color, never slant.
- **Headings are IBM Plex Sans Arabic automatically.** `h1`–`h6` and `.cn-font-heading` map to `font-heading` in the base layer. Don't restate the family on every heading.
- **Compact body by default.** Dense operational surfaces (tables, queues) run at `text-sm`; only reading-oriented form surfaces step up to `text-base`.

---

## 3 — Layout & Spacing

### Spacing Scale (8px base)

| Tailwind | Value | Usage |
|---|---|---|
| `gap-1` / `p-1` | 4px | Icon-to-text gap (rare) |
| `gap-2` / `p-2` | 8px | Internal tight spacing |
| `gap-3` / `p-3` | 12px | Field spacing |
| `gap-4` / `p-4` | 16px | Compact queue card padding, dashboard section gaps |
| `gap-6` / `p-6` | 24px | Full-page card padding, section separation |

### Container & Grid

- **Content layout:** `.layout-boxed` caps at **1200px** (centered); `.layout-full` is unbounded. Chosen per surface, not globally fixed.
- **Card padding:** `p-4` (16px) for compact queue cards; `p-6` (24px) for full-page cards.
- **Section gap:** `gap-6` between dashboard sections.
- **Card grids:** `grid-cols-4` → `max-lg:grid-cols-2` → `max-md:grid-cols-1`.

### Sidebar & Header

- **Sidebar (expanded):** 280px, right-aligned (RTL).
- **Sidebar (collapsed):** 72px, icons only.
- **Nav item height:** ~48px; padding 8px horizontal, 12px vertical.

### Density Modes

A first-class `data-density='compact'` mode (set on `:root`) tightens the whole app:
- Table heads → 2rem tall, `text-xs`; cells → `0.375rem 0.5rem`, `text-[0.8125rem]`.
- Inputs / select triggers → 1.875rem (30px) tall.
- Default buttons → 1.875rem (30px) tall.
- Cards → reduced gap and vertical padding (0.75rem).

Default (comfortable) density uses the component sizes in §6–§7.

### Whitespace Philosophy

The workflow is the exhibit. Major dashboard sections are separated by `gap-6`; cards keep content ~16–24px from their edges. The platform is dense by design (operators process many requests), so whitespace clarifies grouping rather than creating drama.

---

## 4 — Border Radius Scale

One scale, derived from `--radius: 0.625rem` (10px) in `main.css`. **Never hardcode arbitrary `rounded-[Xpx]` values.**

| Token | Formula | Approx | Applied to |
|---|---|---|---|
| `rounded-sm` | `--radius * 0.6` | 6px | Tight utility elements |
| `rounded-md` | `--radius * 0.8` | 8px | Inputs, textareas, selects, small buttons |
| `rounded-lg` | `--radius` | 10px | **Buttons, standard cards** (default) |
| `rounded-xl` | `--radius * 1.4` | 14px | Prominent cards, larger containers |
| `rounded-2xl` | `--radius * 1.8` | 18px | Quick-action tiles |
| `rounded-3xl` | `--radius * 2.2` | 22px | Modals, dialogs (shadcn internal) |
| `rounded-4xl` | `--radius * 2.6` | 26px | **Badges, status chips** |
| `rounded-full` | — | 9999px | Avatars |

### Rules

- **Buttons are `rounded-lg` (10px), not pill.** This is the shipped shadcn-vue new-york default. Do not pill primary buttons.
- **Badges use `rounded-4xl`**, giving a near-pill chip without a literal 9999px radius.
- **Cards `rounded-lg`/`rounded-xl`; modals are handled by shadcn Dialog internally.**

---

## 5 — Elevation & Shadows

Two shadow levels only. No stacked shadows. Depth comes mostly from surface color change (`background` → `card` → `muted`).

| Component | Class |
|---|---|
| Card (default) | `shadow` |
| Card (hover) | `shadow-md` |
| Modal / Sheet / Popover | handled by shadcn Dialog/Sheet internally |

**The Two-Level Rule.** A surface is flat or it has the single resting `shadow`; on hover it lifts to `shadow-md`. Nothing stacks. If a design needs more than two elevation states, the problem is layout, not shadow.

---

## 6 — Buttons

Buttons use the shadcn-vue new-york `buttonVariants`. They are compact and `rounded-lg`, tuned for dense operator workflows. **State/workflow tokens (voting, severity, locked) are never button colors** — buttons use only the variants below.

### Variants

| Variant | Treatment |
|---|---|
| `default` | `bg-primary text-primary-foreground`; hover `bg-primary/80` |
| `outline` | `border-border bg-background`; hover `bg-muted` |
| `secondary` | `bg-secondary text-secondary-foreground`; hover `bg-secondary/80` |
| `ghost` | transparent; hover `bg-muted text-foreground` |
| `destructive` | **tinted, not solid**: `bg-destructive/10 text-destructive`; hover `bg-destructive/20` |
| `link` | `text-primary` with underline on hover |

### Sizes

| Size | Height | Radius | Notes |
|---|---|---|---|
| `default` | `h-8` (32px) | `rounded-lg` | Standard action |
| `sm` | `h-7` (28px) | `rounded-md` (≤12px) | Row actions, secondary |
| `xs` | `h-6` (24px) | `rounded-md` (≤10px) | Inline / compact |
| `lg` | `h-9` (36px) | `rounded-lg` | Prominent CTAs |
| `icon` | `size-8` (32px) | `rounded-lg` | Icon-only (always `aria-label`) |
| `icon-sm` / `icon-xs` / `icon-lg` | 28 / 24 / 36px | scaled | Icon-only at matching scale |

### Interaction

- **Focus:** `focus-visible:ring-3 ring-ring/50` + `border-ring` (brand).
- **Active:** `active:translate-y-px` (subtle press; not `scale`).
- **Disabled:** `pointer-events-none opacity-50`.
- **Transition:** `transition-all`, ~150ms.

### Rules

- **No pill buttons.** Buttons are `rounded-lg` (10px).
- **Destructive is tinted.** The destructive variant is a soft `bg-destructive/10`, not a solid red fill.
- **One clear CTA per surface** (Cancel may sit alongside a primary).
- **State colors are not button colors.** `--voting`, `--swift`, `--severity-*`, `--locked` are for badges, banners, status text, icons, and borders, never `class="bg-[var(--voting)]"` on a button.

---

## 7 — Form Inputs & Controls

### Text Input / Textarea / Select

- **Background:** `bg-input/30`, **border:** `border-input`, **radius:** `rounded-md` (8px).
- **Focus:** `focus-visible:border-ring focus-visible:ring-ring/50` (brand ring).
- **Disabled:** reduced opacity, `muted` treatment.
- **Error:** `aria-invalid` drives `border-destructive` + `ring-destructive/20`.
- **Compact density:** height collapses to 1.875rem (30px).

### Wizard / Stepper

Uses shadcn `Stepper` (`StepperItem` / `StepperTrigger` / `StepperIndicator` / `StepperSeparator`):
- Completed → filled indicator; active → brand ring; future → muted outline.

### Search

`InputGroup` + `InputGroupAddon` with a leading `Search` icon; trailing clear button via `InputGroupButton`.

---

## 8 — Sidebar & Navigation

### Container

- **Width:** 280px expanded / 72px collapsed (icons only).
- **Background:** `--sidebar`; **border:** `--sidebar-border` on the inline-end edge (RTL: left edge).
- **Header:** CBY logo, ~56px.

### Navigation Items

- **Font:** Tajawal (`font-section`), `text-sm`, 500–600.
- **Icon:** 20px, leading (RTL: trailing).
- **Idle:** `--sidebar-foreground`.
- **Hover:** `--sidebar-accent` background.
- **Active:** `--sidebar-primary` indicator (solid brand by default; see `AppSidebar.vue` for `operational` vs alternate active styles).

### User Section

Avatar + name + role at the sidebar bottom with a dropdown (profile, settings, theme toggle, logout).

---

## 9 — Cards & Container Surfaces

### Standard Card

- **Surface:** `bg-card text-card-foreground`, `ring-border` hairline.
- **Radius:** `rounded-lg`/`rounded-xl`.
- **Padding:** `p-4` (compact) or `p-6` (full-page).
- **Shadow:** `shadow` at rest, `shadow-md` on hover.
- Cards default to `border-0` with the `ring-border` hairline doing the edge (see `Card.vue`).

### KPI / Stat Card

Clickable `Card role="button" tabindex="0"` that navigates to a filtered list. Icon tile (`size-9`, tinted with a severity/status token at /10) + value (`text-2xl font-semibold`) + label (`text-xs text-muted-foreground`). Keyboard-activatable (Enter / Space).

### Quick-Action Tile

Multi-line icon + title + description → `Card role="button"`, **not** `Button` (Button can't hold stacked slot content). Primary tile uses `bg-primary text-primary-foreground`.

### List Item / Table Row

Row height ~52px (comfortable) or compressed under compact density. Hover `bg-muted`. No zebra striping.

### Modal / Dialog

shadcn `Dialog` / `AlertDialog`. Radius and backdrop handled internally. **`AlertDialog` is mandatory for destructive confirmations** (reject, delete, revoke claim); `Dialog` is for forms and non-destructive modals.

**The No-Nested-Card Rule.** A card never contains another card. Use `Separator`, spacing, or a muted sub-surface instead.

---

## 10 — Status Badge System

Badges use shadcn `Badge` (`badgeVariants`):

- **Shape:** `rounded-4xl` (chip), height `h-5` (20px), padding `px-2 py-0.5`.
- **Font:** `text-xs font-medium`.
- **Variants:** `default` (brand), `secondary` (muted), `destructive` (red), `outline`.
- **Custom semantic chips:** when `StatusBadge` is not appropriate, tint with a token at /10 plus a /30 border, e.g. voting: `bg-[var(--voting)]/10 text-[var(--voting)] border border-[var(--voting)]/30`.

Always prefer the role-aware `<StatusBadge :status :role />` over hand-built chips.

---

## 11 — Workflow Timeline & Stage Rail

The request detail page shows the workflow progression (see `WorkflowProgress.vue` / `WorkflowTimeline.vue`):

- **Completed stage:** filled brand/success indicator.
- **Current stage:** brand ring.
- **Future stage:** muted outline.
- **Terminal / locked stage:** `--locked` treatment with lock icon.
- **Connector lines:** 2px, success/brand (past) or muted (future).

The rail is informational and non-interactive. Workflow actions happen via the ActionsPanel.

---

## 12 — Tables & Data Grids

shadcn `Table` primitives, or TanStack `useVueTable` + shadcn primitives for sortable/filterable/paginated grids (reference: `RequestsDataTable.vue`).

- **Header:** `text-muted-foreground`, bottom border.
- **Row:** `text-sm`; hover `bg-muted`; no zebra striping.
- **Selected row:** brand inset accent.
- **Empty state:** `TableEmpty` (in-table) or `Empty` component, phrased positively for healthy empty queues.
- **Compact density:** smaller head/cell padding and font (see §3).
- **Mobile:** horizontal scroll inside an `overflow-x-auto` wrapper; never vertical-truncate.

---

## 13 — Request Detail Page Layout

Two-column layout (see `pages/requests/[id]/index.vue`, `WorkflowProgress.vue`):

- **Main column:** Tabs — {المعلومات} / {الوثائق} / {الأطراف} / {التصويت}
  - المعلومات: request fields (read-only or editable by role).
  - الوثائق: document checklist with required-doc indicators; PDF-only.
  - الأطراف: actors (submitted_by, reviewed_by, …) with timestamps.
  - التصويت: voting panel when status is `EXECUTIVE_VOTING_OPEN` / `EXECUTIVE_VOTING_CLOSED`.
- **Side column:** workflow progress rail + ActionsPanel (role- and status-dependent actions).

ActionsPanel renders only the actions valid for the current status and role: Submit, Approve, Reject, Return, Claim Support Review, Open/Close Voting, etc.

---

## 14 — Notification System

### Bell Dropdown

~320px wide, last few notifications, unread dot, relative timestamps, "mark all read" action.

### Notifications Page

Table: unread indicator / timestamp / message / action. Unread rows carry a subtle brand emphasis. Filter tabs (All / Unread). Empty state via `Empty`.

---

## 15 — Dashboard Layouts (Role-Specific)

Every role dashboard leads with its **primary operational queue**; supporting numbers are secondary. Charts appear only on BANK_ADMIN and CBY_ADMIN.

- **DATA_ENTRY:** KPI strip (created / awaiting review / approved / pending FX) + quick actions + amber action banner (pending docs) + My Requests and Recent Approvals queues.
- **BANK_REVIEWER:** KPI strip (pending review / today / quota / approval rate) + Awaiting Review queue. No charts.
- **SUPPORT_COMMITTEE:** KPI strip (pending / claimed / resolved / avg time) + claim-enabled queue. No charts.
- **SWIFT_OFFICER:** KPI strip (pending upload / uploaded / failed / monthly) + Awaiting Upload queue + upload action. No charts.
- **COMMITTEE_DIRECTOR:** KPI strip (pending votes / open sessions / avg vote time) + voting queue. No charts.
- **EXECUTIVE_MEMBER:** KPI strip (assigned / voted / pending) + My Voting Sessions. No charts.
- **BANK_ADMIN:** KPI strip (5) + quick actions + **line chart** (monthly volume) + **bar chart** (by category) + recent requests. Charts use `--chart-*`.
- **CBY_ADMIN:** KPI strip (4) + quick actions (6) + **donut / line / bar / heatmap** analytics + widget row.

**The Operational-First Rule.** No shared analytics dashboard visible to all roles equally. No charts or vanity metrics on operational dashboards (only BANK_ADMIN and CBY_ADMIN earn charts).

---

## 16 — Merchants Page (BANK_ADMIN)

Card grid (2-col → 1-col). Each card: merchant name / status chip (نشط / مُوقَف) / actions. Add via `Dialog` form. Suspend via `AlertDialog`.

---

## 17 — Roles & Users Page (CBY_ADMIN)

Table: Username / Email / Role / Status / Last Login / Actions. Add via `Dialog`. Row actions via `DropdownMenu`. Status badge (active / inactive).

---

## 18 — Audit & Compliance Page (CBY_ADMIN)

KPI strip + tabs: Activity log (table), Duplicate invoices (with مكرر badge), Risk indicators (severity card grid). Uses `--severity-*` for risk emphasis.

---

## 19 — Settings (CBY_ADMIN)

Multi-tab settings: workflow, email (SMTP), notifications, security (MFA, lockout, session timeout, demo mode), appearance, general. The appearance tab includes **fixed theme-preview thumbnails** that legitimately use literal hex (they must depict each theme regardless of the active theme) and the brand-color picker that sets `--brand-color-base`.

---

## 20 — Profile (All Roles)

Two-column: editable form (name, email, phone; role read-only) + profile card (avatar, role badge, dates, MFA). Recent activity list.

---

## 21 — Authentication Pages

### Login

Two-column: form + brand hero panel (`bg-primary`, white text, CBY logo). Saved-account cards for quick re-login. Email + password + remember-me + login. Forgot-password link.

### OTP / MFA

Six OTP cells, "enter the 6-digit code" prompt, resend countdown. `MfaService` backs verification (Redis).

### Demo Mode

When the backend `demoMode` flag is on, a `RoleSwitcher` appears in the user menu for testing all roles. Hidden in production.

---

## 22 — Theme Modes

Four modes, all driven by CSS custom properties; no per-component dark logic.

| Mode | Class on `<html>` | Notes |
|---|---|---|
| Light | (none) | Default |
| Dark | `.dark` | Surfaces darken; brand + severity colors derive via `color-mix` |
| High contrast | `.high-contrast` | Pure black/white text, heavy borders, for accessibility |
| Dark + high contrast | `.dark.high-contrast` | White-on-black, white borders |

**Toggle:** sun/moon in the user menu, with a circular `view-transition` reveal (disabled under `prefers-reduced-motion`). Preference persists to `localStorage`.

---

## 23 — Motion & Transitions

State-conveying motion only. No decorative choreography, no bounce, no parallax.

| Transition | Approx | Notes |
|---|---|---|
| Button / control state | ~150ms | `transition-all` |
| Card elevation | ~200ms | `shadow` → `shadow-md` |
| Dark/light toggle | 500ms | Circular `view-transition` clip-path reveal, `cubic-bezier(0.4,0,0.2,1)` |
| Focus ring | ~150ms | Brand ring appearance |

`prefers-reduced-motion: reduce` disables the view-transition animation entirely.

---

## 24 — Accessibility

- **Contrast:** target 7:1 for primary text, 4.5:1 for secondary; high-contrast modes exceed this.
- **Focus ring:** always visible, brand `--ring`, 3px.
- **Status is never color-only:** always color + text + icon.
- **ARIA:** icons, badges, and interactive elements carry descriptive labels (icon-only buttons require `aria-label`).
- **Keyboard:** full navigation; clickable KPI cards are Enter/Space-activatable.
- **RTL:** `dir="rtl"` on `<html>`; layout mirrors via inline-start/end utilities (`border-s-*`, `ps-*`, `me-*`).
- **Touch targets:** comfortable controls meet target sizes; compact density is opt-in for power users at desks.

---

## 25 — Responsive Behavior

Desktop-first with structural (not fluid-typographic) responsiveness.

| Breakpoint | Behavior |
|---|---|
| ≤ 419px | Single column; sidebar collapses to hamburger; header condenses |
| 420–640px | Single column; reduced padding |
| 641–834px | Sidebar icons-only; content padding tightens |
| 834–1023px | Sidebar expands; 2-column grids possible |
| ≥ 1024px | Full layout |

Collapsing: card grids `grid-cols-4` → `max-lg:grid-cols-2` → `max-md:grid-cols-1`; request detail two-column → stacked; wide tables scroll horizontally inside `overflow-x-auto`.

---

## 26 — Design Do's and Don'ts

### Do
- **Do** reference colors via tokens / Tailwind utilities (`text-foreground`, `bg-primary`, `text-[var(--severity-red)]`).
- **Do** set the brand once via `--brand-color-base`; let `--primary`, `--ring`, and links track it.
- **Do** set light base values for status/severity colors and let dark mode derive via `color-mix`.
- **Do** keep buttons `rounded-lg` (10px) and badges `rounded-4xl`.
- **Do** use the single resting `shadow`, lifting to `shadow-md` on hover.
- **Do** use `active:translate-y-px` as the button press feedback.
- **Do** use shadcn-vue components (`Button`, `Table`, `Badge`, `Alert`, `Skeleton`, `Empty`, `AlertDialog`) instead of raw HTML.
- **Do** lead every role dashboard with its operational queue.
- **Do** mirror layout for RTL with inline-start/end utilities.

### Don't
- **Don't** hardcode hex in components (the only exception: fixed theme-preview thumbnails in settings).
- **Don't** use raw Tailwind color scales (`text-red-600`, `bg-indigo-50`, `text-gray-500`).
- **Don't** pill buttons; the new-york default is `rounded-lg`.
- **Don't** hand-author `.dark` overrides for severity/status colors; `color-mix` already derives them.
- **Don't** use workflow/state tokens (`--voting`, `--swift`, `--severity-*`, `--locked`) as button background colors.
- **Don't** stack shadows or invent a third elevation level.
- **Don't** nest a card inside a card.
- **Don't** put charts, KPIs, or shared analytics on operational dashboards (only BANK_ADMIN and CBY_ADMIN earn charts).
- **Don't** use gradients, glassmorphism, or decorative animation; this is an institutional tool, not consumer fintech.
- **Don't** use color alone to communicate state; always add text or icon.

---

## Summary: Three Layers

1. **shadcn-vue base (new-york / neutral):** semantic CSS custom properties (`--primary`, `--border`, `--ring`, …) and primitive components in `frontend/app/components/ui/`. Untouched.
2. **This document + `main.css`:** token values, the themeable brand, the dual semantic system, dark/high-contrast derivations, density, and the layout/component specs above. The source of truth.
3. **Custom component layer:** page- and role-specific UI built from layer-2 tokens. No raw Tailwind color scales, no hardcoded hex. Change a token in `main.css` → the whole app updates.
