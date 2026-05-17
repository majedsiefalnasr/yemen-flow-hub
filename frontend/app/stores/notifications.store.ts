import { defineStore } from 'pinia'
import { useNotifications } from '../composables/useNotifications'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    unreadCount: 0,
    lastFetched: null as Date | null,
  }),

  actions: {
    async refreshUnreadCount(): Promise<void> {
      const { fetchUnreadCount, unreadCount } = useNotifications()
      await fetchUnreadCount()
      this.unreadCount = unreadCount.value
      this.lastFetched = new Date()
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
