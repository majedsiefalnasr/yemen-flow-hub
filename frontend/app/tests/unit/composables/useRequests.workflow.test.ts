import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'

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
  status: RequestStatus.BANK_REVIEW,
  current_owner_role: 'BANK_REVIEWER',
  currency: 'USD',
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
  port_of_entry: 'Aden',
  notes: null,
  created_by: 1,
  submitted_by: 2,
  reviewed_by: 3,
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
  documents: [],
}

const DOCUMENT_FIXTURE = {
  id: 10,
  type: 'commercial_invoice',
  original_filename: 'invoice.pdf',
  mime_type: 'application/pdf',
  size_bytes: 204800,
  checksum: 'abc123',
  uploaded_at: '2026-05-15T00:00:00.000000Z',
  download_url: 'http://localhost/api/documents/10/download',
}

describe('useRequests — performWorkflowAction', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('calls POST /api/workflow/{id}/bank-review without reason body key', async () => {
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: { ...REQUEST_FIXTURE, status: RequestStatus.BANK_REVIEW } })

    const { performWorkflowAction } = useRequests()
    const result = await performWorkflowAction(42, 'bank-review')

    expect(mockPost).toHaveBeenCalledWith('/api/workflow/42/bank-review', {})
    expect(result.status).toBe(RequestStatus.BANK_REVIEW)
  })

  it('calls POST /api/workflow/{id}/bank-reject with reason in body', async () => {
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: { ...REQUEST_FIXTURE, status: RequestStatus.DRAFT_REJECTED_INTERNAL } })

    const { performWorkflowAction } = useRequests()
    const result = await performWorkflowAction(42, 'bank-reject', 'مستندات ناقصة')

    expect(mockPost).toHaveBeenCalledWith('/api/workflow/42/bank-reject', { reason: 'مستندات ناقصة' })
    expect(result.status).toBe(RequestStatus.DRAFT_REJECTED_INTERNAL)
  })

  it('calls POST /api/workflow/{id}/bank-approve without reason', async () => {
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: { ...REQUEST_FIXTURE, status: RequestStatus.BANK_APPROVED } })

    const { performWorkflowAction } = useRequests()
    await performWorkflowAction(42, 'bank-approve')

    expect(mockPost).toHaveBeenCalledWith('/api/workflow/42/bank-approve', {})
  })

  it('propagates error when API call fails', async () => {
    mockPost.mockRejectedValue(new Error('Network error'))

    const { performWorkflowAction } = useRequests()
    await expect(performWorkflowAction(42, 'bank-review')).rejects.toThrow('Network error')
  })

  it('does not include reason key when reason is undefined', async () => {
    mockPost.mockResolvedValue({ success: true, message: 'ok', data: REQUEST_FIXTURE })

    const { performWorkflowAction } = useRequests()
    await performWorkflowAction(42, 'bank-approve', undefined)

    const body = mockPost.mock.calls[0][1] as Record<string, string>
    expect(Object.keys(body)).not.toContain('reason')
  })
})

describe('useRequests — fetchRequestDocuments', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('fetches documents from GET /api/requests/{id} and returns documents array', async () => {
    mockGet.mockResolvedValue({
      success: true,
      message: 'ok',
      data: { ...REQUEST_FIXTURE, documents: [DOCUMENT_FIXTURE] },
    })

    const { fetchRequestDocuments } = useRequests()
    const docs = await fetchRequestDocuments(42)

    expect(mockGet).toHaveBeenCalledWith('/api/requests/42')
    expect(docs).toHaveLength(1)
    expect(docs[0].id).toBe(10)
    expect(docs[0].original_filename).toBe('invoice.pdf')
  })

  it('returns empty array when documents field is absent', async () => {
    mockGet.mockResolvedValue({
      success: true,
      message: 'ok',
      data: { ...REQUEST_FIXTURE, documents: undefined },
    })

    const { fetchRequestDocuments } = useRequests()
    const docs = await fetchRequestDocuments(42)

    expect(docs).toEqual([])
  })

  it('returns empty array when documents field is empty array', async () => {
    mockGet.mockResolvedValue({
      success: true,
      message: 'ok',
      data: { ...REQUEST_FIXTURE, documents: [] },
    })

    const { fetchRequestDocuments } = useRequests()
    const docs = await fetchRequestDocuments(42)

    expect(docs).toEqual([])
  })

  it('propagates error when GET fails', async () => {
    mockGet.mockRejectedValue(new Error('Unauthorized'))

    const { fetchRequestDocuments } = useRequests()
    await expect(fetchRequestDocuments(42)).rejects.toThrow('Unauthorized')
  })
})
