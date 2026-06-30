import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const REQUEST_FIXTURE = {
  id: 1,
  reference_number: 'YFH-2026-000001',
  bank_id: 1,
  status: RequestStatus.DRAFT,
  current_owner_role: 'DATA_ENTRY',
  currency: 'USD',
  amount: '50000.00',
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
  port_of_entry: 'Aden',
  notes: null,
  created_by: 1,
  claimed_by: null,
  claim_expires_at: null,
  submitted_at: null,
  bank_approved_at: null,
  created_at: '2026-05-15T00:00:00.000000Z',
  updated_at: '2026-05-15T00:00:00.000000Z',
}

const PAGINATED_FIXTURE = {
  data: [REQUEST_FIXTURE],
  meta: { current_page: 1, last_page: 3, per_page: 20, total: 55 },
}

describe('useRequests', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  describe('fetchRequests()', () => {
    it('calls GET /api/requests and returns paginated data', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      const result = await fetchRequests()

      expect(mockGet).toHaveBeenCalledWith('/api/requests')
      expect(result.data).toEqual([REQUEST_FIXTURE])
      expect(result.meta.total).toBe(55)
    })

    it('appends search param when provided', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      await fetchRequests({ search: 'ACME' })

      expect(mockGet).toHaveBeenCalledWith('/api/requests?search=ACME')
    })

    it('appends status param when provided', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      await fetchRequests({ status: RequestStatus.SUBMITTED })

      expect(mockGet).toHaveBeenCalledWith('/api/requests?status=SUBMITTED')
    })

    it('appends page and per_page params', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      await fetchRequests({ page: 2, per_page: 10 })

      expect(mockGet).toHaveBeenCalledWith('/api/requests?page=2&per_page=10')
    })

    it('combines multiple filters correctly', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      await fetchRequests({ search: 'YFH', status: RequestStatus.BANK_REVIEW, page: 3 })

      const calledWith = (mockGet.mock.calls[0] as [string])[0]
      expect(calledWith).toContain('search=YFH')
      expect(calledWith).toContain('status=BANK_REVIEW')
      expect(calledWith).toContain('page=3')
    })

    it('omits empty status string from query', async () => {
      mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGINATED_FIXTURE })

      const { fetchRequests } = useRequests()
      await fetchRequests({ status: '' })

      expect(mockGet).toHaveBeenCalledWith('/api/requests')
    })

    it('propagates API errors', async () => {
      mockGet.mockRejectedValueOnce(new Error('Network error'))

      const { fetchRequests } = useRequests()
      await expect(fetchRequests()).rejects.toThrow('Network error')
    })
  })
})
