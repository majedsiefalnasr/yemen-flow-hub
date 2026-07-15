# Admin Workflows Impeccable Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve all P1 and P2 `/admin/workflows` audit findings without changing backend contracts.

**Architecture:** Keep lifecycle eligibility as computed frontend presentation state derived from existing workflow version resources. Make each audited component own its local read-only, reactive, accessibility, and scope behavior while retaining current composables and APIs.

**Tech Stack:** Nuxt 4, Vue 3.5, TypeScript, shadcn-vue, Tailwind CSS v4, Vue Flow, Vitest, Vue Test Utils, Playwright CLI.

## Global Constraints

- Only versions with `state === 'DRAFT'` and `is_editable === true` are editable.
- Do not change backend, API resource, database, policy, or lifecycle contracts.
- Do not invent request-count or frontend request-usage eligibility data.
- Keep request-linked archived-version deletion under backend authority.
- Use shadcn-vue primitives and semantic theme variables.
- Preserve RTL behavior and compact desktop density.
- Do not execute persistent workflow mutations against live data.
- Preserve unrelated working-tree changes and never stage `graphify-out/`.

---

### Task 1: Draft-only fields and state-aware deletion actions

**Files:**

- Modify: `frontend/app/components/workflow/WorkflowFieldDesigner.vue`
- Modify: `frontend/app/pages/admin/workflows.vue`
- Test: `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`
- Test: `frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts`

**Interfaces:**

- Consumes: `WorkflowVersion.state`, `WorkflowVersion.is_editable`, `WorkflowDefinition.versions`.
- Produces: local computed `editable`, `canDeleteSelectedVersion`, and `canDeleteSelectedDefinition` presentation state.

- [ ] **Step 1: Add failing field-designer tests**

Add behavior tests that mount editable DRAFT, PUBLISHED, and ARCHIVED versions and assert that ordering buttons and editable instructions exist only for the editable draft.

- [ ] **Step 2: Run the field-designer test and verify RED**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts`

Expected: read-only ordering and copy assertions fail against the current component.

- [ ] **Step 3: Implement minimal draft-only field controls**

Use `computed(() => props.version.state === 'DRAFT' && props.version.is_editable)`, render ordering controls only when true, and branch instructional copy between editable and read-only wording.

- [ ] **Step 4: Add failing page deletion-matrix tests**

Cover these MANAGE-user scenarios:

```text
PUBLISHED version: hide Delete version and Delete workflow
ARCHIVED-only definition: show Delete version, hide Delete workflow
DRAFT-only definition: show Delete version and Delete workflow
VIEW-only user: hide both actions in every state
```

- [ ] **Step 5: Run the page test and verify RED**

Run: `pnpm exec vitest run app/tests/unit/pages/WorkflowDesignerPage.test.ts`

Expected: current unconditional MANAGE deletion actions fail the state matrix.

- [ ] **Step 6: Implement state-aware destructive action visibility**

Add computed booleans in `workflows.vue`:

```ts
const canDeleteSelectedVersion = computed(
  () =>
    selectedVersion.value !== null &&
    selectedVersion.value.state !== "PUBLISHED",
);
const canDeleteSelectedDefinition = computed(
  () =>
    selectedDefinition.value !== null &&
    selectedDefinition.value.versions.every(
      (version) => version.state === "DRAFT",
    ),
);
```

Combine each condition with its existing `ScreenGuard`; keep confirmation and backend calls unchanged.

- [ ] **Step 7: Run both focused tests and verify GREEN**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts app/tests/unit/pages/WorkflowDesignerPage.test.ts`

Expected: both files pass.

### Task 2: Reactive publish-panel state

**Files:**

- Modify: `frontend/app/components/workflow/WorkflowPublishPanel.vue`
- Test: `frontend/app/tests/unit/components/WorkflowPublishPanel.test.ts`

**Interfaces:**

