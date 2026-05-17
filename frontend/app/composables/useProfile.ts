import { ref } from 'vue'
import type { AuthUser, ApiResponse } from '../types/models'
import { useAuthStore } from '../stores/auth.store'

export const useProfile = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string
  const auth = useAuthStore()

  const profile = ref<AuthUser | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchProfile = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      profile.value = response.data
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load profile'
      profile.value = null
    }
    finally {
      loading.value = false
    }
  }

  const changePassword = async (data: {
    current_password: string
    password: string
    password_confirmation: string
  }) => {
    loading.value = true
    error.value = null

    try {
      await $fetch<ApiResponse<void>>('/api/profile/change-password', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: data,
      })
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to change password'
      return false
    }
    finally {
      loading.value = false
    }
  }

  return {
    profile,
    loading,
    error,
    fetchProfile,
    changePassword,
  }
}
