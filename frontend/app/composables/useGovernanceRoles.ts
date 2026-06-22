import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceRole } from '@/types/models'

export function useGovernanceRoles() {
  const api = useApi()
  const roles = ref<GovernanceRole[]>([])

  const fetchRoles = async (organizationId?: number) => {
    const response = await api.get<{ data: GovernanceRole[] }>('/api/v1/roles', {
      query: organizationId ? { organization_id: organizationId } : {},
    })
    roles.value = response.data
  }

  const createRole = async (payload: { organization_id: number; code: string; name: string }) => {
    const response = await api.post<{ data: GovernanceRole }>('/api/v1/roles', payload)
    roles.value = [response.data, ...roles.value]
  }

  return { roles, fetchRoles, createRole }
}
