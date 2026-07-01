// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsIndexPage from '@/pages/workflows/index.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

vi.stubGlobal('navigateTo', vi.fn())

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn(),
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

describe('workflows/index.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    // Stub loadQueue/loadList to no-ops so tests that pre-set store data are not overwritten by
    // the composable mock (which always returns empty arrays). Individual tests can re-spy as needed.
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'loadQueue').mockResolvedValue(undefined)
    vi.spyOn(store, 'loadList').mockResolvedValue(undefined)
  })

  it('calls loadQueue on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadQueue')
    mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })
    expect(spy).toHaveBeenCalled()
  })

  it('renders empty state when queue has no items', () => {
    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })
    expect(wrapper.findComponent({ name: 'EmptyTitle' }).exists()).toBe(true)
  })

  it('renders the operational page header and metrics', () => {
    const store = useEngineRequestsStore()
    store.queue = []
    store.instances = []

    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })

    expect(wrapper.text()).toContain('سير العمل الديناميكي')
    expect(wrapper.text()).toContain('طابوري')
    expect(wrapper.text()).toContain('جميع الطلبات')
  })

  it('filters rows by reference search', async () => {
    const store = useEngineRequestsStore()
    store.queue = [
      { id: 1, reference: 'ENG-001', status: 'ACTIVE', current_stage: { name: 'استلام' } },
      { id: 2, reference: 'ENG-002', status: 'ACTIVE', current_stage: { name: 'اعتماد' } },
    ] as any

    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })

    await wrapper.get('input[placeholder="بحث بالمرجع..."]').setValue('ENG-002')

    expect(wrapper.text()).toContain('ENG-002')
    expect(wrapper.text()).not.toContain('ENG-001')
  })

  it('shows explicit view action for rows', () => {
    const store = useEngineRequestsStore()
    store.queue = [
      { id: 1, reference: 'ENG-001', status: 'ACTIVE', current_stage: { name: 'استلام' } },
    ] as any

    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: { NuxtLink: true } },
    })

    expect(wrapper.text()).toContain('عرض')
  })
})
