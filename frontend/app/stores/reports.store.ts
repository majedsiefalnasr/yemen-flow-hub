import { defineStore } from 'pinia'
import type {
  BankReport,
  ReportFilter,
  ReportPreset,
  WorkflowReport,
} from '../composables/useReports'
import { useReports } from '../composables/useReports'

export const useReportsStore = defineStore('reports', {
  state: () => ({
    workflowReport: null as WorkflowReport | null,
    bankReport: null as BankReport | null,
    filters: {} as ReportFilter,
    presets: [] as ReportPreset[],
    loading: false,
    exportLoading: false,
    error: null as string | null,
    exportTruncationNotice: null as string | null,
  }),

  actions: {
    async loadWorkflowReport(): Promise<void> {
      this.loading = true
      this.error = null
      try {
        const { fetchWorkflowReport } = useReports()
        this.workflowReport = await fetchWorkflowReport(this.filters)
      } catch {
        this.error = 'تعذّر تحميل تقرير سير العمل. يرجى المحاولة مرة أخرى.'
      } finally {
        this.loading = false
      }
    },

    async loadBankReport(): Promise<void> {
      this.loading = true
      this.error = null
      try {
        const { fetchBankReport } = useReports()
        this.bankReport = await fetchBankReport(this.filters)
      } catch {
        this.error = 'تعذّر تحميل تقرير البنك. يرجى المحاولة مرة أخرى.'
      } finally {
        this.loading = false
      }
    },

    applyFilters(filters: ReportFilter): void {
      this.filters = { ...filters }
    },

    async loadPresetsFromStorage(): Promise<void> {
      const { loadPresets } = useReports()
      this.presets = await loadPresets()
    },

    async savePreset(name: string): Promise<void> {
      const { savePreset } = useReports()
      const preset = await savePreset(name, this.filters)
      this.presets = [...this.presets, preset]
    },

    async deletePreset(id: string): Promise<void> {
      const { deletePreset } = useReports()
      await deletePreset(id)
      this.presets = this.presets.filter((p) => p.id !== id)
    },

    async exportWorkflow(format: 'excel' | 'pdf'): Promise<void> {
      this.exportLoading = true
      this.exportTruncationNotice = null
      try {
        const {
          exportReport,
          requestExport,
          pollExportUntilComplete,
          buildExportTruncationMessage,
        } = useReports()

        if (format === 'excel') {
          const created = await requestExport('summary', {
            from: this.filters.fromDate,
            to: this.filters.toDate,
          })
          const completed = await pollExportUntilComplete(created.id, {
            intervalMs: 0,
            maxAttempts: 5,
          })
          const notice = buildExportTruncationMessage(completed)
          if (notice) {
            this.exportTruncationNotice = notice
          }
          return
        }

        await exportReport('workflow', format, this.filters)
      } catch {
        this.error = 'تعذّر تصدير التقرير. يرجى المحاولة مرة أخرى.'
      } finally {
        this.exportLoading = false
      }
    },

    async exportBank(format: 'excel' | 'pdf'): Promise<void> {
      this.exportLoading = true
      this.exportTruncationNotice = null
      try {
        const {
          exportReport,
          requestExport,
          pollExportUntilComplete,
          buildExportTruncationMessage,
        } = useReports()

        if (format === 'excel') {
          const created = await requestExport('summary', {
            from: this.filters.fromDate,
            to: this.filters.toDate,
          })
          const completed = await pollExportUntilComplete(created.id, {
            intervalMs: 0,
            maxAttempts: 5,
          })
          const notice = buildExportTruncationMessage(completed)
          if (notice) {
            this.exportTruncationNotice = notice
          }
          return
        }

        await exportReport('bank', format, this.filters)
      } catch {
        this.error = 'تعذّر تصدير التقرير. يرجى المحاولة مرة أخرى.'
      } finally {
        this.exportLoading = false
      }
    },

    clearExportTruncationNotice(): void {
      this.exportTruncationNotice = null
    },
  },
})
