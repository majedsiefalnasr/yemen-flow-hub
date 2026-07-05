// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import type { FieldDefinition, FieldGroup, WorkflowVersion } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDelete, put: mockPut }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

// shadcn Dialog's DialogContent renders inside a reka-ui DialogPortal, which
// teleports its content to document.body — @vue/test-utils' mount() wrapper
// cannot introspect Teleport targets. Per AGENTS.md, Dialog must not be
// downgraded to raw HTML in the SOURCE component to make tests pass; instead
// (same technique as DemoUserSwitcherDialog.test.ts) the TEST replaces the
// shadcn Dialog module with simple passthrough stubs that render their
// default slots directly into the DOM, no Teleport involved.
// WorkflowFieldDesigner.vue itself is untouched and keeps using the real
// `<Dialog>`/`<DialogContent>` API surface.
function passthrough(name: string) {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

// Dialog only renders its slot while `open` is true, mirroring the real
// component's behavior — this keeps multiple sibling dialogs (add-group vs.
// edit-field) from both being present in the DOM at once when only one is
// actually open.
const DialogStub = defineComponent({
  name: 'Dialog',
  props: { open: { type: Boolean, default: false } },
  setup(props, { slots }) {
    return () => (props.open ? h('div', slots.default?.()) : null)
  },
})

vi.mock('../../../components/ui/dialog', () => ({
  Dialog: DialogStub,
  DialogContent: passthrough('DialogContent'),
  DialogHeader: passthrough('DialogHeader'),
  DialogTitle: passthrough('DialogTitle'),
  DialogDescription: passthrough('DialogDescription'),
  DialogFooter: passthrough('DialogFooter'),
}))

const WorkflowFieldDesigner = (
  await import('../../../components/workflow/WorkflowFieldDesigner.vue')
).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 0 }

function makeField(overrides: Partial<FieldDefinition> = {}): FieldDefinition {
  return {
    id: 1,
    workflow_version_id: 7,
    field_group_id: 100,
    key: 'amount',
    label: 'المبلغ',
    type: 'CURRENCY',
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    reference_table_id: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: false,
    is_required: true,
    is_system: false,
    sort_order: 0,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeGroup(overrides: Partial<FieldGroup> = {}): FieldGroup {
  return {
    id: 100,
    workflow_version_id: 7,
    name: 'request_data',
    label: 'بيانات الطلب',
    sort_order: 0,
    fields: [makeField()],
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeVersion(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT'): WorkflowVersion {
  return {
    id: 7,
    workflow_definition_id: 1,
    version_number: 1,
    state,
    is_editable: state === 'DRAFT',
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

async function mountDesigner(
  capabilities: Array<'VIEW' | 'MANAGE'>,
  state: 'DRAFT' | 'PUBLISHED' = 'DRAFT',
  groups = [makeGroup()],
) {
  mockGet.mockImplementation((url: string) => {
    if (url.includes('field-groups')) return Promise.resolve({ data: groups })
    if (url.includes('reference-tables')) return Promise.resolve({ data: [], meta: META })
    return Promise.resolve({ data: [] })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowFieldDesigner, {
    props: { version: makeVersion(state) },
    global: {
      plugins: [pinia],
      stubs: {
        Teleport: true,
        NuxtLink: true,
        // Tooltip needs a TooltipProvider ancestor (supplied at app root); render
        // the trigger slot transparently in isolated mounts.
        Tooltip: { template: '<div><slot /></div>' },
        TooltipTrigger: { template: '<div><slot /></div>' },
        TooltipContent: { template: '<div><slot /></div>' },
      },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim().includes(text))
}

function buttonByLabel(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('button').find((b) => b.attributes('aria-label') === label)
}

describe('WorkflowFieldDesigner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders group tabs and field rows', async () => {
    const wrapper = await mountDesigner(['VIEW'])

    expect(wrapper.text()).toContain('بيانات الطلب')
    expect(wrapper.text()).toContain('amount')
    expect(wrapper.text()).toContain('عملة')
  })

  it('shows add-group and add-field for MANAGE users on a DRAFT version', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])

    expect(buttonByText(wrapper, 'إضافة مجموعة')).toBeDefined()
    expect(buttonByText(wrapper, 'إضافة حقل')).toBeDefined()
  })

  it('hides mutation affordances for VIEW-only users', async () => {
    const wrapper = await mountDesigner(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة مجموعة')).toBeUndefined()
    expect(buttonByText(wrapper, 'إضافة حقل')).toBeUndefined()
  })

  it('locks the designer on a PUBLISHED version', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByText(wrapper, 'إضافة مجموعة')).toBeUndefined()
    expect(wrapper.text()).toContain('مقفلة')
  })

  it('shows an empty state when there are no groups', async () => {
    const wrapper = await mountDesigner(['VIEW'], 'DRAFT', [])

    expect(wrapper.text()).toContain('لا توجد مجموعات حقول')
  })

  it('enables the group select in the add-field dialog', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])
    const addFieldButton = buttonByText(wrapper, 'إضافة حقل')
    await addFieldButton?.trigger('click')

    // The group Select's trigger button must not carry the disabled attribute.
    const disabledTriggers = wrapper.findAll('button[disabled]')
    const groupSelectDisabled = disabledTriggers.some((btn) => btn.html().includes('اختر المجموعة'))
    expect(groupSelectDisabled).toBe(false)
  })

  it('shows an edit affordance for existing fields', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'])

    expect(buttonByLabel(wrapper, 'تعديل الحقل')).toBeDefined()
  })

  it('hides field edit affordance on a PUBLISHED version', async () => {
    const wrapper = await mountDesigner(['VIEW', 'MANAGE'], 'PUBLISHED')

    expect(buttonByLabel(wrapper, 'تعديل الحقل')).toBeUndefined()
  })

  it('sends a PUT with the field version when saving an edited field', async () => {
    const field = makeField({ version: 1 })
    mockPut.mockResolvedValueOnce({ data: makeField({ version: 2 }) })

    const wrapper = await mountDesigner(['VIEW', 'MANAGE'], 'DRAFT', [
      makeGroup({ fields: [field] }),
    ])
    await buttonByLabel(wrapper, 'تعديل الحقل')?.trigger('click')
    await flushPromises()

    await buttonByText(wrapper, 'حفظ')?.trigger('click')
    await flushPromises()

    expect(mockPut).toHaveBeenCalledTimes(1)
    const [, body] = mockPut.mock.calls[0] as [string, Record<string, unknown>]
    expect(body).toMatchObject({ version: field.version })
  })
})
