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

  // FE-002: refreshUnreadCount must read the dedicated notifications/unread-count
  // endpoint (useNotifications().fetchUnreadCount), not fetch the full list page
  // just to derive a count in JS — that duplicates the cheap count endpoint with
  // a full paginated list fetch every time a badge needs to update.
  it('calls the dedicated unread-count endpoint, not the full list fetch', async () => {
    mockUnreadCount.value = 4
    mockFetchUnreadCount.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(mockFetchUnreadCount).toHaveBeenCalledTimes(1)
    expect(mockFetchNotifications).not.toHaveBeenCalled()
  })

  it('adopts the count returned by the dedicated endpoint and sets lastFetched', async () => {
    mockUnreadCount.value = 7
    mockFetchUnreadCount.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(store.unreadCount).toBe(7)
    expect(store.lastFetched).toBeInstanceOf(Date)
  })

  it('does not overwrite already-loaded list items', async () => {
    mockUnreadCount.value = 2
    mockFetchUnreadCount.mockResolvedValueOnce(undefined)

    const store = useNotificationsStore()
    store.items = [{ id: '1', read_at: null }] as any
    await store.refreshUnreadCount()

    expect(store.items).toEqual([{ id: '1', read_at: null }])
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

    store.setItems(
      [
        { id: '1', read_at: null },
        { id: '2', read_at: '2026-06-01T10:00:00.000Z' },
        { id: '3', read_at: '2026-06-01T10:05:00.000Z' },
      ] as any,
      15,
    )

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
