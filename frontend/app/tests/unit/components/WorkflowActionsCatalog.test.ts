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

const WorkflowActionsCatalog = (
  await import('../../../components/workflow/WorkflowActionsCatalog.vue')
).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeAction(overrides = {}) {
  return {
    id: 1,
    code: 'APPROVE',
    name: 'اعتماد',
    kind: 'APPROVE',
    is_active: true,
    is_system: true,
    is_in_use: false,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

async function mountCatalog(
  capabilities: Array<'VIEW' | 'CREATE' | 'UPDATE' | 'DELETE'>,
  actions = [makeAction()],
) {
  mockGet.mockResolvedValueOnce({ data: actions, meta: META })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowActionsCatalog, {
    global: { plugins: [pinia], stubs: { Teleport: true, NuxtLink: true } },
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

describe('WorkflowActionsCatalog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders actions with kind and system badges', async () => {
    const wrapper = await mountCatalog(['VIEW'])

    expect(wrapper.text()).toContain('APPROVE')
    expect(wrapper.text()).toContain('اعتماد')
    expect(wrapper.text()).toContain('نظامي')
  })

  it('hides create/edit/delete affordances for VIEW-only users', async () => {
    const wrapper = await mountCatalog(['VIEW'])

    expect(buttonByText(wrapper, 'إضافة إجراء')).toBeUndefined()
    expect(buttonByLabel(wrapper, 'تعديل الإجراء')).toBeUndefined()
  })

  it('shows create and edit for permitted users', async () => {
    const wrapper = await mountCatalog(['VIEW', 'CREATE', 'UPDATE'])

    expect(buttonByText(wrapper, 'إضافة إجراء')).toBeDefined()
    expect(buttonByLabel(wrapper, 'تعديل الإجراء')).toBeDefined()
  })

  it('hides delete for system actions even with DELETE permission', async () => {
    const wrapper = await mountCatalog(['VIEW', 'DELETE'], [makeAction({ is_system: true })])

    expect(buttonByLabel(wrapper, 'حذف الإجراء')).toBeUndefined()
  })

  it('shows delete for a custom unused action with DELETE permission', async () => {
    const wrapper = await mountCatalog(
      ['VIEW', 'DELETE'],
      [makeAction({ id: 2, code: 'ESCALATE', is_system: false, is_in_use: false })],
    )

    expect(buttonByLabel(wrapper, 'حذف الإجراء')).toBeDefined()
  })
})