- Consumes: reactive `version` prop and existing `validateVersion` and `publishVersion` composable methods.
- Produces: reactive draft visibility and version-scoped transient validation state.

- [ ] **Step 1: Add failing reactive-switch tests**

Add tests that use `wrapper.setProps()` for PUBLISHED to DRAFT, DRAFT to PUBLISHED, and DRAFT to ARCHIVED. Validate that actions appear or disappear immediately and prior validation results are cleared.

- [ ] **Step 2: Add a failing stale-validation test**

Resolve a deferred validation response after changing the version prop and assert that the old response does not enable publishing for the new version.

- [ ] **Step 3: Run the publish-panel test and verify RED**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowPublishPanel.test.ts`

Expected: prop switching and stale-response assertions fail; the existing success-copy assertion also fails.

- [ ] **Step 4: Implement reactive and version-scoped state**

Replace the setup-time boolean with a computed draft check. Watch `[version.id, version.state, version.is_editable]`, clear validation state and the confirmation dialog, and invalidate prior validation operations with a monotonically increasing local token.

- [ ] **Step 5: Keep intended success copy aligned**

Update the stale assertion to `النسخة جاهزة للنشر`, matching the rendered product copy.

- [ ] **Step 6: Run the focused test and verify GREEN**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowPublishPanel.test.ts`

Expected: all publish-panel tests pass without warnings.

### Task 3: Canvas performance, accessibility, layout, and controls

**Files:**

- Modify: `frontend/app/components/workflow/WorkflowCanvas.vue`
- Test: `frontend/app/tests/unit/components/WorkflowCanvas.test.ts`

**Interfaces:**

- Consumes: existing Vue Flow node/edge props and workflow transition composable methods.
- Produces: stable raw node registry, selected transition state, accessible edge and control behavior, and responsive fit behavior.

- [ ] **Step 1: Fetch current Vue Flow documentation**

Run Context7 for the installed Vue Flow version, covering `markRaw` node types, edge `interactionWidth`, focusability, accessible labels, edge-click events, and `fitView` options. Verify every used property against the installed package declarations.

- [ ] **Step 2: Extend the Vue Flow test stub and add failing accessibility tests**

Expose passed nodes and edges from the stub, emit `edge-click`, and assert:

```text
editable edge: selectable, focusable, named, wide hit area
read-only edge: not selectable or focusable
edge selection: shows explicit Edit transition and Delete transition controls
zoom controls: render as shared Button components with Arabic accessible names
```

- [ ] **Step 3: Run the canvas test and verify RED**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowCanvas.test.ts`

Expected: current edges and raw zoom buttons fail the new assertions.

- [ ] **Step 4: Implement stable node registration and edge state**

Import `markRaw`, register `StageNode` in a stable non-reactive object, add `selectedTransitionId`, and derive the selected transition from existing transition data.

- [ ] **Step 5: Implement reliable pointer and keyboard interaction**

Give editable edges a documented wider hit area and accessible label. Disable edge affordances for read-only versions. Route pointer edge selection and the keyboard-accessible transition selector to the same selected state.

Reuse the controlled transition dialog for edit mode with `updateTransition`. Replace native `confirm` deletion with the existing AlertDialog pattern.

- [ ] **Step 6: Improve fit and layout**

Reduce excessive vertical gaps, increase the canvas to a responsive viewport-aware height, and fit after graph load/auto-arrange with documented padding and zoom bounds. Keep node positions session-local.

- [ ] **Step 7: Replace raw canvas controls and hardcoded color**

Render zoom and fit actions with shared `Button` components. Use mobile `h-11 w-11` and compact `md:h-8 md:w-8` sizing. Replace `#0066cc` with the existing brand or primary semantic variable.

