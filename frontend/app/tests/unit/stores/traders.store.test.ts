import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const mockList = vi.fn()
const mockCreate = vi.fn()
const mockUpdate = vi.fn()
const mockGetById = vi.fn()
const mockLookupByTaxNumber = vi.fn()

vi.mock('../../../composables/useTraders', () => ({
  useTraders: () => ({
    list: mockList,
    create: mockCreate,
    update: mockUpdate,
    getById: mockGetById,
    lookupByTaxNumber: mockLookupByTaxNumber,
  }),
}))

const { useTradersStore } = await import('../../../stores/traders')

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

const PAGE = { data: [TRADER], meta: { current_page: 1, last_page: 2, per_page: 20, total: 21 } }

describe('useTradersStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockList.mockReset()
    mockCreate.mockReset()
    mockUpdate.mockReset()
    mockGetById.mockReset()
    mockLookupByTaxNumber.mockReset()
  })

  it('starts empty', () => {
    const store = useTradersStore()
    expect(store.traders).toEqual([])
    expect(store.currentTrader).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('loads traders and pagination meta', async () => {
    mockList.mockResolvedValueOnce(PAGE)

    const store = useTradersStore()
    await store.loadTraders({ trader_name: 'شركة' })

    expect(mockList).toHaveBeenCalledWith({ trader_name: 'شركة' })
    expect(store.traders).toEqual([TRADER])
    expect(store.meta?.total).toBe(21)
  })

  it('loads a single current trader', async () => {
    mockGetById.mockResolvedValueOnce(TRADER)

    const store = useTradersStore()
    await store.loadTrader(1)

    expect(mockGetById).toHaveBeenCalledWith(1)
    expect(store.currentTrader?.id).toBe(1)
  })

  it('creates and stores the current trader', async () => {
    mockCreate.mockResolvedValueOnce(TRADER)

    const store = useTradersStore()
    const id = await store.createTrader({
      tax_number: 'TX-100',
      trader_name: 'شركة الاختبار',
      tax_card_expiry: '2027-01-01',
      commercial_registration_number: 'CR-100',
      commercial_registration_expiry: '2027-01-01',
      companies: [],
      owners: [],
    })

    expect(id).toBe(1)
    expect(store.currentTrader).toEqual(TRADER)
  })

  it('updates the current trader', async () => {
    mockUpdate.mockResolvedValueOnce(TRADER)

    const store = useTradersStore()
    await store.updateTrader(1, { trader_name: 'اسم محدث' })

    expect(mockUpdate).toHaveBeenCalledWith(1, { trader_name: 'اسم محدث' })
    expect(store.currentTrader).toEqual(TRADER)
  })

  it('maps failures to Arabic store errors', async () => {
    mockList.mockRejectedValueOnce(new Error('fail'))

    const store = useTradersStore()
    await store.loadTraders()

    expect(store.traders).toEqual([])
    expect(store.error).toBe('تعذّر تحميل قائمة التجار.')
  })
})
