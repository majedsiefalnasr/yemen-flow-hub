import { defineStore } from 'pinia'

// FE-003: shared cache for stable, slow-changing reference data fetched for
// dropdowns/selectors (e.g. the full banks list) across multiple pages in
// the same session. A modest TTL avoids serving genuinely stale data
// indefinitely; explicit invalidation on admin create/update covers the
// case where a caller mutates the underlying data and needs the next read
// to reflect it immediately, without waiting for the TTL to lapse.
const DEFAULT_TTL_MS = 5 * 60 * 1000

interface CacheEntry<T> {
  value: T
  fetchedAt: number
}

export const useReferenceCacheStore = defineStore('referenceCache', {
  state: () => ({
    entries: {} as Record<string, CacheEntry<unknown>>,
  }),

  actions: {
    async remember<T>(key: string, fetcher: () => Promise<T>, ttlMs = DEFAULT_TTL_MS): Promise<T> {
      const cached = this.entries[key] as CacheEntry<T> | undefined
      if (cached && Date.now() - cached.fetchedAt < ttlMs) {
        return cached.value
      }

      const value = await fetcher()
      this.entries[key] = { value, fetchedAt: Date.now() }
      return value
    },

    invalidate(key: string): void {
      const { [key]: _removed, ...rest } = this.entries
      this.entries = rest
    },

    clear(): void {
      this.entries = {}
    },
  },
})
