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
    mockNotifications.value = []
  })

  it('updates unreadCount from loaded notification rows and sets lastFetched', async () => {
    mockNotifications.value = [
      { id: '1', read_at: null },
      { id: '2', read_at: '2026-06-01T10:00:00.000Z' },
      { id: '3', read_at: null },
    ]
    mockFetchNotifications.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(store.unreadCount).toBe(2)
    expect(store.items).toEqual(mockNotifications.value)
    expect(store.lastFetched).toBeInstanceOf(Date)
  })

  it('fetches the first notifications page for badge parity', async () => {
    mockFetchNotifications.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(mockFetchNotifications).toHaveBeenCalledWith(1)
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

  it('fetchRecent updates items and derives unread count from loaded rows', async () => {
    const store = useNotificationsStore()
    mockNotifications.value = [
      { id: '1', read_at: null },
      { id: '2', read_at: '2026-06-01T10:00:00.000Z' },
      { id: '3', read_at: null },
    ]
    mockUnreadCount.value = 3
    mockFetchNotifications.mockResolvedValueOnce(undefined)
    await store.fetchRecent()
    expect(store.items).toEqual(mockNotifications.value)
    expect(store.unreadCount).toBe(2)
  })

  it('setItems derives unread count from loaded rows instead of stale API counts', () => {
    const store = useNotificationsStore()

    store.setItems([
      { id: '1', read_at: null },
      { id: '2', read_at: '2026-06-01T10:00:00.000Z' },
      { id: '3', read_at: '2026-06-01T10:05:00.000Z' },
    ] as any, 15)

    expect(store.unreadCount).toBe(1)
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
