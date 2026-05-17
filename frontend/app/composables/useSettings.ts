import { ref } from 'vue'
import type { UserPreferences, ApiResponse } from '../types/models'
import { useAuthStore } from '../stores/auth.store'

export const useSettings = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string
  const auth = useAuthStore()

  const preferences = ref<UserPreferences | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchSettings = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<UserPreferences>>('/api/settings', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      preferences.value = response.data
      auth.setUserPreferences(response.data)
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load settings'
      preferences.value = null
    }
    finally {
      loading.value = false
    }
  }

  const updateSettings = async (updates: Partial<UserPreferences>) => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<UserPreferences>>('/api/settings', {
        method: 'PUT',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: updates,
      })
      preferences.value = { ...preferences.value, ...response.data }
      auth.setUserPreferences({ ...preferences.value })
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to update settings'
      return false
    }
    finally {
      loading.value = false
    }
  }

  const resetSettings = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<UserPreferences>>('/api/settings/reset', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      preferences.value = { ...response.data }
      auth.setUserPreferences({ ...preferences.value })
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to reset settings'
      return false
    }
    finally {
      loading.value = false
    }
  }

  return {
    preferences,
    loading,
    error,
    fetchSettings,
    updateSettings,
    resetSettings,
  }
}
