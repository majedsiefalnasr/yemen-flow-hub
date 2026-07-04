// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { FieldDefinition, FieldGroup, WorkflowVersion } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

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
})
