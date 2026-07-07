# Draft Editing Concurrency and Leave-Guard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let any bank user with EXECUTE access on a request's current stage continue an in-progress DRAFT request in wizard mode (not just its original creator), gate simultaneous wizard editing through the existing claim/heartbeat mechanism so no two users edit the same request at once, and warn the user before they navigate away or close/refresh the tab with unsaved wizard changes.

**Architecture:** The wizard's per-step draft-save (`useEngineWizard.next()` → `store.saveDraftData` → `PATCH /engine-requests/{id}/draft`) already persists data at every step boundary — reload-safety needs no new code, only a regression test. The real gap is a frontend-only `wizardMode` gate hardcoded to the request's original creator; this plan changes that gate to the same server-derived `canExecute` flag the read-only view/act panel already uses, then extends the already-generic `EngineClaimService`/`useEngineClaim` claim-heartbeat-release flow (built for Support Committee, but not role-specific in code) into wizard mode so concurrent editors are gated exactly like concurrent stage-executors already are. No new backend service or claim endpoint is needed — `EngineClaimService`, its routes, and `useEngineClaim` are already stage-agnostic. The leave-guard is new: a Vue Router `onBeforeRouteLeave` guard plus a native `beforeunload` listener, scoped to the wizard's dirty-state.

**Tech Stack:** Nuxt 4/Vue 4/TypeScript, Vue Router composables, shadcn-vue (`AlertDialog`), Vitest, `playwright-cli` for live verification. No backend code changes — only a workflow-designer data/config change (toggle `requires_claim` on the initial stage) verified live, not migrated.

## Global Constraints

- Do not touch `EngineClaimService`, its routes, or `useEngineClaim.ts` — all three are already generic and correct; reuse them as-is.
- `wizardMode`'s new condition must still require `route.query.mode === 'wizard'` and `current_stage.is_initial === true` — only the creator check changes to `canExecute`.
- Frontend permission checks are UX-only; the backend's `StagePermissionResolver`/`can_execute` remain the security boundary — this plan does not add or change backend authorization.
- Destructive/leave confirmations use `<AlertDialog>`, never `window.confirm()` — except the native `beforeunload` prompt, which is browser-owned chrome and cannot be replaced by a custom dialog (browsers ignore custom text in `beforeunload` handlers and show their own fixed string).
- Arabic UI copy, formal MSA, RTL-first, matching this project's established tone (see exact strings per task below).
- Commit message format `type(scope): description`, scope `workflow`. Co-author line `Co-Authored-By: Claude <noreply@anthropic.com>`. All commits signed, no `--no-gpg-sign`/`--no-sign` workarounds.
- No raw HTML in place of shadcn-vue components.
- Focused verification only per task (smallest relevant Vitest file/filter); `pnpm typecheck` only for tasks touching shared types or composable contracts.

---

