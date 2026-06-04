// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { RequestStatus, UserRole } from '../../../types/enums'
import { makeImportRequest } from '../fixtures/request-data'
import requestDetailPage from '../../../pages/requests/[id]/index.vue'
import newRequestPage from '../../../pages/requests/new.vue'

vi.stubGlobal('definePageMeta', vi.fn())

const navigateToMock = vi.hoisted(() => vi.fn())
vi.stubGlobal('navigateTo', navigateToMock)

const routeState = vi.hoisted(() => ({
  params: { id: '42' },
  query: {} as Record<string, string>,
  path: '/requests/42',
}))

const routerReplaceMock = vi.hoisted(() => vi.fn())
const routerPushMock = vi.hoisted(() => vi.fn())
const mockCloneRequest = vi.hoisted(() => vi.fn())
const mockLoadRequest = vi.hoisted(() => vi.fn())
const mockLoadVotingDetail = vi.hoisted(() => vi.fn())

const authStoreState = vi.hoisted(() => ({
  user: { id: 1, bank_id: 1, role: 'DATA_ENTRY' },
}))

const requestsStoreState = vi.hoisted(() => ({
  currentRequest: null as ReturnType<typeof makeImportRequest> | null,
  loadingRequest: false,
  downloadingCustoms: false,
  history: [] as any[],
  documents: [] as any[],
  listIds: [42] as number[],
  error: null as string | null,
  loadRequest: mockLoadRequest,
  loadDocuments: vi.fn(),
  loadHistory: vi.fn(),
  bankReturn: vi.fn(),
  supportReturn: vi.fn(),
  bankRejectTerminal: vi.fn(),
  issueCustomsDeclaration: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => routeState,
  useRouter: () => ({
    replace: routerReplaceMock,
    push: routerPushMock,
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => authStoreState,
}))

vi.mock('../../../stores/requests.store', () => ({
  useRequestsStore: () => requestsStoreState,
}))

vi.mock('../../../stores/voting.store', () => ({
  useVotingStore: () => ({
    votingDetail: null,
    loadingDetail: false,
    loadVotingDetail: mockLoadVotingDetail,
    openSession: vi.fn(),
    closeSession: vi.fn(),
    finalizeDecision: vi.fn(),
    directorOverride: vi.fn(),
  }),
}))

vi.mock('../../../composables/useClaimLifecycle', () => ({
  useClaimLifecycle: () => ({
    claimRequest: vi.fn(),
    releaseRequest: vi.fn(),
    verifyClaimAlive: vi.fn(),
    startHeartbeat: vi.fn(),
    stopHeartbeat: vi.fn(),
    claimError: null,
    sessionExpired: false,
  }),
}))

vi.mock('../../../composables/useDocumentPermissions', () => ({
  canDownloadCustoms: () => false,
}))

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    cloneRequest: mockCloneRequest,
  }),
}))

vi.mock('../../../components/ui/alert-dialog', async () => {
  const { defineComponent, h } = await import('vue')

  const slotStub = (tag: string) => defineComponent({
    setup(_, { slots, attrs }) {
      return () => h(tag, attrs, slots.default?.())
    },
  })

  return {
    AlertDialog: defineComponent({
      props: { open: { type: Boolean, default: false } },
      setup(props, { slots }) {
        return () => props.open ? h('div', { 'data-testid': 'alert-dialog' }, slots.default?.()) : null
      },
    }),
    AlertDialogCancel: slotStub('button'),
    AlertDialogContent: slotStub('div'),
    AlertDialogDescription: slotStub('p'),
    AlertDialogFooter: slotStub('div'),
    AlertDialogHeader: slotStub('div'),
    AlertDialogTitle: slotStub('h2'),
  }
})

vi.mock('../../../components/ui/button', async () => {
  const { defineComponent, h } = await import('vue')

  return {
    Button: defineComponent({
      setup(_, { slots, attrs }) {
        return () => h('button', attrs, slots.default?.())
      },
    }),
  }
})

vi.mock('../../../components/wizard/RequestWizard.vue', async () => {
  const { defineComponent, h } = await import('vue')

  return {
    default: defineComponent({
      name: 'RequestWizard',
      setup() {
        return () => h('div', { 'data-testid': 'request-wizard' }, 'RequestWizard')
      },
    }),
  }
})

