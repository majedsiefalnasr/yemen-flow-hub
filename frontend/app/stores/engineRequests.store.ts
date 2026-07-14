import { defineStore } from 'pinia'
import type {
  AvailableWorkflow,
  EngineDuplicateWarning,
  EngineHistoryEntry,
  EngineRequest,
  EngineRequestDocument,
  EngineRequestStats,
  WorkflowGraph,
} from '@/types/models'
import type { EngineSubmitResult, ListOptions } from '@/composables/useEngineRequests'
import { useEngineRequests } from '@/composables/useEngineRequests'
import { useEngineRequestStats } from '@/composables/useEngineRequestStats'
import { isAbortError } from '@/composables/useApi'
import { extractApiErrorMessage, extractHttpStatus } from '@/utils/apiErrors'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineRequestHistory } from '@/composables/useEngineRequestHistory'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type StatsScope = 'all' | 'queue'

/**
 * API-UI-001: the stats endpoint could 500 (MySQL parity bugs, now fixed
 * server-side) while the workflows page re-fired loadStats on every filter,
 * pagination, and view change with no guard — a request storm that eventually
 * tripped the 5/min rate limiter into 429s. These per-scope maps live at module
 * scope (the store is a Pinia singleton) so they stay out of reactive state:
 *
 * - inFlight: single-flight. A concurrent call for the same scope reuses the
 *   pending promise instead of opening a second request.
 * - blockedSignature: the params signature that last failed for this scope.
 *   While the inputs are unchanged, an auto-triggered reload short-circuits
 *   instead of re-hitting a known-failing endpoint. A new signature (the user
 *   changed a filter) or an explicit retry clears it.
 */
const statsInFlight = new Map<StatsScope, Promise<void>>()
const statsBlockedSignature = new Map<StatsScope, string>()

function statsSignature(options: ListOptions & { scope: StatsScope }): string {
  const { scope, ...rest } = options
  return `${scope}:${JSON.stringify(rest)}`
}

