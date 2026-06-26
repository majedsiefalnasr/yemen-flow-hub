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
})
