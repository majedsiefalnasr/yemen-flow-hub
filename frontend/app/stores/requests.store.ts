import { defineStore } from 'pinia'
import type { ImportRequest } from '../types/models'
import type { RequestsFilter } from '../composables/useRequests'
import { useRequests } from '../composables/useRequests'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useRequestsStore = defineStore('requests', {
  state: () => ({
    requests: [] as ImportRequest[],
    loading: false,
    error: null as string | null,
    meta: null as PaginationMeta | null,
    currentFilter: {} as RequestsFilter,
    /** Sequence counter — only the latest in-flight request commits its results */
    _loadToken: 0,
  }),

  getters: {
    hasNextPage: (state): boolean =>
      state.meta !== null && state.meta.current_page < state.meta.last_page,

    hasPrevPage: (state): boolean =>
      state.meta !== null && state.meta.current_page > 1,

    currentPage: (state): number => state.meta?.current_page ?? 1,

    totalCount: (state): number => state.meta?.total ?? 0,
  },

  actions: {
    async loadRequests(filter: RequestsFilter = {}): Promise<void> {
      const token = ++this._loadToken
      this.loading = true
      this.error = null
      this.currentFilter = filter

      try {
        const { fetchRequests } = useRequests()
        const result = await fetchRequests(filter)

        if (token !== this._loadToken) return
        this.requests = result.data
        this.meta = result.meta
      }
      catch (err) {
        if (token !== this._loadToken) return
        if (import.meta.dev) {
          console.error('[requests.store] loadRequests failed:', err)
        }
        this.error = 'تعذّر تحميل قائمة الطلبات.'
        this.requests = []
        this.meta = null
      }
      finally {
        if (token === this._loadToken) {
          this.loading = false
        }
      }
    },

    async nextPage(): Promise<void> {
      if (this.loading || !this.hasNextPage || !this.meta) return
      await this.loadRequests({ ...this.currentFilter, page: this.meta.current_page + 1 })
    },

    async prevPage(): Promise<void> {
      if (this.loading || !this.hasPrevPage || !this.meta) return
      await this.loadRequests({ ...this.currentFilter, page: this.meta.current_page - 1 })
    },
  },
})
