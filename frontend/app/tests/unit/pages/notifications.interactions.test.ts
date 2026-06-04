// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import notificationsPage from '../../../pages/notifications.vue'

vi.stubGlobal('definePageMeta', vi.fn())

const navigateToMock = vi.hoisted(() => vi.fn())
vi.stubGlobal('navigateTo', navigateToMock)

const refreshUnreadCountMock = vi.hoisted(() => vi.fn())
const setItemsMock = vi.hoisted(() => vi.fn())
const decrementUnreadMock = vi.hoisted(() => vi.fn())
vi.mock('../../../stores/notifications.store', () => ({
  useNotificationsStore: () => ({
    get items() {
      return notificationsRef.value
    },
    get unreadCount() {
      return notificationsRef.value.filter((n: any) => !n.read_at).length
    },
    refreshUnreadCount: refreshUnreadCountMock,
    setItems: setItemsMock,
    decrementUnread: decrementUnreadMock,
    markAllRead: vi.fn(),
  }),
}))

const notificationsRef = ref<any[]>([])
const paginationRef = ref({ currentPage: 1, lastPage: 1, perPage: 20, total: 0 })
const loadingRef = ref(false)
const errorRef = ref<string | null>(null)
const fetchNotificationsMock = vi.hoisted(() => vi.fn())
const fetchUnreadCountMock = vi.hoisted(() => vi.fn())
const markReadMock = vi.hoisted(() => vi.fn())
const markAllReadMock = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useNotifications', () => ({
  useNotifications: () => ({
    get notifications() {
      return notificationsRef
    },
    get pagination() {
      return paginationRef
    },
    get loading() {
      return loadingRef
    },
    get error() {
      return errorRef
    },
    get unreadCount() {
      return ref(notificationsRef.value.filter((n: any) => !n.read_at).length)
    },
    fetchNotifications: fetchNotificationsMock,
    fetchUnreadCount: fetchUnreadCountMock,
    markRead: markReadMock,
    markAllRead: markAllReadMock,
  }),
}))

vi.mock('../../../utils/formatRelativeTime', () => ({
  formatRelativeTime: () => 'الآن',
}))

describe('notifications page interactions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    notificationsRef.value = []
    paginationRef.value = { currentPage: 1, lastPage: 1, perPage: 20, total: 0 }
    loadingRef.value = false
    errorRef.value = null
    fetchUnreadCountMock.mockResolvedValue(undefined)
    fetchNotificationsMock.mockResolvedValue(undefined)
    markReadMock.mockResolvedValue(true)
    markAllReadMock.mockResolvedValue(true)
  })

  it('opens a summary dialog and marks a claim_released notification as read', async () => {
    notificationsRef.value = [
      {
        id: 'n-claim',
        type: 'App\\Notifications\\ClaimReleasedNotification',
        data: {
          type: 'claim_released',
          message: 'أُلغيت مطالبة على الطلب YFH-042 — يدوي',
          request_id: 42,
          reference_number: 'YFH-042',
          reason: 'manual',
          released_by_user_id: 5,
          released_by_name: 'سعد',
        },
        read_at: null,
        created_at: '2026-05-21T10:00:00.000000Z',
      },
    ]

    const wrapper = mount(notificationsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('.notification-item').trigger('click')
    await flushPromises()

    expect(markReadMock).toHaveBeenCalledWith('n-claim')
    expect(decrementUnreadMock).toHaveBeenCalled()
    expect(navigateToMock).not.toHaveBeenCalled()
    expect(notificationsRef.value[0]!.read_at).not.toBeNull()
  })

  it('does not navigate when marking a notification as read', async () => {
    notificationsRef.value = [
      {
        id: 'n-claim',
        type: 'App\\Notifications\\ClaimReleasedNotification',
        data: {
          type: 'claim_released',
          message: 'أُلغيت مطالبة على الطلب YFH-042 — يدوي',
          request_id: 42,
          reference_number: 'YFH-042',
        },
        read_at: null,
        created_at: '2026-05-21T10:00:00.000000Z',
      },
    ]

    const wrapper = mount(notificationsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('.read-icon-btn').trigger('click')

    expect(markReadMock).toHaveBeenCalledWith('n-claim')
    expect(navigateToMock).not.toHaveBeenCalled()
  })

  it('does not navigate for non-claim notifications even when request_id is present', async () => {
    notificationsRef.value = [
      {
        id: 'n-submitted',
        type: 'App\\Notifications\\RequestSubmittedNotification',
        data: {
          type: 'request_submitted',
          message: 'طلب جديد',
          request_id: 7,
          reference_number: 'YFH-007',
        },
        read_at: null,
        created_at: '2026-05-21T10:00:00.000000Z',
      },
    ]

    const wrapper = mount(notificationsPage, { global: { stubs: { Teleport: true } } })
    await flushPromises()

    await wrapper.find('.notification-item').trigger('click')

    expect(navigateToMock).not.toHaveBeenCalled()
  })
})
