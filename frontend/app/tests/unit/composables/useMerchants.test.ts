import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useMerchants } = await import('../../../composables/useMerchants')

const MERCHANT_FIXTURE = {
  id: 1,
  bank_id: 1,
  bank_name: 'بنك اليمن',
  name: 'شركة الأمل للتجارة',
  commercial_register: '12345',
  tax_number: null,
  national_id: null,
  owner_name: 'علي أحمد',
  phone: '+967700123456',
  email: null,
  address: 'صنعاء',
  is_active: true,
  created_by: 1,
  created_at: '2026-05-01T00:00:00.000Z',
}

const PAGINATED_RESPONSE = {
  success: true,
  message: 'OK',
  data: {
    data: [MERCHANT_FIXTURE, { ...MERCHANT_FIXTURE, id: 2, name: 'مؤسسة النور' }],
    meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 },
  },
}

describe('useMerchants — fetchMerchants', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockPut.mockReset()
  })

  it('calls GET /api/merchants?per_page=100 by default', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants()
    expect(mockGet).toHaveBeenCalledWith('/api/merchants?per_page=100')
  })

  it('appends search filter to query string', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants({ search: 'أمل' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('search=%D8%A3%D9%85%D9%84'))
  })

  it('appends bank_id filter to query string', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants({ bank_id: 3 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('bank_id=3'))
  })

  it('returns the merchant array from paginated response', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    const result = await fetchMerchants()
    expect(result).toHaveLength(2)
    expect(result[0]?.name).toBe('شركة الأمل للتجارة')
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

  it('propagates API error', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network error'))
    const { fetchMerchants } = useMerchants()
    await expect(fetchMerchants()).rejects.toThrow('Network error')
  })
})

describe('useMerchants — createMerchant', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('posts to /api/merchants and returns created merchant', async () => {
    mockPost.mockResolvedValueOnce({ success: true, data: MERCHANT_FIXTURE })
    const { createMerchant } = useMerchants()
    const result = await createMerchant({ name: 'شركة الأمل للتجارة', bank_id: 1 })
    expect(mockPost).toHaveBeenCalledWith('/api/merchants', expect.objectContaining({ name: 'شركة الأمل للتجارة', bank_id: 1 }))
    expect(result.id).toBe(1)
  })

  it('propagates validation error from API', async () => {
    mockPost.mockRejectedValueOnce({ data: { errors: { name: ['اسم التاجر مطلوب'] } } })
    const { createMerchant } = useMerchants()
    await expect(createMerchant({ name: '' })).rejects.toBeTruthy()
  })
})

describe('useMerchants — updateMerchant', () => {
  beforeEach(() => {
    mockPut.mockReset()
  })

  it('puts to /api/merchants/{id} and returns updated merchant', async () => {
    const updated = { ...MERCHANT_FIXTURE, name: 'شركة النجاح' }
    mockPut.mockResolvedValueOnce({ success: true, data: updated })
    const { updateMerchant } = useMerchants()
    const result = await updateMerchant(1, { name: 'شركة النجاح' })
    expect(mockPut).toHaveBeenCalledWith('/api/merchants/1', expect.objectContaining({ name: 'شركة النجاح' }))
    expect(result.name).toBe('شركة النجاح')
  })

  it('propagates API error on update', async () => {
    mockPut.mockRejectedValueOnce(new Error('Not found'))
    const { updateMerchant } = useMerchants()
    await expect(updateMerchant(999, { name: 'x' })).rejects.toThrow('Not found')
  })
})

describe('useMerchants — suspendMerchant', () => {
  beforeEach(() => {
    mockPut.mockReset()
  })

  it('puts is_active: false to suspend merchant', async () => {
    const suspended = { ...MERCHANT_FIXTURE, is_active: false }
    mockPut.mockResolvedValueOnce({ success: true, data: suspended })
    const { suspendMerchant } = useMerchants()
    const result = await suspendMerchant(1, false)
    expect(mockPut).toHaveBeenCalledWith('/api/merchants/1', { is_active: false })
    expect(result.is_active).toBe(false)
  })

  it('puts is_active: true to reactivate merchant', async () => {
    const active = { ...MERCHANT_FIXTURE, is_active: true }
    mockPut.mockResolvedValueOnce({ success: true, data: active })
    const { suspendMerchant } = useMerchants()
    const result = await suspendMerchant(1, true)
    expect(mockPut).toHaveBeenCalledWith('/api/merchants/1', { is_active: true })
    expect(result.is_active).toBe(true)
  })

  it('propagates API error on suspend', async () => {
    mockPut.mockRejectedValueOnce(new Error('Forbidden'))
    const { suspendMerchant } = useMerchants()
    await expect(suspendMerchant(1, false)).rejects.toThrow('Forbidden')
  })
})
