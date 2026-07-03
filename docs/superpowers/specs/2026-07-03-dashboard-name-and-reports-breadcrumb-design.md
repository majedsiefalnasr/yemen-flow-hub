# Dashboard full name + reports breadcrumb fix — Design

## Context

First sub-project of a larger 9-item admin/UX request. This is the "quick wins" slice: two small, independent, low-risk fixes. Remaining 7 items (merchants table, staff route rename, screen-permissions rewrite, single-source-of-truth docs cleanup, etc.) are separate sub-projects, each to get its own spec/plan.

## 1. Welcome message: full name instead of first name

**Problem:** Home dashboard welcome message shows only the user's first name.

**Found in:**
- `app/pages/dashboard.vue:27` — `const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')`, used at line 52: `` `أهلاً، ${firstName}` ``
- `app/pages/index.vue:26` — identical pattern, used at line 56 inline in template

**Note:** `index.vue` (route `/`) and `dashboard.vue` (route `/dashboard`) are both live, reachable, near-duplicate pages. `index.vue` is a slightly stale fork (hand-rolled header markup instead of `<PageHeader>`, older CBY_ADMIN subtitle text `مسؤول (CBY)` vs `مسؤول اللجنة الوطنية`). No redirect exists between `/` and `/dashboard`.

**Decision:** Fix the name display in both files. Do not deduplicate `index.vue`/`dashboard.vue` in this pass — that consolidation belongs to the later "single source of truth" cleanup item (item 9 of the larger request).

**Fix:** Rename `firstName` → `userName` (or reuse `auth.user?.name` directly) and drop the `.split(' ')[0]`, in both files.

## 2. Reports page breadcrumb

**Problem:** `app/pages/reports/index.vue` hand-rolls its own page header and breadcrumb nav (lines ~296-330) with dedicated scoped CSS (`.breadcrumbs`, `.breadcrumb-link`, `.breadcrumb-sep`, `.breadcrumb-current`, ~lines 598-622), instead of reusing the shared `<PageHeader>` component that every other page (e.g. `notifications.vue`) uses.

**Reference pattern** (`notifications.vue:556-572`):
```vue
<PageHeader
  title="مركز الإشعارات"
  :subtitle="..."
  :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الإشعارات' }]"
>
  <template #actions>
    <Button ...>...</Button>
  </template>
</PageHeader>
```

**Fix for reports page:**
- Replace the hand-rolled `.page-header` / `.breadcrumbs` block with `<PageHeader>`.
- `title`: "التقارير والتحليلات المتقدمة"
- `subtitle`: "مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير"
- `breadcrumbs`: `[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'التقارير' }]`
- Move existing header action buttons (period picker, PDF export, and any other export/action buttons currently in `.header-actions`) into the `#actions` slot, unchanged in behavior.
- Delete the now-dead `.page-header`, `.header-text`, `.header-actions`, `.breadcrumbs`, `.breadcrumb-link`, `.breadcrumb-link:hover`, `.breadcrumb-sep`, `.breadcrumb-current`, `.page-title`, `.page-subtitle` CSS rules from the `<style>` block (scan full file for exact rule set before deleting — only remove rules no longer referenced anywhere else in the file).

## Testing

- Manual verification via `playwright-cli`: load `/dashboard` and `/` for a couple of roles, confirm full name renders. Load `/reports`, confirm breadcrumb renders identically in style/behavior to `/notifications`'s breadcrumb (link back to `/dashboard`, hover state, current-page non-link segment).
- No unit test changes expected — these are template/copy changes, not logic changes. If existing Vitest specs assert on `firstName` variable name or breadcrumb DOM structure, update them to match.

## Out of scope

- Deduplicating `index.vue` vs `dashboard.vue`.
- Any other page's breadcrumb/header pattern.
- The remaining 7 items from the original request.
