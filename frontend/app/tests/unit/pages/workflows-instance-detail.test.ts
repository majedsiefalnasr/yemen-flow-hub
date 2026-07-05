// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowInstanceDetailPage from '@/pages/workflows/instances/[id].vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useAuthStore } from '@/stores/auth.store'

const routerReplace = vi.fn().mockResolvedValue(undefined)

// Mutable so individual tests can opt into `?mode=wizard` without affecting
// the rest of the suite, which relies on the default empty query.
let routeQuery: Record<string, string> = {}

vi.stubGlobal('useRoute', () => ({
  params: { id: '5' },
  query: routeQuery,
  path: '/workflows/instances/5',
}))
vi.stubGlobal('useRouter', () => ({ replace: routerReplace }))

const mockShow = vi.fn().mockResolvedValue({
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
    requires_claim: false,
  },
  data: {},
})

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: null },
    queue: { value: [] },
    queueMeta: { value: null },
    availableWorkflows: { value: [] },
    current: { value: null },
    currentWarnings: { value: [] },
    loading: { value: false },
    error: { value: null },
    fetchList: vi.fn(),
    fetchQueue: vi.fn(),
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn(),
    show: mockShow,
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

// `useEngineClaim` (used directly by the page, not stubbed) calls `useApi().post`
// for `claim()`. Mock the api layer so the wizard-mode claim-prompt test can
// assert the claim handler was invoked without hitting a real network call.
const mockApiPost = vi.fn().mockResolvedValue({ success: true, data: { id: 5, claimed_by: 2 } })

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: mockApiPost,
    put: vi.fn(),
    patch: vi.fn(),
    del: vi.fn(),
    isApiError: () => false,
  }),
}))

const stubs = {
  NuxtLink: true,
  DynamicForm: {
    template: '<div data-stub="dynamic-form" />',
    methods: { validate: () => ({ valid: true, values: {} }) },
  },
  // The read-only data/rail/info components render their own children from the
  // schema/graph; stub them so these page-level tests stay focused on the page.
  EngineRequestDataTabs: { template: '<div data-stub="data-tabs" />' },
  EngineOrgProcessRail: { template: '<div data-stub="org-rail" />' },
  EngineQuickInfo: { template: '<div data-stub="quick-info" />' },
  EngineRequestWizard: { template: '<div data-stub="wizard" />' },
}

