// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsNewPage from '@/pages/workflows/new.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

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

describe('workflows/new.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockNavigateTo.mockReset()
  })

  it('loads available workflows on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadAvailableWorkflows')
    mount(WorkflowsNewPage)
    expect(spy).toHaveBeenCalled()
  })

  it('lists each available workflow as a selectable option', async () => {
    const store = useEngineRequestsStore()
    store.availableWorkflows = [
      {
        id: 1,
        code: 'IMPORT_FINANCING',
        name: 'تمويل الواردات',
        version_id: 10,
        version_number: 1,
      },
    ]
    const wrapper = mount(WorkflowsNewPage)
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('تمويل الواردات')
  })

  it('creates an instance and navigates to its detail page on confirm', async () => {
    const store = useEngineRequestsStore()
    store.availableWorkflows = [
      {
        id: 1,
        code: 'IMPORT_FINANCING',
        name: 'تمويل الواردات',
        version_id: 10,
        version_number: 1,
      },
    ]
    vi.spyOn(store, 'createInstance').mockResolvedValue({ id: 99 } as never)
    const wrapper = mount(WorkflowsNewPage)
    await wrapper.vm.$nextTick()

    await wrapper.find('[data-testid="create-instance-1"]').trigger('click')
    await wrapper.vm.$nextTick()

    expect(store.createInstance).toHaveBeenCalledWith({ workflow_version_id: 10, data: {} })
    expect(mockNavigateTo).toHaveBeenCalledWith('/workflows/instances/99')
  })
})
