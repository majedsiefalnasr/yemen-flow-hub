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
  // Approval cycle fields
  support_committee_size: number
  executive_committee_size: number
  minimum_quorum: number
  review_timeout_hours: number
  secret_voting: boolean
  director_tiebreak: boolean
}

export interface SmtpSettings {
  host: string
  port: number
  username: string
  password: string
  template: string
}

export interface SecurityPolicies {
  mfa_required: boolean
  password_expiry_90_days: boolean
  lockout_after_5_attempts: boolean
  encrypt_uploads_aes256: boolean
  log_all_audit: boolean
  allow_external_access: boolean
}

export const useAdminSettings = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  const settings = ref<AdminSettings | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pendingKeys = ref<Set<string>>(new Set())
  const smtpSettings = ref<SmtpSettings | null>(null)
  const securityPolicies = ref<SecurityPolicies | null>(null)

  const fetchSettings = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ApiResponse<AdminSettings & SecurityPolicies>>('/api/admin/settings', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      const data = response.data
      settings.value = data as AdminSettings
      securityPolicies.value = {
        mfa_required: data.mfa_required ?? false,
        password_expiry_90_days: data.password_expiry_90_days ?? false,
        lockout_after_5_attempts: data.lockout_after_5_attempts ?? false,
        encrypt_uploads_aes256: data.encrypt_uploads_aes256 ?? false,
        log_all_audit: data.log_all_audit ?? true,
        allow_external_access: data.allow_external_access ?? false,
      }
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load admin settings'
      settings.value = null
    }
    finally {
      loading.value = false
    }
  }

  const fetchSmtpSettings = async () => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<SmtpSettings>>('/api/admin/settings/smtp', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      smtpSettings.value = response.data
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load SMTP settings'
    }
  }

  const updateSmtpSettings = async (data: SmtpSettings): Promise<boolean> => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<SmtpSettings>>('/api/admin/settings/smtp', {
        method: 'PUT',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: data,
      })
      smtpSettings.value = response.data
      return true
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to update SMTP settings'
      return false
    }
  }

  const fetchSecurityPolicies = async () => {
    error.value = null
    try {
      const response = await $fetch<ApiResponse<AdminSettings & SecurityPolicies>>('/api/admin/settings', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      const data = response.data
      securityPolicies.value = {
        mfa_required: data.mfa_required ?? false,
        password_expiry_90_days: data.password_expiry_90_days ?? false,
        lockout_after_5_attempts: data.lockout_after_5_attempts ?? false,
        encrypt_uploads_aes256: data.encrypt_uploads_aes256 ?? false,
        log_all_audit: data.log_all_audit ?? true,
        allow_external_access: data.allow_external_access ?? false,
      }
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load security policies'
    }
  }

  const updateSecurityPolicy = async (key: string, value: boolean): Promise<boolean> => {
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
      if (securityPolicies.value) {
        securityPolicies.value = { ...securityPolicies.value, [key]: response.data.value }
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
    smtpSettings,
    securityPolicies,
    fetchSettings,
    fetchSmtpSettings,
    updateSmtpSettings,
    fetchSecurityPolicies,
    updateSecurityPolicy,
    updateSetting,
    resetSetting,
  }
}
