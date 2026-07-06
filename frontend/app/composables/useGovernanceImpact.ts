import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type {
  BankLifecycleImpactPayload,
  GovernanceEntityType,
  GovernanceImpactPayload,
  GovernanceLifecycleAction,
} from '@/types/governance-impact'

export function useGovernanceImpact() {
  const api = useApi()
  const loading = ref(false)
  const error = ref<string | null>(null)
  const impact = ref<GovernanceImpactPayload | BankLifecycleImpactPayload | null>(null)

  const fetchImpact = async (
    entityType: GovernanceEntityType,
    entityId: number,
    action: GovernanceLifecycleAction,
  ) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: GovernanceImpactPayload }>('/api/v1/governance/impact', {
        query: { entity_type: entityType, entity_id: entityId, action },
      })
      impact.value = response.data
      return response.data
    } catch (cause: unknown) {
      impact.value = null
      error.value = extractApiErrorMessage(cause, 'تعذّر تحميل تأثير العملية.')
      throw cause
    } finally {
      loading.value = false
    }
  }

  const fetchBankImpact = async (bankId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: BankLifecycleImpactPayload }>(
        `/api/v1/banks/${bankId}/lifecycle-impact`,
      )
      impact.value = response.data
      return response.data
    } catch (cause: unknown) {
      impact.value = null
      error.value = extractApiErrorMessage(cause, 'تعذّر تحميل تأثير العملية.')
      throw cause
    } finally {
      loading.value = false
    }
  }

  const reset = () => {
    impact.value = null
    error.value = null
    loading.value = false
  }

  return { loading, error, impact, fetchImpact, fetchBankImpact, reset }
}
