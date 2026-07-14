# Designer Semantic-Tag Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a semantic-tag picker to the Workflow Field Designer's field dialog so admins can assign `FieldDefinition.semantic_tag` through the UI instead of a direct database write, plus a visibility badge in the fields table for already-tagged fields.

**Architecture:** Frontend-only. The backend (`StoreFieldDefinitionRequest`, `UpdateFieldDefinitionRequest`, `SemanticResolver::publishErrors()`) already validates, persists, and duplicate-checks `semantic_tag` at publish time — verified in `docs/superpowers/specs/2026-07-14-designer-semantic-tag-picker-design.md`. This plan adds: a tag-catalog constant file, a form field + payload key in `WorkflowFieldDesigner.vue`, a client-side duplicate-tag warning computed from already-loaded data (no new API call), and a table badge.

**Tech Stack:** Vue 3.5 `<script setup>`, TypeScript, shadcn-vue `Select`/`SelectGroup`/`SelectLabel`/`Badge`/`Tooltip`, Vitest + `@vue/test-utils`.

## Global Constraints

- No backend changes (spec Decision 1) — do not touch `StoreFieldDefinitionRequest.php`, `UpdateFieldDefinitionRequest.php`, `SemanticResolver.php`, or `SemanticRegistry.php`.
- No type-based tag filtering (spec Decision 2) — every `SelectItem` is available for every field type, no compatibility matrix.
- Duplicate-tag detection is derived from already-loaded `groups` data, no new endpoint or API call (spec Decision 3).
- Submitting with no tag selected must send `semantic_tag: null`, never `undefined` and never omit the key (spec Decision 8).
- Follow existing `WorkflowFieldDesigner.vue` conventions exactly: `ref('')` for optional selects reset in `openFieldDialog`/populated in `openEditFieldDialog`, `toast.error(extractApiErrorMessage(cause, '...'))` in catch blocks, `SHADCN.md` component usage (no raw `<select>`, no raw `<span class="rounded-full...">` for the badge — must be `<Badge>`).
- Arabic-only UI copy, RTL layout (`dir="rtl"` already set at app root — no per-component `dir` needed for this addition since it's not a raw text input).
- Run `pnpm exec eslint` and `pnpm exec prettier --check` on every touched file before each commit; run the focused Vitest file after every test-affecting step, not the full suite.

---

### Task 1: Tag catalog constant

**Files:**
- Create: `frontend/app/constants/semanticTags.ts`
- Test: `frontend/app/tests/unit/constants/semanticTags.test.ts`

**Interfaces:**
- Consumes: `FieldSemanticTag` type from `frontend/app/types/models.ts` (already has all 14 values as of the merchant-autofill work).
- Produces: `SEMANTIC_TAG_GROUPS: SemanticTagGroup[]` and `SEMANTIC_TAG_LABELS: Record<FieldSemanticTag, string>`, both consumed by Task 2 and Task 3.

- [ ] **Step 1: Write the failing test**

Create `frontend/app/tests/unit/constants/semanticTags.test.ts`:

```typescript
import { describe, expect, it } from 'vitest'
import { SEMANTIC_TAG_GROUPS, SEMANTIC_TAG_LABELS } from '@/constants/semanticTags'
import type { FieldSemanticTag } from '@/types/models'

const ALL_TAGS: FieldSemanticTag[] = [
  'INVOICE_NUMBER',
  'REQUESTED_PERCENTAGE',
  'MERCHANT_TAX_NUMBER',
  'SUPPLIER_NAME',
  'GOODS_DESCRIPTION',
  'PORT_OF_ENTRY',
  'AMOUNT',
  'CURRENCY',
  'MERCHANT_ID',
  'MERCHANT_COMPANY_ID',
  'MERCHANT_TAX_CARD_EXPIRY',
  'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER',
  'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY',
  'MERCHANT_OWNERS',
]

describe('semanticTags', () => {
  it('groups every FieldSemanticTag value exactly once', () => {
    const flattened = SEMANTIC_TAG_GROUPS.flatMap((group) => group.tags.map((t) => t.value))
    expect(flattened.sort()).toEqual([...ALL_TAGS].sort())
    expect(new Set(flattened).size).toBe(flattened.length)
  })

  it('has three groups: التاجر, التمويل, أخرى', () => {
    expect(SEMANTIC_TAG_GROUPS.map((g) => g.label)).toEqual(['التاجر', 'التمويل', 'أخرى'])
  })

  it('places all seven MERCHANT_* tags in the التاجر group', () => {
    const merchantGroup = SEMANTIC_TAG_GROUPS.find((g) => g.label === 'التاجر')
    const merchantTags = merchantGroup?.tags.map((t) => t.value) ?? []
    expect(merchantTags.sort()).toEqual(
      [
        'MERCHANT_TAX_NUMBER',
        'MERCHANT_ID',
        'MERCHANT_COMPANY_ID',
        'MERCHANT_TAX_CARD_EXPIRY',
        'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER',
        'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY',
        'MERCHANT_OWNERS',
      ].sort(),
    )
  })

  it('SEMANTIC_TAG_LABELS has a non-empty Arabic label for every tag', () => {
    for (const tag of ALL_TAGS) {
      expect(SEMANTIC_TAG_LABELS[tag]).toBeTruthy()
      expect(SEMANTIC_TAG_LABELS[tag].length).toBeGreaterThan(0)
    }
  })

  it('SEMANTIC_TAG_LABELS values match the labels used in SEMANTIC_TAG_GROUPS', () => {
    for (const group of SEMANTIC_TAG_GROUPS) {
      for (const tag of group.tags) {
        expect(SEMANTIC_TAG_LABELS[tag.value]).toBe(tag.label)
      }
    }
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/constants/semanticTags.test.ts`
Expected: FAIL with a module-not-found error for `@/constants/semanticTags`.

- [ ] **Step 3: Write the implementation**

Create `frontend/app/constants/semanticTags.ts`:

```typescript
import type { FieldSemanticTag } from '@/types/models'

export interface SemanticTagOption {
  value: FieldSemanticTag
  label: string
}

export interface SemanticTagGroup {
  label: string
  tags: SemanticTagOption[]
}

export const SEMANTIC_TAG_GROUPS: SemanticTagGroup[] = [
  {
    label: 'التاجر',
    tags: [
      { value: 'MERCHANT_TAX_NUMBER', label: 'الرقم الضريبي للتاجر' },
      { value: 'MERCHANT_ID', label: 'التاجر' },
      { value: 'MERCHANT_COMPANY_ID', label: 'الشركة المرتبطة بالتاجر' },
      { value: 'MERCHANT_TAX_CARD_EXPIRY', label: 'تاريخ انتهاء البطاقة الضريبية' },
      { value: 'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER', label: 'رقم السجل التجاري' },
      { value: 'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY', label: 'تاريخ انتهاء السجل التجاري' },
      { value: 'MERCHANT_OWNERS', label: 'الملاك والمساهمون' },
    ],
  },
  {
    label: 'التمويل',
    tags: [
      { value: 'INVOICE_NUMBER', label: 'رقم الفاتورة' },
      { value: 'REQUESTED_PERCENTAGE', label: 'نسبة الطلب' },
      { value: 'AMOUNT', label: 'المبلغ' },
      { value: 'CURRENCY', label: 'العملة' },
    ],
  },
  {
    label: 'أخرى',
    tags: [
      { value: 'SUPPLIER_NAME', label: 'اسم المورّد' },
      { value: 'GOODS_DESCRIPTION', label: 'وصف السلعة' },
      { value: 'PORT_OF_ENTRY', label: 'ميناء الدخول' },
    ],
  },
]

export const SEMANTIC_TAG_LABELS: Record<FieldSemanticTag, string> = Object.fromEntries(
  SEMANTIC_TAG_GROUPS.flatMap((group) => group.tags.map((tag) => [tag.value, tag.label])),
) as Record<FieldSemanticTag, string>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/constants/semanticTags.test.ts`
Expected: PASS, 5 tests.

- [ ] **Step 5: Lint and format**

Run: `cd frontend && pnpm exec eslint app/constants/semanticTags.ts app/tests/unit/constants/semanticTags.test.ts && pnpm exec prettier app/constants/semanticTags.ts app/tests/unit/constants/semanticTags.test.ts --check`
Expected: both exit 0, no output.

- [ ] **Step 6: Commit**

```bash
git add frontend/app/constants/semanticTags.ts frontend/app/tests/unit/constants/semanticTags.test.ts
git commit -m "feat(workflow): add semantic-tag catalog for the Designer picker

One source of truth for the 14 FieldSemanticTag values' Arabic labels
and their التاجر/التمويل/أخرى grouping, consumed by the upcoming
Designer field-dialog picker and fields-table badge."
```

---

### Task 2: `FieldDefinitionPayload` gains `semantic_tag`

**Files:**
- Modify: `frontend/app/composables/useWorkflowFields.ts:11-32`
- Test: `frontend/app/tests/unit/composables/useWorkflowFields.test.ts`

**Interfaces:**
- Consumes: `FieldSemanticTag` type from `@/types/models`.
- Produces: `FieldDefinitionPayload.semantic_tag?: FieldSemanticTag | null`, consumed by Task 3's `submitField()` payload.

- [ ] **Step 1: Read the current type to confirm exact insertion point**

`frontend/app/composables/useWorkflowFields.ts:11-32` currently reads:

```typescript
export type FieldDefinitionPayload = {
  field_group_id: number
  key: string
  label: string
  type: FieldType
  placeholder?: string | null
  help_text?: string | null
  default_value?: string | null
  min_value?: number | null
  max_value?: number | null
  min_length?: number | null
  max_length?: number | null
  regex_pattern?: string | null
  options?: Array<{ value: string; label: string }> | null
  reference_table_id?: number | null
  dynamic_source?: DynamicFieldSource | null
  allowed_file_types?: string[] | null
  max_file_size?: number | null
  multiple?: boolean
  is_required?: boolean
  sort_order?: number
}
```

- [ ] **Step 2: Write the failing test**

Open `frontend/app/tests/unit/composables/useWorkflowFields.test.ts`, find the existing `makeField`-equivalent fixture used by this file's `createField`/`updateField` tests (the fixture at line ~17 flagged by typecheck in prior work as missing `semantic_tag`). Add this test at the end of the file's `describe('useWorkflowFields', ...)` block (match the existing `describe`/`it` nesting in the file — do not create a new top-level `describe`):

```typescript
  it('createField accepts a semantic_tag in the payload and forwards it verbatim', async () => {
    mockPost.mockResolvedValueOnce({ data: makeField({ semantic_tag: 'INVOICE_NUMBER' }) })
    const { createField } = useWorkflowFields()

    await createField(7, {
      field_group_id: 100,
      key: 'invoice_number',
      label: 'رقم الفاتورة',
      type: 'TEXT',
      semantic_tag: 'INVOICE_NUMBER',
    })

    const [, body] = mockPost.mock.calls[0] as [string, Record<string, unknown>]
    expect(body.semantic_tag).toBe('INVOICE_NUMBER')
  })
```

If the file's existing `makeField` fixture does not accept a `semantic_tag` override or does not include `semantic_tag` in its returned object, add `semantic_tag: null` to its defaults and `Partial<FieldDefinition>` override support now (it must already return a full `FieldDefinition` — check the existing fixture in this file before assuming it needs changes; only touch it if `semantic_tag` is actually missing from its return shape).

- [ ] **Step 3: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useWorkflowFields.test.ts -t "semantic_tag"`
Expected: FAIL with a TypeScript error (payload object literal may only specify known properties, `semantic_tag` does not exist in type `FieldDefinitionPayload`) surfaced as a Vitest/esbuild transform error.

- [ ] **Step 4: Write the minimal implementation**

In `frontend/app/composables/useWorkflowFields.ts`, add one line to `FieldDefinitionPayload` (after `dynamic_source`, matching the field's position in the backend's validation rules for readability — exact position doesn't matter functionally):

```typescript
export type FieldDefinitionPayload = {
  field_group_id: number
  key: string
  label: string
  type: FieldType
  semantic_tag?: FieldSemanticTag | null
  placeholder?: string | null
  help_text?: string | null
  default_value?: string | null
  min_value?: number | null
  max_value?: number | null
  min_length?: number | null
  max_length?: number | null
  regex_pattern?: string | null
  options?: Array<{ value: string; label: string }> | null
  reference_table_id?: number | null
  dynamic_source?: DynamicFieldSource | null
  allowed_file_types?: string[] | null
  max_file_size?: number | null
  multiple?: boolean
  is_required?: boolean
  sort_order?: number
}
```

Add `FieldSemanticTag` to the existing type-only import at the top of the file (currently `import type { DynamicFieldSource, FieldDefinition, FieldGroup, FieldType } from '@/types/models'`):

```typescript
import type {
  DynamicFieldSource,
  FieldDefinition,
  FieldGroup,
  FieldSemanticTag,
  FieldType,
} from '@/types/models'
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/composables/useWorkflowFields.test.ts`
Expected: PASS, all tests in the file (including the new one) green.

- [ ] **Step 6: Lint and format**

Run: `cd frontend && pnpm exec eslint app/composables/useWorkflowFields.ts app/tests/unit/composables/useWorkflowFields.test.ts && pnpm exec prettier app/composables/useWorkflowFields.ts app/tests/unit/composables/useWorkflowFields.test.ts --check`
Expected: both exit 0.

- [ ] **Step 7: Commit**

```bash
git add frontend/app/composables/useWorkflowFields.ts frontend/app/tests/unit/composables/useWorkflowFields.test.ts
git commit -m "feat(workflow): accept semantic_tag in FieldDefinitionPayload

Backend already validates and persists this field on create/update —
the frontend payload type just never declared it, so callers got a
TypeScript error trying to send it. No behavior change to existing
callers that omit the key."
```

---

### Task 3: Field dialog picker + duplicate-tag warning

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowFieldDesigner.vue`
- Test: `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`

**Interfaces:**
- Consumes: `SEMANTIC_TAG_GROUPS` from Task 1 (`@/constants/semanticTags`), `FieldDefinitionPayload.semantic_tag` from Task 2, `FieldSemanticTag` type from `@/types/models`, existing `flatFields` computed (`WorkflowFieldDesigner.vue:150-152`), existing `groups`/`createField`/`updateField` from `useWorkflowFields()`.
- Produces: `fieldSemanticTag: Ref<FieldSemanticTag | ''>`, `tagOwners: ComputedRef<Map<FieldSemanticTag, FieldDefinition>>` — both used by Task 4 (table badge) via the same component's template scope; no cross-file interface since Task 4 touches the same file.

- [ ] **Step 1: Write the failing test — picker renders grouped options**

Add to `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`, inside the existing `describe('WorkflowFieldDesigner', ...)` block, after the last existing `it(...)`:

```typescript
  it('shows the semantic-tag picker grouped by category in the field dialog', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])
    await buttonByText(wrapper, 'إضافة حقل')?.trigger('click')

    expect(wrapper.text()).toContain('العلامة الدلالية')
    expect(wrapper.text()).toContain('التاجر')
    expect(wrapper.text()).toContain('التمويل')
    expect(wrapper.text()).toContain('أخرى')
    expect(wrapper.text()).toContain('رقم الفاتورة')
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts -t "semantic-tag picker"`
Expected: FAIL — `expect(wrapper.text()).toContain('العلامة الدلالية')` fails, string not found.

- [ ] **Step 3: Add the picker state and computed to the script block**

In `frontend/app/components/workflow/WorkflowFieldDesigner.vue`, add `FieldSemanticTag` to the existing type-only import (currently at line 14-20):

```typescript
import type {
  DynamicFieldSource,
  FieldDefinition,
  FieldGroup,
  FieldSemanticTag,
  FieldType,
  WorkflowVersion,
} from '@/types/models'
```

Add `SelectGroup` and `SelectLabel` to the existing Select import (currently line 54-60):

```typescript
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
```

Add a new import for the tag catalog, immediately after the `useWorkflowFields` import (line 73):

```typescript
import { SEMANTIC_TAG_GROUPS } from '@/constants/semanticTags'
```

Add the new ref next to `fieldReferenceTableId` (after line 109, before `fieldRequired`):

```typescript
const fieldSemanticTag = ref<FieldSemanticTag | ''>('')
```

Add the `tagOwners` computed after the existing `fieldCount` function (after line 155, before `openGroupDialog`):

```typescript
// Which field in this version already owns each semantic tag, so the picker
// can warn before the admin picks a tag that would collide at publish time
// (SemanticResolver::publishErrors() → SEMANTIC_MAPPING_AMBIGUOUS). Derived
// from data already loaded via useWorkflowFields() — no extra API call.
const tagOwners = computed(() => {
  const owners = new Map<FieldSemanticTag, FieldDefinition>()
  for (const { field } of flatFields.value) {
    if (field.semantic_tag !== null) owners.set(field.semantic_tag, field)
  }
  return owners
})

function isTagTakenByAnotherField(tag: FieldSemanticTag): boolean {
  const owner = tagOwners.value.get(tag)
  return owner !== undefined && owner.id !== editingField.value?.id
}

function tagOwnerLabel(tag: FieldSemanticTag): string | null {
  return tagOwners.value.get(tag)?.label ?? null
}
```

- [ ] **Step 4: Reset and populate the ref in the dialog-open functions**

In `openFieldDialog` (currently lines 163-176), add the reset line after `fieldReferenceTableId.value = ''`:

```typescript
function openFieldDialog(groupId: number | null) {
  editingField.value = null
  fieldGroupId.value = groupId
  fieldKey.value = ''
  fieldLabel.value = ''
  fieldType.value = 'TEXT'
  fieldMinValue.value = ''
  fieldMaxValue.value = ''
  fieldDynamicSource.value = ''
  fieldReferenceTableId.value = ''
  fieldSemanticTag.value = ''
  fieldRequired.value = false
  formError.value = null
  fieldDialogOpen.value = true
}
```

In `openEditFieldDialog` (currently lines 178-193), add the populate line after `fieldReferenceTableId.value = ...`:

```typescript
function openEditFieldDialog(field: FieldDefinition) {
  editingField.value = field
  fieldGroupId.value = field.field_group_id
  fieldKey.value = field.key
  fieldLabel.value = field.label
  fieldType.value = field.type
  fieldMinValue.value =
    field.min_value !== null && field.min_value !== undefined ? String(field.min_value) : ''
  fieldMaxValue.value =
    field.max_value !== null && field.max_value !== undefined ? String(field.max_value) : ''
  fieldDynamicSource.value = field.dynamic_source ?? ''
  fieldReferenceTableId.value = field.reference_table_id ? String(field.reference_table_id) : ''
  fieldSemanticTag.value = field.semantic_tag ?? ''
  fieldRequired.value = field.is_required
  formError.value = null
  fieldDialogOpen.value = true
}
```

- [ ] **Step 5: Add `semantic_tag` to the submit payload**

In `submitField` (currently lines 210-241), add `semantic_tag` to the `payload` object literal, after `type: fieldType.value,`:

```typescript
  const payload = {
    field_group_id: fieldGroupId.value,
    key: fieldKey.value,
    label: fieldLabel.value,
    type: fieldType.value,
    semantic_tag: fieldSemanticTag.value || null,
    min_value: isNumeric.value && fieldMinValue.value ? Number(fieldMinValue.value) : null,
    max_value: isNumeric.value && fieldMaxValue.value ? Number(fieldMaxValue.value) : null,
    dynamic_source: isDynamic.value && fieldDynamicSource.value ? fieldDynamicSource.value : null,
    reference_table_id:
      needsReferenceTable.value && fieldReferenceTableId.value
        ? Number(fieldReferenceTableId.value)
        : null,
    is_required: fieldRequired.value,
  }
```

- [ ] **Step 6: Add the picker to the template**

In `frontend/app/components/workflow/WorkflowFieldDesigner.vue`, the field dialog's template block (currently lines 574-680) has this sequence: "المجموعة" Select → "النوع" Select → (conditional) "المصدر الديناميكي" Select → (conditional) "الجدول المرجعي" Select → `formError` paragraph. Insert the new picker immediately after the "النوع" Select block (which currently ends at line 635) and before the `v-if="isDynamic"` block (which currently starts at line 637):

```vue
          <div class="flex flex-col gap-1.5">
            <Label>العلامة الدلالية</Label>
            <Select :model-value="fieldSemanticTag || 'NONE'" @update:model-value="(v) => (fieldSemanticTag = v === 'NONE' ? '' : (v as FieldSemanticTag))">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="NONE">بدون</SelectItem>
                <SelectGroup v-for="tagGroup in SEMANTIC_TAG_GROUPS" :key="tagGroup.label">
                  <SelectLabel>{{ tagGroup.label }}</SelectLabel>
                  <SelectItem
                    v-for="tag in tagGroup.tags"
                    :key="tag.value"
                    :value="tag.value"
                    :disabled="isTagTakenByAnotherField(tag.value)"
                  >
                    {{ tag.label }}
                    <span
                      v-if="isTagTakenByAnotherField(tag.value)"
                      class="text-muted-foreground text-xs"
                    >
                      (مستخدم في: {{ tagOwnerLabel(tag.value) }})
                    </span>
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

```

Note: shadcn `Select`'s `model-value`/`update:model-value` cannot carry an empty string as a real selectable value (reka-ui's `Select` treats `''` as "no selection" internally, which collides with actually wanting a "بدون" choice) — the `'NONE'` sentinel round-trip above is required, not optional styling. Do not simplify this to a plain `v-model="fieldSemanticTag"` with an empty-string `SelectItem`.

