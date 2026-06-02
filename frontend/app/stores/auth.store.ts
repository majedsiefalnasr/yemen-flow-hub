import { defineStore } from 'pinia'
import type { AuthUser, ApiResponse, UserPreferences } from '../types/models'
import { ROLE_LABELS } from '../constants/workflow'
import { UserRole } from '../types/enums'
import {
  AVATAR_VARIANTS,
  persistUserAvatar,
  type AvatarVariant,
} from '../composables/useUserAvatar'

interface LoginResponseData {
  user?: AuthUser
  token: string | null
  token_type: string | null
  mode: 'cookie' | 'token'
  requires_mfa: boolean
  email?: string
  challenge_id?: string
  role_label?: string
}

interface VerifyOtpResponseData {
  user: AuthUser
  token: string | null
  token_type: string | null
  mode: 'cookie' | 'token'
  requires_mfa: false
}

const ACCESS_TOKEN_STORAGE_KEY = 'yfh-api-token'

function clearAuthState(store: { user: AuthUser | null; isAuthenticated: boolean }) {
  store.user = null
  store.isAuthenticated = false

  if (process.client) {
    localStorage.removeItem('yfh-authenticated')
    localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY)
  }
}

function resolveMfaRoleLabel(data: LoginResponseData): string | undefined {
  if (data.role_label) {
    return data.role_label
  }

  return data.user ? (ROLE_LABELS[data.user.role] ?? data.user.role) : undefined
}

/**
 * Mirror the authoritative avatar variant from the backend into the per-identity
 * localStorage cache. This keeps surfaces that read the cache directly — most
 * notably the saved-account cards on the login page — in lockstep with the
 * server even when the user (or an admin) changed the variant from a different
 * device or session.
 */
function syncAvatarCache(user: AuthUser | undefined | null): void {
  if (!process.client || !user?.email) return
  const variant = user.avatar_variant
  if (typeof variant !== 'string') return
  if (!(AVATAR_VARIANTS as readonly string[]).includes(variant)) return
  persistUserAvatar(user.email, { variant: variant as AvatarVariant })
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
    getAccessToken(): string | null {
      if (!process.client) return null
      return localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY)
    },

    getAuthorizationHeader(): string | null {
      const token = this.getAccessToken()
      return token ? `Bearer ${token}` : null
    },

    persistAuthMode(data: { mode?: 'cookie' | 'token'; token?: string | null }) {
      if (!process.client) return

      if (data.mode === 'token' && data.token) {
        localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, data.token)
      }
      else {
        localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY)
      }
    },

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

    async login(email: string, password: string): Promise<{ requiresMfa: true; email: string; challengeId: string; roleLabel?: string } | void> {
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
        const challengeId = response.data.challenge_id
        if (!challengeId) {
          throw { statusCode: 500, data: { success: false, message: 'تعذر بدء جلسة التحقق. يرجى إعادة المحاولة.' } }
        }

        clearAuthState(this)
        const roleLabel = resolveMfaRoleLabel(response.data)
        return {
          requiresMfa: true,
          email: response.data.email ?? email,
          challengeId,
          ...(roleLabel ? { roleLabel } : {}),
        }
      }

      if (!response.data.user?.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' } }
      }

      this.user = response.data.user!
      this.isAuthenticated = true
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async loginWithPin(email: string, pin: string): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      await $fetch('/sanctum/csrf-cookie', {
        baseURL,
        credentials: 'include',
      })

      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<ApiResponse<LoginResponseData>>('/api/auth/login-pin', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { email, pin },
      })

      if (!response.data.user?.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' } }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async switchDemoRole(role: UserRole): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<ApiResponse<LoginResponseData>>('/api/auth/switch-demo-role', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { role },
      })

      if (!response.data.user?.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حساب العرض التوضيحي غير مفعل.' } }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async verifyOtp(email: string, otp: string, challengeId: string): Promise<void> {
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
        body: { email, otp, challenge_id: challengeId },
      })

      if (!response.data.user.is_active) {
        throw { statusCode: 403, data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' } }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (process.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async logout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const xsrfToken = this.getXsrfToken()
        const authHeader = this.getAuthorizationHeader()
        await $fetch('/api/auth/logout', {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            ...(authHeader ? { Authorization: authHeader } : {}),
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
          },
        })
      }
      catch {
        // Always clear local state, even on network failure
      }
      finally {
        clearAuthState(this)
      }
    },

    async fetchUser(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const authHeader = this.getAuthorizationHeader()
        const response = await $fetch<ApiResponse<AuthUser>>('/api/auth/me', {
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            ...(authHeader ? { Authorization: authHeader } : {}),
          },
        })
        if (!response.data.is_active) {
          clearAuthState(this)
          return
        }
        this.user = response.data
        this.isAuthenticated = true
        syncAvatarCache(this.user)
      }
      catch {
        clearAuthState(this)
      }
    },

    async forceLogout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const xsrfToken = this.getXsrfToken()
        const authHeader = this.getAuthorizationHeader()
        await $fetch('/api/auth/logout', {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            ...(authHeader ? { Authorization: authHeader } : {}),
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
          },
        })
      }
      catch {
        // Clear local state even if the network call fails
      }
      finally {
        clearAuthState(this)
        await navigateTo('/login?reason=inactivity')
      }
    },

    async extendSession(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const response = await $fetch<ApiResponse<AuthUser>>('/api/auth/me', {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        if (response.data.is_active) {
          this.user = response.data
          this.isAuthenticated = true
          syncAvatarCache(this.user)
        }
      }
      catch {
        // Session may have already expired — let inactivity timer handle it
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
