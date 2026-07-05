import { ref } from 'vue'
import type { StageAccessLevel, StagePermission } from '@/types/models'
import { useApi } from '@/composables/useApi'

export type StagePermissionPayload = {
  organization_id?: number | null
  team_id?: number | null
  role_id?: number | null
  user_id?: number | null
  access_level: StageAccessLevel
  display_label: string
}

export function useStagePermissions() {
  const api = useApi()
  const permissions = ref<StagePermission[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchPermissions = async (stageId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: StagePermission[] }>(
        `/api/v1/workflow-stages/${stageId}/permissions`,
      )
      if (token === requestToken) {
        permissions.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        permissions.value = []
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل صلاحيات المرحلة.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createPermission = async (stageId: number, payload: StagePermissionPayload) => {
    const response = await api.post<{ data: StagePermission }>(
      `/api/v1/workflow-stages/${stageId}/permissions`,
      payload,
    )
    permissions.value = [...permissions.value, response.data]
    return response.data
  }

  const updatePermission = async (
    permission: StagePermission,
    payload: Partial<StagePermissionPayload>,
  ) => {
    const response = await api.put<{ data: StagePermission }>(
      `/api/v1/workflow-stages/${permission.stage_id}/permissions/${permission.id}`,
      { ...payload, version: permission.version },
    )
    permissions.value = permissions.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const deletePermission = async (permission: StagePermission) => {
    await api.del(`/api/v1/workflow-stages/${permission.stage_id}/permissions/${permission.id}`)
    permissions.value = permissions.value.filter((item) => item.id !== permission.id)
  }

  return {
    permissions,
    loading,
    error,
    fetchPermissions,
    createPermission,
    updatePermission,
    deletePermission,
  }
}