export const useEngineRequestsStore = defineStore('engineRequests', {
  state: () => ({
    instances: [] as EngineRequest[],
    instancesMeta: null as PaginationMeta | null,
    queue: [] as EngineRequest[],
    queueMeta: null as PaginationMeta | null,
    queueStats: null as EngineRequestStats | null,
    allStats: null as EngineRequestStats | null,
    statsError: null as string | null,
    statsRateLimited: false,
    statsLoading: false,
    availableWorkflows: [] as AvailableWorkflow[],
    current: null as EngineRequest | null,
    duplicateWarnings: [] as EngineDuplicateWarning[],
    history: [] as EngineHistoryEntry[],
    graph: null as WorkflowGraph | null,
    documents: [] as EngineRequestDocument[],
    loading: false,
    error: null as string | null,
    conflictError: false,
    fieldErrors: {} as Record<string, string | undefined>,
  }),
  actions: {
    async loadList(options: ListOptions = {}) {
      const { instances, instancesMeta, loading, error, fetchList } = useEngineRequests()
      await fetchList(options)
      this.instances = instances.value
      this.instancesMeta = instancesMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    async loadQueue(options: ListOptions = {}) {
      const { queue, queueMeta, loading, error, fetchQueue } = useEngineRequests()
      await fetchQueue(options)
      this.queue = queue.value
      this.queueMeta = queueMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    /**
     * API-UI-001: single-flight + terminal-error-aware stats load. Auto-triggered
     * reloads (filter/pagination/view watchers) short-circuit when the same params
     * already failed for this scope, so a failing endpoint is not hammered; the
     * signature changes when the user changes a filter, and retryStats() clears the
     * block for an explicit retry. Errors surface on statsError (rendered by the
     * page Alert) instead of rejecting silently, and 429s set statsRateLimited.
     */
    async loadStats(options: ListOptions & { scope: StatsScope }) {
      const scope = options.scope
      const signature = statsSignature(options)

      // Terminal-error circuit: don't auto-refire the same known-failing request.
      if (statsBlockedSignature.get(scope) === signature) return

      // Single-flight: a concurrent identical scope call reuses the in-flight one.
      const existing = statsInFlight.get(scope)
      if (existing) return existing

      const run = (async () => {
        const { fetchStats, stats } = useEngineRequestStats()
        this.statsLoading = true
        try {
          await fetchStats(options)
          if (scope === 'queue') {
            this.queueStats = stats.value
          } else {
            this.allStats = stats.value
          }
          statsBlockedSignature.delete(scope)
          this.statsError = null
          this.statsRateLimited = false
        } catch (cause: unknown) {
          if (isAbortError(cause)) return
          // Block this signature so the reactive watchers stop re-firing it until
          // the params change or the user retries explicitly.
          statsBlockedSignature.set(scope, signature)
          this.statsRateLimited = extractHttpStatus(cause) === 429
          this.statsError = this.statsRateLimited
            ? 'تم إيقاف التحديث مؤقتاً بسبب كثرة الطلبات. حاول مرة أخرى بعد قليل.'
            : extractApiErrorMessage(cause, 'تعذر تحميل الإحصائيات.')
        } finally {
          this.statsLoading = false
          statsInFlight.delete(scope)
        }
      })()

      statsInFlight.set(scope, run)
      return run
    },

    /**
     * Explicit user-initiated stats retry: clear the terminal-error circuit for
     * both scopes so the next loadStats re-hits the endpoint even if the params
     * are unchanged.
     */
    resetStatsErrorState() {
      statsBlockedSignature.clear()
      this.statsError = null
      this.statsRateLimited = false
    },

    async loadAvailableWorkflows() {
      const { availableWorkflows, error, fetchAvailableWorkflows } = useEngineRequests()
      await fetchAvailableWorkflows()
      this.availableWorkflows = availableWorkflows.value
      this.error = error.value
    },

    /**
     * Deferred-creation submission: one atomic call, no pre-existing draft
     * row. idempotencyKey must be a stable, caller-generated UUID reused
     * across retries of the same wizard submission attempt. May return
     * `in_progress` (202) when another attempt with the same key is still
     * mid-flight server-side — the caller is responsible for retrying, this
     * action does not retry on its own.
     */
    async submitInstance(
      idempotencyKey: string,
      payload: {
        workflow_version_id: number
        merchant_id?: number | null
        data: Record<string, unknown>
        upload_tokens?: string[]
      },
    ): Promise<EngineSubmitResult> {
      const { submit } = useEngineRequests()
      const result = await submit(idempotencyKey, payload)
      if (result.kind === 'completed') {
        this.current = result.data
        this.duplicateWarnings = result.warnings
      }
      return result
    },

    async loadInstance(id: number) {
      const { show, currentWarnings } = useEngineRequests()
      const { history, graph, fetchHistory, fetchGraph } = useEngineRequestHistory()
      const { documents, fetchDocuments } = useEngineRequestDocuments()

      this.current = await show(id)
      this.duplicateWarnings = currentWarnings.value
      await Promise.all([fetchHistory(id), fetchGraph(id), fetchDocuments(id)])
      this.history = history.value
      this.graph = graph.value
      this.documents = documents.value
    },

    async executeTransition(
      id: number,
      transitionId: number,
      comment: string | null,
      data: Record<string, unknown>,
      version: number,
    ) {
      const { executeAction, conflictError, fieldErrors } = useEngineRequestActions()
      try {
        const result = await executeAction(id, transitionId, comment, data, version)
        this.current = result
        this.conflictError = false
        this.fieldErrors = {}
        await this.loadInstance(id)
        return result
      } catch (cause) {
        this.conflictError = conflictError.value
        this.fieldErrors = fieldErrors.value
        throw cause
      }
    },

    async loadHistory(id: number) {
      const { history, fetchHistory } = useEngineRequestHistory()
      await fetchHistory(id)
      this.history = history.value
    },

    async loadGraph(id: number) {
      const { graph, fetchGraph } = useEngineRequestHistory()
      await fetchGraph(id)
      this.graph = graph.value
    },

    async loadDocuments(id: number) {
      const { documents, fetchDocuments } = useEngineRequestDocuments()
      await fetchDocuments(id)
      this.documents = documents.value
    },

    async uploadDocument(id: number, file: File, fieldId: number | null) {
      const { upload } = useEngineRequestDocuments()
      await upload(id, file, fieldId)
      const { documents, fetchDocuments } = useEngineRequestDocuments()
      await fetchDocuments(id)
      this.documents = documents.value
    },

    async removeDocument(id: number, documentId: number) {
      const { remove } = useEngineRequestDocuments()
      await remove(id, documentId)
      const { documents, fetchDocuments } = useEngineRequestDocuments()
      await fetchDocuments(id)
      this.documents = documents.value
    },
  },
})