### Task 1: Gate wizard mode on `canExecute`, not creator identity

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue:52-57`
- Test: `frontend/app/tests/unit/pages/workflow-instance.test.ts` (locate the real file first — search `find frontend/app/tests -iname '*instance*'`; do not assume this path)

**Interfaces:**
- Consumes: `store.current.can_execute` (already present on `EngineRequest`, server-derived from `StagePermissionResolver`), `store.current.current_stage.is_initial`.
- Produces: no new exports; `wizardMode` computed's condition changes.

Current code (confirmed by reading the file in full):

```ts
const wizardMode = computed(
  () =>
    route.query.mode === 'wizard' &&
    store.current?.current_stage?.is_initial === true &&
    store.current?.created_by === auth.user?.id,
)
```

- [ ] **Step 1: Write the failing test**

Locate the real test file for this page first. Add a test asserting that a non-creator user with `can_execute: true` on the initial stage still sees the wizard (adapt to whatever store/route mocking pattern the existing test file already uses — read it in full before writing):

```ts
it('renders the wizard for a non-creator user with execute access on the initial stage', async () => {
  // Arrange: store.current with created_by !== the mocked auth user's id,
  // current_stage.is_initial = true, can_execute = true.
  // Route query: { mode: 'wizard' }.
  // Act: mount the page.
  // Assert: the wizard component (EngineRequestWizard stub) is rendered,
  // not the read-only view/act panel.
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test> -t 'non-creator user with execute access'`
Expected: FAIL — wizard does not render because `created_by` check excludes the mocked non-creator user.

- [ ] **Step 3: Change the condition**

```ts
const wizardMode = computed(
  () =>
    route.query.mode === 'wizard' &&
    store.current?.current_stage?.is_initial === true &&
    canExecute.value,
)
```

`canExecute` is already defined earlier in the same file (`const canExecute = computed(() => store.current?.can_execute === true)`) — reuse it directly, do not redeclare.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test>`
Expected: PASS, including all pre-existing tests in the file (no regression to the creator's own wizard access, since a creator with `can_execute: true` still passes the new condition).

- [ ] **Step 5: Lint and format**

Run: `cd frontend && pnpm exec eslint "app/pages/workflows/instances/[id].vue" && pnpm exec prettier "app/pages/workflows/instances/[id].vue" --check`

- [ ] **Step 6: Commit**

```bash
git add "frontend/app/pages/workflows/instances/[id].vue" frontend/app/tests/unit/pages/
git commit -m "fix(workflow): let any executor continue a draft in wizard mode, not only its creator"
```

---

### Task 2: Generalize claim-banner and claim-button copy for non-review stages

**Files:**
- Modify: `frontend/app/components/workflow/ClaimBanner.vue`
- Modify: `frontend/app/components/workflow/EngineActionsRail.vue`
- Test: `frontend/app/tests/unit/components/ClaimBanner.test.ts` (locate the real file first; create if none exists)
- Test: `frontend/app/tests/unit/components/EngineActionsRail.test.ts` (locate the real file first; extend if it exists)

**Interfaces:**
- No prop/type changes — this task only changes literal Arabic strings.

The current copy assumes a review context ("يراجع" = "is reviewing", "بدء المراجعة" = "start review"), which reads wrong once the same banner/button appear for a Data Entry user filling out a DRAFT. Both need stage-neutral phrasing since this is now shown across both wizard (drafting) and view/act (reviewing) contexts.

- [ ] **Step 1: Read both files in full**

Confirm the exact current template text before editing (`ClaimBanner.vue`'s `AlertDescription` interpolation, `EngineActionsRail.vue`'s claim `<Button>` label) — do not guess indentation/surrounding markup.

- [ ] **Step 2: Write/extend the failing tests**

```ts
// ClaimBanner.test.ts
it('renders stage-neutral working-on-request copy, not review-specific copy', () => {
  // mount with holderName="أحمد" (or the file's existing fixture name)
  // assert wrapper.text() contains 'يعمل على هذا الطلب الآن'
  // assert wrapper.text() does NOT contain 'يراجع'
})
```

```ts
// EngineActionsRail.test.ts — add to the existing claim-button test case
it('renders stage-neutral claim button copy', () => {
  // mount with showClaimButton: true
  // assert wrapper.text() contains 'المتابعة على هذا الطلب'
  // assert wrapper.text() does NOT contain 'بدء المراجعة'
})
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `cd frontend && pnpm exec vitest run <path-to-ClaimBanner-test> <path-to-EngineActionsRail-test>`
Expected: FAIL — old copy still present.

- [ ] **Step 4: Update the copy**

In `ClaimBanner.vue`, change:

```vue
{{ holderName }} يراجع هذا الطلب الآن
```

to:

```vue
{{ holderName }} يعمل على هذا الطلب الآن
```

In `EngineActionsRail.vue`, change:

```vue
<Button v-if="showClaimButton" class="w-full" :disabled="busy" @click="emit('claim')">
  بدء المراجعة
</Button>
```

to:

```vue
<Button v-if="showClaimButton" class="w-full" :disabled="busy" @click="emit('claim')">
  المتابعة على هذا الطلب
</Button>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd frontend && pnpm exec vitest run <path-to-ClaimBanner-test> <path-to-EngineActionsRail-test>`
Expected: PASS

- [ ] **Step 6: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/workflow/ClaimBanner.vue app/components/workflow/EngineActionsRail.vue && pnpm exec prettier app/components/workflow/ClaimBanner.vue app/components/workflow/EngineActionsRail.vue --check`

- [ ] **Step 7: Commit**

```bash
git add frontend/app/components/workflow/ClaimBanner.vue frontend/app/components/workflow/EngineActionsRail.vue frontend/app/tests/unit/components/
git commit -m "fix(workflow): use stage-neutral claim copy so it reads correctly during draft editing"
```

---

### Task 3: Add claim gating to wizard mode

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`
- Test: `frontend/app/tests/unit/pages/workflow-instance.test.ts` (same file as Task 1 — extend, do not duplicate)

**Interfaces:**
- Consumes: `claim`, `isHeldByMe`, `heldByOther`, `claimedBy` (already destructured from `useEngineClaim` at the top of the file — no new composable call needed), `stageRequiresClaim`, `showClaimButton`, `claimRequiredButNotHeld`, `canAct` (all already computed in this file for the view/act branch — reuse the same computed refs for wizard mode, since claim state is per-request, not per-branch).
- Produces: no new exports; the wizard's rendered branch gains claim-aware conditionals.

Today `ClaimBanner`/claim button/claim gating only render in the `v-else` (non-wizard) branch (`[id].vue:190-242`). Once Task 1 lets a non-creator reach wizard mode, two different users could both open the wizard for the same DRAFT with no lock at all. This task wires the same claim primitives already computed in this file into the wizard branch.

- [ ] **Step 1: Write the failing test**

Extend the same test file as Task 1:

```ts
it('shows the claim banner and blocks wizard editing when another user holds the claim', async () => {
  // Arrange: stage.requires_claim = true, claimed_by = a DIFFERENT user id than
  // the mocked current user, route query mode=wizard, is_initial=true, can_execute=true.
  // Act: mount the page.
  // Assert: ClaimBanner (or its stub) renders with the other user's name.
  // Assert: EngineRequestWizard is NOT rendered editable — either not mounted at
  // all while held-by-other, or mounted with a prop/attr indicating read-only,
  // matching whatever pattern this codebase already uses for locked states
  // (check EngineRequestDataTabs / the existing "read-only banner" convention
  // referenced in frontend/DESIGN.md before deciding which).
})

it('requires an explicit claim before the wizard becomes editable when the stage requires a claim', async () => {
  // Arrange: stage.requires_claim = true, claimed_by = null, can_execute = true, wizard mode.
  // Act: mount the page.
  // Assert: a claim button/prompt renders instead of (or above) the editable wizard.
  // Simulate calling the exposed claim handler; assert claim() was invoked.
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test> -t 'claim'`
Expected: FAIL — wizard currently renders unconditionally in wizard mode regardless of claim state.

- [ ] **Step 3: Wire claim gating into the wizard branch**

Read the current wizard-mode template block in full first:

```vue
<!-- Wizard mode: draft creator on the initial stage collects inputs step by step. -->
<EngineRequestWizard
  v-if="wizardMode"
  :request-id="requestId"
  :field-groups="fieldGroups"
  :version="store.current.version"
  :initial-data="formData"
  :documents="store.documents"
  @submitted="onWizardSubmitted"
/>
```

Replace with (moving `ClaimBanner` above both branches so it applies uniformly, and gating the wizard's editable rendering the same way `canAct` already gates the view/act panel's actions):

```vue
<ClaimBanner v-if="heldByOther" :holder-name="claimHolderName ?? 'مستخدم آخر'" />

<!-- Wizard mode: any executor on the initial stage continues the draft step by step. -->
<template v-if="wizardMode">
  <Card v-if="claimRequiredButNotHeld" class="border-0 shadow">
    <CardContent class="flex flex-col items-start gap-3 p-4">
      <p class="text-muted-foreground text-sm">
        يجب متابعة هذا الطلب قبل تعديله لضمان عدم تحرير مستخدمين اثنين للطلب نفسه في الوقت نفسه.
      </p>
      <Button :disabled="actionBusy" @click="startReview">المتابعة على هذا الطلب</Button>
    </CardContent>
  </Card>
  <EngineRequestWizard
    v-else-if="!heldByOther"
    :request-id="requestId"
    :field-groups="fieldGroups"
    :version="store.current.version"
    :initial-data="formData"
    :documents="store.documents"
    @submitted="onWizardSubmitted"
  />
</template>
```

Note: `ClaimBanner` was previously only rendered inside the `v-else` (non-wizard) branch (`[id].vue:176`, immediately before the wizard/view-act `v-if`/`v-else` split) — remove it from its old position since it now renders once, above both branches, covering both modes.

Add `Card`, `CardContent` imports if not already present in this file's import block (they are — confirmed via the existing `<Card class="border-0 shadow">` usage in the view/act branch) — no new import needed.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test>`
Expected: PASS, including all pre-existing tests in the file (the read-only view/act branch's own claim gating is untouched — only `ClaimBanner`'s position moved, not its trigger condition).

- [ ] **Step 5: Lint, format, typecheck**

Run: `cd frontend && pnpm exec eslint "app/pages/workflows/instances/[id].vue" && pnpm exec prettier "app/pages/workflows/instances/[id].vue" --check && pnpm typecheck`

(Typecheck required here since this task's Step 3 touches template logic referencing multiple composable-derived types together — confirm no new type errors.)

- [ ] **Step 6: Commit**

```bash
git add "frontend/app/pages/workflows/instances/[id].vue" frontend/app/tests/unit/pages/
git commit -m "feat(workflow): gate wizard-mode draft editing behind the existing claim mechanism"
```

---

### Task 4: In-app leave-guard confirmation dialog

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`
- Test: `frontend/app/tests/unit/pages/workflow-instance.test.ts` (same file as prior tasks — extend)

**Interfaces:**
- Consumes: Vue Router's `onBeforeRouteLeave` (Nuxt auto-import, no explicit import needed per this project's existing composable usage patterns — confirm via a quick check of another page using Nuxt-auto-imported router composables before assuming; if this codebase explicitly imports composables even when auto-imported elsewhere, import `onBeforeRouteLeave` from `vue-router` explicitly to match that convention).
- Produces: a `hasUnsavedWizardChanges` ref/computed exposed from `EngineRequestWizard` via `defineExpose`, consumed by the parent page to decide whether to intercept navigation.

- [ ] **Step 1: Expose dirty-state from the wizard component**

In `EngineRequestWizard.vue`, add a computed tracking whether `formData` differs from the last-saved snapshot (the data as of the last successful `saveDraft`/`submit` call):

```ts
const lastSavedData = ref<Record<string, unknown>>({ ...props.initialData })

// ... inside validateThen, after a successful action call:
async function validateThen(action: (data: Record<string, unknown>) => Promise<void>) {
  submitError.value = null
  const result = await formRef.value?.validate()
  if (result && !result.valid) return
  const data = result?.values ?? formData.value
  formData.value = { ...formData.value, ...data }
  try {
    await action(data)
    lastSavedData.value = { ...formData.value }
  } catch {
    if (!submitError.value) submitError.value = 'تعذر حفظ الخطوة. حاول مرة أخرى.'
  }
}

const hasUnsavedChanges = computed(
  () => JSON.stringify(formData.value) !== JSON.stringify(lastSavedData.value),
)

defineExpose({ hasUnsavedChanges })
```

- [ ] **Step 2: Write the failing test**

```ts
it('blocks in-app navigation with a confirm dialog when the wizard has unsaved changes', async () => {
  // Arrange: mount the page in wizard mode; simulate the wizard ref reporting
  // hasUnsavedChanges = true (via the stub's exposed property).
  // Act: trigger a simulated router navigation (invoke the onBeforeRouteLeave
  // guard directly if the test harness allows accessing it, or trigger via a
  // sidebar link click if this test file mounts with real router).
  // Assert: an AlertDialog with the leave-confirmation copy appears and the
  // navigation is not immediately completed.
})

it('does not block navigation when the wizard has no unsaved changes', async () => {
  // Arrange: hasUnsavedChanges = false (or wizardMode = false entirely).
  // Act: trigger navigation.
  // Assert: navigation proceeds without a dialog.
})
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test> -t 'navigation'`
Expected: FAIL — no leave-guard exists yet.

- [ ] **Step 4: Add the route-leave guard and confirmation dialog to `[id].vue`**

Add a template ref to the wizard, a dialog open-state ref, and the guard:

```ts
const wizardRef = ref<InstanceType<typeof EngineRequestWizard> | null>(null)
const leaveDialogOpen = ref(false)
let pendingLeave: (() => void) | null = null

function hasUnsavedWizardChanges(): boolean {
  return wizardMode.value && wizardRef.value?.hasUnsavedChanges === true
}

onBeforeRouteLeave((_to, _from, next) => {
  if (!hasUnsavedWizardChanges()) {
    next()
    return
  }
  leaveDialogOpen.value = true
  pendingLeave = () => next()
})

function confirmLeave() {
  leaveDialogOpen.value = false
  pendingLeave?.()
  pendingLeave = null
}

function cancelLeave() {
  leaveDialogOpen.value = false
  pendingLeave = null
}
```

Add the template ref to the wizard component and the confirmation dialog:

```vue
<EngineRequestWizard
  v-else-if="!heldByOther"
  ref="wizardRef"
  :request-id="requestId"
  :field-groups="fieldGroups"
  :version="store.current.version"
  :initial-data="formData"
  :documents="store.documents"
  @submitted="onWizardSubmitted"
/>
```

```vue
<AlertDialog v-model:open="leaveDialogOpen">
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>مغادرة الصفحة دون حفظ</AlertDialogTitle>
      <AlertDialogDescription>
        لديك بيانات لم تُحفظ في هذه الخطوة. سيتم فقدانها إذا غادرت الصفحة الآن دون المتابعة أو
        الإرسال.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel @click="cancelLeave">البقاء في الصفحة</AlertDialogCancel>
      <AlertDialogAction @click="confirmLeave">مغادرة دون حفظ</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

Add imports: `AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction` from `@/components/ui/alert-dialog`, `onBeforeRouteLeave` (confirm import source per Step 1's note).

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test>`
Expected: PASS

- [ ] **Step 6: Lint, format, typecheck**

Run: `cd frontend && pnpm exec eslint "app/pages/workflows/instances/[id].vue" app/components/workflow/EngineRequestWizard.vue && pnpm exec prettier "app/pages/workflows/instances/[id].vue" app/components/workflow/EngineRequestWizard.vue --check && pnpm typecheck`

- [ ] **Step 7: Commit**

```bash
git add "frontend/app/pages/workflows/instances/[id].vue" frontend/app/components/workflow/EngineRequestWizard.vue frontend/app/tests/unit/pages/
git commit -m "feat(workflow): confirm before leaving a wizard step with unsaved changes"
```

---

### Task 5: Native `beforeunload` guard for refresh/close

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`
- Test: `frontend/app/tests/unit/pages/workflow-instance.test.ts` (same file — extend)

**Interfaces:**
- Consumes: `hasUnsavedWizardChanges()` from Task 4.
- Produces: no new exports; adds a `window.addEventListener('beforeunload', ...)` lifecycle hook.

- [ ] **Step 1: Write the failing test**

```ts
it('sets preventDefault on beforeunload when the wizard has unsaved changes', async () => {
  // Arrange: mount with hasUnsavedWizardChanges() true (via the same stub state
  // used in Task 4's tests).
  // Act: dispatch a `beforeunload` Event on window (jsdom supports this) and
  // spy on event.preventDefault.
  // Assert: preventDefault was called.
})

it('does not call preventDefault on beforeunload when there are no unsaved changes', async () => {
  // Arrange: hasUnsavedWizardChanges() false.
  // Act/Assert: preventDefault not called.
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test> -t 'beforeunload'`
Expected: FAIL — no listener registered yet.

- [ ] **Step 3: Add the listener**

```ts
function handleBeforeUnload(event: BeforeUnloadEvent) {
  if (!hasUnsavedWizardChanges()) return
  event.preventDefault()
}

onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})
```

Add `onUnmounted` to the existing Vue import line if not already imported (`import { computed, ref, toRef } from 'vue'` — confirm the exact current import list before editing, add `onMounted, onUnmounted` as needed; `onMounted` is likely already used for `load()` per the file's existing `onMounted(load)` call — reuse the same `onMounted` block by adding the listener registration inside it, or keep as a second `onMounted` call; either is acceptable, prefer consolidating into the existing one to avoid two separate `onMounted` registrations for the same component).

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd frontend && pnpm exec vitest run <path-to-instance-test>`
Expected: PASS

- [ ] **Step 5: Lint, format**

Run: `cd frontend && pnpm exec eslint "app/pages/workflows/instances/[id].vue" && pnpm exec prettier "app/pages/workflows/instances/[id].vue" --check`

- [ ] **Step 6: Commit**

```bash
git add "frontend/app/pages/workflows/instances/[id].vue" frontend/app/tests/unit/pages/
git commit -m "feat(workflow): warn on browser refresh or close with unsaved wizard changes"
```

---

### Task 6: Regression test locking in step-checkpoint autosave

**Files:**
- Test: `frontend/app/tests/unit/composables/useEngineWizard.test.ts` (locate the real file first; extend if it exists, create if not)

**Interfaces:**
- Consumes: `useEngineWizard` (existing, unchanged).
- Produces: no new exports — this task is a regression guard only, confirming existing behavior the user relies on for "reload is safe."

This task adds no new production code. It exists because the "reload-safe" requirement depends entirely on `useEngineWizard.next()` already calling `cb.saveDraft(data)` before advancing `stepIndex` — a behavior that already exists (confirmed by reading `useEngineWizard.ts` in full: `next()`'s body is `await cb.saveDraft(data); if (stepIndex.value < lastIndex.value) stepIndex.value += 1`) but has no explicit test locking in the *ordering* (save-before-advance, not advance-then-save, which would let a reload between the two lose the just-entered step's data).

- [ ] **Step 1: Write the test**

```ts
it('saves the draft before advancing to the next step, not after', async () => {
  const saveDraftCalls: number[] = []
  let stepAtSaveTime = -1

  const groups = ref([
    { id: 1, sort_order: 1 /* ...other required ResolvedFieldGroup fields per fixture pattern already used in this file */ },
    { id: 2, sort_order: 2 },
  ])

  const wizard = useEngineWizard(groups, {
    saveDraft: async (_data) => {
      stepAtSaveTime = wizard.stepIndex.value
      saveDraftCalls.push(stepAtSaveTime)
    },
    submit: async () => {},
  })

  await wizard.next({})

  expect(saveDraftCalls).toEqual([0])
  expect(stepAtSaveTime).toBe(0) // saveDraft ran while still on step 0, before stepIndex advanced to 1
  expect(wizard.stepIndex.value).toBe(1)
})
```

Adapt the `ResolvedFieldGroup` fixture shape to whatever minimal fields the type actually requires (check `frontend/app/types/models.ts`'s `ResolvedFieldGroup` interface first — do not guess extra fields beyond what TypeScript requires for this test to compile).

- [ ] **Step 2: Run test to verify it passes immediately (no code change needed)**

Run: `cd frontend && pnpm exec vitest run <path-to-useEngineWizard-test> -t 'saves the draft before advancing'`
Expected: PASS on first run — this is a characterization test for existing behavior, not a red-green cycle. If it fails, that means `useEngineWizard.next()`'s actual current implementation does NOT save-before-advance (contradicting the file read during planning) — stop and report this discrepancy rather than "fixing" the test to match wrong behavior.

- [ ] **Step 3: Lint and format**

Run: `cd frontend && pnpm exec eslint <path-to-useEngineWizard-test> && pnpm exec prettier <path-to-useEngineWizard-test> --check`

- [ ] **Step 4: Commit**

```bash
git add frontend/app/tests/unit/composables/
git commit -m "test(workflow): lock in save-before-advance ordering in the wizard step composable"
```

---

### Task 7: Manual `playwright-cli` verification pass

**Files:** none (verification only, plus one manual designer data-config change)

**Interfaces:** none

- [ ] **Step 1: Toggle `requires_claim` on the initial stage via the designer UI**

Using `playwright-cli`, log in as CBY Admin, navigate to `/admin/workflows`, select the live "تمويل الواردات" definition's currently-published version, open the "المراحل" tab, edit the "إنشاء الطلب" (initial) stage, and enable the "مطالبة" (`requires_claim`) switch. Save.

- [ ] **Step 2: Verify non-creator wizard access**

Log in as a Data Entry user in the same bank as an existing DRAFT request's creator (but not the creator). Navigate to `/workflows/instances/{id}?mode=wizard` for that DRAFT. Confirm the wizard renders (not a blank/read-only fallback) — this verifies Task 1.

- [ ] **Step 3: Verify claim gating in wizard mode**

While still as the non-creator user, confirm a claim prompt/button renders before the form becomes editable (per Task 3's `claimRequiredButNotHeld` branch). Claim it. Confirm the form becomes editable.

In a second browser context (or after logging in as yet another same-bank Data Entry user), navigate to the same DRAFT's wizard URL. Confirm the `ClaimBanner` renders with the first user's name and the wizard form is not shown editable (per Task 3's `heldByOther` branch).

- [ ] **Step 4: Verify in-app leave-guard**

As the claim-holder, start filling a wizard step's fields (do not click "حفظ ومتابعة"). Click a sidebar link (e.g. "لوحة التحكم"). Confirm the `AlertDialog` from Task 4 appears with the correct Arabic copy. Click "البقاء في الصفحة" — confirm navigation does not proceed. Click the sidebar link again, then "مغادرة دون حفظ" — confirm navigation proceeds.

- [ ] **Step 5: Verify step-checkpoint autosave / reload safety**

Fill a wizard step's fields and click "حفظ ومتابعة" to advance. Reload the page (`playwright-cli` reload or re-`goto` the same URL). Confirm the previously-saved step's data is still present when returning to that step (via "السابق"), and the wizard resumes at the correct step index — verifies Task 6's characterized behavior end-to-end, live.

- [ ] **Step 6: Verify native beforeunload (best-effort)**

`playwright-cli`/Chromium test automation typically cannot observe the native `beforeunload` browser dialog directly (browsers suppress or auto-dismiss it under automation in most configurations) — if the tool cannot demonstrate this step interactively, note the limitation and rely on Task 5's Vitest coverage (`event.preventDefault()` called) as the primary verification for this specific behavior instead of a live browser check.

- [ ] **Step 7: Release the claim and confirm the banner clears**

Release the claim (via the existing release action, if exposed in this UI, or by waiting for TTL expiry in a non-time-sensitive check — prefer an explicit release path if the UI exposes one). Confirm `ClaimBanner` disappears for other viewers and the wizard becomes enterable again.

No commit for this task — it is a verification pass only. Report results, including the Step 6 limitation if encountered.
