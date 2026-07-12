import { defineStore } from 'pinia'
import type { DashboardStats } from '../composables/useDashboard'
import { useDashboard } from '../composables/useDashboard'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: null as DashboardStats | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async loadStats(): Promise<void> {
      this.loading = true
      this.error = null
      this.stats = null

      try {
        const { fetchStats } = useDashboard()
        const stats = await fetchStats()

        // Normalize the optional recent-requests list so templates never crash.
        if ('recent_requests' in stats) {
          stats.recent_requests = stats.recent_requests ?? []
        }

        this.stats = stats
      } catch (err) {
        if (import.meta.dev) {
          console.error('[dashboard.store] loadStats failed:', err)
        }
        this.error = 'تعذّر تحميل بيانات اللوحة. يرجى المحاولة مرة أخرى.'
      } finally {
        this.loading = false
      }
    },
  },
})
