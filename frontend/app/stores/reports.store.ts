import { defineStore } from 'pinia'
import type {
  BankReport,
  EngineReportFilters,
  ReportExportEntry,
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
    exportFailureNotice: null as string | null,
    lastFailedExportRequest: null as {
      reportType: string
      filters: EngineReportFilters
      format: 'csv' | 'pdf'
    } | null,
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
      this.exportFailureNotice = null
      try {
        const {
          exportReport,
          requestExport,
          pollExportUntilComplete,
          buildExportTruncationMessage,
          buildExportFailureMessage,
          isExportFailed,
        } = useReports()

        if (format === 'excel') {
          const filters = {
            from: this.filters.fromDate,
            to: this.filters.toDate,
          }
          const created = await requestExport('summary', filters)
          const completed = await pollExportUntilComplete(created.id, {
            intervalMs: 0,
            maxAttempts: 5,
          })
          if (isExportFailed(completed)) {
            this.exportFailureNotice = buildExportFailureMessage(completed)
            this.lastFailedExportRequest = { reportType: 'summary', filters, format: 'csv' }
            return
          }
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
      this.exportFailureNotice = null
      try {
        const {
          exportReport,
          requestExport,
          pollExportUntilComplete,
          buildExportTruncationMessage,
          buildExportFailureMessage,
          isExportFailed,
        } = useReports()

        if (format === 'excel') {
          const filters = {
            from: this.filters.fromDate,
            to: this.filters.toDate,
          }
          const created = await requestExport('summary', filters)
          const completed = await pollExportUntilComplete(created.id, {
            intervalMs: 0,
            maxAttempts: 5,
          })
          if (isExportFailed(completed)) {
            this.exportFailureNotice = buildExportFailureMessage(completed)
            this.lastFailedExportRequest = { reportType: 'summary', filters, format: 'csv' }
            return
          }
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

    async retryFailedExport(): Promise<void> {
      if (!this.lastFailedExportRequest) return

      const { reportType, filters, format } = this.lastFailedExportRequest
      this.exportLoading = true
      this.exportFailureNotice = null
      try {
        const {
          requestExport,
          pollExportUntilComplete,
          buildExportTruncationMessage,
          buildExportFailureMessage,
          isExportFailed,
        } = useReports()

        const created = await requestExport(reportType, filters, format)
        const completed = await pollExportUntilComplete(created.id, {
          intervalMs: 0,
          maxAttempts: 5,
        })
        if (isExportFailed(completed)) {
          this.exportFailureNotice = buildExportFailureMessage(completed)
          return
        }

        this.lastFailedExportRequest = null
        const notice = buildExportTruncationMessage(completed)
        if (notice) {
          this.exportTruncationNotice = notice
        }
      } catch {
        this.error = 'تعذّر تصدير التقرير. يرجى المحاولة مرة أخرى.'
      } finally {
        this.exportLoading = false
      }
    },

    clearExportFailureNotice(): void {
      this.exportFailureNotice = null
    },

    clearExportTruncationNotice(): void {
      this.exportTruncationNotice = null
    },
  },
})
