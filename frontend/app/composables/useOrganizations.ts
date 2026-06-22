import { ref } from 'vue'
import type { Organization } from '@/types/models'
import { useApi } from '@/composables/useApi'

type ListResponse = {
  data: Organization[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export function useOrganizations() {
  const api = useApi()
  const organizations = ref<Organization[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchOrganizations = async (search = '') => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<ListResponse>('/api/v1/organizations', {
        query: { search },
      })
      organizations.value = response.data
    } catch (cause: any) {
      error.value = cause?.data?.error?.message ?? 'تعذر تحميل المؤسسات.'
    } finally {
      loading.value = false
    }
  }

  const createOrganization = async (payload: { code: string; name: string }) => {
    const response = await api.post<{ data: Organization }>('/api/v1/organizations', payload)
    organizations.value = [response.data, ...organizations.value]
    return response.data
  }

  const updateOrganization = async (organization: Organization, payload: { name: string }) => {
    const response = await api.put<{ data: Organization }>(
      `/api/v1/organizations/${organization.id}`,
      { ...payload, version: organization.version },
    )
    organizations.value = organizations.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const setOrganizationActive = async (organization: Organization, active: boolean) => {
    const response = await api.post<{ data: Organization }>(
      `/api/v1/organizations/${organization.id}/${active ? 'activate' : 'deactivate'}`,
    )
    organizations.value = organizations.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
  }

  return {
    organizations,
    loading,
    error,
    fetchOrganizations,
    createOrganization,
    updateOrganization,
    setOrganizationActive,
  }
}
