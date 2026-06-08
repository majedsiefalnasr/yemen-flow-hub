import { defineStore } from 'pinia'
import type {
  ImportRequest,
  RequestDocument,
  RequestFormData,
  RequestStageHistory,
} from '../types/models'
import type { RequestsFilter } from '../composables/useRequests'
import { useRequests } from '../composables/useRequests'
import { useAuthStore } from './auth.store'

function isAuthTeardown(): boolean {
  if (!import.meta.client) return false
  const auth = useAuthStore()
  return auth.isLoggingOut || !auth.isAuthenticated
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  status_totals?: Partial<Record<string, number>>
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
    uploadingSignedFx: false,
    signedFxUploaded: false,
    uploading: false,
    uploadError: null as string | null,
    saving: false,
    error: null as string | null,
    meta: null as PaginationMeta | null,
    /** Aggregate status counts from a lightweight stats request — accurate across all pages */
    statsMeta: null as PaginationMeta | null,
    loadingStats: false,
    currentFilter: {} as RequestsFilter,
    /** Sequence counter — only the latest in-flight request commits its results */
    _loadToken: 0,
    _statsToken: 0,
  }),

  getters: {
    hasNextPage: (state): boolean =>
      state.meta !== null && state.meta.current_page < state.meta.last_page,

    hasPrevPage: (state): boolean => state.meta !== null && state.meta.current_page > 1,

    currentPage: (state): number => state.meta?.current_page ?? 1,

    totalCount: (state): number => state.meta?.total ?? 0,

    /** Ordered IDs of the currently loaded list page — used for prev/next detail navigation */
    listIds: (state): number[] => state.requests.map((r) => r.id),
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
      } catch (err) {
        if (token !== this._loadToken) return
        if (import.meta.dev && !isAuthTeardown()) {
          console.error('[requests.store] loadRequests failed:', err)
        }
        this.error = isAuthTeardown() ? null : 'تعذّر تحميل قائمة الطلبات.'
        this.requests = []
        this.meta = null
      } finally {
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
      this.uploading = false
      this.uploadError = null
      this.uploadingSignedFx = false
      this.signedFxUploaded = false

      try {
        const { fetchRequest } = useRequests()
        this.currentRequest = await fetchRequest(id)
      } catch (err) {
        if (import.meta.dev && !isAuthTeardown()) {
          console.error('[requests.store] loadRequest failed:', err)
        }
        this.error = isAuthTeardown() ? null : 'تعذّر تحميل بيانات الطلب.'
      } finally {
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
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] createRequest failed:', err)
        }
        this.error = 'تعذّر إنشاء الطلب.'
        throw err
      } finally {
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
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] updateRequest failed:', err)
        }
        this.error = 'تعذّر تحديث الطلب.'
        throw err
      } finally {
        this.saving = false
      }
    },

    async uploadDocument(id: number, file: File): Promise<void> {
      this.uploading = true
      this.uploadError = null

      try {
        const { uploadDocument } = useRequests()
        await uploadDocument(id, file)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] uploadDocument failed:', err)
        }
        this.uploadError = 'تعذّر رفع المستند. يرجى المحاولة مرة أخرى.'
        throw err
      } finally {
        this.uploading = false
      }

      // Refresh document list after successful upload — errors handled by loadDocuments itself
      await this.loadDocuments(id)
    },

    async loadDocuments(id: number): Promise<void> {
      this.loadingDocuments = true
      this.documents = []
      this.documentsError = null

      try {
        const { fetchRequestDocuments } = useRequests()
        this.documents = await fetchRequestDocuments(id)
        this.documentsLoaded = true
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] loadDocuments failed:', err)
        }
        this.documentsError = 'تعذّر تحميل المستندات. يرجى المحاولة مرة أخرى.'
      } finally {
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
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] performAction failed:', err)
        }
        this.error = 'تعذّر تنفيذ الإجراء.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async bankReturn(id: number, comment: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { bankReturn } = useRequests()
        this.currentRequest = await bankReturn(id, comment)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] bankReturn failed:', err)
        }
        this.error = 'تعذّر إعادة الطلب للمدخل.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async supportReturn(id: number, comment: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { supportReturn } = useRequests()
        this.currentRequest = await supportReturn(id, comment)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] supportReturn failed:', err)
        }
        this.error = 'تعذّر إعادة الطلب للمدخل.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async supportForwardToExecutive(id: number, comment: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { supportForwardToExecutive } = useRequests()
        this.currentRequest = await supportForwardToExecutive(id, comment)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] supportForwardToExecutive failed:', err)
        }
        this.error = 'تعذّر إرسال الطلب إلى اللجنة التنفيذية.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async bankRejectTerminal(id: number, comment: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { bankRejectTerminal } = useRequests()
        this.currentRequest = await bankRejectTerminal(id, comment)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] bankRejectTerminal failed:', err)
        }
        this.error = 'تعذّر تنفيذ الرفض النهائي.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async bankReturnAfterSupportReject(id: number, reason?: string): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { bankReturnAfterSupportReject } = useRequests()
        this.currentRequest = await bankReturnAfterSupportReject(id, reason)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] bankReturnAfterSupportReject failed:', err)
        }
        this.error = 'تعذّر إعادة الطلب للمدخل.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async bankFinalizeRejection(id: number): Promise<void> {
      if (this.performingAction) throw new Error('إجراء قيد التنفيذ بالفعل')
      this.performingAction = true
      this.error = null

      try {
        const { bankFinalizeRejection } = useRequests()
        this.currentRequest = await bankFinalizeRejection(id)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] bankFinalizeRejection failed:', err)
        }
        this.error = 'تعذّر إتمام الرفض النهائي.'
        throw err
      } finally {
        this.performingAction = false
      }
    },

    async issueCustomsDeclaration(id: number): Promise<void> {
      if (this.issuingCustoms)
        throw new Error('إصدار وثيقة تأكيد المصارفة الخارجية قيد التنفيذ بالفعل')
      this.issuingCustoms = true
      this.error = null

      try {
        const { generateCustomsDeclaration, fetchRequest } = useRequests()
        await generateCustomsDeclaration(id)
        this.currentRequest = await fetchRequest(id)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] issueCustomsDeclaration failed:', err)
        }
        this.error = 'تعذّر إصدار وثيقة تأكيد المصارفة الخارجية.'
        throw err
      } finally {
        this.issuingCustoms = false
      }
    },

    async uploadSignedFxDoc(id: number, file: File): Promise<void> {
      if (this.uploadingSignedFx) throw new Error('رفع وثيقة المصارفة قيد التنفيذ بالفعل')
      this.uploadingSignedFx = true
      this.error = null

      try {
        const { uploadSignedFxConfirmation, fetchRequest } = useRequests()
        await uploadSignedFxConfirmation(id, file)
        this.signedFxUploaded = true
        this.currentRequest = await fetchRequest(id)
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] uploadSignedFxDoc failed:', err)
        }
        this.error = 'تعذّر رفع وثيقة المصارفة الموقّعة.'
        throw err
      } finally {
        this.uploadingSignedFx = false
      }
    },

    async downloadCustomsDeclaration(
      customsDeclarationId: number,
      filename: string,
    ): Promise<void> {
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
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] downloadCustomsDeclaration failed:', err)
        }
        throw err
      } finally {
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
      } catch (err) {
        if (import.meta.dev) {
          console.error('[requests.store] loadHistory failed:', err)
        }
        this.historyError = 'تعذّر تحميل سجل المراحل. يرجى المحاولة مرة أخرى.'
      } finally {
        this.loadingHistory = false
      }
    },

    /** Lightweight stats-only request — fetches per_page:1 with_status_totals:1 for accurate aggregate counts */
    async loadStats(filter: RequestsFilter = {}): Promise<void> {
      const token = ++this._statsToken
      this.loadingStats = true
      try {
        const { fetchRequests } = useRequests()
        const result = await fetchRequests({
          ...filter,
          per_page: 1,
          page: 1,
          with_status_totals: true,
        })
        if (token !== this._statsToken) return
        this.statsMeta = result.meta
      } catch {
        // Stats are non-critical — silently ignore errors
      } finally {
        if (token === this._statsToken) this.loadingStats = false
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
