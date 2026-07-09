import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useBanks } = await import('../../../composables/useBanks')

const BANK_FIXTURE = {
  id: 1,
  name_ar: 'البنك التجاري اليمني',
  name_en: 'Yemen Commercial Bank',
  code: 'YCB',
  is_active: true,
}

describe('useBanks', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockGet.mockReset()
    mockPost.mockReset()
    mockPut.mockReset()
  })

  describe('fetchBanks()', () => {
    it('calls GET /api/v1/banks with per_page=200 and returns the data array', async () => {
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [BANK_FIXTURE],
          meta: { current_page: 1, last_page: 1, per_page: 200, total: 1 },
        },
      })
      const { fetchBanks } = useBanks()
      const result = await fetchBanks()
      expect(mockGet).toHaveBeenCalledWith('/api/v1/banks?per_page=200')
      expect(result).toEqual([BANK_FIXTURE])
    })

    it('propagates errors from the API', async () => {
      mockGet.mockRejectedValueOnce(new Error('Network error'))
      const { fetchBanks } = useBanks()
      await expect(fetchBanks()).rejects.toThrow('Network error')
    })

    // FE-003: fetchBanks() is the read-only dropdown/selector helper called
    // independently by multiple pages (IdentityUsersPage, merchants.vue,
    // admin/banks.vue) in the same session — it must be cached so a second
    // call in the same session doesn't refetch the identical unfiltered list.
    it('caches the result so a second call does not hit the API again', async () => {
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [BANK_FIXTURE],
          meta: { current_page: 1, last_page: 1, per_page: 200, total: 1 },
        },
      })
      const { fetchBanks } = useBanks()
      await fetchBanks()
      const second = await fetchBanks()

      expect(mockGet).toHaveBeenCalledTimes(1)
      expect(second).toEqual([BANK_FIXTURE])
    })

    it('createBank() invalidates the cache so the next fetchBanks() call refetches', async () => {
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [BANK_FIXTURE],
          meta: { current_page: 1, last_page: 1, per_page: 200, total: 1 },
        },
      })
      mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: BANK_FIXTURE })
      mockGet.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          data: [BANK_FIXTURE, { ...BANK_FIXTURE, id: 2 }],
          meta: { current_page: 1, last_page: 1, per_page: 200, total: 2 },
        },
      })

      const { fetchBanks, createBank } = useBanks()
      await fetchBanks()
      await createBank({
        organization_id: 1,
        name_ar: 'بنك جديد',
        name_en: 'New Bank',
        code: 'NB',
        is_active: true,
      })
      const afterCreate = await fetchBanks()

      expect(mockGet).toHaveBeenCalledTimes(2)
      expect(afterCreate).toHaveLength(2)
    })
  })

  describe('createBank()', () => {
    it('calls POST /api/v1/banks with the payload and returns the created bank', async () => {
      mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: BANK_FIXTURE })
      const { createBank } = useBanks()
      const payload = {
        organization_id: 1,
        name_ar: 'البنك التجاري اليمني',
        name_en: 'Yemen Commercial Bank',
        code: 'YCB',
        is_active: true,
      }
      const result = await createBank(payload)
      expect(mockPost).toHaveBeenCalledWith('/api/v1/banks', payload)
      expect(result).toEqual(BANK_FIXTURE)
    })
  })

  describe('updateBank()', () => {
    it('calls PUT /api/v1/banks/:id with the payload and returns the updated bank', async () => {
      const updated = { ...BANK_FIXTURE, name_ar: 'اسم محدث' }
      mockPut.mockResolvedValueOnce({ success: true, message: 'Updated', data: updated })
      const { updateBank } = useBanks()
      const payload = {
        name_ar: 'اسم محدث',
        name_en: 'Yemen Commercial Bank',
        code: 'YCB',
        is_active: true,
      }
      const result = await updateBank(1, payload)
      expect(mockPut).toHaveBeenCalledWith('/api/v1/banks/1', payload)
      expect(result).toEqual(updated)
    })
  })
})
