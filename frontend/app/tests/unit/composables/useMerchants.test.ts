/**
 * Tests for useMerchants composable — F4 patch: merchant fetch error handling
 */
import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useMerchants } = await import('../../../composables/useMerchants')

const MERCHANTS_RESPONSE = {
  success: true,
  message: 'OK',
  data: {
    data: [
      { id: 1, name: 'شركة الأمل للتجارة', commercial_register: '12345', address: 'صنعاء', bank_id: 1 },
      { id: 2, name: 'مؤسسة النور', commercial_register: '67890', address: 'عدن', bank_id: 1 },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 },
  },
}

describe('useMerchants — fetchMerchants', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/merchants?per_page=100', async () => {
    mockGet.mockResolvedValueOnce(MERCHANTS_RESPONSE)

    const { fetchMerchants } = useMerchants()
    await fetchMerchants()

    expect(mockGet).toHaveBeenCalledWith('/api/merchants?per_page=100')
  })

  it('returns the merchant array from paginated response', async () => {
    mockGet.mockResolvedValueOnce(MERCHANTS_RESPONSE)

    const { fetchMerchants } = useMerchants()
    const result = await fetchMerchants()

    expect(result).toHaveLength(2)
    expect(result[0].id).toBe(1)
    expect(result[0].name).toBe('شركة الأمل للتجارة')
  })

  it('propagates API error (caller shows retry UI — F4)', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network error'))

    const { fetchMerchants } = useMerchants()
    await expect(fetchMerchants()).rejects.toThrow('Network error')
  })

  it('propagates 403 error (org-scope denied)', async () => {
    mockGet.mockRejectedValueOnce(new Error('Forbidden'))

    const { fetchMerchants } = useMerchants()
    await expect(fetchMerchants()).rejects.toThrow('Forbidden')
  })

  it('returns empty array when response data is empty', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 } },
    })

    const { fetchMerchants } = useMerchants()
    const result = await fetchMerchants()

    expect(result).toEqual([])
  })
})
