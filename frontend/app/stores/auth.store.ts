import { defineStore } from 'pinia'
import type { AuthUser, ApiResponse, UserPreferences } from '../types/models'
import { UserRole } from '../types/enums'

interface LoginResponseData {
  user?: AuthUser
  token: string | null
  token_type: string | null
  mode: 'cookie' | 'token'
  requires_mfa: boolean
  email?: string
}

interface VerifyOtpResponseData {
  user: AuthUser
  token: string | null
  token_type: string | null
  mode: 'cookie' | 'token'
  requires_mfa: false
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    isAuthenticated: false,
    userPreferences: null as UserPreferences | null,
  }),

  getters: {
    currentRole: (state): UserRole | null =>
      state.user?.role ?? null,

    isBankUser: (state): boolean =>
      state.user?.role != null && [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.BANK_ADMIN,
        UserRole.SWIFT_OFFICER,
      ].includes(state.user.role),

    isCbyUser: (state): boolean =>
      state.user?.role != null && [
        UserRole.SUPPORT_COMMITTEE,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ].includes(state.user.role),

    isCbyAdmin: (state): boolean =>
      state.user?.role === UserRole.CBY_ADMIN,

    preferredLanguage: (state): string =>
      state.userPreferences?.language ?? 'ar',

    preferredDashboardView: (state): string =>
      state.userPreferences?.dashboard_view ?? 'normal',

    preferredTableDensity: (state): string =>
      state.userPreferences?.table_density ?? 'normal',

    preferredPageSize: (state): number =>
      state.userPreferences?.page_size ?? 25,
  },

  actions: {
    getXsrfToken(): string | null {
      if (!process.client) return null
      const raw = document.cookie
        .split(';')
        .map(cookie => cookie.trim())
        .find(cookie => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=')

      return raw ? decodeURIComponent(raw) : null
    },

    async login(email: string, password: string): Promise<{ requiresMfa: true; email: string } | void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      // Sanctum SPA requires CSRF cookie initialization before the login POST
      await $fetch('/sanctum/csrf-cookie', {
        baseURL,
        credentials: 'include',
      })

      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<ApiResponse<LoginResponseData>>('/api/auth/login', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { email, password },
      })

      if (response.data.requires_mfa) {
        return { requiresMfa: true, email: response.data.email ?? email }
      }

      if (!response.data.user?.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' } }
      }

      this.user = response.data.user!
      this.isAuthenticated = true
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async verifyOtp(email: string, otp: string): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<ApiResponse<VerifyOtpResponseData>>('/api/auth/verify-otp', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { email, otp },
      })

      if (!response.data.user.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' } }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async logout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const xsrfToken = this.getXsrfToken()
        await $fetch('/api/auth/logout', {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
          },
        })
      }
      catch {
        // Always clear local state, even on network failure
      }
      finally {
        this.user = null
        this.isAuthenticated = false
        if (process.client) {
          localStorage.removeItem('yfh-authenticated')
        }
      }
    },

    async fetchUser(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const response = await $fetch<ApiResponse<AuthUser>>('/api/auth/me', {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        if (!response.data.is_active) {
          this.user = null
          this.isAuthenticated = false
          return
        }
        this.user = response.data
        this.isAuthenticated = true
      }
      catch {
        this.user = null
        this.isAuthenticated = false
        if (process.client) {
          localStorage.removeItem('yfh-authenticated')
        }
      }
    },

    async fetchUserPreferences(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const response = await $fetch<ApiResponse<UserPreferences>>('/api/settings', {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        this.userPreferences = response.data
      }
      catch {
        this.userPreferences = null
      }
    },

    setUserPreferences(preferences: UserPreferences): void {
      this.userPreferences = preferences
    },
  },
})
