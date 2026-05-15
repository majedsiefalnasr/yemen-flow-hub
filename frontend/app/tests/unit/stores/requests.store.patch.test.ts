/**
 * Tests for Story 2.5 review patches:
 * - Loading isolation (loadingList vs loadingRequest vs saving)
 * - Double-submit guard (saving guard on createRequest/updateRequest)
 */
import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { RequestFormData } from '../../../types/models'

const mockFetchRequests = vi.fn()
const mockFetchRequest = vi.fn()
const mockCreateRequest = vi.fn()
const mockUpdateRequest = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: mockFetchRequests,
    fetchRequest: mockFetchRequest,
    createRequest: mockCreateRequest,
    updateRequest: mockUpdateRequest,
    uploadDocument: vi.fn(),
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

const REQUEST_FIXTURE = {
  id: 10,
  reference_number: 'YFH-2026-000010',
  bank_id: 1,
  bank_name: null,
  merchant: { id: 3, name: 'تاجر الأمل', commercial_register: null },
  status: 'DRAFT',
  current_owner_role: 'DATA_ENTRY',
  currency: 'USD',
  amount: 25000,
  supplier_name: 'Supplier X',
  goods_description: 'Goods',
  port_of_entry: 'Aden',
  notes: null,
  created_by: 1,
  submitted_by: null,
  reviewed_by: null,
  approved_by: null,
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
  swift_uploaded_at: null,
  executive_decided_at: null,
  customs_issued_at: null,
  revision_count: 0,
  created_at: '2026-05-15T00:00:00.000000Z',
  updated_at: '2026-05-15T00:00:00.000000Z',
}

const FORM_DATA: RequestFormData = {
  merchant_id: 3,
  currency: 'USD',
  amount: 25000,
  supplier_name: 'Supplier X',
  goods_description: 'Goods',
  port_of_entry: 'Aden',
  notes: '',
}

describe('Loading isolation — loadingList does not affect loadingRequest', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchRequests.mockReset()
    mockFetchRequest.mockReset()
  })

  it('loadingList is true during loadRequests, loadingRequest stays false', async () => {
    let resolveList!: (v: unknown) => void
    mockFetchRequests.mockReturnValueOnce(new Promise(r => (resolveList = r)))
    mockFetchRequest.mockResolvedValueOnce(REQUEST_FIXTURE)

    const store = useRequestsStore()
    const listPromise = store.loadRequests()

    expect(store.loadingList).toBe(true)
    expect(store.loadingRequest).toBe(false)

    resolveList({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
    await listPromise
    expect(store.loadingList).toBe(false)
  })

  it('loadingRequest is true during loadRequest, loadingList stays false', async () => {
    let resolveReq!: (v: unknown) => void
    mockFetchRequest.mockReturnValueOnce(new Promise(r => (resolveReq = r)))

    const store = useRequestsStore()
    const reqPromise = store.loadRequest(10)

    expect(store.loadingRequest).toBe(true)
    expect(store.loadingList).toBe(false)

    resolveReq(REQUEST_FIXTURE)
    await reqPromise
    expect(store.loadingRequest).toBe(false)
  })

  it('saving stays false when loadingRequest is in flight', async () => {
    let resolveReq!: (v: unknown) => void
    mockFetchRequest.mockReturnValueOnce(new Promise(r => (resolveReq = r)))

    const store = useRequestsStore()
    const reqPromise = store.loadRequest(10)

    expect(store.saving).toBe(false)

    resolveReq(REQUEST_FIXTURE)
    await reqPromise
  })
})

describe('Double-submit guard — createRequest', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockCreateRequest.mockReset()
  })

  it('rejects concurrent createRequest when saving is in flight', async () => {
    let resolveFirst!: (v: unknown) => void
    mockCreateRequest.mockReturnValueOnce(new Promise(r => (resolveFirst = r)))

    const store = useRequestsStore()
    const first = store.createRequest(FORM_DATA)

    expect(store.saving).toBe(true)
    await expect(store.createRequest(FORM_DATA)).rejects.toThrow()

    resolveFirst(REQUEST_FIXTURE)
    await first
    expect(store.saving).toBe(false)
  })

  it('saving is reset to false after rejection', async () => {
    mockCreateRequest.mockRejectedValueOnce(new Error('Validation'))

    const store = useRequestsStore()
    await expect(store.createRequest(FORM_DATA)).rejects.toThrow('Validation')

    expect(store.saving).toBe(false)
  })

  it('allows second createRequest after first completes', async () => {
    mockCreateRequest.mockResolvedValueOnce(REQUEST_FIXTURE)
    mockCreateRequest.mockResolvedValueOnce({ ...REQUEST_FIXTURE, id: 11 })

    const store = useRequestsStore()
    const id1 = await store.createRequest(FORM_DATA)
    const id2 = await store.createRequest(FORM_DATA)

    expect(id1).toBe(10)
    expect(id2).toBe(11)
  })
})

describe('Double-submit guard — updateRequest', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockUpdateRequest.mockReset()
  })

  it('rejects concurrent updateRequest when saving is in flight', async () => {
    let resolveFirst!: (v: unknown) => void
    mockUpdateRequest.mockReturnValueOnce(new Promise(r => (resolveFirst = r)))

    const store = useRequestsStore()
    const first = store.updateRequest(10, FORM_DATA)

    expect(store.saving).toBe(true)
    await expect(store.updateRequest(10, FORM_DATA)).rejects.toThrow()

    resolveFirst(REQUEST_FIXTURE)
    await first
  })

  it('saving is reset to false after update rejection', async () => {
    mockUpdateRequest.mockRejectedValueOnce(new Error('Locked'))

    const store = useRequestsStore()
    await expect(store.updateRequest(10, FORM_DATA)).rejects.toThrow('Locked')

    expect(store.saving).toBe(false)
  })
})

describe('Amount type — amount is number in RequestFormData', () => {
  it('FORM_DATA.amount is a number (not string)', () => {
    expect(typeof FORM_DATA.amount).toBe('number')
  })

  it('REQUEST_FIXTURE.amount is a number', () => {
    expect(typeof REQUEST_FIXTURE.amount).toBe('number')
  })
})
