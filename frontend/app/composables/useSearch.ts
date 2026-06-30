import { ref } from 'vue'
import type { SearchResults, SearchEntityType } from '../types/models'

const DEBOUNCE_DELAY = 350
const MIN_QUERY_LENGTH = 2

const EMPTY_RESULTS: SearchResults = { requests: [], users: [], banks: [], customs: [] }

export const useSearch = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  const results = ref<SearchResults>({ ...EMPTY_RESULTS })
  const recentSearches = ref<string[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const activeFilter = ref<SearchEntityType | 'all'>('all')

  let debounceTimer: ReturnType<typeof setTimeout> | null = null
  let requestSequence = 0

  const search = (query: string): void => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer)
    }

    if (query.length < MIN_QUERY_LENGTH) {
      requestSequence++
      loading.value = false
      results.value = { ...EMPTY_RESULTS }
      return
    }

    const requestId = ++requestSequence

    debounceTimer = setTimeout(() => {
      void _doSearch(query, requestId)
    }, DEBOUNCE_DELAY)
  }

  const _doSearch = async (query: string, requestId: number): Promise<void> => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<{ success: boolean; data: SearchResults }>('/api/search', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
        method: 'GET',
        query: { q: query },
      })

      if (requestId === requestSequence) {
        results.value = response.data
      }
    } catch (err: any) {
      if (requestId === requestSequence) {
        error.value = err.data?.message || 'Failed to perform search'
        results.value = { ...EMPTY_RESULTS }
      }
    } finally {
      if (requestId === requestSequence) {
        loading.value = false
      }
    }
  }

  const fetchRecent = async (): Promise<void> => {
    try {
      const response = await $fetch<{ success: boolean; data: { recent_searches: string[] } }>(
        '/api/search/recent',
        {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
          method: 'GET',
        },
      )

      recentSearches.value = response.data.recent_searches
    } catch {
      // silently ignore — non-critical
    }
  }

  return {
    results,
    recentSearches,
    loading,
    error,
    activeFilter,
    search,
    fetchRecent,
  }
}
