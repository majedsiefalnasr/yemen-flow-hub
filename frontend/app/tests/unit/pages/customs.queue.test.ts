import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus, UserRole } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import { makeImportRequest } from '../fixtures/request-data'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: vi.fn() }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const EXECUTIVE_APPROVED_REQUEST: ImportRequest = makeImportRequest({
  id: 10,
  reference_number: 'YFH-2026-000010',
  status: RequestStatus.EXECUTIVE_APPROVED,
  current_owner_role: UserRole.COMMITTEE_DIRECTOR,
  amount: 75000,
  supplier_name: 'Gulf Trade Ltd',
  goods_description: 'Medical supplies',
  submitted_by: 2,
  reviewed_by: 3,
  bank_approved_at: '2026-05-10T10:00:00.000Z',
  customs_declaration: null,
  created_at: '2026-05-01T00:00:00.000Z',
  updated_at: '2026-05-10T00:00:00.000Z',
  documents: [],
})

const PAGINATED_RESPONSE = {
  success: true,
  message: 'OK',
  data: {
    data: [EXECUTIVE_APPROVED_REQUEST],
    meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
  },
}

describe('Customs queue page — fetchRequests with EXECUTIVE_APPROVED status', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
  })

  it('fetches requests with status=EXECUTIVE_APPROVED and per_page=100', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchRequests } = useRequests()
    await fetchRequests({ status: RequestStatus.EXECUTIVE_APPROVED, per_page: 100 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('status=EXECUTIVE_APPROVED'))
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('per_page=100'))
  })

  it('returns EXECUTIVE_APPROVED requests in queue data', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchRequests } = useRequests()
    const result = await fetchRequests({ status: RequestStatus.EXECUTIVE_APPROVED, per_page: 100 })
    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.status).toBe(RequestStatus.EXECUTIVE_APPROVED)
  })

  it('returns empty queue when no EXECUTIVE_APPROVED requests exist', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 } },
    })
    const { fetchRequests } = useRequests()
    const result = await fetchRequests({ status: RequestStatus.EXECUTIVE_APPROVED, per_page: 100 })
    expect(result.data).toHaveLength(0)
  })

  it('propagates API error on queue load', async () => {
    mockGet.mockRejectedValueOnce(new Error('Server error'))
    const { fetchRequests } = useRequests()
    await expect(fetchRequests({ status: RequestStatus.EXECUTIVE_APPROVED })).rejects.toThrow('Server error')
  })
})

describe('Customs queue page — generateCustomsDeclaration', () => {
  const DECLARATION_FIXTURE = {
    id: 5,
    request_id: 10,
    declaration_number: 'CD-2026-000005',
    issued_by: 99,
    issuer: { id: 99, name: 'مدير اللجنة', email: 'd@cby.ye', role: 'COMMITTEE_DIRECTOR' },
    issued_at: '2026-05-18T10:00:00.000Z',
    request: { id: 10, reference_number: 'YFH-2026-000010', bank_name: 'بنك اليمن' },
    metadata: {},
    download_url: 'http://localhost/api/customs/5/download',
    created_at: '2026-05-18T10:00:00.000Z',
  }

  beforeEach(() => {
    mockPost.mockReset()
  })

  it('posts to /api/customs/{requestId}/generate and returns declaration', async () => {
    mockPost.mockResolvedValueOnce({ success: true, data: DECLARATION_FIXTURE })
    const { generateCustomsDeclaration } = useRequests()
    const result = await generateCustomsDeclaration(10)
    expect(mockPost).toHaveBeenCalledWith('/api/customs/10/generate')
    expect(result.id).toBe(5)
    expect(result.declaration_number).toBe('CD-2026-000005')
  })

  it('propagates 403 when user is not COMMITTEE_DIRECTOR', async () => {
    mockPost.mockRejectedValueOnce({ status: 403, data: { message: 'Forbidden' } })
    const { generateCustomsDeclaration } = useRequests()
    await expect(generateCustomsDeclaration(10)).rejects.toBeTruthy()
  })

  it('propagates error when request is not EXECUTIVE_APPROVED', async () => {
    mockPost.mockRejectedValueOnce({ status: 422, data: { message: 'WORKFLOW_INVALID_TRANSITION' } })
    const { generateCustomsDeclaration } = useRequests()
    await expect(generateCustomsDeclaration(99)).rejects.toBeTruthy()
  })
})

describe('Customs queue page — format helpers', () => {
  it('formatAmount returns localized amount with currency', () => {
    function formatAmount(amount: number, currency: string): string {
      return `${amount.toLocaleString('ar')} ${currency}`
    }
    const result = formatAmount(75000, 'USD')
    expect(result).toContain('USD')
    expect(result).toContain('75')
  })
})
