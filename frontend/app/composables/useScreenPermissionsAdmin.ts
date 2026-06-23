import type { ScreenCapability } from '@/types/models'
import { useApi } from '@/composables/useApi'

interface ScreenRecord {
  id: number
  key: string
  label: string
}

interface RoleGrants {
  role_id: number
  role_code: string
  grants: Record<string, ScreenCapability[]>
}

export function useScreenPermissionsAdmin() {
  const api = useApi()
  const screens = ref<ScreenRecord[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)

  async function fetchScreens() {
    loading.value = true
    error.value = null
    try {
      const res = await api.get<{ data: ScreenRecord[] }>('/api/v1/screens')
      screens.value = res.data
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Failed to load screens'
    } finally {
      loading.value = false
    }
  }

  async function fetchRoleGrants(roleId: number): Promise<RoleGrants | null> {
    try {
      const res = await api.get<{ data: RoleGrants }>(`/api/v1/roles/${roleId}/screen-permissions`)
      return res.data
    } catch {
      return null
    }
  }

  async function saveRoleGrants(roleId: number, grants: Record<string, ScreenCapability[]>) {
    saving.value = true
    error.value = null
    try {
      await api.put(`/api/v1/roles/${roleId}/screen-permissions`, { grants })
      return true
    } catch (e: any) {
      error.value = e?.data?.message ?? e?.data?.errors?.grants?.[0] ?? 'Failed to save permissions'
      return false
    } finally {
      saving.value = false
    }
  }

  return { screens, loading, saving, error, fetchScreens, fetchRoleGrants, saveRoleGrants }
}