- [ ] **Step 8: Run the canvas test and verify GREEN**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowCanvas.test.ts`

Expected: all executable canvas tests pass; the existing Vue Flow real-render todo remains documented.

### Task 4: Global Actions Catalog clarification and mobile targets

**Files:**

- Modify: `frontend/app/components/workflow/WorkflowActionsCatalog.vue`
- Modify: `frontend/app/pages/admin/workflows.vue`
- Test: `frontend/app/tests/unit/components/WorkflowActionsCatalog.test.ts`
- Test: `frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts`

**Interfaces:**

- Consumes: existing Card, Badge, Alert, Button, Select, and tab primitives.
- Produces: explicit global-scope messaging and responsive workflow control sizing.

- [ ] **Step 1: Add failing catalog-scope test**

Assert that the catalog identifies itself as global and version-independent and warns that changes can affect other workflows.

- [ ] **Step 2: Run the catalog test and verify RED**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowActionsCatalog.test.ts`

Expected: global-scope copy is absent.

- [ ] **Step 3: Add app-native global-scope treatment**

Add a secondary `عام` badge and a restrained informational Alert inside the catalog header. Keep existing action APIs and dialogs unchanged.

- [ ] **Step 4: Apply responsive touch sizing**

Add mobile minimum-height classes to the workflow page's selectors, view switch, tabs, and lifecycle actions, returning to current compact sizes at `md`.

- [ ] **Step 5: Run catalog and page tests and verify GREEN**

Run: `pnpm exec vitest run app/tests/unit/components/WorkflowActionsCatalog.test.ts app/tests/unit/pages/WorkflowDesignerPage.test.ts`

Expected: both files pass.

### Task 5: Guard review, focused verification, browser QA, and re-audit

**Files:**

- Review all files changed by Tasks 1 through 4.

**Interfaces:**

- Consumes: completed implementation and focused tests.
- Produces: verified frontend remediation and refreshed Impeccable score.

- [ ] **Step 1: Run the complete focused suite**

Run:

```bash
pnpm exec vitest run \
  app/tests/unit/pages/WorkflowDesignerPage.test.ts \
  app/tests/unit/components/WorkflowCanvas.test.ts \
  app/tests/unit/components/WorkflowFieldDesigner.test.ts \
  app/tests/unit/components/WorkflowPublishPanel.test.ts \
  app/tests/unit/components/WorkflowStageEditor.test.ts \
  app/tests/unit/components/WorkflowTransitionEditor.test.ts \
  app/tests/unit/components/WorkflowActionsCatalog.test.ts
```

Expected: all executable tests pass; only documented Vue Flow/shadcn limitations may remain skipped or todo.

- [ ] **Step 2: Run touched-file lint and format checks**

Run ESLint and Prettier `--check` against every touched Vue and test file. Expected: exit 0 with zero warnings.

- [ ] **Step 3: Run typecheck only if contracts changed**

If no shared types or cross-module contracts changed, record typecheck as not required under AGENTS.md. Otherwise run `pnpm typecheck` and require exit 0.

- [ ] **Step 4: Apply Clean Code, Test Guard, and Docs Guard reviews**

Review only the changed production code, tests, spec, and plan. Fix any must-fix findings, then rerun the affected checks.

- [ ] **Step 5: Refresh local code intelligence**

Run `graphify update .` without staging `graphify-out/`. Run SocratiCode flow for any new public method; no flow call is required if no public method is added.

- [ ] **Step 6: Verify as System Admin with Playwright CLI**

Verify all five tabs, detail/canvas views, published and archived deletion visibility, read-only fields, canvas fit/controls/console, Actions Catalog scope, keyboard operation, and 390px containment. Use safe route mocking for draft only if needed; perform no live workflow mutations.

- [ ] **Step 7: Re-run the Impeccable audit**

Re-score Accessibility, Performance, Responsive, Theming, and Anti-Patterns. Report remaining P0-P3 findings, changed files, test/check results, browser coverage, and any live-data limitations.

## Follow-up Debt

- **P3 cosmetic, intentionally deferred:** Replace the read-only notice's side-stripe treatment and legacy em-dash copy in a separate visual-polish pass. This has no functional or accessibility impact and is outside the approved P1/P2 remediation scope.
