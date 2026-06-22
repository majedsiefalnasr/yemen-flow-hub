import { defineStore } from 'pinia'
import type {
  AuthMeData,
  AuthUser,
  ApiResponse,
  ScreenPermissions,
  UserPreferences,
} from '../types/models'
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

interface PasswordRecoveryResponseData {
  success: boolean
  message: string
  data: Record<string, never>
}

const ACCESS_TOKEN_STORAGE_KEY = 'yfh-api-token'
const LOGOUT_IN_PROGRESS_STORAGE_KEY = 'yfh-logout-in-progress'

function markLogoutInProgress(value: boolean): void {
  if (!import.meta.client) return
  if (value) {
    sessionStorage.setItem(LOGOUT_IN_PROGRESS_STORAGE_KEY, '1')
  } else {
    sessionStorage.removeItem(LOGOUT_IN_PROGRESS_STORAGE_KEY)
  }
}

function clearAuthState(store: {
  user: AuthUser | null
  isAuthenticated: boolean
  isLoggingOut?: boolean
  screenPermissions?: ScreenPermissions
  capabilities?: Record<string, boolean>
}) {
  store.user = null
  store.isAuthenticated = false
  if ('isLoggingOut' in store) {
    store.isLoggingOut = false
  }
  if ('screenPermissions' in store) {
    store.screenPermissions = {}
  }
  if ('capabilities' in store) {
    store.capabilities = {}
  }

  if (import.meta.client) {
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
  if (!import.meta.client || !user?.email) return
  const variant = user.avatar_variant
  if (typeof variant !== 'string') return
  if (!(AVATAR_VARIANTS as readonly string[]).includes(variant)) return
  persistUserAvatar(user.email, { variant: variant as AvatarVariant })
}

function applyAuthMe(
  store: {
    user: AuthUser | null
    screenPermissions: ScreenPermissions
    capabilities: Record<string, boolean>
  },
  data: AuthMeData | AuthUser,
): AuthUser {
  if (!('user' in data)) {
    store.screenPermissions = {}
    store.capabilities = {}
    return data
  }

  // Guard against a payload that omits/nulls these — without the fallback,
  // `can()` would do `undefined[screen]` and throw, locking out every guarded
  // page instead of just hiding gated controls.
  store.screenPermissions = data.screen_permissions ?? {}
  store.capabilities = data.capabilities ?? {}

  return {
    ...data.user,
    organization: data.organization,
    team: data.team,
    identity_role: data.role,
    bank: data.bank,
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    isAuthenticated: false,
    isLoggingOut: false,
    userPreferences: null as UserPreferences | null,
    screenPermissions: {} as ScreenPermissions,
    capabilities: {} as Record<string, boolean>,
  }),

  getters: {
    currentRole: (state): UserRole | null => state.user?.role ?? null,

    isBankUser: (state): boolean =>
      state.user?.role != null &&
      [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.BANK_ADMIN,
        UserRole.SWIFT_OFFICER,
      ].includes(state.user.role),

    isCbyUser: (state): boolean =>
      state.user?.role != null &&
      [
        UserRole.SUPPORT_COMMITTEE,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ].includes(state.user.role),

    isCbyAdmin: (state): boolean => state.user?.role === UserRole.CBY_ADMIN,

    preferredLanguage: (state): string => state.userPreferences?.language ?? 'ar',

    preferredDashboardView: (state): string => state.userPreferences?.dashboard_view ?? 'normal',

    preferredTableDensity: (state): string => state.userPreferences?.table_density ?? 'normal',

    preferredPageSize: (state): number => state.userPreferences?.page_size ?? 25,

    canAccessScreen:
      (state) =>
      (screen: string, capability: import('../types/models').ScreenCapability = 'VIEW'): boolean =>
        state.screenPermissions[screen]?.includes(capability) ?? false,
  },

  actions: {
    getAccessToken(): string | null {
      if (!import.meta.client) return null
      return localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY)
    },

    getAuthorizationHeader(): string | null {
      const token = this.getAccessToken()
      return token ? `Bearer ${token}` : null
    },

    persistAuthMode(data: { mode?: 'cookie' | 'token'; token?: string | null }) {
      if (!import.meta.client) return

      if (data.mode === 'token' && data.token) {
        localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, data.token)
      } else {
        localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY)
      }
    },

    getXsrfToken(): string | null {
      if (!import.meta.client) return null
      const raw = document.cookie
        .split(';')
        .map((cookie) => cookie.trim())
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=')

      return raw ? decodeURIComponent(raw) : null
    },

    async login(
      email: string,
      password: string,
    ): Promise<
      | {
          requiresMfa: true
          email: string
          challengeId: string
          roleLabel?: string
        }
      | undefined
    > {
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
          throw {
            statusCode: 500,
            data: { success: false, message: 'تعذّر بدء جلسة التحقق. أعد المحاولة.' },
          }
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
        throw {
          statusCode: 403,
          data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' },
        }
      }

      this.user = response.data.user!
      this.isAuthenticated = true
      this.isLoggingOut = false
      markLogoutInProgress(false)
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (import.meta.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async requestPasswordRecovery(email: string): Promise<string> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch('/sanctum/csrf-cookie', {
        baseURL,
        credentials: 'include',
      })
      const xsrfToken = this.getXsrfToken()

      const response = await $fetch<PasswordRecoveryResponseData>('/api/auth/password/forgot', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: { email },
      })

      return response.message
    },

    async verifyPasswordRecoveryCode(email: string, otp: string): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch('/sanctum/csrf-cookie', {
        baseURL,
        credentials: 'include',
      })
      const xsrfToken = this.getXsrfToken()

      await $fetch('/api/auth/password/verify', {
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
    },

    async resetPasswordWithOtp(
      email: string,
      otp: string,
      password: string,
      passwordConfirmation: string,
    ): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      await $fetch('/sanctum/csrf-cookie', {
        baseURL,
        credentials: 'include',
      })
      const xsrfToken = this.getXsrfToken()

      await $fetch('/api/auth/password/reset', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: {
          email,
          otp,
          password,
          password_confirmation: passwordConfirmation,
        },
      })
    },

    async changeTemporaryPassword(password: string, passwordConfirmation: string): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      const xsrfToken = this.getXsrfToken()
      const authHeader = this.getAuthorizationHeader()

      const response = await $fetch<ApiResponse<AuthUser>>(
        '/api/profile/change-temporary-password',
        {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(authHeader ? { Authorization: authHeader } : {}),
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
          },
          body: {
            password,
            password_confirmation: passwordConfirmation,
          },
        },
      )

      this.user = response.data
      this.isAuthenticated = true
      this.isLoggingOut = false
      syncAvatarCache(this.user)
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
        throw {
          statusCode: 403,
          data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' },
        }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.isLoggingOut = false
      markLogoutInProgress(false)
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (import.meta.client) {
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
        throw {
          statusCode: 403,
          data: { success: false, message: 'حساب العرض التوضيحي غير مفعل.' },
        }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.isLoggingOut = false
      markLogoutInProgress(false)
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (import.meta.client) {
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
        throw {
          statusCode: 403,
          data: { success: false, message: 'حسابك موقوف. يرجى التواصل مع المسؤول.' },
        }
      }

      this.user = response.data.user
      this.isAuthenticated = true
      this.isLoggingOut = false
      markLogoutInProgress(false)
      this.persistAuthMode({ mode: response.data.mode, token: response.data.token })
      syncAvatarCache(this.user)
      if (import.meta.client) {
        localStorage.setItem('yfh-authenticated', '1')
      }
    },

    async logout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      this.isLoggingOut = true
      markLogoutInProgress(true)

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
      } catch {
        // Always clear local state, even on network failure
      } finally {
        clearAuthState(this)
      }
    },

    async fetchUser(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const authHeader = this.getAuthorizationHeader()
        const response = await $fetch<ApiResponse<AuthMeData | AuthUser>>('/api/auth/me', {
          baseURL,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            ...(authHeader ? { Authorization: authHeader } : {}),
          },
        })
        const user = applyAuthMe(this, response.data)
        if (!user.is_active) {
          clearAuthState(this)
          return
        }
        this.user = user
        this.isAuthenticated = true
        this.isLoggingOut = false
        markLogoutInProgress(false)
        syncAvatarCache(this.user)
      } catch {
        clearAuthState(this)
      }
    },

    async forceLogout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string
      this.isLoggingOut = true
      markLogoutInProgress(true)

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
      } catch {
        // Clear local state even if the network call fails
      } finally {
        clearAuthState(this)
        await navigateTo('/login?reason=inactivity')
      }
    },

    async extendSession(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        const response = await $fetch<ApiResponse<AuthMeData | AuthUser>>('/api/auth/me', {
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        const user = applyAuthMe(this, response.data)
        if (user.is_active) {
          this.user = user
          this.isAuthenticated = true
          syncAvatarCache(this.user)
        }
      } catch {
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
      } catch {
        this.userPreferences = null
      }
    },

    setUserPreferences(preferences: UserPreferences): void {
      this.userPreferences = preferences
    },
  },
})
