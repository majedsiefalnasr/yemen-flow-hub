# Impeccable Audit — Yemen Flow Hub `frontend/`

**Date:** 2026-06-02
**Register:** Product (internal government banking workflow platform)
**Scope:** `frontend/app/` — 37 pages, 429 components (81 excl. shadcn `ui/` primitives)
**Mode:** Read-only. No files changed. Findings map to follow-up commands.

> Audit authority note: this codebase has a locked design doctrine (`DESIGN.md`, `lovable/` 1:1 parity gate, `docs/user-view/*.md`) that supersedes generic impeccable design laws. Findings below are scoped to **measurable technical defects** (broken classes, token violations, a11y, raw-element usage) that do not conflict with that doctrine. Pure aesthetic opinions are omitted.

---

## Audit Health Score

| # | Dimension | Score | Key Finding |
|---|-----------|-------|-------------|
| 1 | Accessibility | 3/4 | 12 raw `<button>` + 5 raw `<table>` outside `ui/`; otherwise strong (aria-label used 140×, alts present) |
| 2 | Performance | 4/4 | No layout thrashing; blur/transition usage bounded and purposeful |
| 3 | Responsive Design | 3/4 | Desktop-first per spec; a few dense pages (audit, customs-preview) lack explicit narrow-viewport handling |
| 4 | Theming | 2/4 | 17 components use raw Tailwind color scales; broken token classes ship to prod (`bg-success/10/10`, `text-red-700-foreground`); conflicting border utilities |
| 5 | Anti-Patterns | 4/4 | No AI slop. No gradient text, no glass-by-default, no hero-metric template, no side-stripe borders |
| **Total** | | **16/20** | **Good — address Theming, the one weak dimension** |

---

## Anti-Patterns Verdict

