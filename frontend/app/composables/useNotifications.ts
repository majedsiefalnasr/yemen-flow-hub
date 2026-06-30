import { ref } from 'vue'
import type { Notification, ApiResponse } from '../types/models'
import { useApi } from './useApi'

interface NotificationPagination {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

interface EngineNotificationRow {
  id: number
  notification_id: number
  type: string
  severity: string
  title: string
  body: string | null
  entity_type: string | null
  entity_id: number | null
  action_url: string | null
  read_at: string | null
  archived_at: string | null
  created_at: string | null
}

interface EngineNotificationResponse {
  data: EngineNotificationRow[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

function engineRowToNotification(row: EngineNotificationRow): Notification {
  return {
    id: String(row.id),
    type: row.type,
    data: {
      title: row.title,
      body: row.body ?? '',
      severity: row.severity,
      entity_type: row.entity_type,
      entity_id: row.entity_id,
      action_url: row.action_url,
    },
    read_at: row.read_at,
    created_at: row.created_at ?? '',
  }
}

export const useNotifications = () => {
  const { get, post } = useApi()

  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<NotificationPagination>({
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
  })

  const fetchNotifications = async (page = 1) => {
    loading.value = true
    error.value = null

    try {
      const response = await get<ApiResponse<EngineNotificationResponse>>('/api/v1/notifications', {
        query: { page },
      })

      const payload = response.data
      notifications.value = payload.data.map(engineRowToNotification)
      pagination.value = {
        currentPage: payload.meta.current_page,
        lastPage: payload.meta.last_page,
        perPage: payload.meta.per_page,
        total: payload.meta.total,
      }
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to load notifications'
    } finally {
      loading.value = false
    }
  }

  const fetchUnreadCount = async () => {
    try {
      const response = await get<ApiResponse<{ count: number }>>(
        '/api/v1/notifications/unread-count',
      )
      unreadCount.value = response.data.count
    } catch {
      // silently ignore — non-critical
    }
  }

  const markRead = async (id: string): Promise<boolean> => {
    try {
      await post(`/api/v1/notifications/${id}/read`)

      const notif = notifications.value.find((n) => n.id === id)
      if (notif && !notif.read_at) {
        notif.read_at = new Date().toISOString()
        if (unreadCount.value > 0) {
          unreadCount.value -= 1
        }
      }

      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to mark notification as read'
      return false
    }
  }

  const markAllRead = async (): Promise<boolean> => {
    try {
      await post('/api/v1/notifications/read-all')

      const now = new Date().toISOString()
      notifications.value.forEach((n) => {
        if (!n.read_at) n.read_at = now
      })
      unreadCount.value = 0

      return true
    } catch (err: any) {
      error.value = err.data?.message || 'Failed to mark all notifications as read'
      return false
    }
  }

  return {
    notifications,
    unreadCount,
    loading,
    error,
    pagination,
    fetchNotifications,
    fetchUnreadCount,
    markRead,
    markAllRead,
  }
}
