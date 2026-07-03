# Dashboard Full Name + Reports Breadcrumb Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the user's full name (not just first name) in the dashboard welcome message, and make the reports page breadcrumb reuse the shared `PageHeader` component instead of hand-rolled markup/CSS.

**Architecture:** Two independent, template-only changes. No store, composable, or business-logic changes. No new components.

**Tech Stack:** Nuxt 4, Vue 4, TypeScript, Tailwind CSS v4, shadcn-vue `PageHeader` component (`app/components/layout/PageHeader.vue`).

## Global Constraints

- Frontend code must be committed to both repos: frontend team repo (`git@github.com:ultimate-eg/yemen-flow-hub-frontend.git`, from inside `frontend/`) and root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`, from repo root, staging `frontend/<files>`). See `frontend/CLAUDE.md` "Git Scope".
- Conventional commit format: `type(scope): description`. All commits must stay signed — never `--no-gpg-sign`.
- No raw HTML breadcrumb markup — must use `<PageHeader :breadcrumbs="...">` (`frontend/SHADCN.md`).
- RTL-first: breadcrumb must render right-to-left, consistent with `notifications.vue`'s existing breadcrumb.
- Do not run full `pnpm test`; use focused Vitest runs for touched files, per `frontend/CLAUDE.md` Verification Ladder.
- Repo root for all paths below: `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend`.

---

### Task 1: Dashboard welcome message shows full name

**Files:**
- Modify: `app/pages/dashboard.vue:27` and `:52`
- Modify: `app/pages/index.vue:26` and `:56`

**Interfaces:**
- Consumes: `auth.user?.name` (existing `AuthStore` field, unchanged type `string | undefined`).
- Produces: nothing consumed by later tasks — this is a leaf UI change.

- [ ] **Step 1: Update `dashboard.vue` to use full name**

In `app/pages/dashboard.vue`, replace line 27:

```ts
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
```

with:

```ts
const userName = computed(() => auth.user?.name ?? '')
```

Then update line 52 (inside the template):

```vue
<PageHeader :title="`أهلاً، ${firstName}`" :subtitle="roleSubtitle">
```

to:

```vue
<PageHeader :title="`أهلاً، ${userName}`" :subtitle="roleSubtitle">
```

- [ ] **Step 2: Update `index.vue` to use full name**

In `app/pages/index.vue`, replace line 26:

```ts
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
```

with:

```ts
const userName = computed(() => auth.user?.name ?? '')
```

Then update the template at line 56:

```vue
أهلاً، {{ firstName }}
```

to:

```vue
أهلاً، {{ userName }}
```

- [ ] **Step 3: Search for any other `firstName` references in these two files**

Run:
```bash
grep -n "firstName" app/pages/dashboard.vue app/pages/index.vue
```
Expected: no matches (both files fully migrated to `userName`).

- [ ] **Step 4: Search for existing tests referencing `firstName` or the welcome string**

Run:
```bash
grep -rln "firstName\|أهلاً" app/tests 2>/dev/null
```
If any test files match, open them and update assertions from split-first-name expectations to full-name expectations (use a full-name fixture, e.g. `'أحمد محمد علي'`, and assert the welcome text contains the full string, not just the first token).

- [ ] **Step 5: Run focused Vitest for any touched test files (skip if step 4 found none)**

Run: `pnpm exec vitest run <path/to/test-file>` for each file touched in step 4.
Expected: PASS.

- [ ] **Step 6: Typecheck (variable rename touches computed refs)**

Run: `pnpm typecheck`
Expected: no new errors introduced by this task.

- [ ] **Step 7: Commit to frontend repo**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
git add app/pages/dashboard.vue app/pages/index.vue
git commit -m "fix(dashboard): show full user name in welcome message"
```

- [ ] **Step 8: Commit same change to root monorepo**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/dashboard.vue frontend/app/pages/index.vue
git commit -m "fix(dashboard): show full user name in welcome message"
```

---

### Task 2: Reports page breadcrumb uses shared PageHeader

**Files:**
- Modify: `app/pages/reports/index.vue:1-22` (imports), `:296-328` (template header block), `:582-649` (scoped CSS)

**Interfaces:**
- Consumes: `PageHeader` component (`app/components/layout/PageHeader.vue`), props `title: string`, `subtitle?: string`, `breadcrumbs?: { label: string; to?: string }[]`, `#actions` slot. Existing script-level `store.loading`, `store.exportLoading`, `isCbyUser`, `handleExportWorkflow`, `handleExportBank` (all already defined in this file, unchanged).
- Produces: nothing consumed by later tasks — leaf UI change.

- [ ] **Step 1: Add `PageHeader` import**

In `app/pages/reports/index.vue`, after line 17 (`import RankedListCard from '../../components/shared/dashboard/RankedListCard.vue'`), add:

```ts
import PageHeader from '../../components/layout/PageHeader.vue'
```

- [ ] **Step 2: Replace the hand-rolled header block in the template**

Replace lines 296-328:

