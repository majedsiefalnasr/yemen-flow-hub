import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

const mockResetUnread = vi.fn()
let mockStoreUnreadCount = 0

vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    get unreadCount() { return mockStoreUnreadCount },
    set unreadCount(v: number) { mockStoreUnreadCount = v },
    resetUnread: mockResetUnread,
  }),
}))

const NOTIF_UNREAD = {
  id: 'n-1',
  type: 'App\\Notifications\\RequestSubmittedNotification',
  data: { type: 'request_submitted', message: 'طلب جديد', request_id: 1, reference_number: 'YFH-001' },
  read_at: null,
  created_at: '2026-05-17T10:00:00.000000Z',
}
const NOTIF_READ = {
  ...NOTIF_UNREAD,
  id: 'n-2',
  read_at: '2026-05-17T11:00:00.000000Z',
}

const { useNotifications } = await import('../../../composables/useNotifications')

describe('notifications page — fetchNotifications', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockStoreUnreadCount = 0
  })

  it('populates notifications on fetch', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIF_UNREAD, NOTIF_READ],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
      },
    })
    const { notifications, fetchNotifications } = useNotifications()
    await fetchNotifications()
    expect(notifications.value).toHaveLength(2)
  })

  it('shows empty list when no notifications', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    })
    const { notifications, fetchNotifications } = useNotifications()
    await fetchNotifications()
    expect(notifications.value).toHaveLength(0)
  })

  it('sets error when fetch fails', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Unauthorized' } })
    const { error, fetchNotifications } = useNotifications()
    await fetchNotifications()
    expect(error.value).toBe('Unauthorized')
  })
})

describe('notifications page — markRead interaction', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockStoreUnreadCount = 3
  })

  it('marks notification as read and updates store', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIF_UNREAD],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { notifications, unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 3
    await fetchNotifications()
    await markRead('n-1')

    expect(notifications.value[0].read_at).not.toBeNull()
    expect(unreadCount.value).toBe(2)
  })

  it('does not decrement when notification was already read', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIF_READ],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 3
    await fetchNotifications()
    await markRead('n-2')

    expect(unreadCount.value).toBe(3)
  })
})

describe('notifications page — markAllRead interaction', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockStoreUnreadCount = 2
  })

  it('marks all as read and resets unread count', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIF_UNREAD, { ...NOTIF_UNREAD, id: 'n-3' }],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { notifications, unreadCount, fetchNotifications, markAllRead } = useNotifications()
    unreadCount.value = 2
    await fetchNotifications()
    await markAllRead()

    expect(notifications.value.every(n => n.read_at !== null)).toBe(true)
    expect(unreadCount.value).toBe(0)
  })

  it('calls read-all endpoint', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { fetchNotifications, markAllRead } = useNotifications()
    await fetchNotifications()
    await markAllRead()

    expect(mockFetch).toHaveBeenCalledWith('/api/notifications/read-all', expect.objectContaining({
      method: 'POST',
    }))
  })
})

describe('notifications page — pagination', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('fetches correct page on navigation', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { data: [], meta: { current_page: 2, last_page: 3, per_page: 20, total: 60 } },
    })

    const { fetchNotifications } = useNotifications()
    await fetchNotifications(2)

    expect(mockFetch).toHaveBeenCalledWith('/api/notifications', expect.objectContaining({
      query: { page: 2 },
    }))
  })

  it('updates pagination state correctly', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { data: [], meta: { current_page: 2, last_page: 5, per_page: 20, total: 100 } },
    })

    const { pagination, fetchNotifications } = useNotifications()
    await fetchNotifications(2)

    expect(pagination.value.currentPage).toBe(2)
    expect(pagination.value.lastPage).toBe(5)
    expect(pagination.value.total).toBe(100)
  })
})
