import { ref } from 'vue'
import type { WorkflowGraph } from '@/types/models'
import { useApi } from '@/composables/useApi'

export function useWorkflowGraph() {
  const api = useApi()
  const graph = ref<WorkflowGraph | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchGraph = async (versionId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: WorkflowGraph }>(
        `/api/v1/workflow-versions/${versionId}/graph`,
      )
      if (token === requestToken) {
        graph.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        graph.value = null
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل مخطط سير العمل.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  return {
    graph,
    loading,
    error,
    fetchGraph,
  }
}