- [ ] **Step 7: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts -t "semantic-tag picker"`
Expected: PASS.

- [ ] **Step 8: Write the failing test — submitting includes semantic_tag**

Add to the same test file:

```typescript
  it('includes the selected semantic_tag in the create payload', async () => {
    mockPost.mockResolvedValueOnce({ data: makeField({ semantic_tag: 'INVOICE_NUMBER' }) })
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])
    await buttonByText(wrapper, 'إضافة حقل')?.trigger('click')

    const selects = wrapper.findAll('button[role="combobox"]')
    // Order in the dialog: المجموعة, النوع, العلامة الدلالية.
    const tagSelectTrigger = selects[2]
    await tagSelectTrigger?.trigger('click')
    await flushPromises()
    const invoiceOption = wrapper.findAll('[role="option"]').find((o) => o.text().includes('رقم الفاتورة'))
    await invoiceOption?.trigger('click')
    await flushPromises()

    await wrapper.find('input[dir="ltr"]').setValue('invoice_number')
    const labelInputs = wrapper.findAll('input').filter((i) => i.attributes('placeholder') === 'المبلغ')
    await labelInputs[0]?.setValue('رقم الفاتورة')

    await buttonByText(wrapper, 'حفظ')?.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledTimes(1)
    const [, body] = mockPost.mock.calls[0] as [string, Record<string, unknown>]
    expect(body.semantic_tag).toBe('INVOICE_NUMBER')
  })

  it('sends semantic_tag: null when no tag is selected', async () => {
    mockPost.mockResolvedValueOnce({ data: makeField() })
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])
    await buttonByText(wrapper, 'إضافة حقل')?.trigger('click')

    await wrapper.find('input[dir="ltr"]').setValue('notes')
    const labelInputs = wrapper.findAll('input').filter((i) => i.attributes('placeholder') === 'المبلغ')
    await labelInputs[0]?.setValue('ملاحظات')

    await buttonByText(wrapper, 'حفظ')?.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledTimes(1)
    const [, body] = mockPost.mock.calls[0] as [string, Record<string, unknown>]
    expect(body).toHaveProperty('semantic_tag', null)
  })

  it('pre-selects the current tag when editing an already-tagged field', async () => {
    const field = makeField({ semantic_tag: 'INVOICE_NUMBER', label: 'رقم الفاتورة' })
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'], 'DRAFT', [
      makeGroup({ fields: [field] }),
    ])
    await buttonByLabel(wrapper, 'تعديل الحقل')?.trigger('click')
    await flushPromises()

    const selects = wrapper.findAll('button[role="combobox"]')
    const tagSelectTrigger = selects[2]
    expect(tagSelectTrigger?.text()).toContain('رقم الفاتورة')
  })

  it('disables a tag already used by a different field, with the owner shown', async () => {
    const taggedField = makeField({ id: 1, semantic_tag: 'INVOICE_NUMBER', label: 'رقم الفاتورة القديم' })
    const otherField = makeField({ id: 2, key: 'notes', label: 'ملاحظات', semantic_tag: null })
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'], 'DRAFT', [
      makeGroup({ fields: [taggedField, otherField] }),
    ])
    const editButtons = wrapper.findAll('button[aria-label="تعديل الحقل"]')
    await editButtons[1]?.trigger('click') // otherField's edit dialog
    await flushPromises()

    const selects = wrapper.findAll('button[role="combobox"]')
    const tagSelectTrigger = selects[2]
    await tagSelectTrigger?.trigger('click')
    await flushPromises()

    const invoiceOption = wrapper
      .findAll('[role="option"]')
      .find((o) => o.text().includes('رقم الفاتورة القديم'))
    expect(invoiceOption?.text()).toContain('مستخدم في: رقم الفاتورة القديم')
    expect(invoiceOption?.attributes('data-disabled')).toBeDefined()
  })