function makeInstance(overrides: Record<string, unknown> = {}) {
  return {
    id: 5,
    reference: 'ENG-2026-000005',
    status: 'ACTIVE' as const,
    version: 1,
    workflow_version_id: 1,
    current_stage: {
      id: 1,
      code: 'INTAKE',
      name: 'استلام',
      is_initial: true,
      is_final: false,
      sla_duration_minutes: null,
      requires_claim: false,
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
    claimed_by: null,
    claimed_by_user: null,
    claimed_at: null,
    claim_expires_at: null,
    // Default fixture is the stage executor; view-only cases override to false.
    can_execute: true,
    created_by: 1,
    creator: { id: 1, name: 'Test User' },
    created_at: '2026-06-25T00:00:00Z',
    updated_at: '2026-06-25T00:00:00Z',
    ...overrides,
  }
}

describe('workflows/instances/[id].vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    routeQuery = {}
    mockConflictError.value = false
    mockFieldErrors.value = {}
    mockApiPost.mockClear()
    mockShow.mockResolvedValue({
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
        requires_claim: false,
      },
      data: {},
    })
  })

  it('loads the instance on mount', () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadInstance')
    mount(WorkflowInstanceDetailPage, { global: { stubs } })
    expect(spy).toHaveBeenCalledWith(5)
  })

  it('renders the instance reference', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance()
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('ENG-2026-000005')
  })

  it('renders available actions derived from graph edges matching the current stage', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance()
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
    store.current = makeInstance()
    mockConflictError.value = true
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('تم تحديث الطلب من مستخدم آخر')
  })

  it('shows the real claim holder name when claimed by another user', async () => {
    mockShow.mockResolvedValue(
      makeInstance({
        claimed_by: 99,
        claimed_by_user: { id: 99, name: 'سارة أحمد' },
      }),
    )
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('سارة أحمد يعمل على هذا الطلب الآن')
  })

  it('falls back to the generic placeholder when claimed_by_user is null', async () => {
    mockShow.mockResolvedValue(
      makeInstance({
        claimed_by: 99,
        claimed_by_user: null,
      }),
    )
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('مراجع آخر يعمل على هذا الطلب الآن')
  })

  it('renders a stage action panel separate from the form', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance()
    store.graph = {
      nodes: [],
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

    expect(wrapper.text()).toContain('إجراءات المرحلة')
    expect(wrapper.text()).toContain('إرسال')
  })

  it('hides actions and shows a view-only badge for a non-executor', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance({ can_execute: false })
    store.graph = {
      nodes: [],
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

    // The stage has an outgoing edge, but a viewer must not see the action.
    expect(wrapper.text()).not.toContain('إرسال')
    expect(wrapper.text()).toContain('عرض فقط')
    expect(wrapper.text()).toContain('لا تملك صلاحية تنفيذ إجراءات على المرحلة الحالية')
  })

  it('surfaces a duplicate-invoice banner when the show payload carries warnings', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance()
    store.duplicateWarnings = [
      {
        code: 'DUPLICATE_INVOICE',
        message: 'dup',
        duplicates: [{ id: 7, reference: 'ENG-2026-000007' }],
      },
    ]

    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('فاتورة مكرّرة محتملة')
    expect(wrapper.text()).toContain('ENG-2026-000007')
  })

  it('explains why actions are disabled when claim is required and not held', async () => {
    const store = useEngineRequestsStore()
    store.current = makeInstance({
      current_stage: {
        id: 1,
        code: 'INTAKE',
        name: 'استلام',
        is_initial: true,
        is_final: false,
        sla_duration_minutes: null,
        requires_claim: true,
      },
      claimed_by: null,
    })
    store.graph = {
      nodes: [],
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

    expect(wrapper.text()).toContain('يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء')
    expect(wrapper.find('button:disabled').exists()).toBe(true)
  })

  it('renders the wizard for a non-creator user with execute access on the initial stage', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2 } as ReturnType<typeof useAuthStore>['user']
    routeQuery = { mode: 'wizard' }

    const store = useEngineRequestsStore()
    // created_by (1) intentionally differs from the signed-in user (2): a
    // teammate other than the original creator, but still an executor.
    store.current = makeInstance({ created_by: 1, can_execute: true })

    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(true)
    expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(false)
  })

  it('shows the claim banner and blocks wizard editing when another user holds the claim', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2 } as ReturnType<typeof useAuthStore>['user']
    routeQuery = { mode: 'wizard' }

    mockShow.mockResolvedValue(
      makeInstance({
        created_by: 1,
        can_execute: true,
        current_stage: {
          id: 1,
          code: 'INTAKE',
          name: 'استلام',
          is_initial: true,
          is_final: false,
          sla_duration_minutes: null,
          requires_claim: true,
        },
        claimed_by: 99,
        claimed_by_user: { id: 99, name: 'سارة أحمد' },
      }),
    )

    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('سارة أحمد يعمل على هذا الطلب الآن')
    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
  })

  it('requires an explicit claim before the wizard becomes editable when the stage requires a claim', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2 } as ReturnType<typeof useAuthStore>['user']
    routeQuery = { mode: 'wizard' }

    mockShow.mockResolvedValue(
      makeInstance({
        created_by: 1,
        can_execute: true,
        current_stage: {
          id: 1,
          code: 'INTAKE',
          name: 'استلام',
          is_initial: true,
          is_final: false,
          sla_duration_minutes: null,
          requires_claim: true,
        },
        claimed_by: null,
        claimed_by_user: null,
      }),
    )

    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('المتابعة على هذا الطلب')

    const claimButton = wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('المتابعة على هذا الطلب'))
    expect(claimButton).toBeTruthy()
    await claimButton!.trigger('click')

    expect(mockApiPost).toHaveBeenCalledWith('/api/v1/engine-requests/5/claim')
  })
})
