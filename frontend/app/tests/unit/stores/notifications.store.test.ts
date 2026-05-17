import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const mockFetchUnreadCount = vi.fn()
const mockUnreadCount = { value: 0 }

vi.mock('../../../composables/useNotifications', () => ({
  useNotifications: () => ({
    fetchUnreadCount: mockFetchUnreadCount,
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
