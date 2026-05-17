import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

const { useNotifications } = await import('../../../composables/useNotifications')

const NOTIFICATION_FIXTURE = {
  id: 'abc-123',
  type: 'App\\Notifications\\RequestSubmittedNotification',
  data: {
    type: 'request_submitted',
    message: 'تم تقديم طلب جديد: YFH-2026-000001',
    request_id: 1,
    reference_number: 'YFH-2026-000001',
  },
  read_at: null,
  created_at: '2026-05-17T10:00:00.000000Z',
}

const READ_NOTIFICATION_FIXTURE = {
  ...NOTIFICATION_FIXTURE,
  id: 'def-456',
  read_at: '2026-05-17T11:00:00.000000Z',
}

describe('useNotifications — fetchNotifications', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('sets notifications and pagination on success', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      message: 'Notifications retrieved.',
      data: {
        data: [NOTIFICATION_FIXTURE],
        meta: { current_page: 1, last_page: 2, per_page: 20, total: 40 },
      },
    })

    const { notifications, pagination, fetchNotifications } = useNotifications()
    await fetchNotifications()

    expect(notifications.value).toHaveLength(1)
    expect(notifications.value[0].id).toBe('abc-123')
    expect(pagination.value.currentPage).toBe(1)
    expect(pagination.value.lastPage).toBe(2)
    expect(pagination.value.total).toBe(40)
  })

  it('sets error on fetch failure', async () => {
    mockFetch.mockRejectedValueOnce({ data: { message: 'Server error' } })

    const { error, fetchNotifications } = useNotifications()
    await fetchNotifications()

    expect(error.value).toBe('Server error')
  })

  it('calls correct endpoint with page param', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [],
        meta: { current_page: 2, last_page: 3, per_page: 20, total: 60 },
      },
    })

    const { fetchNotifications } = useNotifications()
    await fetchNotifications(2)

    expect(mockFetch).toHaveBeenCalledWith('/api/notifications', expect.objectContaining({
      query: { page: 2 },
    }))
  })
})

describe('useNotifications — fetchUnreadCount', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('sets unreadCount on success', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { count: 5 },
    })

    const { unreadCount, fetchUnreadCount } = useNotifications()
    await fetchUnreadCount()

    expect(unreadCount.value).toBe(5)
  })

  it('silently ignores errors without setting error state', async () => {
    mockFetch.mockRejectedValueOnce(new Error('Network error'))

    const { unreadCount, error, fetchUnreadCount } = useNotifications()
    await fetchUnreadCount()

    expect(unreadCount.value).toBe(0)
    expect(error.value).toBeNull()
  })
})

describe('useNotifications — markRead', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('marks notification as read locally and decrements unread count', async () => {
    // First populate notifications
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIFICATION_FIXTURE],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    // Then mark read
    mockFetch.mockResolvedValueOnce({ success: true })

    const { notifications, unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 3
    await fetchNotifications()
    await markRead('abc-123')

    expect(notifications.value[0].read_at).not.toBeNull()
    expect(unreadCount.value).toBe(2)
  })

  it('calls correct endpoint for mark read', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIFICATION_FIXTURE],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { fetchNotifications, markRead } = useNotifications()
    await fetchNotifications()
    await markRead('abc-123')

    expect(mockFetch).toHaveBeenCalledWith('/api/notifications/abc-123/read', expect.objectContaining({
      method: 'POST',
    }))
  })

  it('does not decrement unread count if notification was already read', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [READ_NOTIFICATION_FIXTURE],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 2
    await fetchNotifications()
    await markRead('def-456')

    expect(unreadCount.value).toBe(2) // unchanged
  })
})

describe('useNotifications — markAllRead', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('marks all notifications as read and resets unread count to 0', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [NOTIFICATION_FIXTURE, { ...NOTIFICATION_FIXTURE, id: 'xyz-789' }],
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
