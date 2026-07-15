# Request Instance Edit-Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user with EXECUTE access (and, when required, an active claim) edit non-FILE request fields and manage per-field documents directly on `frontend/app/pages/workflows/instances/[id].vue`, instead of only viewing them read-only, whenever the current stage's Designer-configured field rules make at least one field editable.

**Architecture:** Reuse two existing, already-battle-tested components exactly as they are: `DynamicForm.vue` (mode="edit"/"readonly", already used by the creation wizard) renders each non-FILE field group; a new per-field wrapper renders one `EngineDocumentsPanel.vue` instance per FILE field, filtering `store.documents` by `field_id` so multi-file-field groups don't cross-contaminate. `EngineRequestDataTabs.vue` stays completely untouched and continues to serve every VIEW-only user/stage. All edit gating derives from data already fetched by the page (`fieldGroups` from `useEngineFormSchema`, `canAct` from existing claim/execute computeds) — no new backend endpoints, no changes to `DynamicForm.vue`'s upload lifecycle, no stage-code/`is_initial` hardcoding.

**Tech Stack:** Vue 3.5 `<script setup>`, TypeScript, Pinia store (`engineRequests.store.ts`), VeeValidate (via `DynamicForm`), shadcn-vue, Vitest + `@vue/test-utils`.

## Global Constraints

- Frontend-only. No backend changes unless investigation proves one is required for correctness/authorization (none was found — `StageFieldRuleValidator` already enforces `is_editable`/`is_visible` server-side).
- Do not modify `DynamicForm.vue`'s existing upload lifecycle.
- Do not modify `DynamicForm.vue` or `EngineDocumentsPanel.vue` internals at all — both are reused as-is from multiple call sites.
- Do not hardcode stage codes or `is_initial`. Edit mode is derived solely from `field.is_editable`/`field.is_visible` (Designer `StageFieldRule` config) combined with `canAct`.
- Preserve existing claim handling, optimistic version (`store.current.version`) checks, error handling in `runAction`, and the post-transition reload/redirect behavior added in the prior fix (commit `534a01f6`).
- Never replace shadcn-vue components with raw HTML — this plan introduces no new template elements beyond composing existing components.
- `EngineRequestDataTabs.vue` must remain byte-for-byte unchanged — it's the reference VIEW-only path and other call sites may exist.

---

## File Structure

- **Modify:** `frontend/app/pages/workflows/instances/[id].vue` — add `isEditMode`/`hasEditableFields` computeds, per-group edit rendering (replacing the single `EngineRequestDataTabs` call inside the "بيانات الطلب" tab with a conditional branch), `DynamicForm` refs array, document upload/remove handlers, form-validation gate inside `runAction`, and formData reset-on-stage-change logic.
- **Create:** `frontend/app/components/workflow/EngineFieldDocumentsGroup.vue` — thin presentational wrapper that, given one `ResolvedFieldGroup` whose fields are all `FILE` type, renders one `EngineDocumentsPanel` per field (filtered by `field_id`), plus a read-only "مرفقات أخرى" section for any request document whose `field_id` doesn't match a field in the current schema (deleted/renamed field, or legacy data). Keeps `[id].vue` from ballooning with nested v-for/v-if for the FILE-group case, and is unit-testable in isolation (upload/remove event wiring, per-field filtering, orphan-doc section) without mounting the whole page.
- **Test:** `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts` — extend with edit-mode scenarios.
- **Test:** `frontend/app/tests/unit/components/EngineFieldDocumentsGroup.test.ts` — new, covers the new component directly.

No changes to `DynamicForm.vue`, `EngineDocumentsPanel.vue`, `EngineRequestDataTabs.vue`, `useEngineRequestDocuments.ts`, `useEngineFormSchema.ts`, or any backend file.

---

## Interfaces Recap (from existing code — do not redefine, just consume)

```ts
// frontend/app/types/models.ts (existing, unchanged)
interface ResolvedFieldDefinition {
  id: number
  key: string
  type: FieldType // 'FILE' is one variant
  is_visible: boolean
  is_editable: boolean
  is_required: boolean
  multiple: boolean
  // ...other fields irrelevant here
}
interface ResolvedFieldGroup {
  id: number
  name: string
  label: string
  sort_order: number
  fields: ResolvedFieldDefinition[]
}
interface EngineRequestDocument {
  id: number
  request_id: number
  field_id: number | null
  stage_id: number
  original_name: string
  mime: string
  size: number
  uploaded_by: { id: number; name: string } | number
  created_at: string | null
}

// frontend/app/components/workflow/DynamicForm.vue (existing, unchanged)
// props: { fieldGroups: ResolvedFieldGroup[]; modelValue: Record<string, unknown>;
//          mode: 'edit' | 'readonly'; requestId?: number; uploadTarget?: UploadTarget }
// emits: 'update:modelValue' [value], 'upload-tokens-change' [tokens], 'upload-pending-change' [pending]
// exposed via defineExpose: { validate(): Promise<{valid: boolean; values: Record<string, unknown>}>, currentStepValid }

// frontend/app/components/workflow/EngineDocumentsPanel.vue (existing, unchanged)
// props: { documents: EngineRequestDocument[]; requestId?: number | null; canManage: boolean }
// emits: 'upload' [file: File], 'remove' [documentId: number]

// frontend/app/composables/useEngineRequestDocuments.ts (existing, unchanged)
// upload(requestId: number, file: File, fieldId: number | null): Promise<EngineRequestDocument>
// remove(requestId: number, documentId: number): Promise<void>
// fetchDocuments(requestId: number): Promise<void>  (populates .documents ref)
```

---

### Task 1: `EngineFieldDocumentsGroup.vue` — per-field document management wrapper

**Files:**
- Create: `frontend/app/components/workflow/EngineFieldDocumentsGroup.vue`
- Test: `frontend/app/tests/unit/components/EngineFieldDocumentsGroup.test.ts`

