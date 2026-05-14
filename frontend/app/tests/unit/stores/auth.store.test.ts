import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { UserRole } from '../../../types/enums'
import type { AuthUser } from '../../../types/models'

// --- Mock Nuxt globals ---
const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000' },
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

  describe('login()', () => {
    it('sets user and isAuthenticated on successful login', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })

      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.isAuthenticated).toBe(true)
      expect(store.user).toEqual(DEMO_USER)
    })

    it('currentRole returns the user role after login', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })

      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.currentRole).toBe(UserRole.DATA_ENTRY)
    })

    it('throws when API returns an error', async () => {
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockRejectedValueOnce({ data: { success: false, message: 'بيانات الدخول غير صحيحة' } })

      const store = useAuthStore()
      await expect(store.login('bad@bank.ye', 'wrong')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })

    it('throws when user is_active is false', async () => {
      const inactiveUser: AuthUser = { ...DEMO_USER, is_active: false }
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: inactiveUser })

      const store = useAuthStore()
      await expect(store.login('ahmed@bank.ye', 'password123')).rejects.toBeDefined()
      expect(store.isAuthenticated).toBe(false)
    })
  })

  describe('logout()', () => {
    it('clears user and isAuthenticated after logout', async () => {
      // First login
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })
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
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })
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
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })
      const store = useAuthStore()
      await store.login('ahmed@bank.ye', 'password123')

      expect(store.isBankUser).toBe(true)
      expect(store.isCbyUser).toBe(false)
    })

    it('CBY_ADMIN is a CBY user', async () => {
      const cbyadmin: AuthUser = { ...DEMO_USER, role: UserRole.CBY_ADMIN, bank_id: null }
      mockFetch.mockResolvedValueOnce(null) // CSRF cookie prefetch
      mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: cbyadmin })
      const store = useAuthStore()
      await store.login('admin@cby.ye', 'password123')

      expect(store.isCbyUser).toBe(true)
      expect(store.isBankUser).toBe(false)
    })
  })
})