```

- [ ] **Step 9: Run tests to verify they fail correctly, then pass**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts`

If `role="combobox"` or `role="option"` selectors don't match reka-ui's actual rendered attributes, inspect actual output first:

```bash
cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts -t "includes the selected semantic_tag" --reporter=verbose 2>&1 | head -100
```

and adjust the selectors in Step 8's tests to match reka-ui's real DOM (reka-ui's `Select` trigger renders as a `button` with `role="combobox"` per its documented ARIA pattern — verify this assumption against the actual rendered HTML before treating a selector mismatch as anything other than a selector-adjustment issue, not a redesign).

Expected after adjustment: all tests in the file PASS, including the 8 pre-existing ones (regression check).

- [ ] **Step 10: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/workflow/WorkflowFieldDesigner.vue app/tests/unit/components/WorkflowFieldDesigner.test.ts && pnpm exec prettier app/components/workflow/WorkflowFieldDesigner.vue app/tests/unit/components/WorkflowFieldDesigner.test.ts --check`
Expected: both exit 0. If prettier reformats, re-run the full test file once more before committing.

- [ ] **Step 11: Commit**

```bash
git add frontend/app/components/workflow/WorkflowFieldDesigner.vue frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts
git commit -m "feat(workflow): add semantic-tag picker to the Designer field dialog

Admins can now assign a field's semantic_tag through the field
add/edit dialog instead of a direct database write — the only way
this worked before (see docs/superpowers/specs/2026-07-14-designer-
semantic-tag-picker-design.md). Options are grouped (التاجر/التمويل/
أخرى) via the new semanticTags.ts catalog. A tag already used by
another field in the same workflow version is disabled in the picker,
with the owning field's label shown — derived from data already
loaded, no new API call. Backend validation and the publish-time
SEMANTIC_MAPPING_AMBIGUOUS check are unchanged and remain
authoritative."
```

---

### Task 4: Fields-table tag badge

**Files:**
- Modify: `frontend/app/components/workflow/WorkflowFieldDesigner.vue`
- Test: `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`

**Interfaces:**
- Consumes: `SEMANTIC_TAG_LABELS` from Task 1 (`@/constants/semanticTags`), `field.semantic_tag` (already on `FieldDefinition`), existing `Tag` icon needed from `lucide-vue-next` (not yet imported in this file).
- Produces: nothing consumed by later tasks — this is the final task in the plan.

- [ ] **Step 1: Write the failing test**

Add to `frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts`:

```typescript
  it('shows a tag badge in the fields table only for tagged fields', async () => {
    const tagged = makeField({ id: 1, key: 'invoice_number', label: 'رقم الفاتورة', semantic_tag: 'INVOICE_NUMBER' })
    const untagged = makeField({ id: 2, key: 'notes', label: 'ملاحظات', semantic_tag: null })
    const wrapper = await mountDesigner(['VIEW'], 'DRAFT', [makeGroup({ fields: [tagged, untagged] })])

    const rows = wrapper.findAll('tr').filter((r) => r.text().includes('رقم الفاتورة') || r.text().includes('ملاحظات'))
    const taggedRow = rows.find((r) => r.text().includes('رقم الفاتورة'))
    const untaggedRow = rows.find((r) => r.text().includes('ملاحظات'))

    expect(taggedRow?.find('[aria-label="علامة دلالية: رقم الفاتورة"]').exists()).toBe(true)
    expect(untaggedRow?.find('[aria-label^="علامة دلالية"]').exists()).toBe(false)
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts -t "tag badge"`
Expected: FAIL — `taggedRow?.find(...).exists()` is `false`, no such element yet.

- [ ] **Step 3: Add the `Tag` icon import and label lookup**

In `frontend/app/components/workflow/WorkflowFieldDesigner.vue`, add `Tag` to the existing `lucide-vue-next` import (currently lines 4-13):

```typescript
import {
  ChevronDown,
  ChevronUp,
  FolderTree,
  ListChecks,
  Lock,
  Pencil,
  Plus,
  Tag,
  Trash2,
} from 'lucide-vue-next'
```

Add `SEMANTIC_TAG_LABELS` to the existing import from Task 1 (currently just `SEMANTIC_TAG_GROUPS`):

```typescript
import { SEMANTIC_TAG_GROUPS, SEMANTIC_TAG_LABELS } from '@/constants/semanticTags'
```

- [ ] **Step 4: Add the badge to the fields-table name cell**

In the fields table (currently lines 459-471), the الاسم cell reads:

```vue
                <TableCell>
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium">{{ field.label }}</span>
                    <Badge
                      v-if="field.is_required"
                      variant="outline"
                      class="border-[var(--severity-amber)]/40 text-[var(--severity-amber)]"
                    >
                      مطلوب
                    </Badge>
                    <Badge v-if="field.is_system" variant="outline">نظامي</Badge>
                  </div>
                </TableCell>
```

Replace with (adds the tag badge, wrapped in `Tooltip`, before the "مطلوب" badge):

```vue
                <TableCell>
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium">{{ field.label }}</span>
                    <Tooltip v-if="field.semantic_tag">
                      <TooltipTrigger as-child>
                        <Badge
                          variant="outline"
                          class="gap-1"
                          :aria-label="`علامة دلالية: ${SEMANTIC_TAG_LABELS[field.semantic_tag]}`"
                        >
                          <Tag class="h-3 w-3" aria-hidden="true" />
                        </Badge>
                      </TooltipTrigger>
                      <TooltipContent>{{ SEMANTIC_TAG_LABELS[field.semantic_tag] }}</TooltipContent>
                    </Tooltip>
                    <Badge
                      v-if="field.is_required"
                      variant="outline"
                      class="border-[var(--severity-amber)]/40 text-[var(--severity-amber)]"
                    >
                      مطلوب
                    </Badge>
                    <Badge v-if="field.is_system" variant="outline">نظامي</Badge>
                  </div>
                </TableCell>
```

`Tooltip`, `TooltipTrigger`, `TooltipContent` are already imported in this file (line 71) and already stubbed in the test file's `mountDesigner` helper (lines 150-154) — no test-infra change needed.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/components/WorkflowFieldDesigner.test.ts`
Expected: PASS, all tests in the file including the new one and all prior tasks' tests (full-file regression check for this component).

- [ ] **Step 6: Lint and format**

Run: `cd frontend && pnpm exec eslint app/components/workflow/WorkflowFieldDesigner.vue app/tests/unit/components/WorkflowFieldDesigner.test.ts && pnpm exec prettier app/components/workflow/WorkflowFieldDesigner.vue app/tests/unit/components/WorkflowFieldDesigner.test.ts --check`
Expected: both exit 0.

- [ ] **Step 7: Typecheck (cross-module change — new prop usage on FieldDefinition.semantic_tag)**

Run: `cd frontend && pnpm run typecheck 2>&1 | grep -i "WorkflowFieldDesigner\|semanticTags\|useWorkflowFields"`
Expected: no output (no new errors attributable to these three files). The command's overall exit code may still be non-zero due to the pre-existing known-red baseline documented in the Bug 3 work (`CommandPalette.vue`, `admin/health.vue`, etc.) — only the filtered grep output matters here.

- [ ] **Step 8: Commit**

```bash
git add frontend/app/components/workflow/WorkflowFieldDesigner.vue frontend/app/tests/unit/components/WorkflowFieldDesigner.test.ts
git commit -m "feat(workflow): show a tag badge in the Designer fields table

A small tooltipped tag icon next to a field's name in the fields table
when it carries a semantic_tag, so admins can audit which fields in a
workflow are already wired to runtime behavior (financing ledger,
merchant autofill, FX-confirmation PDF) without opening each field's
edit dialog."
```

---

## Plan self-review notes

- **Spec coverage:** Decision 1 (no backend work) — enforced via Global Constraints, no backend file touched in any task. Decision 2 (tag catalog, grouping) — Task 1. Decision 3 (client-side duplicate warning, no new endpoint) — Task 3's `tagOwners` computed. Decision 4 (field dialog picker) — Task 3. Decision 5 (table badge) — Task 4. Decision 6 (`FieldDefinitionPayload` type) — Task 2. Decision 7 (error handling) — no new code needed, verified existing `formError`/`toast.error` paths are reused as-is (no task required). Decision 8 (testing checklist) — every item has a corresponding test in Tasks 1, 3, or 4.
- **Type consistency:** `fieldSemanticTag: Ref<FieldSemanticTag | ''>` (Task 3) matches `FieldDefinitionPayload.semantic_tag?: FieldSemanticTag | null` (Task 2) via the `fieldSemanticTag.value || null` coercion at submit time — confirmed consistent across both tasks.
- **Out-of-scope items** (backfilling tags on the live workflow version, type-based filtering, server-side duplicate-check endpoint) are explicitly listed in the spec and not present in any task above.