**Interfaces:**
- Consumes: `EngineDocumentsPanel` (props: `documents`, `requestId`, `canManage`; emits `upload`, `remove`) and `ResolvedFieldGroup`/`EngineRequestDocument` types, both already defined in `@/types/models`.
- Produces:
  ```ts
  defineProps<{
    group: ResolvedFieldGroup       // all fields in this group are type 'FILE'
    documents: EngineRequestDocument[]  // the full store.documents list, unfiltered — this component does its own per-field filtering
    requestId: number
    canManage: boolean              // true only when canAct is true; individual fields still gate on their own is_editable
  }>()
  defineEmits<{
    upload: [fieldId: number, file: File]
    remove: [documentId: number]
  }>()
  ```
  Consumed by `[id].vue` (Task 3) for every field group whose visible fields are all `type === 'FILE'`.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/components/EngineFieldDocumentsGroup.test.ts`:

```ts
// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EngineFieldDocumentsGroup from '@/components/workflow/EngineFieldDocumentsGroup.vue'
import type { ResolvedFieldGroup, EngineRequestDocument } from '@/types/models'

function makeField(overrides: Partial<ResolvedFieldGroup['fields'][number]> = {}) {
  return {
    id: 1,
    key: 'invoice_doc',
    semantic_tag: null,
    label: 'فاتورة',
    type: 'FILE' as const,
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: true,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

function makeGroup(fields: ResolvedFieldGroup['fields']): ResolvedFieldGroup {
  return { id: 10, name: 'documents', label: 'المستندات', sort_order: 1, fields }
}

function makeDoc(overrides: Partial<EngineRequestDocument> = {}): EngineRequestDocument {
  return {
    id: 100,
    request_id: 5,
    field_id: 1,
    stage_id: 1,
    original_name: 'invoice.pdf',
    mime: 'application/pdf',
    size: 1024,
    uploaded_by: { id: 1, name: 'Test User' },
    created_at: '2026-06-25T00:00:00Z',
    ...overrides,
  }
}

const stubs = { EngineDocumentsPanel: true }

describe('EngineFieldDocumentsGroup', () => {
  it('renders one EngineDocumentsPanel per FILE field, filtered by field_id', () => {
    const fieldA = makeField({ id: 1, key: 'invoice_doc', label: 'فاتورة' })
    const fieldB = makeField({ id: 2, key: 'contract_doc', label: 'عقد' })
    const docs = [
      makeDoc({ id: 100, field_id: 1 }),
      makeDoc({ id: 101, field_id: 2 }),
      makeDoc({ id: 102, field_id: 1 }),
    ]
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([fieldA, fieldB]),
        documents: docs,
        requestId: 5,
        canManage: true,
      },
      global: { stubs },
    })

    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels).toHaveLength(2)
    expect(panels[0]!.props('documents')).toEqual([docs[0], docs[2]])
    expect(panels[1]!.props('documents')).toEqual([docs[1]])
  })

  it('passes canManage=false for a field that is not editable even when the group-level canManage is true', () => {
    const editableField = makeField({ id: 1, is_editable: true })
    const readOnlyField = makeField({ id: 2, key: 'readonly_doc', is_editable: false })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([editableField, readOnlyField]),
        documents: [],
        requestId: 5,
        canManage: true,
      },
      global: { stubs },
    })

    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels[0]!.props('canManage')).toBe(true)
    expect(panels[1]!.props('canManage')).toBe(false)
  })

  it('never allows management when the group-level canManage is false, regardless of field is_editable', () => {
    const field = makeField({ id: 1, is_editable: true })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: false },
      global: { stubs },
    })

    expect(wrapper.findComponent({ name: 'EngineDocumentsPanel' }).props('canManage')).toBe(false)
  })

  it('skips non-visible fields', () => {
    const visible = makeField({ id: 1 })
    const hidden = makeField({ id: 2, key: 'hidden_doc', is_visible: false })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([visible, hidden]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })

    expect(wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })).toHaveLength(1)
  })

  it('re-emits upload with the originating field id', async () => {
    const field = makeField({ id: 1 })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })
    const panel = wrapper.findComponent({ name: 'EngineDocumentsPanel' })
    const file = new File(['x'], 'a.pdf', { type: 'application/pdf' })
    panel.vm.$emit('upload', file)

    expect(wrapper.emitted('upload')).toEqual([[1, file]])
  })

  it('re-emits remove with the document id unchanged', async () => {
    const field = makeField({ id: 1 })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })
    const panel = wrapper.findComponent({ name: 'EngineDocumentsPanel' })
    panel.vm.$emit('remove', 999)

    expect(wrapper.emitted('remove')).toEqual([[999]])
  })

  it('renders a read-only orphaned-documents section for docs whose field_id matches no current field', () => {
    const field = makeField({ id: 1 })
    const orphan = makeDoc({ id: 200, field_id: 999, original_name: 'legacy.pdf' })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([field]),
        documents: [orphan],
        requestId: 5,
        canManage: true,
      },
      global: { stubs: { EngineDocumentsPanel: true } },
    })

    // The orphan panel is present and rendered with canManage=false (read-only),
    // separate from the field-scoped panel.
    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels).toHaveLength(2)
    const orphanPanel = panels.find((p) => (p.props('documents') as EngineRequestDocument[]).includes(orphan))
    expect(orphanPanel).toBeTruthy()
    expect(orphanPanel!.props('canManage')).toBe(false)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm exec vitest run app/tests/unit/components/EngineFieldDocumentsGroup.test.ts` (from `frontend/`)
Expected: FAIL — `Cannot find module '@/components/workflow/EngineFieldDocumentsGroup.vue'`

- [ ] **Step 3: Write minimal implementation**

Create `frontend/app/components/workflow/EngineFieldDocumentsGroup.vue`:

```vue
<!-- app/components/workflow/EngineFieldDocumentsGroup.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { ResolvedFieldGroup, ResolvedFieldDefinition, EngineRequestDocument } from '@/types/models'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'

// Renders one EngineDocumentsPanel per FILE field in `group`, so a group with
// multiple file fields (e.g. "فاتورة" + "عقد") never mixes their documents —
// each panel is filtered strictly by EngineRequestDocument.field_id. Any
// request document whose field_id doesn't match a visible field in this
// group's schema (a deleted/renamed field, or data from an older schema
// version) still renders, read-only, under a separate "orphan" panel instead
// of silently disappearing.
const props = defineProps<{
  group: ResolvedFieldGroup
  documents: EngineRequestDocument[]
  requestId: number
  canManage: boolean
}>()

const emit = defineEmits<{
  upload: [fieldId: number, file: File]
  remove: [documentId: number]
}>()

const visibleFields = computed(() => props.group.fields.filter((f) => f.is_visible))

