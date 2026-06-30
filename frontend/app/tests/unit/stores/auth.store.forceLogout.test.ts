import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { UserRole } from '../../../types/enums'
import type { AuthUser } from '../../../types/models'

// --- Mock Nuxt globals ---
const mockFetch = vi.fn()
const mockNavigateTo = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000' },
}))
vi.stubGlobal('navigateTo', mockNavigateTo)

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

describe('useAuthStore — forceLogout()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetch.mockReset()
    mockNavigateTo.mockReset()
  })

  it('clears auth state after forceLogout', async () => {
    mockFetch.mockResolvedValueOnce(null) // logout POST

    const store = useAuthStore()
    store.user = DEMO_USER
    store.isAuthenticated = true

    await store.forceLogout()

    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
  })

  it('navigates to /login?reason=inactivity after forceLogout', async () => {
    mockFetch.mockResolvedValueOnce(null) // logout POST

    const store = useAuthStore()
    store.user = DEMO_USER
    store.isAuthenticated = true

    await store.forceLogout()

    expect(mockNavigateTo).toHaveBeenCalledOnce()
    expect(mockNavigateTo).toHaveBeenCalledWith('/login?reason=inactivity')
  })

  it('still clears state and navigates even if logout API call fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network error'))

    const store = useAuthStore()
    store.user = DEMO_USER
    store.isAuthenticated = true

    await store.forceLogout()

    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(mockNavigateTo).toHaveBeenCalledWith('/login?reason=inactivity')
  })
})

describe('useAuthStore — extendSession()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockFetch.mockReset()
    mockNavigateTo.mockReset()
  })

  it('updates user state when /api/auth/me returns active user', async () => {
    mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: DEMO_USER })

    const store = useAuthStore()
    await store.extendSession()

    expect(store.isAuthenticated).toBe(true)
    expect(store.user).toEqual(DEMO_USER)
  })

  it('does not crash when /api/auth/me request fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network error'))

    const store = useAuthStore()
    await expect(store.extendSession()).resolves.toBeUndefined()
  })

  it('does not set isAuthenticated when user is_active is false', async () => {
    const inactiveUser = { ...DEMO_USER, is_active: false }
    mockFetch.mockResolvedValueOnce({ success: true, message: 'OK', data: inactiveUser })

    const store = useAuthStore()
    await store.extendSession()

    expect(store.isAuthenticated).toBe(false)
  })
})
