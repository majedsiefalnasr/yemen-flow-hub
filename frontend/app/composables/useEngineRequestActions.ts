import { ref } from 'vue'
import type { EngineRequest } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiFieldErrors } from '@/utils/apiErrors'

export function useEngineRequestActions() {
  const api = useApi()
  const executing = ref(false)
  const conflictError = ref(false)
  const fieldErrors = ref<Record<string, string | undefined>>({})

  const executeAction = async (
    id: number,
    transitionId: number,
    comment: string | null,
    data: Record<string, unknown>,
    version: number,
  ): Promise<EngineRequest> => {
    executing.value = true
    conflictError.value = false
    fieldErrors.value = {}
    try {
      const response = await api.post<{ success: boolean; data: EngineRequest }>(
        `/api/v1/engine-requests/${id}/actions`,
        { transition_id: transitionId, comment, data, version },
      )
      return response.data
    } catch (cause: unknown) {
      const status = (cause as { status?: number })?.status
      if (status === 409) {
        conflictError.value = true
      }
      if (status === 422) {
        fieldErrors.value = extractApiFieldErrors(cause)
      }
      throw cause
    } finally {
      executing.value = false
    }
  }

  return { executing, conflictError, fieldErrors, executeAction }
}
