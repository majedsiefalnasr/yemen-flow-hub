import { defineStore } from 'pinia'
import { useNotifications } from '../composables/useNotifications'
import type { Notification } from '../types/models'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    unreadCount: 0,
    items: [] as Notification[],
    lastFetched: null as Date | null,
  }),

  actions: {
    async refreshUnreadCount(): Promise<void> {
      const { fetchUnreadCount, unreadCount } = useNotifications()
      await fetchUnreadCount()
      this.unreadCount = unreadCount.value
      this.lastFetched = new Date()
    },
    async fetchRecent(): Promise<void> {
      const { fetchNotifications, fetchUnreadCount, notifications, unreadCount } = useNotifications()
      await fetchUnreadCount()
      await fetchNotifications(1)
      this.items = notifications.value
      this.unreadCount = unreadCount.value
      this.lastFetched = new Date()
    },
    async markAllRead(): Promise<void> {
      const { markAllRead } = useNotifications()
      const ok = await markAllRead()
      if (!ok) return
      const now = new Date().toISOString()
      this.unreadCount = 0
      this.items = this.items.map(item => ({ ...item, read_at: item.read_at ?? now }))
    },

    decrementUnread(): void {
      if (this.unreadCount > 0) {
        this.unreadCount -= 1
      }
    },

    resetUnread(): void {
      this.unreadCount = 0
    },
  },
})
