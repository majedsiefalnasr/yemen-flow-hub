// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowInstanceDetailPage from '@/pages/workflows/instances/[id].vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

vi.stubGlobal('useRoute', () => ({ params: { id: '5' } }))

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
    show: vi.fn().mockResolvedValue({
      id: 5,
      reference: 'ENG-2026-000005',
      status: 'ACTIVE',
      version: 1,
      current_stage: {
        id: 1,
        code: 'INTAKE',
        name: 'استلام',
        is_initial: true,
        is_final: false,
        sla_duration_minutes: null,
      },
      data: {},
    }),
    saveDraft: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestHistory', () => ({
  useEngineRequestHistory: () => ({
    history: { value: [] },
    graph: {
      value: {
        nodes: [
          {
            id: 1,
            code: 'INTAKE',
            name: 'استلام',
            display_label: null,
            is_initial: true,
            is_final: false,
            sort_order: 0,
          },
        ],
        edges: [
          {
            id: 9,
            from_stage_id: 1,
            to_stage_id: 2,
            action_id: 1,
            action_code: 'SUBMIT',
            action_name: 'إرسال',
            requires_comment: false,
            is_self_loop: false,
            is_return: false,
          },
        ],
      },
    },
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

const mockExecuteAction = vi.fn().mockResolvedValue({ id: 5, version: 2 })
const mockConflictError = ref(false)
const mockFieldErrors = ref({})

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: mockConflictError,
    fieldErrors: mockFieldErrors,
    executeAction: mockExecuteAction,
  }),
}))

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
  }),
}))

const stubs = {
  NuxtLink: true,
  DynamicForm: {
    template: '<div data-stub="dynamic-form" />',
    methods: { validate: () => ({ valid: true, values: {} }) },
  },
}

describe('workflows/instances/[id].vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockConflictError.value = false
    mockFieldErrors.value = {}
  })

  it('loads the instance on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadInstance')
    mount(WorkflowInstanceDetailPage, { global: { stubs } })
    expect(spy).toHaveBeenCalledWith(5)
  })

  it('renders the instance reference', async () => {
    const store = useEngineRequestsStore()
    store.current = {
      id: 5,
      reference: 'ENG-2026-000005',
      status: 'ACTIVE',
      version: 1,
      workflow_version_id: 1,
      current_stage: {
        id: 1,
        code: 'INTAKE',
        name: 'استلام',
        is_initial: true,
        is_final: false,
        sla_duration_minutes: null,
      },
      bank_id: null,
      bank: null,
      merchant_id: null,
      merchant: null,
      data: {},
      amount: null,
      currency: null,
      invoice_number: null,
      sla_status: null,
    }
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('ENG-2026-000005')
  })

  it('renders available actions derived from graph edges matching the current stage', async () => {
    const store = useEngineRequestsStore()
    store.current = {
      id: 5,
      reference: 'ENG-2026-000005',
      status: 'ACTIVE',
      version: 1,
      workflow_version_id: 1,
      current_stage: {
        id: 1,
        code: 'INTAKE',
        name: 'استلام',
        is_initial: true,
        is_final: false,
        sla_duration_minutes: null,
      },
      bank_id: null,
      bank: null,
      merchant_id: null,
      merchant: null,
      data: {},
      amount: null,
      currency: null,
      invoice_number: null,
      sla_status: null,
    }
    store.graph = {
      nodes: [
        {
          id: 1,
          code: 'INTAKE',
          name: 'استلام',
          display_label: null,
          is_initial: true,
          is_final: false,
          sort_order: 0,
        },
      ],
      edges: [
        {
          id: 9,
          from_stage_id: 1,
          to_stage_id: 2,
          action_id: 1,
          action_code: 'SUBMIT',
          action_name: 'إرسال',
          requires_comment: false,
          is_self_loop: false,
          is_return: false,
        },
      ],
    }
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('إرسال')
  })

  it('shows a conflict banner when conflictError is true', async () => {
    const store = useEngineRequestsStore()
    store.current = {
      id: 5,
      reference: 'ENG-2026-000005',
      status: 'ACTIVE',
      version: 1,
      workflow_version_id: 1,
      current_stage: {
        id: 1,
        code: 'INTAKE',
        name: 'استلام',
        is_initial: true,
        is_final: false,
        sla_duration_minutes: null,
      },
      bank_id: null,
      bank: null,
      merchant_id: null,
      merchant: null,
      data: {},
      amount: null,
      currency: null,
      invoice_number: null,
      sla_status: null,
    }
    mockConflictError.value = true
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('تم تحديث الطلب من مستخدم آخر')
  })
})
