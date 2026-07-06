import { defineStore } from 'pinia'
import type {
  AvailableWorkflow,
  EngineDuplicateWarning,
  EngineHistoryEntry,
  EngineRequest,
  EngineRequestDocument,
  WorkflowGraph,
} from '@/types/models'
import { useEngineRequests } from '@/composables/useEngineRequests'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineRequestHistory } from '@/composables/useEngineRequestHistory'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useEngineRequestsStore = defineStore('engineRequests', {
  state: () => ({
    instances: [] as EngineRequest[],
    instancesMeta: null as PaginationMeta | null,
    queue: [] as EngineRequest[],
    queueMeta: null as PaginationMeta | null,
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
    async loadList(options: Record<string, unknown> = {}) {
      const { instances, instancesMeta, loading, error, fetchList } = useEngineRequests()
      await fetchList(options)
      this.instances = instances.value
      this.instancesMeta = instancesMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    async loadQueue(options: Record<string, unknown> = {}) {
      const { queue, queueMeta, loading, error, fetchQueue } = useEngineRequests()
      await fetchQueue(options)
      this.queue = queue.value
      this.queueMeta = queueMeta.value
      this.loading = loading.value
      this.error = error.value
    },

    async loadAvailableWorkflows() {
      const { availableWorkflows, error, fetchAvailableWorkflows } = useEngineRequests()
      await fetchAvailableWorkflows()
      this.availableWorkflows = availableWorkflows.value
      this.error = error.value
    },

    async createInstance(payload: {
      workflow_version_id: number
      bank_id?: number | null
      merchant_id?: number | null
      data: Record<string, unknown>
    }): Promise<EngineRequest> {
      const { create } = useEngineRequests()
      const result = await create(payload)
      this.current = result
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

    async saveDraftData(id: number, data: Record<string, unknown>, version: number) {
      const { saveDraft } = useEngineRequests()
      this.current = await saveDraft(id, data, version)
    },

    async abandonDraft(id: number, version: number) {
      const { abandonDraft } = useEngineRequests()
      this.current = await abandonDraft(id, version)
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
