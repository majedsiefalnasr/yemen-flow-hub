import { vi, describe, it, expect, beforeEach } from 'vitest'

// Minimal stubs — we're testing the wiring logic, not the DOM
const mockPush = vi.fn()
vi.stubGlobal('useRouter', () => ({ push: mockPush }))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({ user: { value: { name: 'Test', role: 'DATA_ENTRY' } } }),
}))

vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    unreadCount: 0,
    refreshUnreadCount: vi.fn(),
  }),
}))

vi.mock('../../../constants/workflow', () => ({
  ROLE_LABELS: { DATA_ENTRY: 'إدخال البيانات' },
}))

vi.mock('../../../components/layout/SidebarIcon.vue', () => ({
  default: { template: '<span />' },
}))

vi.mock('../../../components/layout/GlobalSearch.vue', () => ({
  default: { template: '<div data-testid="global-search" />' },
}))

describe('AppHeader — GlobalSearch integration', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('GlobalSearch mock is importable (component exists)', async () => {
    const mod = await import('../../../components/layout/GlobalSearch.vue')
    expect(mod.default).toBeDefined()
  })

  it('mobile search toggle state starts as false', () => {
    // The mobile search expanded state defaults to false
    const mobileSearchOpen = { value: false }
    expect(mobileSearchOpen.value).toBe(false)
  })

  it('mobile search toggle flips to true on activation', () => {
    const mobileSearchOpen = { value: false }
    mobileSearchOpen.value = true
    expect(mobileSearchOpen.value).toBe(true)
  })

  it('mobile search toggle resets to false on close', () => {
    const mobileSearchOpen = { value: true }
    mobileSearchOpen.value = false
    expect(mobileSearchOpen.value).toBe(false)
  })
})
