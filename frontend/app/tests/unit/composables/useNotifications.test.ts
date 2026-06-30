import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

const { useNotifications } = await import('../../../composables/useNotifications')

const ENGINE_NOTIFICATION_ROW = {
  id: 1,
  notification_id: 10,
  type: 'transition',
  severity: 'info',
  title: 'YFH-2026-000001: انتقال',
  body: 'انتقل الطلب من مرحلة أ إلى مرحلة ب',
  entity_type: 'engine_request',
  entity_id: 1,
  action_url: '/requests/1',
  read_at: null,
  archived_at: null,
  created_at: '2026-06-24T10:00:00.000000Z',
}

const READ_ENGINE_ROW = {
  ...ENGINE_NOTIFICATION_ROW,
  id: 2,
  read_at: '2026-06-24T11:00:00.000000Z',
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
        data: [ENGINE_NOTIFICATION_ROW],
        meta: { current_page: 1, last_page: 2, per_page: 20, total: 40 },
      },
    })

    const { notifications, pagination, fetchNotifications } = useNotifications()
    await fetchNotifications()

    expect(notifications.value).toHaveLength(1)
    expect(notifications.value[0]!.id).toBe('1')
    expect(notifications.value[0]!.data.title).toBe('YFH-2026-000001: انتقال')
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

  it('calls correct V1 endpoint with page param', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [],
        meta: { current_page: 2, last_page: 3, per_page: 20, total: 60 },
      },
    })

    const { fetchNotifications } = useNotifications()
    await fetchNotifications(2)

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/v1/notifications',
      expect.objectContaining({
        query: { page: 2 },
      }),
    )
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
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [ENGINE_NOTIFICATION_ROW],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { notifications, unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 3
    await fetchNotifications()
    await markRead('1')

    expect(notifications.value[0]!.read_at).not.toBeNull()
    expect(unreadCount.value).toBe(2)
  })

  it('calls correct V1 endpoint for mark read', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [ENGINE_NOTIFICATION_ROW],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { fetchNotifications, markRead } = useNotifications()
    await fetchNotifications()
    await markRead('1')

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/v1/notifications/1/read',
      expect.objectContaining({
        method: 'POST',
      }),
    )
  })

  it('does not decrement unread count if notification was already read', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: {
        data: [READ_ENGINE_ROW],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { unreadCount, fetchNotifications, markRead } = useNotifications()
    unreadCount.value = 2
    await fetchNotifications()
    await markRead('2')

    expect(unreadCount.value).toBe(2)
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
        data: [ENGINE_NOTIFICATION_ROW, { ...ENGINE_NOTIFICATION_ROW, id: 3 }],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
      },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { notifications, unreadCount, fetchNotifications, markAllRead } = useNotifications()
    unreadCount.value = 2
    await fetchNotifications()
    await markAllRead()

    expect(notifications.value.every((n) => n.read_at !== null)).toBe(true)
    expect(unreadCount.value).toBe(0)
  })

  it('calls V1 read-all endpoint', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { fetchNotifications, markAllRead } = useNotifications()
    await fetchNotifications()
    await markAllRead()

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/v1/notifications/read-all',
      expect.objectContaining({
        method: 'POST',
      }),
    )
  })
})
