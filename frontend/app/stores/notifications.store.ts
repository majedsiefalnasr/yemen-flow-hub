import { defineStore } from 'pinia'
import { useNotifications } from '../composables/useNotifications'
import type { Notification } from '../types/models'

function countUnread(items: Notification[]): number {
  return items.filter(item => !item.read_at).length
}

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    unreadCount: 0,
    items: [] as Notification[],
    lastFetched: null as Date | null,
  }),

  actions: {
    async refreshUnreadCount(): Promise<void> {
      const { fetchNotifications, notifications } = useNotifications()
      await fetchNotifications(1)
      this.items = notifications.value
      this.unreadCount = countUnread(this.items)
      this.lastFetched = new Date()
    },
    async fetchRecent(): Promise<void> {
      const { fetchNotifications, notifications } = useNotifications()
      await fetchNotifications(1)
      this.items = notifications.value
      this.unreadCount = countUnread(this.items)
      this.lastFetched = new Date()
    },
    setItems(items: Notification[], _unreadCount?: number): void {
      this.items = items
      this.unreadCount = countUnread(items)
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

    incrementUnread(): void {
      this.unreadCount += 1
    },

    removeItems(ids: Set<string>): void {
      const removed = this.items.filter(n => ids.has(n.id))
      const removedUnread = removed.filter(n => !n.read_at).length
      this.items = this.items.filter(n => !ids.has(n.id))
      if (this.unreadCount > 0) {
        this.unreadCount = Math.max(0, this.unreadCount - removedUnread)
      }
    },

    resetUnread(): void {
      this.unreadCount = 0
    },
  },
})
