import { ref } from 'vue'
import type { FinalOutcome, WorkflowStage } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export type WorkflowStagePayload = {
  code: string
  name: string
  description?: string | null
  sort_order?: number
  is_initial?: boolean
  is_final?: boolean
  final_outcome?: FinalOutcome | null
  requires_claim?: boolean
  sla_duration_minutes?: number | null
  status?: WorkflowStage['status']
}

export function useWorkflowStages() {
  const api = useApi()
  const stages = ref<WorkflowStage[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchStages = async (versionId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: WorkflowStage[] }>(
        `/api/v1/workflow-versions/${versionId}/stages`,
      )
      if (token === requestToken) {
        stages.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        stages.value = []
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل المراحل.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createStage = async (versionId: number, payload: WorkflowStagePayload) => {
    const response = await api.post<{ data: WorkflowStage }>(
      `/api/v1/workflow-versions/${versionId}/stages`,
      payload,
    )
    stages.value = [...stages.value, response.data].sort((a, b) => a.sort_order - b.sort_order)
    return response.data
  }

  const updateStage = async (stage: WorkflowStage, payload: Partial<WorkflowStagePayload>) => {
    const response = await api.put<{ data: WorkflowStage }>(
      `/api/v1/workflow-versions/${stage.workflow_version_id}/stages/${stage.id}`,
      { ...payload, version: stage.version },
    )
    stages.value = stages.value
      .map((item) => (item.id === response.data.id ? response.data : item))
      .sort((a, b) => a.sort_order - b.sort_order)
    return response.data
  }

  const deleteStage = async (stage: WorkflowStage) => {
    await api.del(`/api/v1/workflow-versions/${stage.workflow_version_id}/stages/${stage.id}`)
    stages.value = stages.value.filter((item) => item.id !== stage.id)
  }

  return {
    stages,
    loading,
    error,
    fetchStages,
    createStage,
    updateStage,
    deleteStage,
  }
}
