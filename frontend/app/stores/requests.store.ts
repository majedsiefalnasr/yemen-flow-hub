import { defineStore } from 'pinia'
import type { ImportRequest, RequestFormData } from '../types/models'
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
    currentRequest: null as ImportRequest | null,
    loadingList: false,
    loadingRequest: false,
    saving: false,
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
      this.loadingList = true
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
          this.loadingList = false
        }
      }
    },

    async loadRequest(id: number): Promise<void> {
      this.loadingRequest = true
      this.error = null
      this.currentRequest = null

      try {
        const { fetchRequest } = useRequests()
        this.currentRequest = await fetchRequest(id)
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] loadRequest failed:', err)
        }
        this.error = 'تعذّر تحميل بيانات الطلب.'
      }
      finally {
        this.loadingRequest = false
      }
    },

    async createRequest(data: RequestFormData): Promise<number> {
      if (this.saving) throw new Error('حفظ قيد التنفيذ بالفعل')
      this.saving = true
      this.error = null

      try {
        const { createRequest } = useRequests()
        const created = await createRequest(data)
        this.currentRequest = created
        return created.id
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] createRequest failed:', err)
        }
        this.error = 'تعذّر إنشاء الطلب.'
        throw err
      }
      finally {
        this.saving = false
      }
    },

    async updateRequest(id: number, data: RequestFormData): Promise<void> {
      if (this.saving) throw new Error('حفظ قيد التنفيذ بالفعل')
      this.saving = true
      this.error = null

      try {
        const { updateRequest } = useRequests()
        const updated = await updateRequest(id, data)
        this.currentRequest = updated
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] updateRequest failed:', err)
        }
        this.error = 'تعذّر تحديث الطلب.'
        throw err
      }
      finally {
        this.saving = false
      }
    },

    async nextPage(): Promise<void> {
      if (this.loadingList || !this.hasNextPage || !this.meta) return
      await this.loadRequests({ ...this.currentFilter, page: this.meta.current_page + 1 })
    },

    async prevPage(): Promise<void> {
      if (this.loadingList || !this.hasPrevPage || !this.meta) return
      await this.loadRequests({ ...this.currentFilter, page: this.meta.current_page - 1 })
    },
  },
})
