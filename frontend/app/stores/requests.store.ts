import { defineStore } from 'pinia'
import type { ImportRequest, RequestDocument, RequestFormData, RequestStageHistory } from '../types/models'
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
    documents: [] as RequestDocument[],
    documentsError: null as string | null,
    documentsLoaded: false,
    history: [] as RequestStageHistory[],
    historyError: null as string | null,
    historyLoaded: false,
    loadingList: false,
    loadingRequest: false,
    loadingDocuments: false,
    loadingHistory: false,
    performingAction: false,
    issuingCustoms: false,
    downloadingCustoms: false,
    uploading: false,
    uploadError: null as string | null,
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
      this.documents = []
      this.documentsError = null
      this.documentsLoaded = false
      this.history = []
      this.historyError = null
      this.historyLoaded = false

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

    async uploadDocument(id: number, file: File): Promise<void> {
      this.uploading = true
      this.uploadError = null

      try {
        const { uploadDocument } = useRequests()
        await uploadDocument(id, file, file.name)
        await this.loadDocuments(id)
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] uploadDocument failed:', err)
        }
        this.uploadError = 'تعذّر رفع المستند. يرجى المحاولة مرة أخرى.'
        throw err
      }
      finally {
        this.uploading = false
      }
    },

    async loadDocuments(id: number): Promise<void> {
      this.loadingDocuments = true
      this.documents = []
      this.documentsError = null

      try {
        const { fetchRequestDocuments } = useRequests()
        this.documents = await fetchRequestDocuments(id)
        this.documentsLoaded = true
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] loadDocuments failed:', err)
        }
        this.documentsError = 'تعذّر تحميل المستندات. يرجى المحاولة مرة أخرى.'
      }
      finally {
        this.loadingDocuments = false
      }
    },

    async performAction(id: number, action: string, reason?: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { performWorkflowAction } = useRequests()
        const updated = await performWorkflowAction(id, action, reason)
        this.currentRequest = updated
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] performAction failed:', err)
        }
        this.error = 'تعذّر تنفيذ الإجراء.'
        throw err
      }
      finally {
        this.performingAction = false
      }
    },

    async issueCustomsDeclaration(id: number): Promise<void> {
      if (this.issuingCustoms) throw new Error('إصدار البيان الجمركي قيد التنفيذ بالفعل')
      this.issuingCustoms = true
      this.error = null

      try {
        const { generateCustomsDeclaration, fetchRequest } = useRequests()
        await generateCustomsDeclaration(id)
        this.currentRequest = await fetchRequest(id)
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] issueCustomsDeclaration failed:', err)
        }
        this.error = 'تعذّر إصدار البيان الجمركي.'
        throw err
      }
      finally {
        this.issuingCustoms = false
      }
    },

    async downloadCustomsDeclaration(customsDeclarationId: number, filename: string): Promise<void> {
      if (this.downloadingCustoms) return
      this.downloadingCustoms = true

      try {
        const { downloadCustomsDeclaration } = useRequests()
        const response = await downloadCustomsDeclaration(customsDeclarationId)
        const url = URL.createObjectURL(response)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = filename
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] downloadCustomsDeclaration failed:', err)
        }
        throw err
      }
      finally {
        this.downloadingCustoms = false
      }
    },

    async loadHistory(id: number): Promise<void> {
      this.loadingHistory = true
      this.history = []
      this.historyError = null

      try {
        const { fetchRequestHistory } = useRequests()
        this.history = await fetchRequestHistory(id)
        this.historyLoaded = true
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] loadHistory failed:', err)
        }
        this.historyError = 'تعذّر تحميل سجل المراحل. يرجى المحاولة مرة أخرى.'
      }
      finally {
        this.loadingHistory = false
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