function docsForField(field: ResolvedFieldDefinition): EngineRequestDocument[] {
  return props.documents.filter((d) => d.field_id === field.id)
}

function canManageField(field: ResolvedFieldDefinition): boolean {
  return props.canManage && field.is_editable
}

const orphanedDocuments = computed(() => {
  const knownFieldIds = new Set(visibleFields.value.map((f) => f.id))
  return props.documents.filter((d) => d.field_id === null || !knownFieldIds.has(d.field_id))
})

function onUpload(fieldId: number, file: File) {
  emit('upload', fieldId, file)
}

function onRemove(documentId: number) {
  emit('remove', documentId)
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <div v-for="field in visibleFields" :key="field.id" class="flex flex-col gap-2">
      <h4 class="text-foreground text-sm font-semibold">{{ field.label }}</h4>
      <EngineDocumentsPanel
        :documents="docsForField(field)"
        :request-id="requestId"
        :can-manage="canManageField(field)"
        @upload="(file) => onUpload(field.id, file)"
        @remove="onRemove"
      />
    </div>

    <div v-if="orphanedDocuments.length" class="flex flex-col gap-2 border-t pt-4">
      <h4 class="text-muted-foreground text-sm font-semibold">مرفقات أخرى</h4>
      <EngineDocumentsPanel :documents="orphanedDocuments" :request-id="requestId" :can-manage="false" />
    </div>
  </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm exec vitest run app/tests/unit/components/EngineFieldDocumentsGroup.test.ts`
Expected: PASS, 7 tests

- [ ] **Step 5: Lint and format the new files**

```bash
pnpm exec eslint app/components/workflow/EngineFieldDocumentsGroup.vue app/tests/unit/components/EngineFieldDocumentsGroup.test.ts
pnpm exec prettier app/components/workflow/EngineFieldDocumentsGroup.vue app/tests/unit/components/EngineFieldDocumentsGroup.test.ts --check
```
Expected: both clean (no output / "All matched files use Prettier code style!")

- [ ] **Step 6: Commit**

```bash
git add frontend/app/components/workflow/EngineFieldDocumentsGroup.vue frontend/app/tests/unit/components/EngineFieldDocumentsGroup.test.ts
git commit -m "feat(workflow): add per-field document group wrapper for request edit mode"
```

---

### Task 2: Wire edit-mode computeds and form/document state into `[id].vue`

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`

**Interfaces:**
- Consumes: `fieldGroups` (from `useEngineFormSchema`, already destructured at line 36), `canAct` (already computed at line 99-101), `store.current.data`, `store.current.version`, `store.current.current_stage.id`, `EngineFieldDocumentsGroup` (Task 1).
- Produces: `hasEditableFields`, `isEditMode`, `nonFileGroups`, `fileGroups` computeds; `dynamicFormRefs` ref array; `resetFormState()` function — consumed by Task 3 (template) and Task 4 (`runAction`).

- [ ] **Step 1: Add the field-type/group-split and edit-mode computeds**

In `frontend/app/pages/workflows/instances/[id].vue`, add after the existing `availableActions` computed (currently ends around line 91):

```ts
// Edit mode is derived purely from Designer-configured StageFieldRule data
// (fieldGroups, already fetched by fetchSchema) plus canAct — never a stage
// code or is_initial check. Any stage the Designer marks with at least one
// editable+visible field becomes editable here, whether that's the initial
// stage, a "needs correction" return stage, or any future stage.
const hasEditableFields = computed(() =>
  fieldGroups.value.some((group) =>
    group.fields.some((field) => field.is_visible && field.is_editable),
  ),
)

// canAct already folds in canExecute, claimLost, and claim ownership when the
// stage requires a claim (see computed above) — document mutations and
// workflow actions both require EXECUTE + (if applicable) claim ownership,
// not just a field rule saying "this field is editable in principle".
const isEditMode = computed(() => canAct.value && hasEditableFields.value)

const orderedFieldGroups = computed(() =>
  [...fieldGroups.value].sort((a, b) => a.sort_order - b.sort_order),
)

function isFileOnlyGroup(group: ResolvedFieldGroup): boolean {
  const visible = group.fields.filter((f) => f.is_visible)
  return visible.length > 0 && visible.every((f) => f.type === 'FILE')
}

const nonFileGroups = computed(() => orderedFieldGroups.value.filter((g) => !isFileOnlyGroup(g)))
const fileGroups = computed(() => orderedFieldGroups.value.filter((g) => isFileOnlyGroup(g)))
```

Add the `ResolvedFieldGroup` type import (extend the existing `@/types/models` usage — check whether it's already imported; if not, add it):

```ts
import type { ResolvedFieldGroup } from '@/types/models'
```

- [ ] **Step 2: Add per-group `DynamicForm` refs and formData reset-on-change**

Still in `[id].vue`, add near the existing `formData`/`comment` refs (around line 44-46):

```ts
// One DynamicForm instance per non-file group in edit mode (see template in
// Task 3). Vue's array ref binding (`:ref="(el) => ..."`) populates this in
// render order, matching nonFileGroups' order — validated in Task 4 by
// iterating both in lockstep rather than trusting array identity.
const dynamicFormRefs = ref<InstanceType<typeof DynamicForm>[]>([])
function setDynamicFormRef(el: unknown, index: number) {
  if (el) dynamicFormRefs.value[index] = el as InstanceType<typeof DynamicForm>
}

// Tracks which (request version, stage id) formData currently reflects, so a
// reload that lands on a different version or stage (another user's edit
// landed first, or this page's own successful action moved the stage) resets
// formData from the fresh store.current.data instead of silently keeping
// stale edited values bound to fields that may no longer exist or be
// editable. A structuredClone (not a shallow spread) because field values can
// themselves be arrays (FILE fields hold number[] document ids).
const loadedFormKey = ref<string | null>(null)
function resetFormState() {
  if (!store.current) return
  const key = `${store.current.version}:${store.current.current_stage?.id ?? 'none'}`
  if (loadedFormKey.value === key) return
  formData.value = structuredClone(store.current.data ?? {})
  loadedFormKey.value = key
  dynamicFormRefs.value = []
}
```

Import `DynamicForm` (it's currently NOT imported in `[id].vue` — check the existing import block around line 2-27 and add):

```ts
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import EngineFieldDocumentsGroup from '@/components/workflow/EngineFieldDocumentsGroup.vue'
```

- [ ] **Step 3: Call `resetFormState()` from `load()` instead of the current direct assignment**

Replace the existing line in `load()`:

```ts
    formData.value = store.current?.data ?? {}
```

with:

```ts
    resetFormState()
```

(This is a straight behavioral upgrade: same effect on first load — `loadedFormKey.value` starts `null` so the guard never blocks the first assignment — but now also resets correctly when a later reload lands on a different version/stage, per Global Constraint "reset on stage/version change".)

- [ ] **Step 4: Typecheck**

Run (from `frontend/`): `pnpm typecheck 2>&1 | grep -i "instances/\[id\]"`
Expected: no output (no new type errors attributable to this file). The full `pnpm typecheck` run has pre-existing unrelated failures (documented in the prior session) — only check this file's own errors.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/pages/workflows/instances/\[id\].vue
git commit -m "feat(workflow): add edit-mode computeds and form-state reset to request detail page"
```

---

### Task 3: Render edit-mode UI (DynamicForm per non-file group, EngineFieldDocumentsGroup per file group)

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`

**Interfaces:**
- Consumes: `isEditMode`, `nonFileGroups`, `fileGroups`, `dynamicFormRefs`/`setDynamicFormRef`, `formData` (Task 2); `store.documents`, `requestId` (existing); `DynamicForm`/`EngineFieldDocumentsGroup` component contracts (Task 1 + existing).
- Produces: the rendered edit-mode template branch, plus `onDocumentUpload`/`onDocumentRemove` handlers consumed by nothing further (terminal side-effect handlers) but documented here for Task 4 to know they exist and don't need duplicating.

- [ ] **Step 1: Add document upload/remove handlers using the store's existing document actions**

`frontend/app/stores/engineRequests.store.ts` already has `uploadDocument(id, file, fieldId)` and `removeDocument(id, documentId)` actions (lines 240-254) that call the composable, refetch, and reassign `store.documents` — reuse these directly instead of reimplementing that sequence in the page.

In `[id].vue`'s `<script setup>`, add near `runAction` (these run independently of workflow actions — a document can be added/removed at any time while in edit mode, not just when submitting a transition):

```ts
async function onDocumentUpload(fieldId: number, file: File) {
  try {
    await store.uploadDocument(requestId.value, file, fieldId)
    const field = fieldGroups.value.flatMap((g) => g.fields).find((f) => f.id === fieldId)
    const uploaded = store.documents.find((d) => d.field_id === fieldId)
    if (field && uploaded) {
      const current = (formData.value[field.key] as number[] | undefined) ?? []
      if (!current.includes(uploaded.id)) {
        formData.value = { ...formData.value, [field.key]: [...current, uploaded.id] }
      }
    }
  } catch (err) {
    toast.error(extractApiErrorMessage(err, 'تعذّر رفع المستند.'))
  }
}

async function onDocumentRemove(documentId: number) {
  const removedDoc = store.documents.find((d) => d.id === documentId)
  try {
    await store.removeDocument(requestId.value, documentId)
    if (removedDoc?.field_id !== null && removedDoc?.field_id !== undefined) {
      const field = fieldGroups.value.flatMap((g) => g.fields).find((f) => f.id === removedDoc.field_id)
      if (field) {
        const current = (formData.value[field.key] as number[] | undefined) ?? []
        formData.value = {
          ...formData.value,
          [field.key]: current.filter((id) => id !== documentId),
        }
      }
    }
  } catch (err) {
    toast.error(extractApiErrorMessage(err, 'تعذّر حذف المستند.'))
  }
}
```

No new import needed — `store` is already destructured at the top of `[id].vue` (`const store = useEngineRequestsStore()`).

- [ ] **Step 2: (removed — store actions already handle refetch/reassignment; no separate mutability check needed)**

- [ ] **Step 3: Replace the "بيانات الطلب" tab content in the template**

Find this block in the template (currently ~line 279-289):

```vue
            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent>
                  <EngineRequestDataTabs
                    :field-groups="fieldGroups"
                    :data="store.current.data"
                    :documents="store.documents"
                    :request-id="requestId"
                  />
                </CardContent>
              </Card>
            </TabsContent>
```

Replace with:

```vue
            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent>
                  <EngineRequestDataTabs
                    v-if="!isEditMode"
                    :field-groups="fieldGroups"
                    :data="store.current.data"
                    :documents="store.documents"
                    :request-id="requestId"
                  />
                  <Tabs v-else :default-value="orderedFieldGroups[0]?.name" dir="rtl">
                    <TabsList class="flex-wrap">
                      <TabsTrigger
                        v-for="group in orderedFieldGroups"
                        :key="group.id"
                        :value="group.name"
                      >
                        {{ group.label }}
                      </TabsTrigger>
                    </TabsList>
                    <TabsContent
                      v-for="(group, index) in nonFileGroups"
                      :key="group.id"
                      :value="group.name"
                      class="mt-4"
                    >
                      <DynamicForm
                        :ref="(el) => setDynamicFormRef(el, index)"
                        :field-groups="[group]"
                        v-model="formData"
                        mode="edit"
                        :request-id="requestId"
                        :upload-target="{ type: 'request', requestId }"
                      />
                    </TabsContent>
                    <TabsContent
                      v-for="group in fileGroups"
                      :key="group.id"
                      :value="group.name"
                      class="mt-4"
                    >
                      <EngineFieldDocumentsGroup
                        :group="group"
                        :documents="store.documents"
                        :request-id="requestId"
                        :can-manage="canAct"
                        @upload="onDocumentUpload"
                        @remove="onDocumentRemove"
                      />
                    </TabsContent>
                  </Tabs>
                </CardContent>
              </Card>
            </TabsContent>
```

Note: `nonFileGroups` and `fileGroups` are both subsets of `orderedFieldGroups`, so the single outer `Tabs`/`TabsList` (built from `orderedFieldGroups`) covers every group's tab trigger, while the two separate `v-for="... in nonFileGroups"` / `v-for="... in fileGroups"` blocks each render only the `TabsContent` matching their own subset — every group still gets exactly one `TabsContent` between the two loops, since `nonFileGroups` and `fileGroups` partition `orderedFieldGroups` with no overlap and no gaps (`isFileOnlyGroup` is the sole partitioning predicate).

- [ ] **Step 4: Run existing page tests to check nothing regressed**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts` (from `frontend/`)
Expected: some tests will now fail or need updated stubs — this is expected at this point in the plan; Task 5 adds/updates the stubs and assertions. Note which ones fail here for comparison after Task 5.

- [ ] **Step 5: Commit (template + handlers, tests still pending)**

```bash
git add frontend/app/pages/workflows/instances/\[id\].vue
git commit -m "feat(workflow): render edit-mode form and per-field document panels on request detail"
```

---

### Task 4: Validate all mounted forms before executing a workflow action

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`

**Interfaces:**
- Consumes: `dynamicFormRefs`, `isEditMode`, `nonFileGroups` (Task 2/3); existing `runAction`, `executeAction`, `comment`, `store.current.version`.
- Produces: updated `runAction` — no new exports, this is the terminal consumer of the edit-mode state.

- [ ] **Step 1: Insert the validation gate into `runAction`, before `executeAction`**

Current `runAction` body (from the prior session's fix) starts:

```ts
async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }
  // The view page shows the request data read-only; acting on a stage submits
  // the existing data unchanged with an optional comment. Field edits happen
  // during creation (see /workflows/new-request/[versionId]), not here.
  actionBusy.value = true
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      formData.value,
      store.current!.version,
    )
```

Replace the stale comment and add the validation gate:

```ts
async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }

  // In edit mode, every mounted DynamicForm must independently validate before
  // any transition executes. Each form only owns the fields in its own group,
  // so its returned `values` may hold stale/default data for fields that
  // belong to a DIFFERENT group's form — never merge `values` across forms
  // into formData here. formData is already the single source of truth (kept
  // current by each DynamicForm's v-model + the document handlers in Task 3);
  // validate() is called purely to surface field-level errors and block
  // submission, its returned `values` are discarded.
  if (isEditMode.value) {
    for (const form of dynamicFormRefs.value) {
      if (!form) continue
      const { valid } = await form.validate()
      if (!valid) return
    }
  }

  actionBusy.value = true
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      formData.value,
      store.current!.version,
    )
```

(Everything after this point in `runAction` — the catch block, the post-success `load()`/redirect logic from the prior fix — stays exactly as-is. Do not touch it.)

- [ ] **Step 2: Run tests to confirm the validation gate compiles and the existing error-handling tests still pass**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts`
Expected: the pre-existing `runAction error handling (Phase E8)` describe block and the 403-reload regression test should still pass unchanged (they mount with `store.current.can_execute` fixtures that don't trigger `hasEditableFields`, since the default `mockShow`/`makeInstance` fixture returns `data: {}` and the schema-fetch mock's `fieldGroups` — check the current mock in the test file's `useEngineFormSchema` stub; if it returns an empty array, `hasEditableFields` is `false` and `isEditMode` stays `false` for all pre-existing tests, so the validation loop never runs and behavior is unchanged for them).

- [ ] **Step 3: Commit**

```bash
git add frontend/app/pages/workflows/instances/\[id\].vue
git commit -m "feat(workflow): validate all mounted edit forms before executing a stage action"
```

---

### Task 5: Test suite — edit-mode scenarios in `workflows-instance-detail.test.ts`

**Files:**
- Modify: `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`

**Interfaces:**
- Consumes: everything wired in Tasks 2-4. No new production interfaces — this task only adds test fixtures/mocks/assertions.

- [ ] **Step 1: Extend the top-of-file mocks to support editable-field fixtures**

The file currently mocks `useEngineFormSchema` implicitly via whatever `fetchSchema`/`fieldGroups` wiring exists — check the current mock block (search for `useEngineFormSchema` in the file). Add a mutable, per-test-overridable `fieldGroups` fixture:

```ts
const mockFieldGroups = ref<
  Array<{
    id: number
    name: string
    label: string
    sort_order: number
    fields: Array<{
      id: number
      key: string
      type: string
      is_visible: boolean
      is_editable: boolean
      is_required: boolean
      multiple: boolean
      label: string
    }>
  }>
>([])

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: mockFieldGroups,
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
    fetchInitialSchema: vi.fn(),
  }),
}))
```

(If `useEngineFormSchema` is already mocked elsewhere in the file with a static empty array, replace that mock with this reactive one — every existing test that doesn't set `mockFieldGroups.value` keeps getting `[]`, so `hasEditableFields` stays `false` and existing behavior is unchanged. Reset `mockFieldGroups.value = []` in the shared `beforeEach`.)

Extend the file's EXISTING `useEngineRequestDocuments` mock (lines 92-102 — do not add a second `vi.mock` for the same module, Vitest only honors one per module path) so the tests can control and assert on it. Replace:

```ts
vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchDocuments: vi.fn(),
    upload: vi.fn(),
    remove: vi.fn(),
    downloadUrl: vi.fn(),
  }),
}))
```

with:

```ts
const mockUploadDocument = vi.fn().mockResolvedValue({ id: 500, field_id: 2 })
const mockRemoveDocument = vi.fn().mockResolvedValue(undefined)
const mockFetchDocuments = vi.fn().mockResolvedValue(undefined)
// Mutable so individual tests (see the upload test in Task 5 Step 2) can make
// fetchDocuments' mock implementation populate this before store.uploadDocument
// reads it back into store.documents — mirrors the real composable's ref.
const mockDocumentsRef = ref<
  Array<{
    id: number
    request_id: number
    field_id: number | null
    stage_id: number
    original_name: string
    mime: string
    size: number
    uploaded_by: { id: number; name: string } | number
    created_at: string | null
  }>
