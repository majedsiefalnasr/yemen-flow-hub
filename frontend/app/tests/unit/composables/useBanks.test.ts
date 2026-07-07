import { vi, describe, it, expect, beforeEach } from 'vitest'

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
