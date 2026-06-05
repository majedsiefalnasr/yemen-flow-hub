import { ref } from 'vue'
import type { AuthUser, ApiResponse } from '../types/models'
import { useAuthStore } from '../stores/auth.store'

function getXsrfToken(): string | null {
  if (!import.meta.client) return null
  const raw = document.cookie
    .split(';')
    .map((c) => c.trim())
    .find((c) => c.startsWith('XSRF-TOKEN='))
    ?.split('=')
    .slice(1)
    .join('=')
  return raw ? decodeURIComponent(raw) : null
}

function xsrfHeaders(): Record<string, string> {
  const token = getXsrfToken()
  return token ? { 'X-XSRF-TOKEN': token } : {}
}

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
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to load profile'
      profile.value = null
    } finally {
      loading.value = false
    }
  }

  const updateProfile = async (data: {
    name: string
    email: string
    phone?: string
    avatar_variant?: string
  }): Promise<boolean> => {
    loading.value = true
    error.value = null
    const body = {
      ...data,
      name: data.name.trim(),
      email: data.email.trim(),
      phone: data.phone?.replace(/\s+/g, '') || null,
    }

    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile', {
        method: 'PUT',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body,
      })
      profile.value = response.data
      if (auth.user) auth.user = response.data
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to update profile'
      return false
    } finally {
      loading.value = false
    }
  }

  /**
   * Persist only avatar preferences. Used by the picker for instant feedback
   * so the user does not have to press a save button after changing variant
   * or colour.
   */
  const updateAvatar = async (data: { avatar_variant?: string }): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/avatar', {
        method: 'PUT',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: data,
      })
      profile.value = response.data
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to update avatar'
      return false
    }
  }

  const toggleMfa = async (): Promise<boolean> => {
    error.value = null

    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/mfa/toggle', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', ...xsrfHeaders() },
      })
      profile.value = response.data
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to toggle MFA'
      return false
    }
  }

  /**
   * Initiate TOTP setup — asks the backend to generate a secret and provisioning URI.
   * Returns { provisioning_uri, secret } to display the QR code.
   */
  const setupTotp = async (): Promise<{ provisioning_uri: string; secret: string } | null> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<{ provisioning_uri: string; secret: string }>>(
        '/api/profile/mfa/setup',
        {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json', ...xsrfHeaders() },
        },
      )
      return response.data
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to initiate TOTP setup'
      return null
    }
  }

  /**
   * Confirm TOTP setup by submitting the 6-digit code from the authenticator app.
   * On success the backend stores the secret and enables TOTP for the user.
   */
  const verifyTotpSetup = async (code: string): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/mfa/setup/verify', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: { code },
      })
      profile.value = response.data
      if (auth.user) auth.user.totp_enabled = true
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'الرمز غير صحيح أو انتهت صلاحيته'
      return false
    }
  }

  /**
   * Disable TOTP — requires the user to confirm with their current authenticator code.
   */
  const disableTotp = async (code: string): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/mfa/disable', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: { code },
      })
      profile.value = response.data
      if (auth.user) auth.user.totp_enabled = false
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'رمز التحقق غير صحيح'
      return false
    }
  }

  /**
   * Disable TOTP using password as fallback — for when the user cannot access their authenticator app.
   */
  const disableTotpWithPassword = async (password: string): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>(
        '/api/profile/mfa/disable-with-password',
        {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xsrfHeaders(),
          },
          body: { password },
        },
      )
      profile.value = response.data
      if (auth.user) auth.user.totp_enabled = false
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'كلمة المرور غير صحيحة'
      return false
    }
  }

  const setPin = async (newPin: string, currentPin?: string): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/pin', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: { new_pin: newPin, ...(currentPin ? { current_pin: currentPin } : {}) },
      })
      profile.value = response.data
      if (auth.user) auth.user.pin_enabled = true
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'تعذّر حفظ رمز PIN'
      return false
    }
  }

  const disablePin = async (currentPin: string): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AuthUser>>('/api/profile/pin', {
        method: 'DELETE',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: { current_pin: currentPin },
      })
      profile.value = response.data
      if (auth.user) auth.user.pin_enabled = false
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'تعذّر تعطيل رمز PIN'
      return false
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
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...xsrfHeaders(),
        },
        body: data,
      })
      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to change password'
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    profile,
    loading,
    error,
    fetchProfile,
    updateProfile,
    updateAvatar,
    toggleMfa,
    setupTotp,
    verifyTotpSetup,
    disableTotp,
    disableTotpWithPassword,
    setPin,
    disablePin,
    changePassword,
  }
}
