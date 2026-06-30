// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { RequestStatus, UserRole } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'
import { makeImportRequest } from '../fixtures/request-data'
import editPage from '../../../pages/requests/[id]/edit.vue'

vi.stubGlobal('definePageMeta', vi.fn())

const FORM_DATA: RequestFormData = {
  merchant_id: 5,
  currency: 'USD',
  amount: 1000,
  supplier_name: 'Supplier',
  goods_description: 'Goods',
  port_of_entry: 'ميناء عدن',
  notes: '',
  goods_type: 'مواد غذائية',
  payment_terms: 'LC',
  due_date: null,
  invoice_number: 'INV-1',
  invoice_date: '2026-06-03',
  origin_country: 'الصين',
  arrival_port: 'ميناء عدن',
  shipping_port: null,
  customs_office: 'جمارك عدن',
  bl_number: null,
}

const routeState = vi.hoisted(() => ({
  params: { id: '1343' },
}))

const routerPushMock = vi.hoisted(() => vi.fn())
const routerReplaceMock = vi.hoisted(() => vi.fn())
const mockLoadRequest = vi.hoisted(() => vi.fn())
const mockLoadDocuments = vi.hoisted(() => vi.fn())
const mockUpdateRequest = vi.hoisted(() => vi.fn())
const mockPerformAction = vi.hoisted(() => vi.fn())

const requestsStoreState = vi.hoisted(() => ({
  currentRequest: null as ReturnType<typeof makeImportRequest> | null,
  loadingRequest: false,
  loadingDocuments: false,
  documentsLoaded: true,
  documents: [] as any[],
  documentsError: null as string | null,
  saving: false,
  performingAction: false,
  uploading: false,
  uploadError: null as string | null,
  error: null as string | null,
  loadRequest: mockLoadRequest,
  loadDocuments: mockLoadDocuments,
  updateRequest: mockUpdateRequest,
  performAction: mockPerformAction,
  uploadDocument: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => routeState,
  useRouter: () => ({
    push: routerPushMock,
    replace: routerReplaceMock,
  }),
  onBeforeRouteLeave: vi.fn(),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, role: UserRole.DATA_ENTRY, bank_id: 1 },
  }),
}))

vi.mock('../../../stores/requests.store', () => ({
  useRequestsStore: () => requestsStoreState,
}))

function mountPage() {
  return mount(editPage, {
    global: {
      stubs: {
        PageHeader: true,
        CorrectionBanner: true,
        LockedBanner: true,
        DocumentChecklist: true,
        Alert: defineComponent({
          setup(_, { slots }) {
            return () => h('div', slots.default?.())
          },
        }),
        AlertAction: defineComponent({
          setup(_, { slots }) {
            return () => h('div', slots.default?.())
          },
        }),
        AlertDescription: defineComponent({
          setup(_, { slots }) {
            return () => h('p', slots.default?.())
          },
        }),
        AlertTitle: defineComponent({
          setup(_, { slots }) {
            return () => h('h2', slots.default?.())
          },
        }),
        AlertDialog: true,
        AlertDialogAction: true,
        AlertDialogCancel: true,
        AlertDialogContent: true,
        AlertDialogDescription: true,
        AlertDialogFooter: true,
        AlertDialogHeader: true,
        AlertDialogTitle: true,
        Card: defineComponent({
          setup(_, { slots }) {
            return () => h('section', slots.default?.())
          },
        }),
        CardContent: defineComponent({
          setup(_, { slots }) {
            return () => h('div', slots.default?.())
          },
        }),
        CardDescription: defineComponent({
          setup(_, { slots }) {
            return () => h('p', slots.default?.())
          },
        }),
        CardHeader: defineComponent({
          setup(_, { slots }) {
            return () => h('header', slots.default?.())
          },
        }),
        CardTitle: defineComponent({
          setup(_, { slots }) {
            return () => h('h2', slots.default?.())
          },
        }),
        Skeleton: true,
        Button: defineComponent({
          setup(_, { slots, attrs }) {
            return () => h('button', attrs, slots.default?.())
          },
        }),
        RequestForm: defineComponent({
          emits: ['submit'],
          setup(_, { emit, slots }) {
            return () =>
              h(
                'form',
                {
                  'data-testid': 'request-form',
                  onSubmit: (event: Event) => {
                    event.preventDefault()
                    emit('submit', FORM_DATA)
                  },
                },
                slots.actions?.(),
              )
          },
        }),
      },
    },
  })
}

describe('request edit returned resubmit', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.params.id = '1343'
    requestsStoreState.currentRequest = makeImportRequest({
      id: 1343,
      status: RequestStatus.BANK_RETURNED,
    })
    requestsStoreState.loadingRequest = false
    requestsStoreState.loadingDocuments = false
    requestsStoreState.documentsLoaded = true
    requestsStoreState.error = null
    requestsStoreState.saving = false
    requestsStoreState.performingAction = false
    mockLoadRequest.mockResolvedValue(undefined)
    mockLoadDocuments.mockResolvedValue(undefined)
    mockUpdateRequest.mockResolvedValue(undefined)
    mockPerformAction.mockResolvedValue(undefined)
  })

  it('updates then resubmits a bank-returned request', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="request-form"]').trigger('submit')
    await flushPromises()

    expect(mockUpdateRequest).toHaveBeenCalledWith(1343, FORM_DATA)
    expect(mockPerformAction).toHaveBeenCalledWith(1343, 'submit')
    expect(routerPushMock).toHaveBeenCalledWith('/requests/1343')
  })

  it('does not resubmit a plain draft save', async () => {
    requestsStoreState.currentRequest = makeImportRequest({ id: 1343, status: RequestStatus.DRAFT })

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="request-form"]').trigger('submit')
    await flushPromises()

    expect(mockUpdateRequest).toHaveBeenCalledWith(1343, FORM_DATA)
    expect(mockPerformAction).not.toHaveBeenCalled()
  })
})
