import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const mockFetchUnreadCount = vi.fn()
const mockFetchNotifications = vi.fn()
const mockMarkAllRead = vi.fn()
const mockUnreadCount = { value: 0 }
const mockNotifications = { value: [] as Array<{ id: string; read_at: string | null }> }

vi.mock('../../../composables/useNotifications', () => ({
  useNotifications: () => ({
    fetchUnreadCount: mockFetchUnreadCount,
    fetchNotifications: mockFetchNotifications,
    markAllRead: mockMarkAllRead,
    notifications: mockNotifications,
    unreadCount: mockUnreadCount,
  }),
}))

const { useNotificationsStore } = await import('../../../stores/notifications.store')

describe('useNotificationsStore — state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    mockUnreadCount.value = 0
  })

  it('starts with unreadCount 0 and lastFetched null', () => {
    const store = useNotificationsStore()
    expect(store.unreadCount).toBe(0)
    expect(store.items).toEqual([])
    expect(store.lastFetched).toBeNull()
  })
})

describe('useNotificationsStore — refreshUnreadCount', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    mockUnreadCount.value = 0
  })

  it('updates unreadCount from composable and sets lastFetched', async () => {
    mockFetchUnreadCount.mockResolvedValueOnce(undefined)
    mockUnreadCount.value = 7

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(store.unreadCount).toBe(7)
    expect(store.lastFetched).toBeInstanceOf(Date)
  })

  it('calls fetchUnreadCount', async () => {
    mockFetchUnreadCount.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(mockFetchUnreadCount).toHaveBeenCalledOnce()
  })
})

describe('useNotificationsStore — decrementUnread', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('decrements unreadCount by 1', () => {
    const store = useNotificationsStore()
    store.unreadCount = 5
    store.decrementUnread()
    expect(store.unreadCount).toBe(4)
  })

  it('does not go below 0', () => {
    const store = useNotificationsStore()
    store.unreadCount = 0
    store.decrementUnread()
    expect(store.unreadCount).toBe(0)
  })
})

describe('useNotificationsStore — recent items', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('fetchRecent updates items and unread count', async () => {
    const store = useNotificationsStore()
    mockNotifications.value = [{ id: '1', read_at: null }]
    mockUnreadCount.value = 3
    mockFetchNotifications.mockResolvedValueOnce(undefined)
    await store.fetchRecent()
    expect(store.items).toEqual([{ id: '1', read_at: null }])
    expect(store.unreadCount).toBe(3)
  })

  it('markAllRead sets unread count to 0 and marks items', async () => {
    const store = useNotificationsStore()
    store.items = [{ id: '1', read_at: null }] as any
    store.unreadCount = 5
    mockMarkAllRead.mockResolvedValueOnce(true)
    await store.markAllRead()
    expect(store.unreadCount).toBe(0)
    expect(store.items[0]?.read_at).not.toBeNull()
  })
})

describe('useNotificationsStore — resetUnread', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sets unreadCount to 0', () => {
    const store = useNotificationsStore()
    store.unreadCount = 10
    store.resetUnread()
    expect(store.unreadCount).toBe(0)
  })
})