**PASS.** This does not look AI-generated. The design is a committed institutional system: single accent (#0066cc), two-font Arabic-first stack, pill CTAs, one card shadow, RTL-native. None of the cross-register tells are present (no gradient text, no decorative glassmorphism, no hero-metric gradient template, no identical-card-grid filler, no side-stripe accent borders). The category-reflex check passes at both altitudes: a government banking tool would reflex to "navy + gold + dense gray tables," and this deliberately isn't that. Credit where due, this is the strongest dimension.

---

## Executive Summary

- **Audit Health Score: 16/20 (Good)**
- **Issues by severity:** P0: 0 · P1: 3 · P2: 4 · P3: 2
- **Top issues:**
  1. **[P1] Broken token class `bg-success/10/10`** ships in `VotingPanel.vue` (×2). The double `/10/10` opacity suffix is invalid; the success background silently fails to render on the finalized-vote banner and the "you voted" alert. This is the executive voting outcome surface, the highest-stakes screen in the workflow.
  2. **[P1] Conflicting border utilities** on Alert components: `border-red-600 ... border-0` and `border-0 ... border-green-600` appear together (VotingPanel ×3, MerchantModal, SuspendConfirmDialog). `border-0` wins or fights depending on order, so the intended colored error/success border is dropped. Error states look unstyled.
  3. **[P1] `text-red-700-foreground` is not a real class** (calendar trigger components ×2). The "unavailable date" state has no color, only strikethrough, so disabled dates are nearly indistinguishable.
- **Recommended next step:** `clarify` is the wrong tool here; these are **theming defects**, fix with a targeted token cleanup pass, then re-audit.

---

## Detailed Findings by Severity

### [P1] Invalid opacity suffix `bg-success/10/10`
- **Location:** `app/components/voting/VotingPanel.vue:187` and `:352`
- **Category:** Theming
- **Impact:** Tailwind cannot parse a double opacity modifier; the rule is dropped. The finalized-vote outcome banner (`EXECUTIVE_APPROVED`) and the "my vote recorded" alert render with no success background tint. On the single most consequential workflow screen, the visual confirmation of approval is missing.
- **Recommendation:** `bg-success/10/10` → `bg-success/10`. Verify against `lovable/` parity reference for the intended tint.

### [P1] Conflicting `border-0` + colored border on Alerts
- **Location:** `VotingPanel.vue:192,352`, `MerchantModal.vue:194`, `SuspendConfirmDialog.vue:63` (and the `border-amber-500 ... border-0` warning at `VotingPanel.vue:232`)
- **Category:** Theming / Anti-Pattern (contradictory utilities)
- **Impact:** `border-0` sets `border-width:0`, so `border-red-600` has nothing to paint. Error and tie-break alerts lose their intended colored edge and read as flat tinted boxes. Inconsistent with the status-badge border system in `DESIGN.md §10`.
- **Recommendation:** Drop `border-0` where a colored border is intended, or drop the color where borderless is intended. Also migrate `border-red-600`/`border-amber-500`/`border-green-600` to the semantic tokens (`border-destructive`, `var(--color-border-warning)`, `var(--color-border-success)`) per `DESIGN.md` "no raw Tailwind color scales" rule.

### [P1] Non-existent `text-red-700-foreground`
- **Location:** `app/components/ui/calendar/CalendarCellTrigger.vue:30`, `app/components/ui/range-calendar/RangeCalendarCellTrigger.vue:34`
- **Category:** Theming / Accessibility
- **Impact:** Unavailable calendar dates rely on color + strikethrough to signal "can't pick this." The class is malformed (`-foreground` appended to a scale color), so no color applies. Strikethrough alone is a weak, color-blind-unfriendly signal, and `DESIGN.md §24` requires status never be conveyed by a single channel.
- **Recommendation:** Replace with a valid disabled token, e.g. `text-destructive` or `text-muted-foreground` plus the existing line-through. These are vendored shadcn primitives, so confirm the change doesn't drift from the registry before editing.

### [P2] Raw Tailwind color scales bypass the token system (17 components)
- **Location:** Concentrated in `app/components/customs/PrintablePermit.vue` (~30 instances of `bg-gray-*`, `text-gray-900`, `border-gray-200`, `text-green-700`), plus `DataTableWithTabs.vue:268` (`bg-emerald-500`), `VotingPanel.vue` (`border-indigo-600`, `focus:border-indigo-600`)
- **Category:** Theming
- **Impact:** `DESIGN.md` mandates semantic tokens only (`text-muted-foreground`, not `text-gray-500`). PrintablePermit is partially exempt because print needs literal ink colors, but the on-screen path still inherits raw scales, so it won't follow dark mode or token changes.
- **Recommendation:** For PrintablePermit, gate raw colors behind `print:` variants only and use tokens for screen. For `DataTableWithTabs` and `VotingPanel`, swap to `bg-success` / `border-[var(--voting)]`. Suggested command: a scoped `colorize`/token pass on these 3 files, not a global rewrite.

### [P2] Hardcoded hex in settings theme-preview swatches
- **Location:** `app/pages/settings/index.vue:672-699` (`bg-[#111827]`, `bg-[#0066cc]/40`, inline `linear-gradient(...#0f1218...)`)
- **Category:** Theming
- **Impact:** These are deliberate mini-mockups of light/dark/system themes inside the appearance picker, so literal hex is arguably justified (they must show fixed theme colors regardless of the active theme). Low real-world impact, but they duplicate values that exist as tokens and will drift if the palette changes.
- **Recommendation:** Acceptable as-is for the preview thumbnails. If touched, reference the canonical hex via a small constant so a palette change updates both. Do not bulk-tokenize, the previews intentionally don't follow the active theme.

### [P2] Raw `<button>` outside shadcn primitives (11 files)
- **Location:** `LoginSavedAccountCard.vue`, `RoleSwitcher.vue`, `GlobalSearch.vue`, `AvatarPicker.vue`, `MerchantModal.vue`, `StaffModal.vue`, `organization.vue`, `settings/index.vue`, `admin/settings.vue`, `requests/[id]/customs-preview.vue`, `requests/[id]/print.vue`
- **Category:** Accessibility / Consistency
- **Impact:** `AGENTS.md` and `SHADCN.md` mandate shadcn `Button`. Raw buttons miss the standardized focus ring, disabled/hover/active states, and 44px touch target from `DESIGN.md §6`. Some (print, customs-preview) are print/utility surfaces where a raw button may be fine; others (modals, search, role switcher) are interactive app chrome and should use `Button` or `Button variant="ghost"`.
- **Recommendation:** Audit each: print/preview pages can keep raw buttons (they're outside the app shell); `GlobalSearch`, `RoleSwitcher`, `AvatarPicker`, and the two modals should adopt shadcn `Button`. Note per `AGENTS.md`: if a Vitest test can't introspect the shadcn component, skip the test, don't downgrade the component.

### [P2] Raw `<table>` outside shadcn Table (5 files)
- **Location:** `PrintablePermit.vue`, `VotingPanel.vue`, `RequestPrintable.vue`, `audit.vue`, `requests/[id]/customs-preview.vue`
- **Category:** Accessibility / Consistency
- **Impact:** Three of these (`PrintablePermit`, `RequestPrintable`, `customs-preview`) are **print documents** where a raw semantic `<table>` is correct and shadcn Table would fight print styles. `audit.vue` and `VotingPanel.vue` are on-screen and should use shadcn `Table` for consistent row height, hover, and sort affordances per `DESIGN.md §12`.
- **Recommendation:** Leave the 3 print components alone. Convert `audit.vue` and the `VotingPanel` voting-tally table to shadcn `Table`.

### [P3] Dense pages without explicit narrow-viewport handling
- **Location:** `audit.vue`, `requests/[id]/customs-preview.vue`, `admin/roles.vue`, `admin/entities.vue` (no `sm:`/`md:`/`lg:` utilities)
- **Category:** Responsive
- **Impact:** Platform is desktop-first by spec (`DESIGN.md §25`), and `DESIGN.md` allows tables to scroll horizontally on mobile, so this is mostly by-design. Risk is only that a few admin/audit tables may overflow without a scroll container on <640px.
- **Recommendation:** Confirm each wide table sits inside an `overflow-x-auto` wrapper. No layout rewrite needed. Low priority given desktop-first posture.

### [P3] `transition-all` used in 33 places
- **Location:** spread across components
- **Category:** Performance
- **Impact:** Minor. `transition-all` can animate layout properties unintentionally and does slightly more work than scoped transitions. None observed causing dropped frames.
- **Recommendation:** Where the intent is color/opacity/transform only, narrow to `transition-colors` / `transition-transform`. Cosmetic, fix opportunistically.

---

## Patterns & Systemic Issues

1. **Token discipline breaks down in two zones: voting and print.** `VotingPanel.vue` alone holds the two `bg-success/10/10` typos, three conflicting-border alerts, `border-indigo-600`, and a hardcoded `bg-[#5856d6]`. PrintablePermit holds the bulk of raw-gray usage. The rest of the app is clean. Fixing these two files resolves most of the Theming score gap.
2. **Conflicting-utility pattern (`border-0` + `border-{color}`) recurs 5 times**, suggesting a copy-paste of one bad Alert template. Fixing the source pattern prevents recurrence.
3. **Raw `<button>`/`<table>` cluster on print/preview surfaces**, which is legitimate. The genuine violations are a small subset (search, role switcher, modals, audit table).

## Positive Findings

- **No AI slop. Anti-Patterns dimension is a clean 4/4** — the institutional design system is coherent and committed.
- **aria-label appears 140×**; accessibility intent is clearly built in, not bolted on.
- **All `<img>` tags carry `alt`** (initial grep flagged 2 as false positives; both have alt on the following line).
- **Performance is sound**: no layout thrashing, bounded blur/transition usage, lazy-loaded detail tabs (per project memory).
- **Token system is real and mostly followed** — the violations are localized, not endemic.

---

## Recommended Actions (priority order)

1. **[P1] Token-defect cleanup** (not a named impeccable command, a targeted edit): fix `bg-success/10/10` → `bg-success/10` (2×), resolve the `border-0` + colored-border conflicts (5×), and replace `text-red-700-foreground` (2×). ~9 edits, all in `VotingPanel.vue`, `MerchantModal.vue`, `SuspendConfirmDialog.vue`, and the two calendar primitives.
2. **[P2] `colorize` (scoped)** on `VotingPanel.vue`, `DataTableWithTabs.vue`, and the screen path of `PrintablePermit.vue`: migrate raw `gray/emerald/indigo` scales to semantic tokens. Keep print colors behind `print:`.
3. **[P2] `harden` (scoped)** on `audit.vue` and the interactive modals/search: adopt shadcn `Button`/`Table` where the surface is app chrome (skip print/preview pages).
4. **[P3] `adapt` (light)**: confirm wide admin/audit tables have `overflow-x-auto`. Verify, don't rewrite.
5. **`impeccable polish`** as the final pass once the above land.

> You can ask me to run these one at a time, all at once, or in any order you prefer.
>
> Re-run `impeccable audit` after fixes to see your score improve.

**Important:** Every recommended edit should be checked against `lovable/` parity and run through the parity-evidence gate (`check-parity-evidence.ts`) plus Vitest before commit, per Epic 9. The P1 token fixes are the safest (they fix broken CSS that parity itself would want corrected); the raw-element swaps carry the most parity risk and should be confirmed against the Lovable reference first.
