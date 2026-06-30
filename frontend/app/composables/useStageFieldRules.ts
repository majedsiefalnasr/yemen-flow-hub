import { ref } from 'vue'
import type { StageFieldRule } from '@/types/models'
import { useApi } from '@/composables/useApi'

export type StageFieldRulePayload = {
  field_id: number
  is_visible?: boolean
  is_editable?: boolean
  is_required?: boolean
}

export function useStageFieldRules() {
  const api = useApi()
  const rules = ref<StageFieldRule[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchRules = async (stageId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: StageFieldRule[] }>(
        `/api/v1/workflow-stages/${stageId}/field-rules`,
      )
      if (token === requestToken) {
        rules.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        rules.value = []
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل قواعد الحقول.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const setRule = async (stageId: number, payload: StageFieldRulePayload) => {
    const response = await api.post<{ data: StageFieldRule }>(
      `/api/v1/workflow-stages/${stageId}/field-rules`,
      payload,
    )
    const rule = response.data
    const index = rules.value.findIndex((r) => r.field_id === rule.field_id)
    if (index >= 0) {
      rules.value = rules.value.map((r) => (r.field_id === rule.field_id ? rule : r))
    } else {
      rules.value = [...rules.value, rule]
    }
    return rule
  }

  return {
    rules,
    loading,
    error,
    fetchRules,
    setRule,
  }
}
