// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const WorkflowStageEditor = (await import('../../../components/workflow/WorkflowStageEditor.vue'))
  .default

function makeStage(overrides = {}) {
  return {
    id: 1,
    workflow_version_id: 7,
    code: 'intake',
    name: 'Intake',
    description: null,
    sort_order: 1,
    is_initial: true,
    is_final: false,
    sla_duration_minutes: null,
    status: 'ACTIVE',
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeVersion(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT') {
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

async function mountEditor(
  capabilities: Array<'VIEW' | 'CREATE' | 'UPDATE' | 'DELETE'>,
  state: 'DRAFT' | 'PUBLISHED' = 'DRAFT',
  stages = [makeStage()],
) {
  mockGet.mockResolvedValueOnce({ data: stages })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowStageEditor, {
    props: { version: makeVersion(state) },
    global: {
      plugins: [pinia],
      stubs: {
        Teleport: true,
        NuxtLink: true,
        // Tooltip requires a TooltipProvider ancestor supplied at the app root.
        // In isolated mounts we render the trigger's default slot transparently.
        Tooltip: { template: '<div><slot /></div>' },
        TooltipTrigger: { template: '<div><slot /></div>' },
        TooltipContent: { template: '<div><slot /></div>' },
      },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByLabel(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('button').find((b) => b.attributes('aria-label') === label)
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim().includes(text))
}

describe('WorkflowStageEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders ordered stages with type badges', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(wrapper.text()).toContain('intake')
    expect(wrapper.text()).toContain('بداية')
  })

  it('shows add/edit/delete affordances on a DRAFT version for permitted users', async () => {
    const wrapper = await mountEditor(['VIEW', 'CREATE', 'UPDATE', 'DELETE'])

    expect(buttonByText(wrapper, 'إضافة مرحلة')).toBeDefined()
    expect(buttonByLabel(wrapper, 'تعديل المرحلة')).toBeDefined()
    expect(buttonByLabel(wrapper, 'حذف المرحلة')).toBeDefined()
  })

  it('hides mutation affordances on a PUBLISHED (non-DRAFT) version', async () => {
    const wrapper = await mountEditor(['VIEW', 'CREATE', 'UPDATE', 'DELETE'], 'PUBLISHED')

    expect(buttonByText(wrapper, 'إضافة مرحلة')).toBeUndefined()
    expect(buttonByLabel(wrapper, 'تعديل المرحلة')).toBeUndefined()
    expect(wrapper.text()).toContain('مقفلة')
  })

  it('hides create for VIEW-only users even on a DRAFT version', async () => {
    const wrapper = await mountEditor(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة مرحلة')).toBeUndefined()
  })

  it('shows an empty state when there are no stages', async () => {
    const wrapper = await mountEditor(['VIEW'], 'DRAFT', [])

    expect(wrapper.text()).toContain('لا توجد مراحل')
  })
})
