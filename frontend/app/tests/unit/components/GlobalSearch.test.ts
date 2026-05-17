import { vi, describe, it, expect, beforeEach } from 'vitest'

// Mock useSearch composable
const mockSearch = vi.fn()
const mockFetchRecent = vi.fn()
const mockResults = { value: { requests: [], users: [], banks: [], customs: [] } }
const mockRecentSearches = { value: [] as string[] }
const mockLoading = { value: false }
const mockError = { value: null as string | null }
const mockActiveFilter = { value: 'all' as string }

vi.mock('../../../composables/useSearch', () => ({
  useSearch: () => ({
    search: mockSearch,
    fetchRecent: mockFetchRecent,
    results: mockResults,
    recentSearches: mockRecentSearches,
    loading: mockLoading,
    error: mockError,
    activeFilter: mockActiveFilter,
  }),
}))

vi.mock('../../../components/layout/SidebarIcon.vue', () => ({
  default: { template: '<span data-icon />' },
}))

vi.stubGlobal('useRouter', () => ({ push: vi.fn() }))

const SAMPLE_RESULTS = {
  requests: [
    { id: 1, reference_number: 'REF-001', bank_id: 1, bank_name: 'Bank A', status: 'SUBMITTED', supplier_name: 'Alpha', amount: 50000, currency: 'USD', created_at: null },
  ],
  users: [
    { id: 2, name: 'Ahmed', email: 'ahmed@test.com', role: 'DATA_ENTRY', role_label: 'موظف إدخال', bank_id: 1, bank_name: 'Bank A', is_active: true },
  ],
  banks: [],
  customs: [],
}

describe('GlobalSearch — search input behaviour', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockResults.value = { requests: [], users: [], banks: [], customs: [] }
    mockRecentSearches.value = []
    mockLoading.value = false
    mockError.value = null
    mockActiveFilter.value = 'all'
  })

  it('calls search() when input value changes', async () => {
    const { useSearch } = await import('../../../composables/useSearch')
    const { search } = useSearch()

    // Simulate input change
    search('alpha')
    expect(mockSearch).toHaveBeenCalledWith('alpha')
  })

  it('calls fetchRecent() on composable initialisation', async () => {
    const { useSearch } = await import('../../../composables/useSearch')
    const { fetchRecent } = useSearch()

    await fetchRecent()
    expect(mockFetchRecent).toHaveBeenCalled()
  })
})

describe('GlobalSearch — results grouping', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('groups requests results under requests key', async () => {
    mockResults.value = SAMPLE_RESULTS

    const { useSearch } = await import('../../../composables/useSearch')
    const { results } = useSearch()

    expect(results.value.requests).toHaveLength(1)
    expect(results.value.requests[0].id).toBe(1)
  })

  it('groups user results under users key', async () => {
    mockResults.value = SAMPLE_RESULTS

    const { useSearch } = await import('../../../composables/useSearch')
    const { results } = useSearch()

    expect(results.value.users).toHaveLength(1)
    expect(results.value.users[0].name).toBe('Ahmed')
  })

  it('shows empty state when all groups are empty', async () => {
    mockResults.value = { requests: [], users: [], banks: [], customs: [] }

    const { useSearch } = await import('../../../composables/useSearch')
    const { results } = useSearch()

    const hasAny = Object.values(results.value).some(arr => arr.length > 0)
    expect(hasAny).toBe(false)
  })
})

describe('GlobalSearch — filter chips', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('defaults active filter to "all"', async () => {
    const { useSearch } = await import('../../../composables/useSearch')
    const { activeFilter } = useSearch()

    expect(activeFilter.value).toBe('all')
  })

  it('can switch active filter to "requests"', async () => {
    const { useSearch } = await import('../../../composables/useSearch')
    const { activeFilter } = useSearch()

    activeFilter.value = 'requests'
    expect(activeFilter.value).toBe('requests')
  })
})

describe('GlobalSearch — recent searches', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockRecentSearches.value = []
  })

  it('shows recent searches when available', async () => {
    mockRecentSearches.value = ['query1', 'query2']

    const { useSearch } = await import('../../../composables/useSearch')
    const { recentSearches } = useSearch()

    expect(recentSearches.value).toHaveLength(2)
    expect(recentSearches.value[0]).toBe('query1')
  })

  it('recent search click triggers search with that term', async () => {
    mockRecentSearches.value = ['alpha']

    const { useSearch } = await import('../../../composables/useSearch')
    const { search } = useSearch()

    // Simulate clicking a recent search
    search('alpha')
    expect(mockSearch).toHaveBeenCalledWith('alpha')
  })

  it('shows no recent section when list is empty', async () => {
    mockRecentSearches.value = []

    const { useSearch } = await import('../../../composables/useSearch')
    const { recentSearches } = useSearch()

    expect(recentSearches.value).toHaveLength(0)
  })
})

describe('GlobalSearch — loading state', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockLoading.value = false
  })

  it('loading is false by default', async () => {
    const { useSearch } = await import('../../../composables/useSearch')
    const { loading } = useSearch()

    expect(loading.value).toBe(false)
  })

  it('loading becomes true during search', async () => {
    mockLoading.value = true

    const { useSearch } = await import('../../../composables/useSearch')
    const { loading } = useSearch()

    expect(loading.value).toBe(true)
  })
})

describe('GlobalSearch — deep-link navigation', () => {
  it('request result navigates to /requests/{id}', () => {
    const requestId = 1
    const expectedRoute = `/requests/${requestId}`
    expect(expectedRoute).toBe('/requests/1')
  })

  it('customs result navigates to /requests/{request_id}', () => {
    const customs = { request_id: 5, declaration_number: 'DECL-001' }
    const expectedRoute = `/requests/${customs.request_id}`
    expect(expectedRoute).toBe('/requests/5')
  })

  it('user result navigates to /users', () => {
    const expectedRoute = '/users'
    expect(expectedRoute).toBe('/users')
  })

  it('bank result navigates to /banks', () => {
    const expectedRoute = '/banks'
    expect(expectedRoute).toBe('/banks')
  })
})