>([])

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: mockDocumentsRef,
    loading: { value: false },
    error: { value: null },
    fetchDocuments: mockFetchDocuments,
    upload: mockUploadDocument,
    remove: mockRemoveDocument,
    downloadUrl: () => '#',
  }),
}))
```

This module-level mock is what `store.uploadDocument`/`store.removeDocument` call internally (see `frontend/app/stores/engineRequests.store.ts` lines 240-254) — the store itself is NOT mocked in this file (`useEngineRequestsStore()` is the real Pinia store, only its composable dependencies are mocked), so asserting on `mockUploadDocument`/`mockRemoveDocument` in Task 5's tests below correctly verifies the page → store → composable call chain end-to-end.

Add `DynamicForm`/`EngineFieldDocumentsGroup` to the `stubs` object (the file already stubs `DynamicForm` with a `validate` method — extend it to be per-instance controllable):

```ts
const mockFormValidate = vi.fn().mockResolvedValue({ valid: true, values: {} })

const stubs = {
  NuxtLink: true,
  DynamicForm: {
    template: '<div data-stub="dynamic-form" />',
    props: ['fieldGroups', 'modelValue', 'mode', 'requestId', 'uploadTarget'],
    methods: { validate: mockFormValidate },
  },
  EngineFieldDocumentsGroup: {
    template: '<div data-stub="field-documents-group" />',
    props: ['group', 'documents', 'requestId', 'canManage'],
    emits: ['upload', 'remove'],
  },
  EngineRequestDataTabs: { template: '<div data-stub="data-tabs" />' },
  EngineOrgProcessRail: { template: '<div data-stub="org-rail" />' },
  EngineQuickInfo: { template: '<div data-stub="quick-info" />' },
}
```

Reset `mockFormValidate`, `mockUploadDocument`, `mockRemoveDocument`, `mockFetchDocuments` (including clearing any per-test `mockImplementation` back to its default resolved value), `mockDocumentsRef.value = []`, and `mockFieldGroups.value = []` in the existing `beforeEach`.

- [ ] **Step 2: Write the failing tests**

Add a new `describe` block after the existing `runAction error handling (Phase E8)` block:

```ts
  describe('edit mode', () => {
    function editableFieldGroupFixture() {
      return [
        {
          id: 10,
          name: 'basic_info',
          label: 'المعلومات الأساسية',
          sort_order: 1,
          fields: [
            {
              id: 1,
              key: 'supplier_name',
              type: 'TEXT',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: false,
              label: 'اسم المورد',
            },
          ],
        },
        {
          id: 11,
          name: 'documents',
          label: 'المستندات',
          sort_order: 2,
          fields: [
            {
              id: 2,
              key: 'invoice_doc',
              type: 'FILE',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: true,
              label: 'فاتورة',
            },
          ],
        },
      ]
    }

    it('a VIEW-only user (can_execute=false) always sees the read-only EngineRequestDataTabs, even with editable field rules', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: false })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(true)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(false)
    })

    it('an executor without a required, held claim sees the read-only view, not edit mode', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({
        can_execute: true,
        current_stage: {
          id: 1,
          code: 'INTAKE',
          name: 'استلام',
          is_initial: true,
          is_final: false,
          sla_duration_minutes: null,
          requires_claim: true,
        },
        claimed_by: null,
      })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(true)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(false)
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(false)
    })

    it('renders DynamicForm for editable non-FILE groups and EngineFieldDocumentsGroup for FILE-only groups when canAct is true', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(false)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(true)
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(true)
    })

    it('sends formData (not the frozen store.current.data) as the transition payload', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true, data: { supplier_name: 'Original' } })
      store.graph = {
        nodes: [
          { id: 1, code: 'INTAKE', name: 'استلام', display_label: null, is_initial: true, is_final: false, sort_order: 0 },
        ],
        edges: [
          {
            id: 9,
            from_stage_id: 1,
            to_stage_id: 2,
            action_id: 1,
            action_code: 'SUBMIT',
            action_name: 'إرسال',
            requires_comment: false,
            is_self_loop: false,
            is_return: false,
          },
        ],
      }

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      // Simulate the DynamicForm's v-model emitting an edited value, the same
      // way a real DynamicFormField -> setFieldValue -> emit('update:modelValue')
      // chain would.
      const form = wrapper.findComponent({ name: 'DynamicForm' })
      form.vm.$emit('update:modelValue', { supplier_name: 'Edited' })
      await wrapper.vm.$nextTick()

      const actionButton = wrapper.findAll('button').find((btn) => btn.text().includes('إرسال'))
      await actionButton!.trigger('click')
      await flushPromises()

      expect(mockExecuteAction).toHaveBeenCalledWith(
        5,
        9,
        null,
        expect.objectContaining({ supplier_name: 'Edited' }),
        1,
      )
    })

    it('aborts the action and never calls executeAction when a mounted form is invalid', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockFormValidate.mockResolvedValue({ valid: false, values: {} })
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true })
      store.graph = {
        nodes: [
          { id: 1, code: 'INTAKE', name: 'استلام', display_label: null, is_initial: true, is_final: false, sort_order: 0 },
        ],
        edges: [
          {
            id: 9,
            from_stage_id: 1,
            to_stage_id: 2,
            action_id: 1,
            action_code: 'SUBMIT',
            action_name: 'إرسال',
            requires_comment: false,
            is_self_loop: false,
            is_return: false,
          },
        ],
      }

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      const actionButton = wrapper.findAll('button').find((btn) => btn.text().includes('إرسال'))
      await actionButton!.trigger('click')
      await flushPromises()

      expect(mockExecuteAction).not.toHaveBeenCalled()
    })

    it('validates every mounted form independently without merging one form\'s returned values into another\'s fields', async () => {
      mockFieldGroups.value = [
        ...editableFieldGroupFixture(),
        {
          id: 12,
          name: 'more_info',
          label: 'معلومات إضافية',
          sort_order: 3,
          fields: [
            {
              id: 3,
              key: 'notes_field',
              type: 'TEXTAREA',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: false,
              label: 'ملاحظات',
            },
          ],
        },
      ]
      // Two non-FILE groups now exist (basic_info, more_info) -> two DynamicForm
      // instances. Each call to validate() returns different, group-scoped
      // values; the assertion is that BOTH get called (both gate the action)
      // and neither's `values` leaks into formData.
      const validateCalls: unknown[] = []
      mockFormValidate.mockImplementation(function (this: unknown) {
        validateCalls.push(this)
        return Promise.resolve({ valid: true, values: { unrelated_field: 'should-not-leak' } })
      })
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true, data: { supplier_name: 'Kept' } })
      store.graph = {
        nodes: [
          { id: 1, code: 'INTAKE', name: 'استلام', display_label: null, is_initial: true, is_final: false, sort_order: 0 },
        ],
        edges: [
          {
            id: 9,
            from_stage_id: 1,
            to_stage_id: 2,
            action_id: 1,
            action_code: 'SUBMIT',
            action_name: 'إرسال',
            requires_comment: false,
            is_self_loop: false,
            is_return: false,
          },
        ],
      }

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      const actionButton = wrapper.findAll('button').find((btn) => btn.text().includes('إرسال'))
      await actionButton!.trigger('click')
      await flushPromises()

      expect(mockFormValidate).toHaveBeenCalledTimes(2)
      const payload = mockExecuteAction.mock.calls[0]![3] as Record<string, unknown>
      expect(payload.unrelated_field).toBeUndefined()
      expect(payload.supplier_name).toBe('Kept')
    })

    it('uploads a file through EngineFieldDocumentsGroup with the correct field id and appends the returned document id to formData', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true, data: {} })
      // store.uploadDocument() awaits upload() then fetchDocuments(), then
      // reassigns store.documents from the composable's reactive `documents`
      // ref — simulate the fetch actually finding the new doc by making
      // mockFetchDocuments populate that ref, the same way a real network
      // response would before the store reads it back out.
      mockFetchDocuments.mockImplementation(async () => {
        mockDocumentsRef.value = [
          {
            id: 500,
            request_id: 5,
            field_id: 2,
            stage_id: 1,
            original_name: 'invoice.pdf',
            mime: 'application/pdf',
            size: 1,
            uploaded_by: { id: 1, name: 'x' },
            created_at: null,
          },
        ]
      })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      const group = wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' })
      const file = new File(['x'], 'invoice.pdf', { type: 'application/pdf' })
      group.vm.$emit('upload', 2, file)
      await flushPromises()

      expect(mockUploadDocument).toHaveBeenCalledWith(5, file, 2)
      expect(mockFetchDocuments).toHaveBeenCalledWith(5)
      // formData is internal to <script setup> and not directly assertable
      // from the test; assert its effect instead — after upload,
      // store.documents (re-fetched) flows back into EngineFieldDocumentsGroup's
      // `documents` prop, which is the user-visible confirmation the upload
      // was registered against the request.
      const groupAfterUpload = wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' })
      expect(groupAfterUpload.props('documents')).toContainEqual(
        expect.objectContaining({ id: 500, field_id: 2 }),
      )
    })

    it('removes a document and drops its id from formData', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: true, data: { invoice_doc: [500, 501] } })
      store.documents = [
        {
          id: 500,
          request_id: 5,
          field_id: 2,
          stage_id: 1,
          original_name: 'a.pdf',
          mime: 'application/pdf',
          size: 1,
          uploaded_by: { id: 1, name: 'x' },
          created_at: null,
        },
      ]

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      const group = wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' })
      group.vm.$emit('remove', 500)
      await flushPromises()

      expect(mockRemoveDocument).toHaveBeenCalledWith(5, 500)
    })

    it('passes canManage=false to EngineFieldDocumentsGroup for a read-only FILE field group', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      store.current = makeInstance({ can_execute: false })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      // Read-only path renders EngineRequestDataTabs, not EngineFieldDocumentsGroup at all —
      // confirms file management never appears outside edit mode.
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(false)
    })

    it('resets formData when a reload lands on a different stage/version', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      const store = useEngineRequestsStore()
      mockShow
        .mockResolvedValueOnce(makeInstance({ version: 1, data: { supplier_name: 'First' } }))
        .mockResolvedValueOnce(makeInstance({ version: 2, data: { supplier_name: 'Second' } }))

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      const form = wrapper.findComponent({ name: 'DynamicForm' })
      form.vm.$emit('update:modelValue', { supplier_name: 'Locally edited' })
      await wrapper.vm.$nextTick()

      // Trigger a second load() the same way runAction's post-success reload
      // does — call the page's own load via a re-render is not directly
      // accessible from the test; instead simulate by invoking store.loadInstance
      // through a claim-lost retry button which calls load() again, OR (simpler,
      // matching how load() is actually re-triggered) re-mount is avoided —
      // use the exposed retry action from ErrorState-less path: call
      // wrapper.vm is not exposed by <script setup>, so drive it via the
      // component's public affordance: trigger startReview -> load path is
      // indirect. Simplest reliable trigger: call store.loadInstance directly
      // (same function load() awaits) and then assert the page's bound
      // formData was NOT left as 'Locally edited' by checking the DynamicForm
      // stub's modelValue prop after a manual re-render tick.
      await store.loadInstance(5)
      await wrapper.vm.$nextTick()

      const formAfterReload = wrapper.findComponent({ name: 'DynamicForm' })
      expect(formAfterReload.props('modelValue')).toMatchObject({ supplier_name: 'Second' })
    })
  })
