import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDel = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut, del: mockDel }),
}))

const { useMerchants } = await import('../../../composables/useMerchants')

const MERCHANT_FIXTURE = {
  id: 1,
  bank_id: 1,
  bank_name: 'بنك اليمن',
  name: 'شركة الأمل للتجارة',
  tax_number: '4123456',
  tax_card_expiry: '2027-12-31',
  phone: '+967700123456',
  address: 'صنعاء',
  status: 'ACTIVE',
  version: 1,
  transaction_count: 3,
  owners: [{ id: 1, name: 'علي أحمد', ownership_percentage: 60 }],
  companies: [
    {
      id: 1,
      name: 'الأمل التجارية',
      commercial_registration_number: 'CR-100',
      commercial_registration_expiry: '2028-06-30',
      sector_reference_value_id: null,
      is_active: true,
    },
  ],
  created_by: 1,
  created_at: '2026-05-01T00:00:00.000Z',
  updated_at: '2026-05-01T00:00:00.000Z',
}

const PAGINATED_RESPONSE = {
  success: true,
  message: 'OK',
  data: [MERCHANT_FIXTURE, { ...MERCHANT_FIXTURE, id: 2, name: 'مؤسسة النور' }],
}

describe('useMerchants — fetchMerchants', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls GET /api/v1/merchants?per_page=200 by default', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/merchants?per_page=200')
  })

  it('appends search filter', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants({ search: 'أمل' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('search=%D8%A3%D9%85%D9%84'))
  })

  it('appends bank_id filter', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants({ bank_id: 3 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('bank_id=3'))
  })

  it('appends status filter', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    await fetchMerchants({ status: 'SUSPENDED' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('status=SUSPENDED'))
  })

  it('returns merchant array from flat-array response', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchMerchants } = useMerchants()
    const result = await fetchMerchants()
    expect(result).toHaveLength(2)
    expect(result[0]?.name).toBe('شركة الأمل للتجارة')
  })

  it('unwraps wrapped { data, meta } response', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: {
        data: [MERCHANT_FIXTURE],
        meta: { current_page: 1, last_page: 1, per_page: 200, total: 1 },
      },
    })
    const { fetchMerchants } = useMerchants()
    const result = await fetchMerchants()
    expect(result).toHaveLength(1)
  })

  it('returns empty array when data is empty', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: [] })
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

describe('useMerchants — fetchMerchant', () => {
  beforeEach(() => vi.clearAllMocks())

  it('calls GET /api/v1/merchants/{id}', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: MERCHANT_FIXTURE })
    const { fetchMerchant } = useMerchants()
    const result = await fetchMerchant(1)
    expect(mockGet).toHaveBeenCalledWith('/api/v1/merchants/1')
    expect(result.id).toBe(1)
  })
})

describe('useMerchants — createMerchant', () => {
  beforeEach(() => vi.clearAllMocks())

  it('posts to /api/v1/merchants with nested owners and companies', async () => {
    mockPost.mockResolvedValueOnce({ success: true, data: MERCHANT_FIXTURE })
    const { createMerchant } = useMerchants()
    const payload = {
      name: 'شركة الأمل للتجارة',
      tax_number: '4123456',
      bank_id: 1,
      owners: [{ name: 'علي', ownership_percentage: 100 }],
      companies: [{ name: 'الأمل', commercial_registration_number: 'CR-100' }],
    }
    const result = await createMerchant(payload)
    expect(mockPost).toHaveBeenCalledWith('/api/v1/merchants', payload)
    expect(result.id).toBe(1)
  })

  it('propagates validation error', async () => {
    mockPost.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { name: ['أدخل اسم المستورد.'] } } },
    })
    const { createMerchant } = useMerchants()
    await expect(createMerchant({ name: '', tax_number: '' })).rejects.toBeTruthy()
  })
})

describe('useMerchants — updateMerchant', () => {
  beforeEach(() => vi.clearAllMocks())

  it('puts to /api/v1/merchants/{id} with version', async () => {
    const updated = { ...MERCHANT_FIXTURE, name: 'شركة النجاح', version: 2 }
    mockPut.mockResolvedValueOnce({ success: true, data: updated })
    const { updateMerchant } = useMerchants()
    const result = await updateMerchant(1, { version: 1, name: 'شركة النجاح' })
    expect(mockPut).toHaveBeenCalledWith(
      '/api/v1/merchants/1',
      expect.objectContaining({ version: 1, name: 'شركة النجاح' }),
    )
    expect(result.name).toBe('شركة النجاح')
    expect(result.version).toBe(2)
  })

  it('propagates stale version error', async () => {
    mockPut.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { error: { code: 'STALE_RESOURCE', message: 'Resource modified' } },
      },
    })
    const { updateMerchant } = useMerchants()
    await expect(updateMerchant(1, { version: 1, name: 'x' })).rejects.toBeTruthy()
  })
})

describe('useMerchants — deleteMerchant', () => {
  beforeEach(() => vi.clearAllMocks())

  it('calls DELETE /api/v1/merchants/{id}', async () => {
    mockDel.mockResolvedValueOnce(undefined)
    const { deleteMerchant } = useMerchants()
    await deleteMerchant(5)
    expect(mockDel).toHaveBeenCalledWith('/api/v1/merchants/5')
  })
})

describe('useMerchants — extractBusinessError', () => {
  it('extracts business error from response', () => {
    const { extractBusinessError } = useMerchants()
    const error = {
      response: {
        status: 409,
        data: {
          error: {
            code: 'MERCHANT_TAX_NUMBER_EXISTS',
            message: 'الرقم الضريبي مسجل مسبقاً',
          },
        },
      },
    }
    const result = extractBusinessError(error)
    expect(result?.code).toBe('MERCHANT_TAX_NUMBER_EXISTS')
    expect(result?.message).toBe('الرقم الضريبي مسجل مسبقاً')
  })

  it('returns null for non-business errors', () => {
    const { extractBusinessError } = useMerchants()
    expect(extractBusinessError(new Error('network'))).toBeNull()
    expect(extractBusinessError(null)).toBeNull()
    expect(extractBusinessError(undefined)).toBeNull()
  })
})
