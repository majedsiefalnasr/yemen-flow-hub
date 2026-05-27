# Yemen Flow Hub — Apple-Inspired Design System

> **Two-layer design system:**
> - This file (`DESIGN.md`) = **What** — token values, spacing philosophy, component specs, role dashboard layouts. The design authority for all tools.
> - `frontend/DESIGN.md.md` = **How** — Tailwind/Vue code patterns that implement this. Frontend AI reads both; this file wins on any conflict.

## Overview

Yemen Flow Hub is a government banking workflow platform for the Central Bank of Yemen (CBY). The design system follows Apple's design philosophy adapted for institutional, Arabic-first context: **clarity through restraint, confidence through simplicity, authority through typography**.

The design is **RTL-first, workflow-centric, and desktop-focused** with responsive degradation at ≤ 600px. Unlike consumer product design, every surface prioritizes *content density* for workflow context. There are no gradients, no glassmorphism, no decorative chrome — only what the transaction requires.

**Platform aesthetic:** Institutional clarity. Single accent color (#0066cc). Two-font system (Inter for Latin, IBM Plex Sans Arabic for Arabic). Pill-shaped primary CTAs. Minimal elevation (card shadows only). Generous whitespace between major sections. 17px body text. Tight headline tracking. The entire system rests on five foundations:

1. **Color:** Single primary blue #0066cc, near-black text #1d1d1f, white/parchment surfaces, semantic status triplets
2. **Typography:** Inter Variable (headings) + IBM Plex Sans Arabic (body), 17px base body, tight tracking on display sizes
3. **Spacing:** 8px base unit, 80px major section breaks, 24px card padding
4. **Shapes:** Pill primary buttons (9999px), 18px card radius, 8px utility buttons, 24px modals
5. **Elevation:** One subtle card shadow only — no stacked shadows, no backdrop blur except sticky bars

---

## 1 — Color Palette

Yemen Flow Hub uses Apple's minimal color vocabulary adapted for institutional authority. Every token has a clear purpose. No hex value appears inline in code — all colors are CSS custom properties referenced as `var(--color-name)`.

### Core Surfaces

| Token | Hex | Usage | Light mode | Dark mode |
|---|---|---|---|---|
| `--background` | #ffffff | Page canvas | oklch(1 0 0) | oklch(0.09 0.012 258) |
| `--foreground` | #1d1d1f | Primary text | oklch(0.141 0 0) | oklch(0.94 0 0) |
| `--card` | #ffffff | Card background | oklch(1 0 0) | oklch(0.13 0.015 258) |
| `--card-foreground` | #1d1d1f | Card text | oklch(0.141 0 0) | oklch(0.94 0 0) |
| `--muted` | #f5f5f7 | Alternate rows | oklch(0.961 0 286) | oklch(0.16 0.015 258) |
| `--muted-foreground` | #7a7a7a | Secondary text | oklch(0.48 0 0) | oklch(0.68 0 0) |
| `--border` | #cccccc | Divider lines | oklch(0.8 0 0) | oklch(0.22 0.015 258) |
| `--input` | #cccccc | Input border | oklch(0.8 0 0) | oklch(0.22 0.015 258) |
| `--ring` | #0066cc | Focus outline | oklch(0.486 0.203 258.7) | oklch(0.68 0.15 258.7) |

### Primary Accent

| Token | Hex | Usage |
|---|---|---|
| `--primary` | #0066cc | All interactive elements |
| `--primary-foreground` | #ffffff | Text on primary background |

### Semantic Status Triplets (text / background / border)

| Status | Text | Background | Border | Usage |
|---|---|---|---|---|
| Success | #1b5e20 | #f1f8f4 | #c8e6c9 | Approved, completed |
| Error | #c62828 | #ffebee | #ffcdd2 | Rejected, failed |
| Warning | #f57f17 | #fff8e1 | #ffe082 | At-risk, pending |
| Info | #0d47a1 | #e3f2fd | #bbdefb | Informational |
| Voting | #5856d6 | rgba(88,86,214,0.08) | rgba(88,86,214,0.25) | Executive voting |
| SWIFT | #32ade6 | rgba(50,173,230,0.08) | rgba(50,173,230,0.25) | SWIFT upload |
| Locked | #8e8e93 | #f5f5f5 | #d1d1d6 | Immutable state |

### Sidebar-Specific Tokens

| Token | Light | Dark | Usage |
|---|---|---|---|
| `--sidebar` | #ffffff | #111827 | Sidebar background |
| `--sidebar-foreground` | #1d1d1f | #e8eaed | Sidebar text |
| `--sidebar-primary` | #0066cc | #4da6ff | Active nav item |
| `--sidebar-accent` | #e3f2fd | #1a3a5c | Hover state |
| `--sidebar-border` | #cccccc | #2d3748 | Sidebar divider |

### Rules

- **No decoration via color.** Every colored element has semantic meaning.
- **Contrast minimum:** 7:1 for primary text on background; 4.5:1 for secondary.
- **No color-only status.** Every status badge combines color + text + icon.
- **Border rule:** Use `--border` (#cccccc) for passive dividers only. Use `--primary` for active focus rings.

---

## 2 — Typography

Yemen Flow Hub uses two fonts:
- **Inter Variable** — Latin text and headings, screen-optimized
- **IBM Plex Sans Arabic** — Arabic body copy, RTL-optimized

Fallback stack: `IBM Plex Sans Arabic, Inter Variable, system-ui, -apple-system, sans-serif` (Arabic-first, reflecting RTL default).

### Typographic Hierarchy

| Token | Font | Size | Weight | Line Height | Letter Spacing | Usage |
|---|---|---|---|---|---|
| `display` | Inter | 56px | 600 | 68px | -0.02em | Page headline (CBY Admin overview) |
| `headline-lg` | Inter | 40px | 600 | 48px | -0.01em | Section headline |
| `headline-md` | Inter | 28px | 600 | 36px | 0 | Subsection header |
| `title-lg` | IBM Plex Sans Arabic | 20px | 700 | 28px | 0 | Card title, form section |
| `body-lg` | IBM Plex Sans Arabic | 18px | 400 | 28px | 0.02em | Large body (queue lists) |
| `body` | IBM Plex Sans Arabic | 17px | 400 | 26px | 0.02em | Default paragraph text |
| `label-md` | IBM Plex Sans Arabic | 14px | 600 | 20px | 0.01em | Button text, form labels |
| `label-sm` | IBM Plex Sans Arabic | 12px | 500 | 16px | 0.03em | Captions, badges |
| `caption` | IBM Plex Sans Arabic | 12px | 400 | 16px | 0.02em | Secondary captions |
| `fine-print` | IBM Plex Sans Arabic | 11px | 400 | 16px | 0 | Footer copy, disclaimers |

### Rules

- **Headline letter-spacing.** Display, headline-lg, and headline-md use negative tracking to create Apple "tight" headline feel.
- **Body at 17px, not 16px.** The extra pixel gives intentional reading pace.
- **Weight 600 for labels and small emphasis.** Weight 700 is absent except where buttons need extra weight.
- **No italics.** Emphasis uses weight (400 → 600) or color, never slant.
- **Font loading:** Both fonts loaded via Google Fonts with `display=swap`.

---

## 3 — Layout & Spacing

Spacing reflects Apple's principle: **generous whitespace makes content breathe**.

### Spacing Scale

| Token | Value | Usage |
|---|---|---|
| `xs` | 4px | Icon-to-text gap (rare) |
| `sm` | 8px | Internal card spacing |
| `md` | 12px | Form field vertical spacing |
| `lg` | 24px | Card padding, section gutters |
| `xl` | 40px | Major section separation |
| `2xl` | 64px | Hero-level separation |

### Container & Grid

- **Max content width:** 1600px, centered with padding
- **Grid:** 12-column base, gutters 24px
- **Container padding:** 24px desktop / 16px tablet / 12px mobile
- **Card padding:** Always 24px (lg)
- **Section vertical spacing:** 64px top/bottom between major sections

### Sidebar & Header

- **Sidebar width (expanded):** 280px, right-aligned (RTL)
- **Sidebar width (collapsed):** 72px, icons only
- **Header height:** 56px fixed
- **Nav item padding:** 8px horizontal (sm), 12px vertical (md)

### Whitespace Philosophy

Like Apple's museum gallery principle: *the workflow is the exhibit*. Every major section begins with 64px of breathing room above its headline. Cards don't feel crowded; the nearest content is always 24px away. Modals and popovers follow the same rule: 40px padding between content and modal edge.

---

## 4 — Border Radius Scale

Radius follows Apple's grammar: pill for actions, larger radius for containers, smaller radius for utility.

| Token | Value | Applied to |
|---|---|---|
| `sm` | 8px | Utility buttons, nav items, small badges |
| `md` | 12px | Form inputs, selects, small modals |
| `lg` | 18px | Standard cards, buttons, larger containers |
| `xl` | 24px | Modals, large feature cards, dialogs |
| `pill` | 9999px | Primary CTAs, status badges, search inputs |

### Rules

- **Primary buttons are always pill-shaped.** The 9999px radius is the visual "click me" signal.
- **Cards use `lg` (18px), modals use `xl` (24px).**
- **Utility buttons stay compact at `sm` (8px).**
- **Never use radius < 8px.** Minimum is 8px for readability and touch target.

---

## 5 — Elevation & Shadows

Yemen Flow Hub uses **one shadow only**: the card shadow, `0 1px 3px rgba(0, 0, 0, 0.08)`. No stacking shadows.

| Component | Shadow |
|---|---|
| Card (default) | 0 1px 3px rgba(0, 0, 0, 0.08) |
| Card (hover) | 0 4px 12px rgba(0, 0, 0, 0.10) |
| Modal / Popover | 0 16px 40px rgba(0, 0, 0, 0.12) |
| Sticky bar (AppHeader) | backdrop-filter: blur(4px) only |

### Philosophy

Elevation comes from *surface color change* (white → parchment → muted), not shadows. Shadows appear only where a card hovers above content or a popover floats. The single drop-shadow on cards gives them weight without distracting from content.

---

## 6 — Buttons

Buttons follow Apple's two-button grammar: pill for primary actions, rounded-rect for utility.

### Button Variants

| Variant | Background | Text | Border | Height | Padding | Radius | Usage |
|---|---|---|---|---|---|---|---|
| **primary** | #0066cc | white | none | 44px | 16–24px | pill | Main CTA |
| **primary-hover** | #0052a3 | white | none | 44px | 16–24px | pill | Hover state |
| **secondary** | transparent | #0066cc | 1px #0066cc | 44px | 16–24px | pill | Alternative CTA |
| **ghost** | transparent | #0066cc | none | 40px | 12–20px | sm | Tertiary action |
| **destructive** | #d32f2f | white | none | 44px | 16–24px | pill | High-risk action |
| **icon** | transparent | #7a7a7a | none | 40px | 8px | sm | Icon-only buttons |
| **disabled** | #e9ecef | #9aa0a6 | none | 44px | 16–24px | pill | Disabled state |

### Typography & Interaction

- **Label font:** label-md (14px, 600 weight)
- **Hover:** Slightly darker bg
- **Focus:** Ring 2px solid #0066cc with 0.2 alpha outer ring
- **Active/press:** Scale 0.95 (micro-interaction)
- **Transition:** 150ms ease all

### Rules

- **Pill is action, rectangle is utility.** Primary buttons are always pill. Secondary buttons are pill. Ghost/icon buttons use `sm` (8px) radius.
- **Button size matters.** 44px tall (mobile-friendly), never smaller than 40px.
- **Never nest buttons.** One clear CTA per surface (except Cancel alongside a primary).
- **Disabled state is clear.** Muted bg + muted text, always lower contrast.

---

## 7 — Form Inputs & Controls

### Text Input / Textarea / Select

- **Height:** 44px (touch target minimum)
- **Background:** White (#ffffff)
- **Border:** 1px #cccccc, `border-border` class
- **Radius:** md (12px)
- **Padding:** 12px horizontal, 12px vertical
- **Font:** body (17px)
- **Focus:** Border #0066cc, `box-shadow: 0 0 0 3px rgba(0,102,204,0.1)`
- **Placeholder:** Muted foreground (#7a7a7a)
- **Disabled:** Bg #e9ecef, text #9aa0a6, border #d0d0d0
- **Error:** Border #d32f2f, bg white (no tint)

### Search Input

- **Special case:** Pill-shaped (rounded-pill, 24px height)
- **Icon position:** Leading icon at 14px opacity, muted color
- **Clear button:** Icon button on trailing side

### Multi-Select / Tags Input

- **Chips:** Pill-shaped (rounded-pill), 32px height, padding 8px 12px
- **Remove icon:** Hover action on each chip
- **Background:** #f5f5f7 (muted), dark text
- **Border:** 1px #d0d0d0

### Wizard / Step Indicator

Four-step horizontal stepper with circular step nodes:
- **Completed step:** Green checkmark in circle
- **Active step:** Blue circle with outer ring
- **Future step:** Gray outline circle
- **Skipped/locked:** Gray circle with lock icon
- **Connector lines:** 2px between steps, gray (future) or green (completed)

---

## 8 — Sidebar & Navigation

### Sidebar Container

- **Width (expanded):** 280px
- **Width (collapsed):** 72px (icons only)
- **Background:** White (sidebar) / #111827 (dark)
- **Border:** 1px #cccccc right border (rtl: left border in RTL)
- **Header:** 56px, white bg, centered CBY logo (40px icon)
- **Collapse/expand toggle:** Bottom of sidebar, chevron icon

### Navigation Items

- **Height:** 48px
- **Padding:** 8px horizontal (sm), 12px vertical (md)
- **Font:** label-md (14px, 600)
- **Icon:** 20px, left of text (rtl: right)
- **Idle state:** Muted text (#7a7a7a)
- **Hover state:** Bg #f5f5f7 (muted), text #0066cc
- **Active state:** Bg #0066cc (primary), text white, full-width pill with rounded-lg (18px)

### User Section

- **Position:** Bottom of sidebar
- **Content:** Avatar (40px, rounded-full) + name + role (label-sm) + dropdown chevron
- **Dropdown:** Profile, settings, logout
- **Avatar bg:** Primary (#0066cc)

---

## 9 — Cards & Container Surfaces

### Standard Card

- **Background:** White / dark-mode card bg
- **Border:** 1px #cccccc
- **Radius:** lg (18px)
- **Padding:** 24px (lg)
- **Shadow:** 0 1px 3px rgba(0,0,0,0.08)
- **Hover shadow:** 0 4px 12px rgba(0,0,0,0.10), 200ms transition

### Stat Card (KPI)

- **Grid layout:** 4 columns (desktop), responsive to 2 (tablet), 1 (mobile)
- **Height:** 180px
- **Content:** Top-aligned icon (40px) + number (headline-md) + label (label-sm)
- **Bg:** White, same card styling as standard

### List Item Card

- **Height:** 52px (table row height)
- **Padding:** 12px 16px
- **Hover:** Bg #f5f5f7
- **Selected:** Inset 2px #0066cc box-shadow + border
- **Border:** 1px #cccccc between rows, no outer border

### Modal / Dialog

- **Background:** White / dark-mode bg
- **Radius:** xl (24px)
- **Padding:** 40px
- **Shadow:** 0 16px 40px rgba(0,0,0,0.12)
- **Backdrop:** rgba(12,18,26,0.4) with blur(4px)
- **Z-index:** 40+

---

## 10 — Status Badge System

Badges communicate state at a glance using **consistent styling**:

- **Shape:** Pill (rounded-pill / 9999px)
- **Height:** 24px
- **Padding:** 4px 12px
- **Font:** label-sm (12px, 500)
- **Border:** 1px, semantic color at ~50% opacity
- **Text:** Semantic status color (success, error, warning, etc.)
- **Bg:** Semantic status bg (10% opacity of status color)

**Examples:**
- Success: Text #1b5e20, bg #f1f8f4, border #c8e6c9
- Error: Text #c62828, bg #ffebee, border #ffcdd2
- Locked: Text #8e8e93, bg #f5f5f5, border #d1d1d6

---

## 11 — Workflow Timeline & Stage Rail

The right panel of the request detail shows an 18-stage vertical rail:

- **Completed stage:** Green filled checkmark in circle
- **Current stage:** Blue circle with outer ring
- **Future stage:** Gray outline circle
- **Terminal/locked stage:** Gray circle with lock icon
- **Skipped stage:** Gray dashed circle
- **Connector lines:** 2px line between stages, green (past) or gray (future)

The rail is non-interactive; it's purely informational. The main workflow interaction happens via ActionsPanel buttons on the left.

---

## 12 — Tables & Data Grids

- **Background:** White / dark-mode card
- **Header row:** label-md, text #7a7a7a, border-bottom #cccccc
- **Data row:** body-lg, text #1d1d1f
- **Row height:** 52px
- **Cell padding:** 16px horizontal, 12px vertical
- **Border:** 1px #cccccc between rows, no outer border
- **Hover row:** Bg #f5f5f7
- **Selected row:** Inset left 2px #0066cc box-shadow
- **Striping:** No zebra striping (modern design avoids it)
- **Pagination:** Previous/Next buttons + page count, centered below table

### Sorting & Filtering

- **Sort indicator:** Icon (up/down arrow) in header cell, muted until active
- **Active sort:** #0066cc arrow, reverse on click
- **Filter bar:** Horizontal strip above table, chips for active filters, clear button right-aligned

---

## 13 — Request Detail Page Layout

The request detail page uses a **65/35 split**:

- **Left panel (65%):** Four tabs — {معلومات} / {وثائق} / {أطراف} / {تصويت}
  - معلومات: Request form fields read-only or editable (role-dependent)
  - وثائق: Document upload/download, checkmarks for required docs
  - أطراف: Actors involved (submitted_by, reviewed_by, etc.) with timestamps
  - تصويت: Voting panel (if status is EXECUTIVE_VOTING_OPEN or EXECUTIVE_VOTING_CLOSED)
- **Right panel (35%):** 18-stage workflow rail + ActionsPanel (role-dependent actions)

The ActionsPanel shows contextual buttons: Submit, Approve, Reject, Claim Support Review, etc. — always based on current status and user role.

---

## 14 — Notification System

### Bell Dropdown

- **Width:** 320px
- **Content:** Last 5 notifications
- **Unread indicator:** Blue dot (unread) / gray (read)
- **Timestamp:** Relative format (2h ago)
- **Mark all read:** Link bottom-right

### Notifications Page

- **Layout:** Table with columns: unread-indicator / timestamp / message / action
- **Unread visual:** Left 2px #0066cc border + bg #f5f5f7
- **Filter tabs:** All / Unread / Archive
- **No notifications:** Empty state with icon + "No notifications"

---

## 15 — Dashboard Layouts (Role-Specific)

Each role dashboard has a unique layout and content type.

### DATA_ENTRY Dashboard
- **KPI strip:** 4 cards (requests created, awaiting review, approved, pending customs)
- **Quick actions:** 3 buttons (New request, View template, Download report)
- **Alert banner:** Amber strip if pending documents
- **Queues:** My Requests (paginated table), Recent Approvals (table)

### BANK_REVIEWER Dashboard
- **KPI strip:** 4 cards (pending review, today's reviewed, monthly quota, approval rate)
- **Queue:** Awaiting Review (table, clickable rows → request detail)
- **No charts**

### SUPPORT_COMMITTEE Dashboard
- **KPI strip:** 4 cards (pending, claimed, resolved, average review time)
- **Queue:** Support Review Queue (table with "اطّلاع" claim button)
- **No charts**

### SWIFT_OFFICER Dashboard
- **KPI strip:** 4 cards (pending SWIFT upload, uploaded, failed, monthly total)
- **Queue:** Awaiting SWIFT Upload (table, upload button)
- **Upload form:** Modal with file input, preview, submit

### COMMITTEE_DIRECTOR Dashboard
- **KPI strip:** 3 cards (pending votes, open sessions, avg vote time)
- **Voting queue:** Table with session info, vote counts, open session button
- **No charts**

### EXECUTIVE_MEMBER Dashboard
- **KPI strip:** 3 cards (assigned votes, voted, pending)
- **Queue:** My Voting Sessions (table with vote status icons)
- **No charts**

### BANK_ADMIN Dashboard
- **KPI strip:** 5 cards (monthly requests, avg processing time, approval rate, pending vendors, active users)
- **Quick actions:** 4 buttons (Add staff, Manage merchants, View audit, Download report)
- **Charts:**
  - **Line chart (monthly volume):** Last 12 months, Y-axis count, X-axis month (Arabic labels)
  - **Bar chart (by category):** Stacked or grouped, 6–8 categories
- **Recent requests:** Table (10 rows, paginated)

### CBY_ADMIN Dashboard
- **KPI strip:** 4 cards (total requests, avg processing time, success rate, pending review)
- **Quick actions:** 6 buttons (Manage banks, Manage staff, Audit log, Settings, Reports, Export)
- **Charts:**
  - **Donut chart (by import category):** 5–8 categories, legend right
  - **Line chart (monthly trend):** 12 months, trend line with area fill
  - **Bar chart (customs ports activity):** Top 5 ports by volume
  - **Heatmap (submission volume by hour/day):** Intensity color scale
- **Widget row:** Two-column layout with KPI cards or mini charts below main content

---

## 16 — Merchants Page (BANK_ADMIN)

- **Layout:** Card grid, 2 columns (tablet: 1)
- **Card content:** Merchant name / logo / status chip (نشط / مُوقَف) / actions (edit, delete, suspend)
- **Add merchant:** Button top-right, modal form
- **Status chip:** Pill-shaped, success (green) / muted (gray) based on is_active

---

## 17 — Roles & Users Page (CBY_ADMIN)

- **Layout:** Table
- **Columns:** Username / Email / Role / Status / Last Login / Actions
- **Add user:** Button top-right, modal with form
- **Edit/Delete:** Dropdown menu per row
- **Status:** Active / Inactive (green / muted badge)

---

## 18 — Audit & Compliance Page (CBY_ADMIN)

- **KPI strip:** 4 cards (total audit records, critical issues, resolved issues, pending review)
- **Tabs:**
  1. **Activity log:** Table with user / action / timestamp / details / status
  2. **Duplicate invoices:** Table with detected duplicates, "مكرر" badge, actions (merge, archive)
  3. **Risk indicators:** Card grid showing risk severity (critical / high / medium / low) with counts

---

## 19 — Settings Page (CBY_ADMIN)

- **Sidebar nav:** 5 tabs (workflow, email, notifications, security, general)
- **Workflow tab:** Configurable workflow stages, role permissions, status rules
- **Email tab:** SMTP config (host, port, user, password)
- **Notifications tab:** Global toggles for each notification type
- **Security tab:** MFA enforcement, lockout policy, session timeouts, demo mode toggle
- **General tab:** Platform name, timezone, language, logo upload

---

## 20 — Profile Page (All Roles)

- **Layout:** Two-column (left form, right card)
- **Left panel:** Editable form (name, email, phone, role read-only)
- **Right panel:** Profile card with avatar, role badge, creation date, last login
- **Actions:** Save button, Change password, Enable MFA
- **Activity list:** Recent actions (transactions, logins, settings changes)

---

## 21 — Authentication Pages

### Login Page
- **Layout:** Two-column, 40% form + 60% hero panel
- **Hero panel:** Bg #0066cc (primary), white text, CBY logo, tagline
- **Form:** Email input + password input + "Remember me" checkbox + Login button + "Forgot password?" link
- **Typography:** headline-lg for logo, body for form labels

### OTP Page
- **Layout:** Same as login
- **Form:** 6 individual OTP cells (each 60px × 60px, centered, monospace font)
- **Label:** "Enter the 6-digit code sent to your email"
- **Resend link:** Countdown timer + "Resend code" button

### Demo Mode Controls
- Demo mode enabled: Add a "RoleSwitcher" dropdown in the user menu for testing all roles (backend flag)
- Production mode: No role switcher visible

---

## 22 — Dark Mode

Dark mode is a first-class concern, supported across all surfaces using CSS custom properties.

| Component | Light | Dark |
|---|---|---|
| Background | #ffffff | #0c121a |
| Surface (card) | #ffffff | #1a2332 |
| Foreground text | #1d1d1f | #f0f0f0 |
| Secondary text | #7a7a7a | #a8adb5 |
| Border | #cccccc | #2d3748 |
| Primary button | #0066cc | #4da6ff (brighter on dark) |
| Sidebar bg | #ffffff | #111827 |
| Muted bg | #f5f5f7 | #16202b |

**Toggle:** Sun/moon icon in user header dropdown. Saved to localStorage under `theme-preference`.

---

## 23 — Motion & Transitions

All state changes use **150ms ease** by default. No springs, bounces, or parallax.

| Transition | Duration | Easing | Example |
|---|---|---|---|
| Button press | 150ms | ease | Hover state, active state |
| Card elevation | 200ms | ease | Hover shadow on card |
| Sidebar collapse | 200ms | ease-in-out | Sidebar width animation |
| Modal appear | 120ms | ease | Backdrop fade-in |
| Dropdown open/close | 150ms | ease | Popover fade + 4px slide |
| Focus ring | 150ms | ease | Focus outline appearance |

---

## 24 — Accessibility

- **Contrast:** 7:1 minimum for primary text; 4.5:1 for secondary
- **Focus ring:** Always visible, 2px solid #0066cc with 2px offset
- **ARIA labels:** All icons, badges, and interactive elements require descriptive labels
- **Status indicators:** Never color-only; always combine color + text + icon
- **Keyboard navigation:** Full support, no focus trap
- **RTL support:** `dir="rtl"` on `<html>`, all layout mirrors for RTL languages
- **Touch targets:** Minimum 44px × 44px for all buttons
- **Font size:** Minimum 12px (fine-print), 14px for all body-related text

---

## 25 — Responsive Behavior

### Breakpoints

| Name | Width | Layout Changes |
|---|---|---|
| Small phone | ≤ 419px | Single-column, sidebar collapses, header condenses |
| Phone | 420–640px | Single-column, padding reduces to 12px, font scales down |
| Tablet portrait | 641–834px | Sidebar still visible but icons-only, content padding 16px |
| Tablet landscape | 834–1023px | Sidebar expands, 2-column grids become possible |
| Desktop | 1024–1600px | Full layout, max-width 1600px content |

### Collapsing Strategy

- **Sidebar:** Full width (280px) → collapsed (72px icons) → hamburger menu (≤834px)
- **Hero typography:** 56px → 40px → 28px as screen narrows
- **Card grids:** 4-column → 3-column → 2-column → 1-column (desktop → phone)
- **Request detail split:** 65/35 → 100% stacked (single-column on ≤640px)
- **Table:** Horizontal scroll on mobile, no vertical scroll
- **Modal:** Full viewport with small padding (≤640px)

---

## 26 — Design Do's and Don'ts

### Do
✓ Use `--primary` (#0066cc) for every interactive element — links, buttons, focus rings, badges  
✓ Set headlines in the `display` or `headline-lg` token with tight letter-spacing  
✓ Run body copy at 17px — the extra pixel is intentional  
✓ Pill all primary buttons and status badges (rounded-pill)  
✓ Keep card radius at 18px (lg) and modal at 24px (xl)  
✓ Apply the single card shadow (`0 1px 3px rgba(0,0,0,0.08)`) generously — it clarifies depth  
✓ Use `transform: scale(0.95)` as the active state on all buttons  
✓ Color semantic states consistently: success (green), error (red), warning (amber), locked (gray)  
✓ Load fonts via Google Fonts with `display=swap`  
✓ Reference all colors via CSS custom properties, never hardcode hex  

### Don't
✗ Don't introduce a second accent color; every action signal is #0066cc  
✗ Don't use gradients as backgrounds or decorative effects  
✗ Don't stack shadows (e.g., multiple drop-shadows on one element)  
✗ Don't set button radius smaller than pill (8px minimum for utility)  
✗ Don't use weight 500 or 700 for body text — ladder is 400 / 600 / 700 only  
✗ Don't tighten body line-height below 26px — the editorial pace is intentional  
✗ Don't apply border-radius to full-bleed hero sections (tiles are rectangular)  
✗ Don't use color alone to communicate state (always add text or icon)  
✗ Don't exceed 44px button height on desktop; 40px for compact utility buttons  
✗ Don't load more than two fonts per category (one for Latin, one for Arabic)  

---

## Summary: Three Layers

Yemen Flow Hub follows a three-layer design architecture:

1. **shadcn-vue base layer:** Semantic CSS custom properties (`--primary`, `--border`, `--ring`, etc.) and primitive components (Button, Card, Input, etc.). These are untouched.

2. **Design.md (this document):** Token definitions, component specs, layout rules, and institutional patterns. Every color, font, radius, and shadow is named here as the source of truth.

3. **Custom component layer:** Page-specific and role-specific customization using the design tokens defined in layer 2. No raw Tailwind color scales (no `text-gray-*`, `bg-blue-*`, etc.). Always `text-muted-foreground`, `bg-primary`, etc.

This structure ensures consistency, maintainability, and rapid iteration. Change a token in the design system → the entire app updates instantly.
