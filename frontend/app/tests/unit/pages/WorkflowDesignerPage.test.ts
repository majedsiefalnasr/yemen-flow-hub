// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    del: vi.fn(),
  }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const WorkflowDesignerPage = (await import('../../../pages/admin/workflows.vue')).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }

function makeDefinition(overrides = {}) {
  return {
    id: 1,
    code: 'import_financing',
    name: 'تمويل الاستيراد',
    description: null,
    is_active: true,
    created_at: null,
    updated_at: null,
    version: 1,
    versions: [
      {
        id: 10,
        workflow_definition_id: 1,
        version_number: 1,
        state: 'PUBLISHED',
        is_editable: false,
        published_at: null,
        created_at: null,
        updated_at: null,
        version: 2,
      },
    ],
    ...overrides,
  }
}

async function mountPage(
  capabilities: Array<'VIEW' | 'CREATE' | 'UPDATE' | 'DELETE'>,
  definitions = [makeDefinition()],
) {
  // The page mounts both useWorkflows (definitions) and WorkflowActionsCatalog
  // (actions). Child onMounted runs before parent, so route by URL, not call order.
  mockGet.mockImplementation((url: string) => {
    if (url.includes('workflow-actions')) {
      return Promise.resolve({ data: [], meta: META })
    }
    return Promise.resolve({ data: definitions, meta: META })
  })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: capabilities }

  const wrapper = mount(WorkflowDesignerPage, {
    global: {
      plugins: [pinia],
      stubs: { Teleport: true, NuxtLink: true },
    },
  })
  await flushPromises()

  return wrapper
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((button) => button.text().trim().includes(text))
}

describe('workflow designer page', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('does not mount the page without VIEW permission', async () => {
    const wrapper = await mountPage([])

    expect(wrapper.text()).not.toContain('مصمم مسارات العمل')
  })

  it('renders definitions and version state badges for VIEW users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(wrapper.text()).toContain('تمويل الاستيراد')
    expect(wrapper.text()).toContain('import_financing')
    expect(wrapper.text()).toContain('منشورة')
  })

  it('hides create and clone controls for VIEW-only users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(buttonByText(wrapper, 'إنشاء مسار عمل')).toBeUndefined()
    expect(buttonByText(wrapper, 'استنساخ')).toBeUndefined()
  })

  it('shows the clone action on published versions for CREATE users', async () => {
    const wrapper = await mountPage(['VIEW', 'CREATE'])

    expect(buttonByText(wrapper, 'استنساخ')).toBeDefined()
  })

  it('disables clone for draft versions (locked, not cloneable)', async () => {
    const draftDefinition = makeDefinition({
      versions: [
        {
          id: 11,
          workflow_definition_id: 1,
          version_number: 2,
          state: 'DRAFT',
          is_editable: true,
          published_at: null,
          created_at: null,
          updated_at: null,
          version: 1,
        },
      ],
    })
    const wrapper = await mountPage(['VIEW', 'CREATE'], [draftDefinition])

    expect(wrapper.text()).toContain('مسودة')
    expect(buttonByText(wrapper, 'استنساخ')).toBeUndefined()
  })

  it('shows an empty state when there are no definitions', async () => {
    const wrapper = await mountPage(['VIEW'], [])

    expect(wrapper.text()).toContain('لا توجد مسارات عمل')
  })
})