function buildDetailRequest(status: RequestStatus = RequestStatus.BANK_REJECTED) {
  return makeImportRequest({
    id: 42,
    status,
    bank_name: 'بنك اليمن',
    merchant: { id: 1, name: 'التاجر', commercial_register: null },
    created_by_user: { id: 1, name: 'صاحب الطلب' },
  })
}

function mountDetailPage() {
  return mount(requestDetailPage, {
    global: {
      stubs: {
        NuxtLink: defineComponent({ props: ['to'], setup(_, { slots }) { return () => h('a', slots.default?.()) } }),
        StatusBadge: true,
        LockedBanner: true,
        CorrectionBanner: true,
        ActiveReviewBanner: true,
        ClaimedByOthersBanner: true,
        ActionsPanel: true,
        DocumentChecklist: true,
        VotingPanel: true,
        WorkflowTimeline: true,
        AuditTimeline: true,
        WorkflowProgress: true,
      },
    },
  })
}

describe('request detail clone flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.params.id = '42'
    routeState.path = '/requests/42'
    routeState.query = {}
    authStoreState.user.role = UserRole.DATA_ENTRY
    requestsStoreState.currentRequest = buildDetailRequest()
    requestsStoreState.loadingRequest = false
    requestsStoreState.error = null
    mockLoadRequest.mockImplementation(async () => {})
  })

  it('shows the clone button for BANK_ADMIN on a terminal rejection', async () => {
    authStoreState.user.role = UserRole.BANK_ADMIN

    const wrapper = mountDetailPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="clone-request-btn"]').exists()).toBe(true)
  })

  it('opens the dialog and navigates to the cloned draft after confirm', async () => {
    mockCloneRequest.mockResolvedValueOnce(99)

    const wrapper = mountDetailPage()
    await flushPromises()

    await wrapper.get('[data-testid="clone-request-btn"]').trigger('click')
    expect(wrapper.find('[data-testid="alert-dialog"]').exists()).toBe(true)

    await wrapper.get('[data-testid="clone-confirm-btn"]').trigger('click')
    await flushPromises()

    expect(mockCloneRequest).toHaveBeenCalledWith(42)
    expect(navigateToMock).toHaveBeenCalledWith('/requests/99/edit')
  })

  it('keeps the dialog open and shows the error when cloning fails', async () => {
    mockCloneRequest.mockRejectedValueOnce(new Error('Forbidden'))

    const wrapper = mountDetailPage()
    await flushPromises()

    await wrapper.get('[data-testid="clone-request-btn"]').trigger('click')
    await wrapper.get('[data-testid="clone-confirm-btn"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('تعذر إنشاء النسخة الآن. أعد المحاولة بعد قليل.')
    expect(wrapper.find('[data-testid="alert-dialog"]').exists()).toBe(true)
  })
})

describe('new.vue clone_of flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.path = '/requests/new'
    routeState.params.id = '42'
    routeState.query = {}
  })

  it('strips the query before cloning and redirects to the edit page', async () => {
    routeState.query = { clone_of: '42' }
    mockCloneRequest.mockResolvedValueOnce(77)
    const replaceStateSpy = vi.spyOn(window.history, 'replaceState')

    const wrapper = mount(newRequestPage, {
      global: {
        stubs: {
          RequestWizard: true,
        },
      },
    })
    await flushPromises()

    expect(mockCloneRequest).toHaveBeenCalledWith(42)
    expect(replaceStateSpy).toHaveBeenCalledWith(window.history.state, '', '/requests/new')
    expect(navigateToMock).toHaveBeenCalledWith('/requests/77/edit', { replace: true })
    expect(wrapper.find('[data-testid="request-wizard"]').exists()).toBe(false)

    replaceStateSpy.mockRestore()
  })

  it('shows the forbidden banner when clone_of fails with 403', async () => {
    routeState.query = { clone_of: '42' }
    mockCloneRequest.mockRejectedValueOnce({ statusCode: 403 })

    const wrapper = mount(newRequestPage, {
      global: {
        stubs: {
          RequestWizard: true,
        },
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('ليس لديك صلاحية نسخ هذا الطلب.')
    expect(navigateToMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="request-wizard"]').exists()).toBe(false)
  })

  it('mounts the complete request wizard for normal request creation', async () => {
    const wrapper = mount(newRequestPage, {
      global: {
        stubs: {
          RequestWizard: {
            template: '<div data-testid="request-wizard">RequestWizard</div>',
          },
        },
      },
    })
    await flushPromises()

    expect(mockCloneRequest).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="request-wizard"]').exists()).toBe(true)
  })
})
