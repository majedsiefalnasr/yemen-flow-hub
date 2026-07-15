// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowInstanceDetailPage from '@/pages/workflows/instances/[id].vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const routerReplace = vi.fn().mockResolvedValue(undefined)
const routerPush = vi.fn().mockResolvedValue(undefined)

vi.stubGlobal('useRoute', () => ({
  params: { id: '5' },
  query: {},
  path: '/workflows/instances/5',
}))
vi.stubGlobal('useRouter', () => ({ replace: routerReplace, push: routerPush }))

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
    submit: vi.fn(),
    show: mockShow,
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

const mockUploadDocument = vi.fn().mockResolvedValue({ id: 500, field_id: 2 })
const mockRemoveDocument = vi.fn().mockResolvedValue(undefined)
const mockFetchDocuments = vi.fn().mockResolvedValue(undefined)
const mockDocumentsRef = ref<
  Array<{
    id: number
    request_id: number
    field_id: number | null
    stage_id: number
    original_name: string
    mime: string
    size: number
    uploaded_by: { id: number; name: string } | number
    created_at: string | null
  }>
>([])

vi.mock('@/composables/useEngineRequestDocuments', () => ({
  useEngineRequestDocuments: () => ({
    documents: mockDocumentsRef,
    loading: { value: false },
    error: { value: null },
    fetchDocuments: mockFetchDocuments,
    upload: mockUploadDocument,
    remove: mockRemoveDocument,
    downloadUrl: () => '#',
  }),
}))

const mockExecuteAction = vi.fn().mockResolvedValue({ id: 5, version: 2 })
const mockConflictError = ref(false)
const mockFieldErrors = ref({})

const mockToastError = vi.hoisted(() => vi.fn())
const mockToastSuccess = vi.hoisted(() => vi.fn())
vi.mock('vue-sonner', () => ({
  toast: { error: mockToastError, success: mockToastSuccess },
}))

vi.mock('@/composables/useEngineRequestActions', () => ({
  useEngineRequestActions: () => ({
    executing: { value: false },
    conflictError: mockConflictError,
    fieldErrors: mockFieldErrors,
    executeAction: mockExecuteAction,
  }),
}))

const mockFieldGroups = ref<
  Array<{
    id: number
    name: string
    label: string
    sort_order: number
    fields: Array<{
      id: number
      key: string
      type: string
      is_visible: boolean
      is_editable: boolean
      is_required: boolean
      multiple: boolean
      label: string
    }>
  }>
>([])

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: mockFieldGroups,
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
    fetchInitialSchema: vi.fn(),
  }),
}))

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

const mockFormValidate = vi.fn().mockResolvedValue({ valid: true, values: {} })

