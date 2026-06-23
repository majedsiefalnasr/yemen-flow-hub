// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { StageFieldRule, WorkflowStage, WorkflowVersion } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const StageFieldRuleMatrix = (await import('../../../components/workflow/StageFieldRuleMatrix.vue'))
  .default

const FIELD = {
  id: 10,
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
  is_required: false,
  is_system: false,
  sort_order: 0,
  created_at: null,
  updated_at: null,
  version: 1,
}

const GROUP = {
  id: 100,
  workflow_version_id: 7,
  name: 'request_data',
  label: 'بيانات الطلب',
  sort_order: 0,
  fields: [FIELD],
  created_at: null,
  updated_at: null,
  version: 1,
}

function makeStage(): WorkflowStage {
  return {
    id: 5,
    workflow_version_id: 7,
    code: 'intake',
    name: 'الاستلام',
    description: null,
    sort_order: 1,
    is_initial: true,
    is_final: false,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
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

async function mountMatrix(
  state: 'DRAFT' | 'PUBLISHED' = 'DRAFT',
  rules: StageFieldRule[] = [],
  groups = [GROUP],
) {
  mockGet.mockImplementation((url: string) => {
    if (url.includes('field-rules')) return Promise.resolve({ data: rules })
    if (url.includes('field-groups')) return Promise.resolve({ data: groups })
    return Promise.resolve({ data: [] })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: ['VIEW', 'CREATE', 'UPDATE'] }

  const wrapper = mount(StageFieldRuleMatrix, {
    props: { stage: makeStage(), version: makeVersion(state) },
    global: { plugins: [pinia], stubs: { Teleport: true, NuxtLink: true } },
  })
  await flushPromises()

  return wrapper
}

function checkboxByLabel(wrapper: VueWrapper, label: string) {
  return wrapper.find(`[aria-label="${label}"]`)
}

describe('StageFieldRuleMatrix', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders a row per field with three rule toggles', async () => {
    const wrapper = await mountMatrix()

    expect(wrapper.text()).toContain('amount')
    expect(checkboxByLabel(wrapper, 'ظاهر amount').exists()).toBe(true)
    expect(checkboxByLabel(wrapper, 'قابل للتعديل amount').exists()).toBe(true)
    expect(checkboxByLabel(wrapper, 'مطلوب amount').exists()).toBe(true)
  })

  it('disables toggles on a PUBLISHED version', async () => {
    const wrapper = await mountMatrix('PUBLISHED')

    expect(checkboxByLabel(wrapper, 'ظاهر amount').attributes('disabled')).toBeDefined()
  })

  it('shows an empty state when there are no fields', async () => {
    const wrapper = await mountMatrix('DRAFT', [], [{ ...GROUP, fields: [] }])

    expect(wrapper.text()).toContain('لا توجد حقول')
  })
})
