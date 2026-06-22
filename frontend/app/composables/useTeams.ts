import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceTeam } from '@/types/models'

export function useTeams() {
  const api = useApi()
  const teams = ref<GovernanceTeam[]>([])
  const loading = ref(false)

  const fetchTeams = async (organizationId?: number) => {
    loading.value = true
    try {
      const response = await api.get<{ data: GovernanceTeam[] }>('/api/v1/teams', {
        query: organizationId ? { organization_id: organizationId } : {},
      })
      teams.value = response.data
    } finally {
      loading.value = false
    }
  }

  const createTeam = async (payload: { organization_id: number; code: string; name: string }) => {
    const response = await api.post<{ data: GovernanceTeam }>('/api/v1/teams', payload)
    teams.value = [response.data, ...teams.value]
    return response.data
  }

  return { teams, loading, fetchTeams, createTeam }
}