const stubs = {
  NuxtLink: true,
  DynamicForm: {
    name: 'DynamicForm',
    template: '<div data-stub="dynamic-form" />',
    props: ['fieldGroups', 'modelValue', 'mode', 'requestId', 'uploadTarget'],
    methods: { validate: mockFormValidate },
  },
  EngineFieldDocumentsGroup: {
    name: 'EngineFieldDocumentsGroup',
    template: '<div data-stub="field-documents-group" />',
    props: ['group', 'documents', 'requestId', 'canManage'],
    emits: ['upload', 'remove'],
  },
  // The read-only data/rail/info components render their own children from the
  // schema/graph; stub them so these page-level tests stay focused on the page.
  EngineRequestDataTabs: { template: '<div data-stub="data-tabs" />' },
  EngineOrgProcessRail: { template: '<div data-stub="org-rail" />' },
  EngineQuickInfo: { template: '<div data-stub="quick-info" />' },
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
    mockConflictError.value = false
    mockFieldErrors.value = {}
    mockApiPost.mockClear()
    mockExecuteAction.mockReset().mockResolvedValue({ id: 5, version: 2 })
    mockFormValidate.mockReset().mockResolvedValue({ valid: true, values: {} })
    mockUploadDocument.mockReset().mockResolvedValue({ id: 500, field_id: 2 })
    mockRemoveDocument.mockReset().mockResolvedValue(undefined)
    mockFetchDocuments.mockReset().mockResolvedValue(undefined)
    mockDocumentsRef.value = []
    mockFieldGroups.value = []
    mockToastError.mockClear()
    mockToastSuccess.mockClear()
    routerPush.mockClear()
    mockShow.mockReset().mockResolvedValue(makeInstance())
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

  // UI-RBAC-002: a failed load must render a coded ErrorState with a retry, not
  // a blank shell.
  it('renders a 403 error state when the instance load is forbidden', async () => {
    mockShow.mockRejectedValueOnce({ status: 403 })
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('403')
    expect(wrapper.text()).toContain('لا تملك صلاحية الوصول')
    expect(wrapper.text()).toContain('إعادة المحاولة')
  })

  it('renders a rate-limit error state on a 429 load', async () => {
    mockShow.mockRejectedValueOnce({ status: 429 })
    const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('429')
    expect(wrapper.text()).toContain('كثرة الطلبات')
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
    expect(wrapper.text()).toContain('مستخدم آخر يعمل على هذا الطلب الآن')
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

  // Phase E8: runAction's catch block previously swallowed every error
  // except CLAIM_NOT_HELD (409 REQUEST_STALE, 422 TRANSITION_NOT_AVAILABLE/
  // field errors, 429, 500 all produced zero user feedback — the button just
  // stopped spinning). These assert each branch now surfaces a toast.
  describe('runAction error handling (Phase E8)', () => {
    async function mountWithActionableStage() {
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
      const actionButton = wrapper.findAll('button').find((btn) => btn.text().includes('إرسال'))
      expect(actionButton).toBeTruthy()
      await actionButton!.trigger('click')
      await flushPromises()
      return wrapper
    }

    it('shows a toast and reloads on REQUEST_STALE (409)', async () => {
      mockExecuteAction.mockRejectedValue({
        status: 409,
        data: {
          error_code: 'REQUEST_STALE',
          message: 'Request has been modified by another user.',
        },
      })
      mockShow.mockClear()

      await mountWithActionableStage()

      expect(mockToastError).toHaveBeenCalledWith(
        'تم تعديل الطلب من قِبل مستخدم آخر. تم تحديث البيانات.',
      )
      expect(mockShow).toHaveBeenCalled()
    })

    it('shows a toast and reloads on TRANSITION_NOT_AVAILABLE (422)', async () => {
      mockExecuteAction.mockRejectedValue({
        status: 422,
        data: { error_code: 'TRANSITION_NOT_AVAILABLE', message: 'not available' },
      })
      mockShow.mockClear()

      await mountWithActionableStage()

      expect(mockToastError).toHaveBeenCalledWith(
        'هذا الإجراء لم يعد متاحاً في المرحلة الحالية للطلب.',
      )
      expect(mockShow).toHaveBeenCalled()
    })

    it('surfaces the backend message on STAGE_FIELDS_INVALID (422)', async () => {
      mockExecuteAction.mockRejectedValue({
        status: 422,
        data: { error_code: 'STAGE_FIELDS_INVALID', message: 'Field validation failed.' },
      })

      await mountWithActionableStage()

      expect(mockToastError).toHaveBeenCalledWith('Field validation failed.')
    })

    it('shows a rate-limit toast on 429', async () => {
      mockExecuteAction.mockRejectedValue({ status: 429, data: {} })

      await mountWithActionableStage()

      expect(mockToastError).toHaveBeenCalledWith(
        'عدد كبير من الطلبات خلال وقت قصير. الرجاء الانتظار قليلاً والمحاولة مرة أخرى.',
      )
    })

    it('shows a generic toast on an unhandled 500', async () => {
      mockExecuteAction.mockRejectedValue({ status: 500, data: {} })

      await mountWithActionableStage()

      expect(mockToastError).toHaveBeenCalledWith('تعذّر تنفيذ الإجراء. حاول مرة أخرى.')
    })

    // Regression: a transition can legitimately move the request into a stage
    // the acting user has no VIEW grant on (e.g. a bank reviewer approving into
    // CBY's internal queue). executeAction succeeds; the reload-only load()
    // call that follows then 403s. That must surface as success + a redirect
    // to the queue, never as "the action failed."
    it('shows success and returns to the queue when the post-action reload 403s', async () => {
      // mountWithActionableStage's mount() already triggers one onMounted
      // load() that must succeed normally; only the *second* show() call
      // (triggered by runAction's post-transition reload) should 403.
      mockShow.mockResolvedValueOnce(makeInstance()).mockRejectedValueOnce({
        status: 403,
        data: {},
      })

      await mountWithActionableStage()

      expect(mockToastError).not.toHaveBeenCalled()
      expect(mockToastSuccess).toHaveBeenCalledWith('تم تنفيذ الإجراء بنجاح.')
      expect(routerPush).toHaveBeenCalledWith('/workflows')
    })
  })

  describe('edit mode', () => {
    function editableFieldGroupFixture() {
      return [
        {
          id: 10,
          name: 'basic_info',
          label: 'المعلومات الأساسية',
          sort_order: 1,
          fields: [
            {
              id: 1,
              key: 'supplier_name',
              type: 'TEXT',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: false,
              label: 'اسم المورد',
            },
          ],
        },
        {
          id: 11,
          name: 'documents',
          label: 'المستندات',
          sort_order: 2,
          fields: [
            {
              id: 2,
              key: 'invoice_doc',
              type: 'FILE',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: true,
              label: 'فاتورة',
            },
          ],
        },
      ]
    }

    function findActionButton(wrapper: ReturnType<typeof mount>) {
      return wrapper.findAll('button').find((button) => button.text().includes('إرسال'))
    }

    async function selectTab(wrapper: ReturnType<typeof mount>, label: string) {
      const trigger = wrapper.findAll('button').find((button) => button.text().includes(label))
      expect(trigger).toBeTruthy()
      await trigger!.trigger('mousedown', { button: 0, ctrlKey: false })
      await flushPromises()
    }

    it('a VIEW-only user always sees the read-only data tabs despite editable field rules', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(makeInstance({ can_execute: false }))

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(true)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(false)
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(false)
    })

    it('an executor without a required held claim sees the read-only view', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(
        makeInstance({
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
        }),
      )

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(true)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(false)
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(false)
    })

    it('renders editable forms and FILE groups when the executor can act', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(
        makeInstance({
          can_execute: true,
          current_stage: {
            id: 1,
            code: 'CORRECTION_REVIEW',
            name: 'مراجعة التصحيح',
            is_initial: false,
            is_final: false,
            sla_duration_minutes: null,
            requires_claim: false,
          },
        }),
      )

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      expect(wrapper.find('[data-stub="data-tabs"]').exists()).toBe(false)
      expect(wrapper.find('[data-stub="dynamic-form"]').exists()).toBe(true)
      await selectTab(wrapper, 'المستندات')
      expect(wrapper.find('[data-stub="field-documents-group"]').exists()).toBe(true)
      expect(wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' }).props('canManage')).toBe(
        true,
      )
    })

    it('sends edited formData instead of the frozen request data', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(
        makeInstance({ can_execute: true, data: { supplier_name: 'Original' } }),
      )

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()
      wrapper
        .findComponent({ name: 'DynamicForm' })
        .vm.$emit('update:modelValue', { supplier_name: 'Edited' })
      await wrapper.vm.$nextTick()

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockExecuteAction).toHaveBeenCalledWith(
        5,
        9,
        null,
        expect.objectContaining({ supplier_name: 'Edited' }),
        1,
      )
    })

    it('does not execute the action when a mounted form is invalid', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockFormValidate.mockResolvedValue({ valid: false, values: {} })
      mockShow.mockResolvedValue(makeInstance({ can_execute: true }))

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockExecuteAction).not.toHaveBeenCalled()
    })

    it("validates each mounted form without merging another form's returned values", async () => {
      mockFieldGroups.value = [
        ...editableFieldGroupFixture(),
        {
          id: 12,
          name: 'more_info',
          label: 'معلومات إضافية',
          sort_order: 3,
          fields: [
            {
              id: 3,
              key: 'notes_field',
              type: 'TEXTAREA',
              is_visible: true,
              is_editable: true,
              is_required: false,
              multiple: false,
              label: 'ملاحظات',
            },
          ],
        },
      ]
      mockFormValidate.mockResolvedValue({
        valid: true,
        values: { unrelated_field: 'should-not-leak' },
      })
      mockShow.mockResolvedValue(
        makeInstance({ can_execute: true, data: { supplier_name: 'Kept' } }),
      )

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()

      await selectTab(wrapper, 'معلومات إضافية')

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockFormValidate).toHaveBeenCalledTimes(2)
      const payload = mockExecuteAction.mock.calls[0]![3] as Record<string, unknown>
      expect(payload).toMatchObject({ supplier_name: 'Kept' })
      expect(payload).not.toHaveProperty('unrelated_field')
    })

    it('adds an uploaded document id to the eventual transition payload', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(makeInstance({ can_execute: true, data: {} }))

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()
      await selectTab(wrapper, 'المستندات')
      const file = new File(['x'], 'invoice.pdf', { type: 'application/pdf' })
      wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' }).vm.$emit('upload', 2, file)
      await flushPromises()

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockUploadDocument).toHaveBeenCalledWith(5, file, 2)
      const payload = mockExecuteAction.mock.calls[0]![3] as Record<string, unknown>
      expect(payload).toMatchObject({ invoice_doc: [500] })
    })

    it('drops a removed document id from the eventual transition payload', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow.mockResolvedValue(
        makeInstance({
          can_execute: true,
          data: { invoice_doc: [500, 501] },
        }),
      )
      mockDocumentsRef.value = [
        {
          id: 500,
          request_id: 5,
          field_id: 2,
          stage_id: 1,
          original_name: 'a.pdf',
          mime: 'application/pdf',
          size: 1,
          uploaded_by: { id: 1, name: 'x' },
          created_at: null,
        },
      ]

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()
      await selectTab(wrapper, 'المستندات')
      wrapper.findComponent({ name: 'EngineFieldDocumentsGroup' }).vm.$emit('remove', 500)
      await flushPromises()

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockRemoveDocument).toHaveBeenCalledWith(5, 500)
      const payload = mockExecuteAction.mock.calls[0]![3] as Record<string, unknown>
      expect(payload).toMatchObject({ invoice_doc: [501] })
    })

    it('resets locally edited formData after a stale-action reload returns a new version', async () => {
      mockFieldGroups.value = editableFieldGroupFixture()
      mockShow
        .mockResolvedValueOnce(makeInstance({ version: 1, data: { supplier_name: 'First' } }))
        .mockResolvedValueOnce(makeInstance({ version: 2, data: { supplier_name: 'Second' } }))
      mockExecuteAction.mockRejectedValue({
        status: 409,
        data: { error_code: 'REQUEST_STALE', message: 'stale' },
      })

      const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
      await flushPromises()
      wrapper
        .findComponent({ name: 'DynamicForm' })
        .vm.$emit('update:modelValue', { supplier_name: 'Locally edited' })
      await wrapper.vm.$nextTick()

      await findActionButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(wrapper.findComponent({ name: 'DynamicForm' }).props('modelValue')).toMatchObject({
        supplier_name: 'Second',
      })
    })
  })
})