```vue
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-text">
        <nav class="breadcrumbs" aria-label="مسار التنقل">
          <NuxtLink to="/dashboard" class="breadcrumb-link">الرئيسية</NuxtLink>
          <span class="breadcrumb-sep">←</span>
          <span class="breadcrumb-current">التقارير</span>
        </nav>
        <h1 class="page-title">التقارير والتحليلات المتقدمة</h1>
        <p class="page-subtitle">مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير</p>
      </div>
      <div class="header-actions">
        <Button variant="outline" :disabled="store.loading" aria-label="تحديد الفترة الزمنية">
          <CalendarDays class="h-4 w-4" aria-hidden="true" />
          الفترة
        </Button>
        <Button
          variant="outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('pdf') : handleExportBank('pdf')"
        >
          تصدير PDF
        </Button>
        <Button
          variant="outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('excel') : handleExportBank('excel')"
        >
          تصدير Excel
        </Button>
      </div>
    </div>
```

with:

```vue
    <!-- Page Header -->
    <PageHeader
      title="التقارير والتحليلات المتقدمة"
      subtitle="مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'التقارير' }]"
    >
      <template #actions>
        <Button variant="outline" :disabled="store.loading" aria-label="تحديد الفترة الزمنية">
          <CalendarDays class="h-4 w-4" aria-hidden="true" />
          الفترة
        </Button>
        <Button
          variant="outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('pdf') : handleExportBank('pdf')"
        >
          تصدير PDF
        </Button>
        <Button
          variant="outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('excel') : handleExportBank('excel')"
        >
          تصدير Excel
        </Button>
      </template>
    </PageHeader>
```

- [ ] **Step 3: Delete the now-dead header/breadcrumb CSS**

In the `<style scoped>` block, delete these rules in full (verify exact current line numbers with `grep -n "^\.page-header\|^\.header-actions" app/pages/reports/index.vue` before editing, since line numbers shift after Step 2):

```css
/* ─── Header ────────────────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-section);
  font-size: 0.8125rem;
  line-height: 1.25rem;
  color: var(--muted-foreground);
  margin-bottom: 6px;
}

.breadcrumb-link {
  color: var(--color-primary);
  text-decoration: none;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-sep {
  color: var(--border);
}

.breadcrumb-current {
  color: var(--foreground);
}

.page-title {
  font-family: var(--font-heading);
  font-size: 1.75rem;
  line-height: 2.25rem;
  font-weight: 600;
  color: var(--foreground);
  margin: 0;
}

.page-subtitle {
  max-width: 68ch;
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: var(--muted-foreground);
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  align-items: center;
}
```

Keep `.reports-page` and everything from `/* ─── Buttons ───────────────────────────────────────────────── */` (`.btn` and onward) — those are unrelated and still used elsewhere in the file. Confirm with:

```bash
grep -n "class=\"btn\|class=\"page-header\|class=\"header-text\|class=\"header-actions\|class=\"breadcrumb\|class=\"page-title\|class=\"page-subtitle" app/pages/reports/index.vue
```

Expected: zero matches (all removed classes are now unused in the template).

- [ ] **Step 4: Check if `NuxtLink` import/auto-import is still needed elsewhere in the file**

Run:
```bash
grep -n "NuxtLink" app/pages/reports/index.vue
```
`NuxtLink` is Nuxt's auto-import (no explicit import statement to remove), so this step only confirms no other template usage was broken. Expected: zero matches after Step 2 (breadcrumb was the only usage).

- [ ] **Step 5: Typecheck**

Run: `pnpm typecheck`
Expected: no new errors.

- [ ] **Step 6: Lint the touched file**

Run: `pnpm exec eslint app/pages/reports/index.vue`
Expected: no errors. Fix any (e.g. unused `CalendarDays` import would already exist pre-change — do not touch unrelated lint issues outside this diff).

- [ ] **Step 7: Manual verification with playwright-cli**

Start dev server if not running, navigate to `/reports` as a CBY-role demo user, confirm:
- Breadcrumb renders "الرئيسية ← التقارير" with the same visual style as `/notifications`'s breadcrumb (muted text, chevron separator, hover underline on the link).
- Clicking "الرئيسية" navigates to `/dashboard`.
- The three action buttons (الفترة, تصدير PDF, تصدير Excel) still render in the header and remain functional (export buttons still call their existing handlers).

- [ ] **Step 8: Commit to frontend repo**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
git add app/pages/reports/index.vue
git commit -m "style(reports): use shared PageHeader for breadcrumb and title"
```

- [ ] **Step 9: Commit same change to root monorepo**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/reports/index.vue
git commit -m "style(reports): use shared PageHeader for breadcrumb and title"
```

---

## Done Criteria

- Both `dashboard.vue` and `index.vue` show the user's full name in the welcome message.
- `reports/index.vue` uses `<PageHeader>` for its title/subtitle/breadcrumb/actions, matching the pattern in `notifications.vue`.
- No dead CSS remains for the removed header markup.
- `pnpm typecheck` clean for both tasks.
- Each task committed separately to both the frontend repo and the root monorepo (4 commits total).
