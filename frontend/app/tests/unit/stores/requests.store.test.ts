import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus } from '../../../types/enums'

const mockFetchRequests = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({ fetchRequests: mockFetchRequests }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

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

const buildPage = (page: number, lastPage: number) => ({
  data: [REQUEST_FIXTURE],
  meta: { current_page: page, last_page: lastPage, per_page: 20, total: lastPage * 20 },
})

describe('useRequestsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetchRequests.mockReset()
  })

  describe('initial state', () => {
    it('starts empty with no loading or error', () => {
      const store = useRequestsStore()
      expect(store.requests).toEqual([])
      expect(store.loading).toBe(false)
      expect(store.error).toBeNull()
      expect(store.meta).toBeNull()
    })
  })

  describe('loadRequests()', () => {
    it('sets loading to true while fetching and false after', async () => {
      let resolveFn!: (v: unknown) => void
      mockFetchRequests.mockReturnValueOnce(new Promise(r => (resolveFn = r)))

      const store = useRequestsStore()
      const promise = store.loadRequests()
      expect(store.loading).toBe(true)

      resolveFn(buildPage(1, 1))
      await promise
      expect(store.loading).toBe(false)
    })

    it('populates requests and meta on success', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 3))

      const store = useRequestsStore()
      await store.loadRequests()

      expect(store.requests).toEqual([REQUEST_FIXTURE])
      expect(store.meta?.total).toBe(60)
      expect(store.error).toBeNull()
    })

    it('passes the filter through to fetchRequests', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 1))

      const store = useRequestsStore()
      await store.loadRequests({ search: 'YFH', status: RequestStatus.SUBMITTED, page: 2 })

      expect(mockFetchRequests).toHaveBeenCalledWith({
        search: 'YFH',
        status: RequestStatus.SUBMITTED,
        page: 2,
      })
    })

    it('sets error message and clears data on failure', async () => {
      mockFetchRequests.mockRejectedValueOnce(new Error('fail'))

      const store = useRequestsStore()
      await store.loadRequests()

      expect(store.error).toBe('تعذّر تحميل قائمة الطلبات.')
      expect(store.requests).toEqual([])
      expect(store.meta).toBeNull()
    })
  })

  describe('pagination getters', () => {
    it('hasNextPage is true when not on last page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 3))
      const store = useRequestsStore()
      await store.loadRequests()
      expect(store.hasNextPage).toBe(true)
      expect(store.hasPrevPage).toBe(false)
    })

    it('hasPrevPage is true when not on first page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(2, 3))
      const store = useRequestsStore()
      await store.loadRequests()
      expect(store.hasPrevPage).toBe(true)
    })

    it('neither hasNext nor hasPrev when only one page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 1))
      const store = useRequestsStore()
      await store.loadRequests()
      expect(store.hasNextPage).toBe(false)
      expect(store.hasPrevPage).toBe(false)
    })

    it('currentPage reflects meta.current_page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(2, 5))
      const store = useRequestsStore()
      await store.loadRequests()
      expect(store.currentPage).toBe(2)
    })
  })

  describe('nextPage() / prevPage()', () => {
    it('nextPage loads page + 1', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 3))
      const store = useRequestsStore()
      await store.loadRequests({ search: 'test' })

      mockFetchRequests.mockResolvedValueOnce(buildPage(2, 3))
      await store.nextPage()

      expect(mockFetchRequests).toHaveBeenLastCalledWith({ search: 'test', page: 2 })
    })

    it('prevPage loads page - 1', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(3, 5))
      const store = useRequestsStore()
      await store.loadRequests()

      mockFetchRequests.mockResolvedValueOnce(buildPage(2, 5))
      await store.prevPage()

      expect(mockFetchRequests).toHaveBeenLastCalledWith({ page: 2 })
    })

    it('nextPage is a no-op on last page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(3, 3))
      const store = useRequestsStore()
      await store.loadRequests()

      await store.nextPage()
      expect(mockFetchRequests).toHaveBeenCalledTimes(1)
    })

    it('prevPage is a no-op on first page', async () => {
      mockFetchRequests.mockResolvedValueOnce(buildPage(1, 3))
      const store = useRequestsStore()
      await store.loadRequests()

      await store.prevPage()
      expect(mockFetchRequests).toHaveBeenCalledTimes(1)
    })
  })
})
