import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceTeam } from '@/types/models'

export type UpdateTeamPayload = {
  name: string
  organization_id?: number
  code?: string
}

export function useTeams() {
  const api = useApi()
  const teams = ref<GovernanceTeam[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchTeams = async (organizationId?: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: GovernanceTeam[] }>('/api/v1/teams', {
        query: organizationId ? { organization_id: organizationId } : {},
      })
      teams.value = response.data
    } catch (cause: unknown) {
      teams.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الفرق.')
    } finally {
      loading.value = false
    }
  }

  const createTeam = async (payload: { organization_id: number; code: string; name: string }) => {
    const response = await api.post<{ data: GovernanceTeam }>('/api/v1/teams', payload)
    teams.value = [response.data, ...teams.value]
    return response.data
  }

  const updateTeam = async (team: GovernanceTeam, payload: UpdateTeamPayload) => {
    const response = await api.put<{ data: GovernanceTeam }>(`/api/v1/teams/${team.id}`, {
      ...payload,
      version: team.version,
    })
    teams.value = teams.value.map((t) => (t.id === response.data.id ? response.data : t))
    return response.data
  }

  const setTeamActive = async (team: GovernanceTeam, active: boolean) => {
    const response = await api.post<{ data: GovernanceTeam }>(
      `/api/v1/teams/${team.id}/${active ? 'activate' : 'deactivate'}`,
    )
    teams.value = teams.value.map((t) => (t.id === response.data.id ? response.data : t))
    return response.data
  }

  const deleteTeam = async (team: GovernanceTeam) => {
    await api.del(`/api/v1/teams/${team.id}`)
    teams.value = teams.value.filter((t) => t.id !== team.id)
  }

  return { teams, loading, error, fetchTeams, createTeam, updateTeam, setTeamActive, deleteTeam }
}
