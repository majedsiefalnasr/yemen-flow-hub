import { ref } from 'vue'
import type { Notification, PaginatedResponse, ApiResponse } from '../types/models'

interface NotificationPagination {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

export const useNotifications = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

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
      const response = await $fetch<ApiResponse<PaginatedResponse<Notification>>>('/api/notifications', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
        query: { page },
      })

      notifications.value = response.data.data
      pagination.value = {
        currentPage: response.data.meta.current_page,
        lastPage: response.data.meta.last_page,
        perPage: response.data.meta.per_page,
        total: response.data.meta.total,
      }
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to load notifications'
    }
    finally {
      loading.value = false
    }
  }

  const fetchUnreadCount = async () => {
    try {
      const response = await $fetch<ApiResponse<{ count: number }>>('/api/notifications/unread-count', {
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      unreadCount.value = response.data.count
    }
    catch {
      // silently ignore — non-critical
    }
  }

  const markRead = async (id: string) => {
    try {
      await $fetch(`/api/notifications/${id}/read`, {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })

      const notif = notifications.value.find(n => n.id === id)
      if (notif && !notif.read_at) {
        notif.read_at = new Date().toISOString()
        if (unreadCount.value > 0) {
          unreadCount.value -= 1
        }
      }
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to mark notification as read'
    }
  }

  const markAllRead = async () => {
    try {
      await $fetch('/api/notifications/read-all', {
        method: 'POST',
        baseURL,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })

      const now = new Date().toISOString()
      notifications.value.forEach((n) => {
        if (!n.read_at) n.read_at = now
      })
      unreadCount.value = 0
    }
    catch (err: any) {
      error.value = err.data?.message || 'Failed to mark all notifications as read'
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
