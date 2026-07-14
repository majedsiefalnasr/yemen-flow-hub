import { ref } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineFormSchema() {
  const api = useApi()
  const fieldGroups = ref<ResolvedFieldGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchSchema = async (requestId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: { field_groups: ResolvedFieldGroup[] } }>(
        `/api/v1/engine-requests/${requestId}/form-schema`,
      )
      fieldGroups.value = response.data.field_groups
    } catch (cause: unknown) {
      fieldGroups.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل نموذج الطلب.')
    } finally {
      loading.value = false
    }
  }

  /**
   * Version-scoped counterpart for the pre-submission wizard: no
   * EngineRequest exists yet under the deferred-creation architecture, so
   * the initial stage's schema is fetched by workflow_version_id alone.
   * Unlike fetchSchema(), this rethrows after recording the error — the
   * calling page needs the real HTTP status (403/404/etc.) to render the
   * correct ErrorState, which a swallowed error can't provide.
   */
  const fetchInitialSchema = async (workflowVersionId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: { field_groups: ResolvedFieldGroup[] } }>(
        `/api/v1/engine-requests/initial-form-schema/${workflowVersionId}`,
      )
      fieldGroups.value = response.data.field_groups
    } catch (cause: unknown) {
      fieldGroups.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل نموذج الطلب.')
      throw cause
    } finally {
      loading.value = false
    }
  }

  return { fieldGroups, loading, error, fetchSchema, fetchInitialSchema }
}
