import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { RequestFormData } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useRequests } = await import('../../../composables/useRequests')

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

describe('useRequests — fetchRequest', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/requests/{id} and returns the request', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: REQUEST_FIXTURE })

    const { fetchRequest } = useRequests()
    const result = await fetchRequest(42)

    expect(mockGet).toHaveBeenCalledWith('/api/requests/42')
    expect(result.id).toBe(42)
    expect(result.reference_number).toBe('YFH-2026-000042')
  })

  it('returns merchant nested object', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: REQUEST_FIXTURE })

    const { fetchRequest } = useRequests()
    const result = await fetchRequest(42)

    expect(result.merchant?.id).toBe(5)
    expect(result.merchant?.name).toBe('شركة الأمل')
  })

  it('propagates API error', async () => {
    mockGet.mockRejectedValueOnce(new Error('Not found'))

    const { fetchRequest } = useRequests()
    await expect(fetchRequest(99)).rejects.toThrow('Not found')
  })
})

describe('useRequests — createRequest', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('calls POST /api/requests with form data and returns created request', async () => {
    mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: REQUEST_FIXTURE })

    const { createRequest } = useRequests()
    const result = await createRequest(FORM_DATA)

    expect(mockPost).toHaveBeenCalledWith('/api/requests', FORM_DATA)
    expect(result.id).toBe(42)
    expect(result.status).toBe(RequestStatus.DRAFT)
  })

  it('sends all required fields', async () => {
    mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: REQUEST_FIXTURE })

    const { createRequest } = useRequests()
    await createRequest(FORM_DATA)

    const sent = (mockPost.mock.calls[0] as [string, RequestFormData])[1]
    expect(sent.merchant_id).toBe(5)
    expect(sent.currency).toBe('USD')
    expect(sent.amount).toBe(50000)
    expect(sent.supplier_name).toBe('ACME Corp')
    expect(sent.goods_description).toBe('Electronics')
    expect(sent.port_of_entry).toBe('Aden')
  })

  it('propagates API errors', async () => {
    mockPost.mockRejectedValueOnce(new Error('Validation failed'))

    const { createRequest } = useRequests()
    await expect(createRequest(FORM_DATA)).rejects.toThrow('Validation failed')
  })
})

describe('useRequests — updateRequest', () => {
  beforeEach(() => {
    mockPut.mockReset()
  })

  it('calls PUT /api/requests/{id} and returns updated request', async () => {
    const updated = { ...REQUEST_FIXTURE, supplier_name: 'Global Trade' }
    mockPut.mockResolvedValueOnce({ success: true, message: 'Updated', data: updated })

    const { updateRequest } = useRequests()
    const result = await updateRequest(42, { ...FORM_DATA, supplier_name: 'Global Trade' })

    expect(mockPut).toHaveBeenCalledWith('/api/requests/42', { ...FORM_DATA, supplier_name: 'Global Trade' })
    expect(result.supplier_name).toBe('Global Trade')
  })

  it('propagates API errors on update', async () => {
    mockPut.mockRejectedValueOnce(new Error('Immutable state'))

    const { updateRequest } = useRequests()
    await expect(updateRequest(42, FORM_DATA)).rejects.toThrow('Immutable state')
  })
})
