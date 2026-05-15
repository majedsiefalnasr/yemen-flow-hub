import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus } from '../../../types/enums'
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
  id: 42,
  reference_number: 'YFH-2026-000042',
  bank_id: 1,
  bank_name: 'بنك اليمن',
  merchant: { id: 5, name: 'شركة الأمل', commercial_register: '12345' },
  status: RequestStatus.DRAFT,
  current_owner_role: 'DATA_ENTRY',
  currency: 'USD',
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
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
  merchant_id: 5,
  currency: 'USD',
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
  port_of_entry: 'Aden',
  notes: '',
}

describe('useRequestsStore — initial state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchRequest.mockReset()
    mockCreateRequest.mockReset()
    mockUpdateRequest.mockReset()
  })

  it('starts with currentRequest null and saving false', () => {
    const store = useRequestsStore()
    expect(store.currentRequest).toBeNull()
    expect(store.saving).toBe(false)
  })
})

describe('useRequestsStore — loadRequest()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchRequest.mockReset()
  })

  it('sets loading to true while fetching and false after', async () => {
    let resolveFn!: (v: unknown) => void
    mockFetchRequest.mockReturnValueOnce(new Promise(r => (resolveFn = r)))

    const store = useRequestsStore()
    const promise = store.loadRequest(42)
    expect(store.loading).toBe(true)

    resolveFn(REQUEST_FIXTURE)
    await promise
    expect(store.loading).toBe(false)
  })

  it('populates currentRequest on success', async () => {
    mockFetchRequest.mockResolvedValueOnce(REQUEST_FIXTURE)

    const store = useRequestsStore()
    await store.loadRequest(42)

    expect(store.currentRequest).toEqual(REQUEST_FIXTURE)
    expect(store.error).toBeNull()
  })

  it('sets error and keeps currentRequest null on failure', async () => {
    mockFetchRequest.mockRejectedValueOnce(new Error('Not found'))

    const store = useRequestsStore()
    await store.loadRequest(99)

    expect(store.currentRequest).toBeNull()
    expect(store.error).toBe('تعذّر تحميل بيانات الطلب.')
    expect(store.loading).toBe(false)
  })
})

describe('useRequestsStore — createRequest()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockCreateRequest.mockReset()
  })

  it('sets saving to true while creating and false after', async () => {
    let resolveFn!: (v: unknown) => void
    mockCreateRequest.mockReturnValueOnce(new Promise(r => (resolveFn = r)))

    const store = useRequestsStore()
    const promise = store.createRequest(FORM_DATA)
    expect(store.saving).toBe(true)

    resolveFn(REQUEST_FIXTURE)
    await promise
    expect(store.saving).toBe(false)
  })

  it('returns new request id and stores currentRequest on success', async () => {
    mockCreateRequest.mockResolvedValueOnce(REQUEST_FIXTURE)

    const store = useRequestsStore()
    const id = await store.createRequest(FORM_DATA)

    expect(id).toBe(42)
    expect(store.currentRequest).toEqual(REQUEST_FIXTURE)
    expect(store.error).toBeNull()
  })

  it('sets error, saving=false and re-throws on failure', async () => {
    mockCreateRequest.mockRejectedValueOnce(new Error('Validation error'))

    const store = useRequestsStore()
    await expect(store.createRequest(FORM_DATA)).rejects.toThrow('Validation error')

    expect(store.error).toBe('تعذّر إنشاء الطلب.')
    expect(store.saving).toBe(false)
  })
})

describe('useRequestsStore — updateRequest()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockUpdateRequest.mockReset()
  })

  it('sets saving to true while updating and false after', async () => {
    let resolveFn!: (v: unknown) => void
    mockUpdateRequest.mockReturnValueOnce(new Promise(r => (resolveFn = r)))

    const store = useRequestsStore()
    const promise = store.updateRequest(42, FORM_DATA)
    expect(store.saving).toBe(true)

    resolveFn(REQUEST_FIXTURE)
    await promise
    expect(store.saving).toBe(false)
  })

  it('updates currentRequest on success', async () => {
    const updated = { ...REQUEST_FIXTURE, supplier_name: 'Global Trade' }
    mockUpdateRequest.mockResolvedValueOnce(updated)

    const store = useRequestsStore()
    await store.updateRequest(42, { ...FORM_DATA, supplier_name: 'Global Trade' })

    expect(store.currentRequest?.supplier_name).toBe('Global Trade')
    expect(store.error).toBeNull()
  })

  it('sets error, saving=false and re-throws on failure', async () => {
    mockUpdateRequest.mockRejectedValueOnce(new Error('Locked'))

    const store = useRequestsStore()
    await expect(store.updateRequest(42, FORM_DATA)).rejects.toThrow('Locked')

    expect(store.error).toBe('تعذّر تحديث الطلب.')
    expect(store.saving).toBe(false)
  })
})
