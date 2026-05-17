import { ref } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import type { ApiResponse } from '../types/models'

export interface AdminSettings {
  support_claim_ttl: number
  voting_session_timeout: number
  pdf_upload_size_limit: number
  login_lockout_duration: number
  notifications_phase_1_enabled: boolean
  search_phase_1_enabled: boolean
  customs_print_preview_enabled: boolean
}

export const useAdminSettings = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  const settings = ref<AdminSettings | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pendingKeys = ref<Set<string>>(new Set())

  const fetchSettings = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<AdminSettings>>('/api/admin/settings', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      settings.value = response.data
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load admin settings'
      settings.value = null
    }
    finally {
      loading.value = false
    }
  }

  const updateSettingAsync = async (key: string, value: any) => {
    pendingKeys.value.add(key)
    error.value = null

    try {
      const response = await $fetch<ApiResponse<{ key: string; value: any }>>(`/api/admin/settings/${key}`, {
        method: 'PUT',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: { value },
      })

      if (settings.value) {
        settings.value = {
          ...settings.value,
          [key]: response.data.value,
        }
      }
      pendingKeys.value.delete(key)
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || `Failed to update ${key}`
      pendingKeys.value.delete(key)
      return false
    }
  }

  const updateSetting = useDebounceFn(updateSettingAsync, 500)

  const resetSetting = async (key: string) => {
    pendingKeys.value.add(key)
    error.value = null

    try {
      const response = await $fetch<ApiResponse<{ key: string; value: any }>>(`/api/admin/settings/${key}/reset`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })

      if (settings.value) {
        settings.value = {
          ...settings.value,
          [key]: response.data.value,
        }
      }
      pendingKeys.value.delete(key)
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || `Failed to reset ${key}`
      pendingKeys.value.delete(key)
      return false
    }
  }

  return {
    settings,
    loading,
    error,
    pendingKeys,
    fetchSettings,
    updateSetting,
    resetSetting,
  }
}
