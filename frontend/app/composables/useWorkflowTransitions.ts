import { ref } from 'vue'
import type { WorkflowTransition } from '@/types/models'
import { useApi } from '@/composables/useApi'

export type WorkflowTransitionPayload = {
  from_stage_id: number
  action_id: number
  to_stage_id: number
  requires_comment?: boolean
  confirmation_message?: string | null
}

export function useWorkflowTransitions() {
  const api = useApi()
  const transitions = ref<WorkflowTransition[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchTransitions = async (versionId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: WorkflowTransition[] }>(
        `/api/v1/workflow-versions/${versionId}/transitions`,
      )
      if (token === requestToken) {
        transitions.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        transitions.value = []
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل الانتقالات.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createTransition = async (versionId: number, payload: WorkflowTransitionPayload) => {
    const response = await api.post<{ data: WorkflowTransition }>(
      `/api/v1/workflow-versions/${versionId}/transitions`,
      payload,
    )
    transitions.value = [...transitions.value, response.data]
    return response.data
  }

  const deleteTransition = async (transition: WorkflowTransition) => {
    await api.del(
      `/api/v1/workflow-versions/${transition.workflow_version_id}/transitions/${transition.id}`,
    )
    transitions.value = transitions.value.filter((item) => item.id !== transition.id)
  }

  return {
    transitions,
    loading,
    error,
    fetchTransitions,
    createTransition,
    deleteTransition,
  }
}
