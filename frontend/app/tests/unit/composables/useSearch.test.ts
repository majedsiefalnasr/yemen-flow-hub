import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

const { useSearch } = await import('../../../composables/useSearch')

const EMPTY_RESULTS = { requests: [], users: [], banks: [], customs: [] }

const SAMPLE_RESULTS = {
  requests: [
    { id: 1, reference_number: 'REF-001', bank_id: 1, bank_name: 'Bank A', status: 'SUBMITTED', supplier_name: 'Alpha Supplier', amount: 50000, currency: 'USD', created_at: '2026-05-17T10:00:00.000Z' },
  ],
  users: [],
  banks: [],
  customs: [],
}

describe('useSearch — search()', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does not call API for query shorter than 2 chars', async () => {
    const { search } = useSearch()
    search('a')

    vi.advanceTimersByTime(500)
    await Promise.resolve()

    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('does not call API for empty query', async () => {
    const { search } = useSearch()
    search('')

    vi.advanceTimersByTime(500)
    await Promise.resolve()

    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('calls API with 2+ char query after 350ms debounce', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: EMPTY_RESULTS })

    const { search } = useSearch()
    search('al')

    // Before debounce fires — no call
    vi.advanceTimersByTime(300)
    expect(mockFetch).not.toHaveBeenCalled()

    // After debounce fires
    vi.advanceTimersByTime(100)
    await Promise.resolve()

    expect(mockFetch).toHaveBeenCalledWith('/api/search', expect.objectContaining({
      query: { q: 'al' },
    }))
  })

  it('sets results on successful API response', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: SAMPLE_RESULTS })

    const { results, search } = useSearch()
    search('alpha')

    vi.advanceTimersByTime(400)
    await Promise.resolve()
    await Promise.resolve()

    expect(results.value.requests).toHaveLength(1)
    expect(results.value.requests[0]!.reference_number).toBe('REF-001')
  })

  it('sets loading true while in-flight and false after', async () => {
    let resolvePromise: (v: any) => void
    const pending = new Promise(res => { resolvePromise = res })
    mockFetch.mockReturnValueOnce(pending)

    const { loading, search } = useSearch()
    search('al')

    vi.advanceTimersByTime(400)
    await Promise.resolve()

    expect(loading.value).toBe(true)

    resolvePromise!({ success: true, data: EMPTY_RESULTS })
    await Promise.resolve()
    await Promise.resolve()

    expect(loading.value).toBe(false)
  })

  it('sets error on API failure', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Server error' } })

    const { error, search } = useSearch()
    search('al')

    vi.advanceTimersByTime(400)
    await Promise.resolve()
    await Promise.resolve()

    expect(error.value).toBe('Server error')
  })

  it('cancels previous debounce when new query arrives', async () => {
    mockFetch.mockResolvedValue({ success: true, data: EMPTY_RESULTS })

    const { search } = useSearch()
    search('al')
    vi.advanceTimersByTime(200)
    search('alp')
    vi.advanceTimersByTime(400)
    await Promise.resolve()

    // Only one call (the debounced 'alp' one)
    expect(mockFetch).toHaveBeenCalledTimes(1)
    expect(mockFetch).toHaveBeenCalledWith('/api/search', expect.objectContaining({
      query: { q: 'alp' },
    }))
  })

  it('ignores stale responses from older in-flight requests', async () => {
    let resolveFirst: ((value: any) => void) | undefined
    let resolveSecond: ((value: any) => void) | undefined

    mockFetch
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve }))
      .mockImplementationOnce(() => new Promise((resolve) => { resolveSecond = resolve }))

    const oldResults = { requests: [{ id: 1, reference_number: 'OLD', bank_id: 1, bank_name: 'Bank A', status: 'SUBMITTED', supplier_name: 'Old', amount: 1, currency: 'USD', created_at: null }], users: [], banks: [], customs: [] }
    const newResults = { requests: [{ id: 2, reference_number: 'NEW', bank_id: 1, bank_name: 'Bank A', status: 'SUBMITTED', supplier_name: 'New', amount: 2, currency: 'USD', created_at: null }], users: [], banks: [], customs: [] }

    const { search, results } = useSearch()

    search('old')
    vi.advanceTimersByTime(400)
    await Promise.resolve()

    search('new')
    vi.advanceTimersByTime(400)
    await Promise.resolve()

    resolveSecond?.({ success: true, data: newResults })
    await Promise.resolve()
    await Promise.resolve()
    expect(results.value.requests[0]!.reference_number).toBe('NEW')

    resolveFirst?.({ success: true, data: oldResults })
    await Promise.resolve()
    await Promise.resolve()
    expect(results.value.requests[0]!.reference_number).toBe('NEW')
  })
})

describe('useSearch — fetchRecent()', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('populates recentSearches from API', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { recent_searches: ['query1', 'query2'] } })

    const { recentSearches, fetchRecent } = useSearch()
    await fetchRecent()

    expect(recentSearches.value).toEqual(['query1', 'query2'])
  })

  it('calls correct endpoint', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, data: { recent_searches: [] } })

    const { fetchRecent } = useSearch()
    await fetchRecent()

    expect(mockFetch).toHaveBeenCalledWith('/api/search/recent', expect.objectContaining({
      method: 'GET',
    }))
  })

  it('silently ignores fetch errors', async () => {
    mockFetch.mockRejectedValueOnce(new Error('Network error'))

    const { recentSearches, fetchRecent } = useSearch()
    await fetchRecent()

    expect(recentSearches.value).toEqual([])
  })
})

describe('useSearch — activeFilter chip', () => {
  it('defaults to "all"', () => {
    const { activeFilter } = useSearch()
    expect(activeFilter.value).toBe('all')
  })

  it('can be set to an entity type', () => {
    const { activeFilter } = useSearch()
    activeFilter.value = 'requests'
    expect(activeFilter.value).toBe('requests')
  })
})
