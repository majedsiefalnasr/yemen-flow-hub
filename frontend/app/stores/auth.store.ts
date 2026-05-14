import { defineStore } from 'pinia'
import type { AuthUser, ApiResponse } from '../types/models'
import { UserRole } from '../types/enums'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    isAuthenticated: false,
  }),

  getters: {
    currentRole: (state): UserRole | null =>
      state.user?.role ?? null,

    isBankUser: (state): boolean =>
      state.user?.role != null && [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.SWIFT_OFFICER,
      ].includes(state.user.role),

    isCbyUser: (state): boolean =>
      state.user?.role != null && [
        UserRole.SUPPORT_COMMITTEE,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ].includes(state.user.role),
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      const response = await $fetch<ApiResponse<AuthUser>>('/api/auth/login', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: { email, password },
      })

      this.user = response.data
      this.isAuthenticated = true
    },

    async logout(): Promise<void> {
      const config = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      try {
        await $fetch('/api/auth/logout', {
          method: 'POST',
          baseURL,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
      }
      catch {
        // Always clear local state, even on network failure
      }
      finally {
        this.user = null
        this.isAuthenticated = false
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
        this.user = response.data
        this.isAuthenticated = true
      }
      catch {
        this.user = null
        this.isAuthenticated = false
      }
    },
  },
})
