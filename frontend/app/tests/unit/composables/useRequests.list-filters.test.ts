import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const PAGINATED_FIXTURE = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}

describe('useRequests – extended list filters (Story 7.3)', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockGet.mockResolvedValue({ success: true, message: 'OK', data: PAGINATED_FIXTURE })
  })

  it('appends bank_id param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ bank_id: 3 })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('bank_id=3')
  })

  it('omits bank_id when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ bank_id: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('appends currency param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ currency: 'USD' })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('currency=USD')
  })

  it('omits currency when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ currency: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('combines bank_id, currency, search, and status together', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({
      bank_id: 2,
      currency: 'EUR',
      search: 'YFH',
      status: RequestStatus.SUBMITTED,
      page: 1,
    })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('bank_id=2')
    expect(calledWith).toContain('currency=EUR')
    expect(calledWith).toContain('search=YFH')
    expect(calledWith).toContain('status=SUBMITTED')
    expect(calledWith).toContain('page=1')
  })

  it('uses correct API path without trailing ?', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({})

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })
})