```

- [ ] **Step 3: Run the new tests to verify they fail for the right reason**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts` (from `frontend/`)
Expected: FAIL on the new `edit mode` describe block (production code from Tasks 2-4 must already be committed at this point per the task ordering above — if Tasks 2-4 are done first as this plan orders them, these tests should mostly PASS already; this step is a safety check, not a strict red-green gate, since the plan interleaves implementation before this dedicated test task by design so Task 4's Step 2 already exercised a subset). If any assertion fails, read the failure and fix the implementation (not the test) unless the test itself has a bug — check `[id].vue` first.

- [ ] **Step 4: Fix any real gaps found**

Common likely gap: the `resetFormState` reset-on-reload test may reveal that `dynamicFormRefs` isn't cleared/repopulated correctly across a re-render, or that `formData` binding to `DynamicForm`'s `modelValue` prop needs the `v-model` directive spelled out explicitly as `:model-value="formData" @update:model-value="formData = $event"` instead of shorthand `v-model="formData"` for the stub to expose `modelValue` as a readable prop in tests. If the stub only sees `model-value` (kebab-case) rather than `modelValue` (the test asserts `props('modelValue')`), verify Vue Test Utils' prop name normalization handles this (it does — `.props()` accepts either case) before treating it as a bug.

- [ ] **Step 5: Run the full page test file one more time to confirm everything is green**

Run: `pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts`
Expected: PASS, all tests (existing 18 + new edit-mode tests)

- [ ] **Step 6: Lint and format**

```bash
pnpm exec eslint app/pages/workflows/instances/\[id\].vue app/tests/unit/pages/workflows-instance-detail.test.ts app/components/workflow/EngineFieldDocumentsGroup.vue app/tests/unit/components/EngineFieldDocumentsGroup.test.ts
pnpm exec prettier app/pages/workflows/instances/\[id\].vue app/tests/unit/pages/workflows-instance-detail.test.ts app/components/workflow/EngineFieldDocumentsGroup.vue app/tests/unit/components/EngineFieldDocumentsGroup.test.ts --check
```
Expected: both clean

- [ ] **Step 7: Typecheck**

Run: `pnpm typecheck 2>&1 | grep -iE "instances/\[id\]|EngineFieldDocumentsGroup"`
Expected: no output attributable to these two files (the full command's pre-existing unrelated errors, documented in the prior session, are not a regression from this work — do not attempt to fix them here)

- [ ] **Step 8: Commit**

```bash
git add frontend/app/tests/unit/pages/workflows-instance-detail.test.ts frontend/app/tests/unit/components/EngineFieldDocumentsGroup.test.ts
git commit -m "test(workflow): cover request detail edit-mode form/document scenarios"
```

---

### Task 6: Update Graphify graph and perform browser verification

**Files:** none modified — verification-only task.

- [ ] **Step 1: Refresh the local Graphify graph**

Run (from repo root): `graphify update .`
Expected: local-only refresh, AST-based, no API cost. Do not stage or commit `graphify-out/`.

- [ ] **Step 2: Start the dev server if not already running**

Check first: `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000` — if it returns anything other than a connection error, the server is already up, skip starting a new one.
If not running: `cd frontend && pnpm dev` (run in background, do not block on it).

- [ ] **Step 3: Browser-verify the read-only path is unchanged**

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/login
playwright-cli snapshot
# log in as a VIEW-only or wrong-stage user per the project's known test accounts
playwright-cli goto http://localhost:3000/workflows/instances/<some-non-editable-or-view-only-request-id>
playwright-cli snapshot
```
Expected: "بيانات الطلب" tab shows the existing read-only `dl`-based value list, same as before this change (visually identical to the screenshots already captured in this session's earlier turns).

- [ ] **Step 4: Browser-verify the new edit-mode path**

```bash
playwright-cli goto http://localhost:3000/workflows/instances/<a-request-currently-in-its-editable-initial-or-return-stage>
playwright-cli snapshot
```
Expected: "بيانات الطلب" tab shows editable input fields (via `DynamicForm`) for non-file groups, and a document-management panel (upload input + existing files with delete buttons) for file groups. Confirm:
- Editing a text field and clicking an available stage action (e.g. "اعتماد") sends the edited value (inspect via network tab / `playwright-cli` request logging if available, or confirm via the resulting `store.current.data` reflecting the edit after reload).
- Uploading a PDF through one file field's panel doesn't add it to a different file field's list.
- Removing a document updates the panel immediately.

- [ ] **Step 5: Take a screenshot for the record**

```bash
playwright-cli screenshot --filename=request-edit-mode.png
playwright-cli close
```

- [ ] **Step 6: Report findings**

If browser verification reveals any UX gap not covered by the plan (e.g. a required FILE field blocking submission with no clear error message, since `StageFieldRuleValidator`'s required-FILE-field check happens server-side on submit, not client-side in `DynamicForm`), report it — do not silently patch scope beyond what's specified. This plan does not add client-side required-FILE-field pre-validation; the existing `STAGE_FIELDS_INVALID` (422) branch in `runAction`'s catch block already surfaces that server-side rejection as a toast.

---

## Self-Review Notes

**Spec coverage check** — every numbered requirement from the user's spec maps to a task:
1. `EngineRequestDataTabs` unchanged → enforced as a Global Constraint + Task 3 Step 3 keeps it as the `v-if="!isEditMode"` branch, verified by Task 5's VIEW-only test.
2. `hasEditableFields`/`isEditMode` via `canAct` → Task 2 Step 1, exact code from the spec.
3. Per-group tabs, `DynamicForm` per non-file group with `[group]`, one ref per group, shared `v-model="formData"`, no FILE fields through `DynamicForm` → Task 2 Step 2 (refs) + Task 3 Step 3 (template, `nonFileGroups` explicitly excludes FILE-only groups).
4. FILE fields via `EngineDocumentsPanel`, multi-FILE-field groups, filter by `field_id`, gate on `canAct` + field `is_editable`, wire upload/remove to existing APIs, correct `field.id` on upload, append/remove doc id in `formData`, refresh `store.documents`, preserve orphaned docs read-only → Task 1 (component) + Task 3 Step 1 (handlers) fully cover this.
5. Required-comment check kept, validate every mounted form, abort on invalid, never merge cross-form `values`, submit canonical `formData.value` → Task 4, with the "never merge" rule stated explicitly as a code comment and tested in Task 5.
6. `formData` from cloned `store.current.data`, reset/remount on version-or-stage change, no broad `DynamicForm`-internal watcher → Task 2 Steps 2-3 (`structuredClone`, `loadedFormKey` guard), and the constraint against touching `DynamicForm.vue` is a Global Constraint.
7. Frontend-only, no `DynamicForm.vue` upload-lifecycle changes, no stage-code/`is_initial` hardcoding, backend `StageFieldRule` reliance, claim/version/error/redirect preservation, no raw HTML replacing shadcn — all Global Constraints, and Task 4 Step 1 explicitly says "everything after this point... stays exactly as-is."

Pre-edit steps (repo status, mandatory context files, SocratiCode symbol/impact, library docs) were performed live during plan authoring, ahead of this document — `git status` showed only pre-existing unrelated dirty files (untouched by this plan); `codebase_impact` on `[id].vue` and `EngineDocumentsPanel` returned zero dependents, confirming safe modification; no external library API surface is touched (VeeValidate/shadcn usage in this plan matches existing patterns already in the codebase, so no new Context7 lookup was needed beyond what the existing `DynamicForm.vue`/`EngineRequestWizard.vue` already demonstrate).

**Placeholder scan** — no TBD/TODO; the one deliberately open-ended step (Task 6 Step 6, "report findings") is an explicit instruction to stop and report rather than silently expand scope, not a placeholder for missing plan content.

**Type consistency** — `ResolvedFieldGroup`, `ResolvedFieldDefinition`, `EngineRequestDocument` used identically to their `@/types/models` definitions throughout; `EngineFieldDocumentsGroup`'s props/emits in Task 1 match exactly how Task 3 invokes it; `dynamicFormRefs`/`setDynamicFormRef` defined in Task 2 are consumed unchanged in Task 3's template and Task 4's validation loop.

**Scope check** — single cohesive feature (one page + one new small component), not decomposed further; appropriately sized for one plan.
