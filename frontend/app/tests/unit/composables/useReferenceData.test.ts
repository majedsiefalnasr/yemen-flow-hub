import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: mockPut,
    del: mockDelete,
  }),
}))

const { useReferenceData } = await import('../../../composables/useReferenceData')

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }
const TABLE = {
  id: 1,
  key: 'sector_activity',
  label: 'النشاط القطاعي',
  sort_order: 0,
  is_system: true,
  is_active: true,
  is_in_use: true,
  created_at: null,
  updated_at: null,
  version: 3,
}
const VALUE = {
  id: 2,
  reference_table_id: 1,
  key: 'retail',
  label: 'تجزئة',
  sort_order: 0,
  is_system: false,
  is_active: true,
  is_in_use: false,
  created_at: null,
  updated_at: null,
  version: 4,
}

describe('useReferenceData', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('requests the required pagination and sorting contract', async () => {
    mockGet.mockResolvedValueOnce({ data: [TABLE], meta: META })
    const { fetchReferenceTables, referenceTablesMeta } = useReferenceData()

    await fetchReferenceTables({ page: 2, sort: 'key', direction: 'desc' })

    expect(mockGet).toHaveBeenCalledWith('/api/v1/reference-tables', {
      query: {
        page: 2,
        per_page: 25,
        search: '',
        sort: 'key',
        direction: 'desc',
      },
    })
    expect(referenceTablesMeta.value).toEqual(META)
  })

  it('ignores a stale values response after a newer table selection resolves', async () => {
    let resolveFirst!: (value: unknown) => void
    let resolveSecond!: (value: unknown) => void
    mockGet
      .mockReturnValueOnce(new Promise((resolve) => (resolveFirst = resolve)))
      .mockReturnValueOnce(new Promise((resolve) => (resolveSecond = resolve)))
    const { fetchReferenceValues, referenceValues } = useReferenceData()

    const first = fetchReferenceValues(1)
    const second = fetchReferenceValues(2)
    resolveSecond({ data: [{ ...VALUE, id: 22, reference_table_id: 2 }], meta: META })
    await second
    resolveFirst({ data: [VALUE], meta: META })
    await first

    expect(referenceValues.value).toEqual([
      expect.objectContaining({ id: 22, reference_table_id: 2 }),
    ])
  })

  it('sends the current version with activation mutations', async () => {
    mockPost
      .mockResolvedValueOnce({ data: { ...TABLE, is_active: false, version: 4 } })
      .mockResolvedValueOnce({ data: { ...VALUE, is_active: false, version: 5 } })
    const { setReferenceTableActive, setReferenceValueActive } = useReferenceData()

    await setReferenceTableActive(TABLE, false)
    await setReferenceValueActive(VALUE, false)

    expect(mockPost).toHaveBeenNthCalledWith(1, '/api/v1/reference-tables/1/deactivate', {
      version: 3,
    })
    expect(mockPost).toHaveBeenNthCalledWith(2, '/api/v1/reference-values/2/deactivate', {
      version: 4,
    })
  })
})
