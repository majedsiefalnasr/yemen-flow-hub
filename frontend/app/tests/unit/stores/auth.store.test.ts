import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { UserRole } from '../../../types/enums'
import type { AuthUser } from '../../../types/models'

// --- Mock Nuxt globals ---
const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000', demoEnabled: true },
}))
// navigateTo is only available in Nuxt context — stub for test isolation
vi.stubGlobal('navigateTo', vi.fn())

// Import AFTER globals are mocked
const { useAuthStore } = await import('../../../stores/auth.store')

const DEMO_USER: AuthUser = {
  id: 1,
  name: 'Ahmed Al-Yamani',
  email: 'ahmed@bank.ye',
  role: UserRole.DATA_ENTRY,
  bank_id: 1,
  bank_name_ar: 'بنك عدن',
  bank_name_en: 'Aden Bank',
  is_active: true,
}

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetch.mockReset()
  })

  describe('initial state', () => {
    it('starts unauthenticated with no user', () => {
      const store = useAuthStore()
      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })

    it('currentRole getter returns null when not authenticated', () => {
      const store = useAuthStore()
      expect(store.currentRole).toBeNull()
    })
  })

  describe('password recovery CSRF', () => {
    it('initializes Sanctum CSRF and sends the token for every recovery mutation', async () => {
      mockFetch.mockResolvedValue({
        success: true,
        message: 'If this email exists, a recovery code has been sent.',
        data: {},
      })

      const store = useAuthStore()
      vi.spyOn(store, 'getXsrfToken').mockReturnValue('recovery-token')
      await store.requestPasswordRecovery('user@example.gov.ye')
      await store.verifyPasswordRecoveryCode('user@example.gov.ye', '123456')
      await store.resetPasswordWithOtp(
        'user@example.gov.ye',
        '123456',
        'NewPassword123',
        'NewPassword123',
      )

      for (const path of [
        '/api/auth/password/forgot',
        '/api/auth/password/verify',
        '/api/auth/password/reset',
      ]) {
        expect(mockFetch).toHaveBeenCalledWith(
          path,
          expect.objectContaining({
            headers: expect.objectContaining({
              'X-XSRF-TOKEN': 'recovery-token',
            }),
          }),
        )
      }
      expect(mockFetch.mock.calls.filter(([path]) => path === '/sanctum/csrf-cookie')).toHaveLength(
        3,
      )
    })
  })

  describe('switchDemoUser', () => {
    it('logs in as the target user and persists auth state', async () => {
      const targetUser: AuthUser = {
        id: 42,
        name: 'Nada Al-Kibsi',
        email: 'exec2@cby.gov.ye',
        role: UserRole.EXECUTIVE_MEMBER,
        bank_id: null,
        bank_name_ar: null,
        bank_name_en: null,
        is_active: true,
      }

      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'Login successful.',
        data: {
          user: targetUser,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })
      mockFetch.mockResolvedValueOnce({
        success: true,
        data: {
          user: targetUser,
          screen_permissions: {},
          capabilities: {},
        },
      })

      const store = useAuthStore()
      await store.switchDemoUser(42)

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/auth/switch-demo-user',
        expect.objectContaining({
          method: 'POST',
          body: { user_id: 42 },
        }),
      )
      expect(store.user).toEqual(targetUser)
      expect(store.isAuthenticated).toBe(true)
    })

    it('throws when the returned user is inactive', async () => {
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'Login successful.',
        data: {
          user: { ...DEMO_USER, is_active: false },
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await expect(store.switchDemoUser(1)).rejects.toMatchObject({ statusCode: 403 })
    })

    it('throws when demo switching is disabled in runtime config', async () => {
      vi.stubGlobal('useRuntimeConfig', () => ({
        public: { apiBase: 'http://localhost:8000', demoEnabled: false },
      }))

      const store = useAuthStore()
      await expect(store.switchDemoUser(1)).rejects.toMatchObject({ statusCode: 404 })
      expect(mockFetch).not.toHaveBeenCalled()
    })
  })

  describe('login()', () => {
    it('sets user and isAuthenticated on successful login', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: DEMO_USER,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.isAuthenticated).toBe(true)
      expect(store.user).toEqual(DEMO_USER)
    })

    it('currentRole returns the user role after login', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: DEMO_USER,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.currentRole).toBe(UserRole.DATA_ENTRY)
    })

    it('throws when API returns an error', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockRejectedValueOnce({
        data: { success: false, message: 'بيانات الدخول غير صحيحة' },
      })

      const store = useAuthStore()
      await expect(store.login('bad@bank.ye', 'wrong')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })

    it('throws when user is_active is false', async () => {
      const inactiveUser: AuthUser = { ...DEMO_USER, is_active: false }
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: inactiveUser,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await expect(store.login('ahmed@bank.ye', 'password123')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })

    it('returns requiresMfa signal when MFA is required', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OTP sent',
        data: { requires_mfa: true, email: 'ahmed@bank.ye', challenge_id: 'challenge-1' },
      })

      const store = useAuthStore()
      const result = await store.login('ahmed@bank.ye', 'password123')

      expect(result).toEqual({
        requiresMfa: true,
        email: 'ahmed@bank.ye',
        challengeId: 'challenge-1',
      })
      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })

    it('returns MFA role label when backend includes it', async () => {
      mockFetch.mockResolvedValueOnce(null)
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OTP sent',
        data: {
          requires_mfa: true,
          email: 'ahmed@bank.ye',
          challenge_id: 'challenge-1',
          role_label: 'مدير النظام',
        },
      })

      const store = useAuthStore()
      const result = await store.login('ahmed@bank.ye', 'password123')

      expect(result).toEqual({
        requiresMfa: true,
        email: 'ahmed@bank.ye',
        challengeId: 'challenge-1',
        roleLabel: 'مدير النظام',
      })
    })

    it('clears stale authenticated state before returning an MFA challenge', async () => {
      mockFetch.mockResolvedValueOnce(null)
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OTP sent',
        data: { requires_mfa: true, email: 'ahmed@bank.ye', challenge_id: 'challenge-1' },
      })

      const store = useAuthStore()
      store.user = DEMO_USER
      store.isAuthenticated = true

      await store.login('ahmed@bank.ye', 'password123')

      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
    })

    it('derives MFA role label from the returned user role when role_label is missing', async () => {
      mockFetch.mockResolvedValueOnce(null)
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OTP sent',
        data: {
          requires_mfa: true,
          email: 'ahmed@bank.ye',
          challenge_id: 'challenge-1',
          user: DEMO_USER,
        },
      })

      const store = useAuthStore()
      const result = await store.login('ahmed@bank.ye', 'password123')

      expect(result).toEqual({
        requiresMfa: true,
        email: 'ahmed@bank.ye',
        challengeId: 'challenge-1',
        roleLabel: 'إدخال البيانات',
      })
    })
  })

  describe('verifyOtp()', () => {
    it('sets user and isAuthenticated on successful OTP verification', async () => {
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: DEMO_USER,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await store.verifyOtp('ahmed@bank.ye', '123456', 'challenge-1')

      expect(store.isAuthenticated).toBe(true)
      expect(store.user).toEqual(DEMO_USER)
    })

    it('throws when OTP is invalid (API error)', async () => {
      mockFetch.mockRejectedValueOnce({
        data: { success: false, message: 'الرمز المدخل غير صحيح أو منتهي الصلاحية.' },
      })

      const store = useAuthStore()
      await expect(store.verifyOtp('ahmed@bank.ye', '000000', 'challenge-1')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })

    it('throws when user is inactive after OTP verification', async () => {
      const inactiveUser: AuthUser = { ...DEMO_USER, is_active: false }
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: inactiveUser,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await expect(store.verifyOtp('ahmed@bank.ye', '123456', 'challenge-1')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })
  })

  describe('loginWithPin()', () => {
    it('sets user and isAuthenticated on successful PIN login', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: DEMO_USER,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })

      const store = useAuthStore()
      await store.loginWithPin('ahmed@bank.ye', '125812')

      expect(store.isAuthenticated).toBe(true)
      expect(store.user).toEqual(DEMO_USER)
    })

    it('throws and keeps state unauthenticated when PIN login fails', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockRejectedValueOnce({
        data: { message: 'رمز PIN غير صحيح. يرجى المحاولة مرة أخرى.' },
      })

      const store = useAuthStore()
      await expect(store.loginWithPin('ahmed@bank.ye', '000000')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })
  })

  describe('logout()', () => {
    it('clears user and isAuthenticated after logout', async () => {
      // First login
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: { user: DEMO_USER, token: null, token_type: null, mode: 'cookie' },
      })
      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')
      expect(store.isAuthenticated).toBe(true)

      // Then logout
      mockFetch.mockResolvedValueOnce({ success: true, message: 'Logged out' })
      await store.logout()

      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })

    it('clears state even if API call fails', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: { user: DEMO_USER, token: null, token_type: null, mode: 'cookie' },
      })
      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      mockFetch.mockRejectedValueOnce(new Error('Network error'))
      await store.logout()

      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })
  })

  describe('fetchUser()', () => {
    it('hydrates user on successful me request', async () => {
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })

      const store = useAuthStore()
      await store.fetchUser()

      expect(store.isAuthenticated).toBe(true)
      expect(store.user).toEqual(DEMO_USER)
    })

    it('stays unauthenticated on 401 (silent catch)', async () => {
      mockFetch.mockRejectedValueOnce({ statusCode: 401 })

      const store = useAuthStore()
      await store.fetchUser()

      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })

    it('clears auth state when is_active is false', async () => {
      const inactiveUser: AuthUser = { ...DEMO_USER, is_active: false }
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: inactiveUser })

      const store = useAuthStore()
      await store.fetchUser()

      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
    })
  })

  describe('isBankUser / isCbyUser getters', () => {
    it('DATA_ENTRY is a bank user', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: DEMO_USER,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })
      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.isBankUser).toBe(true)
      expect(store.isCbyUser).toBe(false)
    })

    it('CBY_ADMIN is a CBY user', async () => {
      const cbyadmin: AuthUser = { ...DEMO_USER, role: UserRole.CBY_ADMIN, bank_id: null }
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({
        success: true,
        message: 'OK',
        data: {
          user: cbyadmin,
          token: null,
          token_type: null,
          mode: 'cookie',
          requires_mfa: false,
        },
      })
      const store = useAuthStore()
      await store.login('admin@cby.ye', 'password123')

      expect(store.isCbyUser).toBe(true)
      expect(store.isBankUser).toBe(false)
    })
  })
})
