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

        // Normalize optional queue arrays so role switches never crash templates.
        if ('support_queue' in stats) {
          stats.support_queue = stats.support_queue ?? []
        }
        if ('review_queue' in stats) {
          stats.review_queue = stats.review_queue ?? []
        }
        if ('swift_queue' in stats) {
          stats.swift_queue = stats.swift_queue ?? []
        }
        if ('voting_queue' in stats) {
          stats.voting_queue = stats.voting_queue ?? []
          stats.customs_declaration_pending = stats.customs_declaration_pending ?? []
        }
        if ('recent_requests' in stats) {
          stats.recent_requests = stats.recent_requests ?? []
        }
        if ('returned_requests' in stats) {
          stats.returned_requests = stats.returned_requests ?? []
        }
        if ('draft_requests' in stats) {
          stats.draft_requests = stats.draft_requests ?? []
        }

        this.stats = stats
      }
      catch (err) {
        if (import.meta.dev) {
          console.error('[dashboard.store] loadStats failed:', err)
        }
        this.error = 'تعذّر تحميل بيانات اللوحة. يرجى المحاولة مرة أخرى.'
      }
      finally {
        this.loading = false
      }
    },
  },
})
