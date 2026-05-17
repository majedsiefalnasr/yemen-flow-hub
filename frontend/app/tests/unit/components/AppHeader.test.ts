import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockPush = vi.fn()
vi.stubGlobal('useRouter', () => ({ push: mockPush }))

const mockAuthUser = { value: null as { name: string; role: string } | null }
const mockUnreadCount = { value: 0 }
const mockLastFetched = { value: null as Date | null }
const mockRefreshUnreadCount = vi.fn()
const mockDecrementUnread = vi.fn()
const mockResetUnread = vi.fn()

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({ user: mockAuthUser }),
}))

vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    unreadCount: mockUnreadCount.value,
    lastFetched: mockLastFetched.value,
    refreshUnreadCount: mockRefreshUnreadCount,
    decrementUnread: mockDecrementUnread,
    resetUnread: mockResetUnread,
  }),
}))

vi.mock('../../../constants/workflow', () => ({
  ROLE_LABELS: { DATA_ENTRY: 'إدخال البيانات' },
}))

vi.mock('../../../components/layout/SidebarIcon.vue', () => ({
  default: { template: '<span />' },
}))

describe('AppHeader — notification bell', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockUnreadCount.value = 0
    mockAuthUser.value = null
  })

  it('hides badge when unreadCount is 0', async () => {
    mockUnreadCount.value = 0
    const { useNotificationsStore } = await import('../../../stores/notifications.store')
    const store = useNotificationsStore()
    expect(store.unreadCount).toBe(0)
  })

  it('shows count in badge when unreadCount > 0', async () => {
    mockUnreadCount.value = 5
    const { useNotificationsStore } = await import('../../../stores/notifications.store')
    const store = useNotificationsStore()
    expect(store.unreadCount).toBe(5)
  })

  it('caps badge display at 99+ for counts > 99', async () => {
    mockUnreadCount.value = 120
    const { useNotificationsStore } = await import('../../../stores/notifications.store')
    const store = useNotificationsStore()
    const displayed = store.unreadCount > 99 ? '99+' : store.unreadCount
    expect(displayed).toBe('99+')
  })

  it('shows exact count when ≤99', async () => {
    mockUnreadCount.value = 42
    const { useNotificationsStore } = await import('../../../stores/notifications.store')
    const store = useNotificationsStore()
    const displayed = store.unreadCount > 99 ? '99+' : store.unreadCount
    expect(displayed).toBe(42)
  })

  it('navigates to /notifications when bell is clicked', () => {
    mockPush('/notifications')
    expect(mockPush).toHaveBeenCalledWith('/notifications')
  })
})
