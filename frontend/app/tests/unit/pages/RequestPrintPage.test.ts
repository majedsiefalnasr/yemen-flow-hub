// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { RequestStatus } from '../../../types/enums'
import type { ImportRequest, RequestStageHistory } from '../../../types/models'

const mockFetchRequest = vi.fn()
const mockFetchRequestHistory = vi.fn()

const routeState = {
  params: { id: '42' },
}

vi.mock('vue-router', () => ({
  useRoute: () => routeState,
}))

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequest: mockFetchRequest,
    fetchRequestHistory: mockFetchRequestHistory,
  }),
}))

vi.stubGlobal('definePageMeta', vi.fn())

const requestPrintPage = (await import('../../../pages/requests/[id]/print.vue')).default

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return {
    id: 42,
    reference_number: 'REQ-2026-0042',
    bank_id: 1,
    bank_name: 'بنك الأمل',
    merchant: null,
    status: RequestStatus.BANK_REVIEW,
    current_owner_role: null as never,
    currency: 'USD',
    amount: 75000,
    supplier_name: 'Test Supplier',
    goods_description: 'Test Goods',
    port_of_entry: 'عدن',
    notes: null,
    goods_type: null,
    payment_terms: null,
    due_date: null,
    invoice_number: null,
    invoice_date: null,
    origin_country: null,
    arrival_port: null,
    shipping_port: null,
    customs_office: null,
    bl_number: null,
    created_by: 1,
    created_by_user: { id: 1, name: 'محمد علي' },
    submitted_by: null,
    approved_by: null,
    reviewed_by: null,
    rejected_by: null,
    resubmitted_by: null,
    claimed_by: null,
    claimed_until: null,
    is_claimed: false,
    is_claimed_by_me: false,
    can_be_claimed: false,
    submitted_at: null,
    bank_approved_at: null,
    support_approved_at: null,
    swift_uploaded_by: null,
    swift_uploaded_at: null,
    voting_opened_by: null,
    voting_opened_at: null,
    voting_closed_by: null,
    voting_closed_at: null,
    voting_session_status: null,
    executive_decided_at: null,
    customs_issued_at: null,
    bank_return_comment: null,
    bank_reject_comment: null,
    support_return_comment: null,
    revision_count: 0,
    created_at: '2026-05-18T08:00:00Z',
    updated_at: '2026-05-18T08:00:00Z',
    documents: [
      {
        id: 1,
        type: 'invoice',
        original_filename: 'invoice.pdf',
        mime_type: 'application/pdf',
        size_bytes: 1024,
        checksum: 'abc123',
        uploaded_by: 1,
        uploaded_by_name: 'محمد علي',
        uploaded_at: '2026-05-18T09:00:00Z',
      },
    ],
    ...overrides,
  }
}

function makeHistory(): RequestStageHistory[] {
  return [
    {
      id: 1,
      request_id: 42,
      from_status: null,
      to_status: RequestStatus.DRAFT,
      from_owner_role: null,
      to_owner_role: 'DATA_ENTRY',
      actor_id: 1,
      actor_role: 'DATA_ENTRY',
      performed_by: { id: 1, name: 'محمد علي', role: 'DATA_ENTRY' },
      action: 'create',
      notes: null,
      metadata: null,
      created_at: '2026-05-18T08:00:00Z',
    },
    {
      id: 2,
      request_id: 42,
      from_status: RequestStatus.DRAFT,
      to_status: RequestStatus.BANK_REVIEW,
      from_owner_role: 'DATA_ENTRY',
      to_owner_role: 'BANK_REVIEWER',
      actor_id: 2,
      actor_role: 'BANK_REVIEWER',
      performed_by: { id: 2, name: 'سارة', role: 'BANK_REVIEWER' },
      action: 'bank_review',
      notes: null,
      metadata: null,
      created_at: '2026-05-19T10:00:00Z',
    },
  ]
}

function mountPage() {
  return mount(requestPrintPage, {
    global: {
      stubs: {
        NuxtLink: defineComponent({
          name: 'NuxtLinkStub',
          props: ['to'],
          setup(props, { slots }) {
            return () => h('a', { href: String(props.to ?? '') }, slots.default?.())
          },
        }),
        RequestPrintable: defineComponent({
          name: 'RequestPrintableStub',
          props: ['request', 'history', 'documents'],
          setup(props) {
            return () =>
              h('div', { 'data-testid': 'request-printable' }, [
                h(
                  'span',
                  { 'data-testid': 'printable-ref' },
                  String(props.request?.reference_number ?? ''),
                ),
                h(
                  'span',
                  { 'data-testid': 'printable-history-size' },
                  String(props.history?.length ?? 0),
                ),
                h(
                  'span',
                  { 'data-testid': 'printable-doc-count' },
                  String(props.documents?.length ?? 0),
                ),
              ])
          },
        }),
      },
    },
  })
}

describe('RequestPrintPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
    routeState.params.id = '42'
    window.print = vi.fn()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads request and history, then passes request documents into the printable component', async () => {
    mockFetchRequest.mockResolvedValue(makeRequest())
    mockFetchRequestHistory.mockResolvedValue(makeHistory())

    const wrapper = mountPage()
    await flushPromises()

    expect(mockFetchRequest).toHaveBeenCalledWith(42)
    expect(mockFetchRequestHistory).toHaveBeenCalledWith(42)
    expect(wrapper.get('[data-testid="printable-ref"]').text()).toBe('REQ-2026-0042')
    expect(wrapper.get('[data-testid="printable-history-size"]').text()).toBe('2')
    expect(wrapper.get('[data-testid="printable-doc-count"]').text()).toBe('1')
  })

  it('auto-prints once after a successful load', async () => {
    mockFetchRequest.mockResolvedValue(makeRequest())
    mockFetchRequestHistory.mockResolvedValue(makeHistory())

    mountPage()
    await flushPromises()

    expect(window.print).not.toHaveBeenCalled()
    vi.advanceTimersByTime(299)
    expect(window.print).not.toHaveBeenCalled()
    vi.advanceTimersByTime(1)
    expect(window.print).toHaveBeenCalledTimes(1)
  })

  it('clears the pending auto-print when the page unmounts early', async () => {
    mockFetchRequest.mockResolvedValue(makeRequest())
    mockFetchRequestHistory.mockResolvedValue(makeHistory())

    const wrapper = mountPage()
    await flushPromises()
    wrapper.unmount()

    vi.advanceTimersByTime(300)
    expect(window.print).not.toHaveBeenCalled()
  })

  it('shows a 403-specific message when authorization fails', async () => {
    mockFetchRequest.mockRejectedValue({ statusCode: 403 })
    mockFetchRequestHistory.mockResolvedValue(makeHistory())

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('ليس لديك صلاحية طباعة هذا الطلب.')
    expect(window.print).not.toHaveBeenCalled()
  })

  it('shows a generic error for non-403 failures', async () => {
    mockFetchRequest.mockRejectedValue(new Error('boom'))
    mockFetchRequestHistory.mockResolvedValue(makeHistory())

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('تعذّر تحميل بيانات الطلب.')
    expect(window.print).not.toHaveBeenCalled()
  })

  it('rejects invalid route ids before loading', async () => {
    routeState.params.id = 'invalid'

    const wrapper = mountPage()
    await flushPromises()

    expect(mockFetchRequest).not.toHaveBeenCalled()
    expect(mockFetchRequestHistory).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('معرّف الطلب غير صالح.')
  })
})
