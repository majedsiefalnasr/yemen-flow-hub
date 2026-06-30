import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'

const mockPost = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: vi.fn(), post: mockPost, put: vi.fn() }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const CLONED_REQUEST_FIXTURE = {
  id: 99,
  reference_number: 'YFH-2026-000099',
  bank_id: 1,
  status: RequestStatus.DRAFT,
  current_owner_role: 'DATA_ENTRY',
  currency: 'USD',
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
  port_of_entry: 'Aden',
  notes: null,
  created_by: 1,
  revision_count: 2,
  created_at: '2026-05-21T00:00:00.000000Z',
  updated_at: '2026-05-21T00:00:00.000000Z',
}

describe('useRequests.cloneRequest', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('posts to /api/requests/{id}/clone and returns the new id', async () => {
    mockPost.mockResolvedValueOnce({ data: CLONED_REQUEST_FIXTURE })

    const { cloneRequest } = useRequests()
    const newId = await cloneRequest(42)

    expect(mockPost).toHaveBeenCalledWith('/api/requests/42/clone', {})
    expect(newId).toBe(99)
  })

  it('returns the id from the response data', async () => {
    mockPost.mockResolvedValueOnce({ data: { ...CLONED_REQUEST_FIXTURE, id: 123 } })

    const { cloneRequest } = useRequests()
    const newId = await cloneRequest(7)

    expect(newId).toBe(123)
  })

  it('propagates errors from the API', async () => {
    mockPost.mockRejectedValueOnce(new Error('403 Forbidden'))

    const { cloneRequest } = useRequests()
    await expect(cloneRequest(5)).rejects.toThrow('403 Forbidden')
  })
})
