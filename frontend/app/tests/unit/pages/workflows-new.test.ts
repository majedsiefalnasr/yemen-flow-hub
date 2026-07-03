// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsNewPage from '@/pages/workflows/new.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import type { AuthUser } from '@/types/models'

const mockNavigateTo = vi.fn()
vi.stubGlobal('navigateTo', mockNavigateTo)

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: {
      value: [
        {
          id: 1,
          code: 'IMPORT_FINANCING',
          name: 'تمويل الواردات',
          version_id: 10,
          version_number: 1,
        },
      ],
    },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn().mockResolvedValue({ id: 99 }),
    show: vi.fn(),
    saveDraft: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: { value: false },
    fieldErrors: { value: {} },
    executeAction: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestHistory', () => ({
  useEngineRequestHistory: () => ({
    history: { value: [] },
    graph: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchHistory: vi.fn(),
    fetchGraph: vi.fn(),
  }),
}))

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

const DATA_ENTRY_USER: AuthUser = {
  id: 1,
  name: 'موظف الإدخال',
  email: 'data-entry@example.com',
  role: UserRole.DATA_ENTRY,
  bank_id: 1,
  bank_name_ar: 'البنك اليمني الدولي',
  bank_name_en: 'Yemen International Bank',
  is_active: true,
}

describe('workflows/new.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockNavigateTo.mockReset()
    const auth = useAuthStore()
    auth.user = DATA_ENTRY_USER
  })

  const WF_IMPORT = {
    id: 1,
    code: 'IMPORT_FINANCING',
    name: 'تمويل الواردات',
    version_id: 10,
    version_number: 1,
  }
  const WF_EXPORT = {
    id: 2,
    code: 'EXPORT_FINANCING',
    name: 'تمويل الصادرات',
    version_id: 20,
    version_number: 1,
  }

  it('loads available workflows on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadAvailableWorkflows')
    mount(WorkflowsNewPage)
    expect(spy).toHaveBeenCalled()
  })

  it('auto-starts the sole workflow without rendering a picker', async () => {
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'loadAvailableWorkflows').mockImplementation(async () => {
      store.availableWorkflows = [WF_IMPORT]
    })
    vi.spyOn(store, 'createInstance').mockResolvedValue({ id: 99 } as never)

    const wrapper = mount(WorkflowsNewPage)
    await flushPromises()

    // No card was clicked, yet the one workflow was created and we navigated in.
    expect(store.createInstance).toHaveBeenCalledWith({ workflow_version_id: 10, data: {} })
    expect(mockNavigateTo).toHaveBeenCalledWith('/workflows/instances/99?mode=wizard')
    expect(wrapper.find('[data-testid="create-instance-1"]').exists()).toBe(false)
  })

  it('renders a picker and creates on click when multiple workflows exist', async () => {
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'loadAvailableWorkflows').mockImplementation(async () => {
      store.availableWorkflows = [WF_IMPORT, WF_EXPORT]
    })
    vi.spyOn(store, 'createInstance').mockResolvedValue({ id: 99 } as never)

    const wrapper = mount(WorkflowsNewPage)
    await flushPromises()

    // A genuine choice exists, so no auto-start; both options are listed.
    expect(store.createInstance).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('تمويل الواردات')
    expect(wrapper.text()).toContain('تمويل الصادرات')

    await wrapper.find('[data-testid="create-instance-2"]').trigger('click')
    await flushPromises()

    expect(store.createInstance).toHaveBeenCalledWith({ workflow_version_id: 20, data: {} })
    expect(mockNavigateTo).toHaveBeenCalledWith('/workflows/instances/99?mode=wizard')
  })
})
