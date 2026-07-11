import { defineStore } from 'pinia'
import type { DashboardWork } from '../composables/useDashboardWork'
import { useDashboardWork } from '../composables/useDashboardWork'
import { isAbortError } from '../composables/useApi'
import { extractApiErrorMessage, extractHttpStatus } from '../utils/apiErrors'

/**
 * State for the generic work dashboard (Phase D0). One store for every workflow
 * user; the sections it renders come from the API, not from a role code.
 * errorStatus carries the HTTP code so the page can pick the right denial /
 * rate-limit state (403/404/429/500).
 */
export const useDashboardWorkStore = defineStore('dashboardWork', {
  state: () => ({
    work: null as DashboardWork | null,
    loading: false,
    error: null as string | null,
    errorStatus: null as number | null,
  }),

  actions: {
    async loadWork(): Promise<void> {
      this.loading = true
      this.error = null
      this.errorStatus = null

      try {
        const { fetchWork } = useDashboardWork()
        this.work = await fetchWork()
      } catch (cause: unknown) {
        if (isAbortError(cause)) return
        this.errorStatus = extractHttpStatus(cause)
        this.error =
          this.errorStatus === 429
            ? 'تم إيقاف التحميل مؤقتاً بسبب كثرة الطلبات. حاول مرة أخرى بعد قليل.'
            : extractApiErrorMessage(cause, 'تعذّر تحميل مهامك. حاول مرة أخرى.')
      } finally {
        this.loading = false
      }
    },
  },
})
