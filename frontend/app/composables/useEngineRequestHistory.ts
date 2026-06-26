import { ref } from 'vue'
import type { EngineHistoryEntry, WorkflowGraph } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineRequestHistory() {
  const api = useApi()
  const history = ref<EngineHistoryEntry[]>([])
  const graph = ref<WorkflowGraph | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchHistory = async (id: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: EngineHistoryEntry[] }>(
        `/api/v1/engine-requests/${id}/history`,
      )
      history.value = response.data
    } catch (cause: unknown) {
      history.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل سجل الطلب.')
    } finally {
      loading.value = false
    }
  }

  const fetchGraph = async (id: number) => {
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: WorkflowGraph }>(
        `/api/v1/engine-requests/${id}/graph`,
      )
      graph.value = response.data
    } catch (cause: unknown) {
      graph.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل مخطط سير العمل.')
    }
  }

  return { history, graph, loading, error, fetchHistory, fetchGraph }
}
