// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsNewPage from '@/pages/workflows/new.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import type { AuthUser } from '@/types/models'

const mockNavigateTo = vi.fn()
vi.stubGlobal('navigateTo', mockNavigateTo)

// shadcn Dialog's DialogContent renders inside a reka-ui DialogPortal, which
// teleports its content to document.body — @vue/test-utils' mount() wrapper
// cannot introspect Teleport targets. Per AGENTS.md, Dialog must not be
// downgraded to raw HTML in the SOURCE component to make tests pass; instead
// (same technique as DemoUserSwitcherDialog.test.ts) the TEST replaces the
// shadcn Dialog module with simple passthrough stubs that render their
// default slots directly into the DOM, no Teleport involved. new.vue itself
// is untouched and keeps using the real `<Dialog>`/`<DialogContent>` API.
function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

vi.mock('@/components/ui/dialog', () => ({
  Dialog: passthrough('Dialog'),
  DialogContent: passthrough('DialogContent'),
  DialogHeader: passthrough('DialogHeader'),
  DialogTitle: passthrough('DialogTitle'),
  DialogDescription: passthrough('DialogDescription'),
  DialogFooter: passthrough('DialogFooter'),
  DialogClose: passthrough('DialogClose'),
}))

vi.mock('@/composables/useScreenPermissions', () => ({
  useScreenPermissions: () => ({
    can: (_screen: string, capability: string) => capability === 'CREATE',
  }),
}))

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

  it('loads available workflows on mount', async () => {
    const store = useEngineRequestsStore()
    const spy = vi.spyOn(store, 'loadAvailableWorkflows')
    mount(WorkflowsNewPage)
    await flushPromises()
    expect(spy).toHaveBeenCalled()
  })

  it('renders a single-workflow picker without auto-creating on mount', async () => {
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'loadAvailableWorkflows').mockImplementation(async () => {
      store.availableWorkflows = [WF_IMPORT]
    })
    vi.spyOn(store, 'createInstance').mockResolvedValue({ id: 99 } as never)

    const wrapper = mount(WorkflowsNewPage)
    await flushPromises()

    expect(store.createInstance).not.toHaveBeenCalled()
    expect(mockNavigateTo).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="create-instance-1"]').exists()).toBe(true)
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

  it('renders the picker inside a dialog and cancel navigates back to the queue', async () => {
    const store = useEngineRequestsStore()
    vi.spyOn(store, 'loadAvailableWorkflows').mockImplementation(async () => {
      store.availableWorkflows = [WF_IMPORT, WF_EXPORT]
    })

    const wrapper = mount(WorkflowsNewPage)
    await flushPromises()

    expect(wrapper.text()).toContain('اختر مسار العمل')

    const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'إلغاء')
    expect(cancelButton).toBeTruthy()

    await cancelButton!.trigger('click')
    await flushPromises()

    expect(mockNavigateTo).toHaveBeenCalledWith('/workflows')
  })
})
