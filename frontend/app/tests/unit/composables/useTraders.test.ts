import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useTraders } = await import('../../../composables/useTraders')

const TRADER = {
  id: 1,
  tax_number: 'TX-100',
  trader_name: 'شركة الاختبار',
  tax_card_expiry: '2027-01-01',
  commercial_registration_number: 'CR-100',
  commercial_registration_expiry: '2027-01-01',
  companies_count: 0,
  owners_count: 0,
  companies: [],
  owners: [],
  created_at: '2026-06-08T00:00:00.000000Z',
  updated_at: '2026-06-08T00:00:00.000000Z',
}

const PAGE = { data: [TRADER], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } }

describe('useTraders', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockPut.mockReset()
  })

  it('lists traders with tax number and name filters', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: PAGE })

    const { list } = useTraders()
    const result = await list({ tax_number: 'TX', trader_name: 'شركة', page: 2, per_page: 10 })

    expect(mockGet).toHaveBeenCalledWith(
      '/api/traders?tax_number=TX&trader_name=%D8%B4%D8%B1%D9%83%D8%A9&page=2&per_page=10',
    )
    expect(result.data).toEqual([TRADER])
  })

  it('creates a trader through the API wrapper', async () => {
    mockPost.mockResolvedValueOnce({ success: true, message: 'Created', data: TRADER })

    const { create } = useTraders()
    const result = await create({
      tax_number: 'TX-100',
      trader_name: 'شركة الاختبار',
      tax_card_expiry: '2027-01-01',
      commercial_registration_number: 'CR-100',
      commercial_registration_expiry: '2027-01-01',
      companies: [],
      owners: [],
    })

    expect(mockPost).toHaveBeenCalledWith('/api/traders', expect.any(Object))
    expect(result.id).toBe(1)
  })

  it('updates a trader through PUT', async () => {
    mockPut.mockResolvedValueOnce({ success: true, message: 'Updated', data: TRADER })

    const { update } = useTraders()
    await update(1, { trader_name: 'اسم محدث' })

    expect(mockPut).toHaveBeenCalledWith('/api/traders/1', { trader_name: 'اسم محدث' })
  })

  it('loads a trader by id', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: TRADER })

    const { getById } = useTraders()
    const result = await getById(1)

    expect(mockGet).toHaveBeenCalledWith('/api/traders/1')
    expect(result.tax_number).toBe('TX-100')
  })

  it('looks up a trader by tax number', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: TRADER })

    const { lookupByTaxNumber } = useTraders()
    const result = await lookupByTaxNumber('TX-100')

    expect(mockGet).toHaveBeenCalledWith('/api/traders/lookup?tax_number=TX-100')
    expect(result?.id).toBe(1)
  })

  it('propagates API errors for caller-level mapping', async () => {
    mockGet.mockRejectedValueOnce(new Error('validation'))

    const { list } = useTraders()
    await expect(list()).rejects.toThrow('validation')
  })
})
