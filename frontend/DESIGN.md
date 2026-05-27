# Yemen Flow Hub — Frontend Design System

## Platform Character

Institutional clarity. Government banking workflow. Desktop-first, RTL-first, Arabic-first. Every surface is a queue or a form. No gradients, no glassmorphism, no decorative chrome. The workflow is the product.

**Visual register:** Product (design serves the workflow, not the brand)  
**Aesthetic:** Restrained — tinted neutrals + one primary accent (#0066cc) across all surfaces

---

## 1 — Color Tokens

All colors are CSS custom properties. **Never hardcode hex. Never use raw Tailwind color scale classes** (`text-gray-*`, `bg-blue-500`, `text-red-600`, etc.). Always use the semantic token.

### Core shadcn-vue tokens (via Tailwind utilities)

| Utility class | Token | Usage |
|---|---|---|
| `bg-background` / `text-foreground` | `--background` / `--foreground` | Page canvas / primary text |
| `bg-card` / `text-card-foreground` | `--card` / `--card-foreground` | Card surfaces |
| `bg-muted` / `text-muted-foreground` | `--muted` / `--muted-foreground` | Alternate rows, secondary text, disabled states |
| `bg-primary` / `text-primary-foreground` | `--primary` / `--primary-foreground` | CTA buttons, active nav, focus rings |
| `bg-secondary` / `text-secondary-foreground` | `--secondary` / `--secondary-foreground` | Secondary button variant |
| `bg-destructive` / `text-destructive` | `--destructive` | Destructive actions and errors |
| `border-border` | `--border` | All dividers, card borders, input borders |
| `ring-ring` | `--ring` | Focus outlines |
| `bg-accent` / `text-accent-foreground` | `--accent` | Hover highlights on ghost/menu items |

### Project-specific semantic tokens (via `var()`)

These must be referenced as `text-[var(--token)]`, `bg-[var(--token)]/10`, etc.

| Token | Value (light) | Value (dark) | Usage |
|---|---|---|---|
| `--brand-color` | `#0066cc` | same | Primary blue — same as `--primary` but available as a direct var |
| `--severity-red` | `#ff3b30` | `#ff453a` | Errors, rejections, destructive states |
| `--severity-amber` | `#ff9f0a` | `#ffb340` | Warnings, pending action required, at-risk |
| `--severity-green` | `#34c759` | `#32d74b` | Success, approved, completed |
| `--voting` | `#5856d6` | `#7b79e8` | Executive voting sessions |
| `--info` / `--swift` | `#32ade6` | same | SWIFT officer, info states |
| `--locked` | `#8e8e93` | `#a8b0ba` | Immutable/locked workflow states |
| `--success` | `#1b5e20` | `#81c784` | Success text (lower saturation than severity-green) |
| `--warning` | `#f57f17` | `#ffb74d` | Warning text |

### Token usage rules

```vue
<!-- ✅ CORRECT — semantic tokens -->
<span class="text-[var(--severity-red)]">رُفض</span>
<div class="bg-[var(--voting)]/10 text-[var(--voting)]">جلسة تصويت</div>
<div class="border-s-4 border-s-[var(--severity-amber)]">تحذير</div>

<!-- ❌ WRONG — raw Tailwind color scale -->
<span class="text-red-600">رُفض</span>
<div class="bg-indigo-50 text-indigo-600">جلسة تصويت</div>
<div class="border-l-4 border-amber-500">تحذير</div>
```

### RTL border rule

Always use `border-s-*` (inline-start) not `border-l-*` (left). In RTL, `s` = right side.

```vue
<!-- ✅ RTL-correct accent border -->
<Card class="border-s-4 border-s-[var(--severity-amber)]">

<!-- ❌ LTR-only, breaks RTL -->
<div class="border-l-4 border-amber-500">
```

---

## 2 — Typography

| Usage | Font | Class |
|---|---|---|
| Page headlines, section headings | Cairo | `font-heading` |
| Subheadings, navigation labels | Tajawal | `font-section` |
| Body text, form fields, table content | IBM Plex Sans Arabic | `font-sans` (default) |
| Latin strings, numbers in LTR context | Inter | automatic via font stack |

Body base: `text-sm` (14px) for compact dashboard tables; `text-base` (16px) for form content.  
No italics. Emphasis via weight (`font-medium`, `font-semibold`) or color. No `font-bold` on body.

---

## 3 — Spacing & Layout

- **Base unit:** 8px (`gap-2`, `p-2`)
- **Card padding:** `p-4` (16px) for compact queue cards; `p-6` (24px) for full-page cards
- **Section gap:** `gap-6` between dashboard sections
- **Container max:** `max-w-[1600px]`
- **Sidebar expanded:** 280px right-aligned | Collapsed: 72px icons-only
- **Grid breakpoints:** `grid-cols-4` → `max-lg:grid-cols-2` → `max-md:grid-cols-1`

---

## 4 — Border Radius

The app uses Tailwind's theme radius tokens, mapped from the shadcn `--radius` base (0.625rem = 10px):

| Token | Approx value | Usage |
|---|---|---|
| `rounded-sm` | 6px | Tight utility elements |
| `rounded-md` | 8px | Inputs, textareas, selects |
| `rounded-lg` | 10px | Standard cards, buttons |
| `rounded-xl` | 14px | Prominent cards, larger containers |
| `rounded-2xl` | 18px | Quick-action tiles, modals |
| `rounded-full` | 9999px | Badges, avatars, status chips |

Primary buttons use `rounded-lg` (shadcn default). Quick-action tiles use `rounded-2xl`.

---

## 5 — Elevation

One shadow level only. No stacked shadows.

| State | Class |
|---|---|
| Card default | `shadow` |
| Card hover | `shadow-md` |
| Modal/popover | handled by shadcn Dialog/Sheet internally |

---

## 6 — RTL Rules (mandatory)

```vue
<!-- Root layout -->
<div dir="rtl">

<!-- Sidebar: right side -->
<!-- Action columns: last (rightmost in RTL) -->

<!-- Inline-start/end instead of left/right -->
class="border-s-4"   <!-- not border-l-4 -->
class="ps-4"         <!-- not pl-4 -->
class="me-2"         <!-- not mr-2 -->
class="text-start"   <!-- not text-left -->
```

---

## 7 — Status Badge Color Mapping

Every status badge is role-aware (use `<StatusBadge :status="..." :role="UserRole.xxx" />`).  
When building custom inline chips (not StatusBadge), use this mapping:

| Status category | Color token | bg/10 tint |
|---|---|---|
| Approved / Completed / Uploaded | `--severity-green` | `bg-[var(--severity-green)]/10` |
| Rejected / Error | `--severity-red` | `bg-[var(--severity-red)]/10` |
| Pending / Action required | `--severity-amber` | `bg-[var(--severity-amber)]/10` |
| Voting open/closed | `--voting` | `bg-[var(--voting)]/10` |
| SWIFT stages | `--info` | `bg-[var(--info)]/10` |
| Locked / Immutable | `--locked` | `bg-[var(--locked)]/10` |
| In progress / Neutral | `text-foreground` + `bg-muted` | |

---

## 8 — Skeleton Loading Pattern

Replace every `<div class="animate-pulse ...">` with `<Skeleton>`:

```vue
<!-- ✅ CORRECT -->
<Skeleton class="h-24 w-full rounded-xl" />
<Skeleton class="h-4 w-32" />

<!-- ❌ WRONG -->
<div class="h-24 animate-pulse rounded-xl bg-muted" />
```

---

## 9 — Error State Pattern

Replace every custom error `div` with `<Alert variant="destructive">`:

```vue
<!-- ✅ CORRECT -->
<Alert variant="destructive" role="alert">
  <AlertCircle class="h-4 w-4" />
  <AlertTitle>خطأ في التحميل</AlertTitle>
  <AlertDescription>{{ store.error }}</AlertDescription>
  <AlertAction>
    <Button variant="outline" size="sm" @click="store.loadStats()">إعادة المحاولة</Button>
  </AlertAction>
</Alert>

<!-- ❌ WRONG -->
<div class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-destructive">
  <button class="px-4 py-1.5 border ...">إعادة المحاولة</button>
</div>
```

---

## 10 — Action Banner Pattern (amber/red/indigo strips)

Action-required banners above the KPI grid use `Card` with start border:

```vue
<Card class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm" role="alert">
  <CardContent class="pt-4 pb-4 flex items-center gap-3">
    <AlertTriangle class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
    <div class="flex-1 min-w-0">
      <span class="font-semibold text-foreground text-sm">{{ count }} طلبات تحتاج إجراء</span>
      <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ detail }}</p>
    </div>
    <Button size="sm" class="bg-[var(--severity-amber)] text-white hover:opacity-90 flex-shrink-0" @click="...">
      ابدأ
    </Button>
  </CardContent>
</Card>
```

---

## 11 — Do / Don't Summary

| ✅ Do | ❌ Don't |
|---|---|
| `text-[var(--severity-red)]` | `text-red-600` |
| `bg-[var(--voting)]/10` | `bg-indigo-50` |
| `border-s-4 border-s-[var(--severity-amber)]` | `border-l-4 border-amber-500` |
| `<Skeleton class="h-24 w-full rounded-xl" />` | `<div class="animate-pulse ...">` |
| `<Button variant="outline" size="sm">` | `<button class="px-2 py-1 border ...">` |
| `<Table><TableHeader>…</TableHeader></Table>` | `<table><thead>…</thead></table>` |
| `<Alert variant="destructive">` | `<div class="border-destructive ...">` |
| `<Card role="button" tabindex="0">` for multi-line tiles | `<button class="flex flex-col ...">` |
| `<AlertDialog>` for destructive confirmations | `window.confirm()` or `<Dialog>` |
| `<Empty>` for zero-state sections | `<div class="text-center text-muted-foreground">` |
