import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceRole } from '@/types/models'

export function useGovernanceRoles() {
  const api = useApi()
  const roles = ref<GovernanceRole[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchRoles = async (organizationId?: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: GovernanceRole[] }>('/api/v1/roles', {
        query: organizationId ? { organization_id: organizationId } : {},
      })
      roles.value = response.data
    } catch (cause: unknown) {
      roles.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الأدوار.')
    } finally {
      loading.value = false
    }
  }

  const createRole = async (payload: { organization_id: number; code: string; name: string }) => {
    const response = await api.post<{ data: GovernanceRole }>('/api/v1/roles', payload)
    roles.value = [response.data, ...roles.value]
    return response.data
  }

  const updateRole = async (role: GovernanceRole, payload: { name: string }) => {
    const response = await api.put<{ data: GovernanceRole }>(`/api/v1/roles/${role.id}`, {
      ...payload,
      version: role.version,
    })
    roles.value = roles.value.map((r) => (r.id === response.data.id ? response.data : r))
    return response.data
  }

  const setRoleActive = async (role: GovernanceRole, active: boolean) => {
    const response = await api.post<{ data: GovernanceRole }>(
      `/api/v1/roles/${role.id}/${active ? 'activate' : 'deactivate'}`,
    )
    roles.value = roles.value.map((r) => (r.id === response.data.id ? response.data : r))
    return response.data
  }

  const deleteRole = async (role: GovernanceRole) => {
    await api.del(`/api/v1/roles/${role.id}`)
    roles.value = roles.value.filter((r) => r.id !== role.id)
  }

  return { roles, loading, error, fetchRoles, createRole, updateRole, setRoleActive, deleteRole }
}
