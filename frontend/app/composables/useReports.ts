import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

export interface ReportFilter {
  fromDate?: string
  toDate?: string
}

export interface ReportExportEntry {
  id: number
  report_type: string
  filters: Record<string, any> | null
  format: string
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED'
  total_matching?: number | null
  exported_count?: number | null
  truncated?: boolean
  truncation_note?: string | null
  created_at: string
}

export function buildExportTruncationMessage(entry: ReportExportEntry): string | null {
  if (!entry.truncated) return null

  const exported = entry.exported_count ?? 0
  const total = entry.total_matching ?? exported

  return `تم تصدير ${exported.toLocaleString('ar-EG')} من أصل ${total.toLocaleString('ar-EG')} صفًا مطابقًا. ضيّق الفلاتر لتصدير كامل.`
}

export function buildExportFailureMessage(entry: ReportExportEntry): string | null {
  if (entry.status !== 'FAILED') return null

  return 'تعذّر إكمال التصدير. يرجى المحاولة مرة أخرى.'
}

export function isExportFailed(entry: ReportExportEntry): boolean {
  return entry.status === 'FAILED'
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export interface EngineReportFilters {
  from?: string
  to?: string
  workflow?: number
  version?: number
  bank?: number
  stage?: number
  status?: string
  currency?: string
}

export interface ReportSummary {
  total: number
  active: number
  closed: number
  rejected: number
  totalAmount: number
}

export interface MonthlyRow {
  month: string
  total: number
  closed: number
  rejected: number
}

export interface StageCount {
  stage_code: string
  stage_name: string
  count: number
}

export interface BankBreakdown {
  bank_id: number
  bank_name: string
  total: number
  closed: number
  rejected: number
  total_amount: number
}

export interface MerchantBreakdown {
  merchant_id: number
  merchant_name: string
  total: number
  total_amount: number
}

export interface SectorBreakdown {
  sector: string
  count: number
  total_amount: number
}

export interface CurrencyBreakdown {
  currency: string
  count: number
  total_amount: number
}

export interface StageDuration {
  stage_code: string
  stage_name: string
  avg_hours: number
  transitions: number
}

export interface SlaReport {
  stage_code: string
  stage_name: string
  total: number
  breached: number
  nearing: number
  ok: number
  breach_rate: number
}

export interface TeamPerformance {
  role: string
  actions: number
  members: number
  avg_actions_per_member: number
}

export interface WorkflowReport {
  counts_by_status: Record<string, number>
  counts_by_bank: Array<{ bank_id: number; bank_name: string; total: number }>
  bank_breakdown?: Array<{
    bank_id?: number
    bank_name: string
    total: number
    approved?: number
    rejected?: number
    total_value?: number
  }>
  avg_time_per_stage_hours: Record<string, number>
  throughput: { completed: number; approved: number; rejected: number }
  // New in 7.8
  monthly_trend: Array<{ month: string; total: number; approved: number; rejected: number }>
  category_distribution: Array<{ category: string; count: number }>
  amount_by_currency: Array<{ currency: string; amount: number }>
  submission_heatmap: Array<{ day: number; slot: number; count: number }>
  total_financing_value: number
  duplicate_invoice_count: number
  voting_analytics?: Array<{
    user_id: number
    name: string
    sessions: number
    approvals: number
    rejections: number
    avg_hours?: number | null
  }>
  sla_performance?: Array<{ stage: string; avg_hours?: number | null; breach_rate?: number | null }>
  swift_stats?: { uploaded?: number; avg_upload_hours?: number | null; pending?: number }
  fx_stats?: { completed?: number; pending?: number }
  compliance?: { on_time_rate?: number | null; sla_violations?: number; returned_count?: number }
  audit_summary?: { total_events?: number; auth_failures?: number }
}

export interface BankReport {
  total_requests: number
  approved_count: number
  rejected_count: number
  pending_count: number
  approval_rate: number
  rejection_rate: number
  avg_processing_hours: number
  monthly_trend?: Array<{ month: string; total: number; approved: number; rejected: number }>
  category_distribution?: Array<{ category: string; count: number }>
  amount_by_currency?: Array<{ currency: string; amount: number }>
  submission_heatmap?: Array<{ day: number; slot: number; count: number }>
  per_bank?: Array<{
    bank_id: number
    bank_name: string
    total_requests: number
    approved_count: number
    rejected_count: number
    pending_count: number
    approval_rate: number
    rejection_rate: number
  }>
}

export interface ReportPreset {
  id: string
  name: string
  filter: ReportFilter
  createdAt: string
}

export function useReports() {
  const { get, post, del } = useApi()
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  function buildQuery(filter: ReportFilter): string {
    const params = new URLSearchParams()
    if (filter.fromDate) params.set('from_date', filter.fromDate)
    if (filter.toDate) params.set('to_date', filter.toDate)
    const q = params.toString()
    return q ? `?${q}` : ''
  }

  async function fetchWorkflowReport(filter: ReportFilter = {}): Promise<WorkflowReport> {
    const response = await get<ApiResponse<WorkflowReport>>(
      `/api/reports/workflow${buildQuery(filter)}`,
    )
    return response.data
  }

  async function fetchBankReport(filter: ReportFilter = {}): Promise<BankReport> {
    const response = await get<ApiResponse<BankReport>>(`/api/reports/bank${buildQuery(filter)}`)
    return response.data
  }

  async function exportReport(
    type: 'workflow' | 'bank',
    format: 'excel' | 'pdf',
    filter: ReportFilter = {},
  ): Promise<void> {
    const params = new URLSearchParams({ format })
    if (filter.fromDate) params.set('from_date', filter.fromDate)
    if (filter.toDate) params.set('to_date', filter.toDate)

    const url = `${baseURL}/api/reports/${type}/export?${params.toString()}`
    const filename = `${type}-report.${format === 'pdf' ? 'pdf' : 'csv'}`

    const blob = await $fetch<Blob>(url, {
      credentials: 'include',
      responseType: 'blob',
      headers: { Accept: format === 'pdf' ? 'application/pdf' : 'text/csv' },
    })

    const objectUrl = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = objectUrl
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    document.body.removeChild(anchor)
    URL.revokeObjectURL(objectUrl)
  }

  async function loadPresets(): Promise<ReportPreset[]> {
    try {
      const response = await get<ApiResponse<ReportPreset[]>>('/api/report-presets')
      return response.data ?? []
    } catch {
      return []
    }
  }

  async function savePreset(name: string, filter: ReportFilter): Promise<ReportPreset> {
    const preset: ReportPreset = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      name: name.slice(0, 50),
      filter,
      createdAt: new Date().toISOString(),
    }
    await post<ApiResponse<ReportPreset[]>>('/api/report-presets', preset)
    return preset
  }

  async function deletePreset(id: string): Promise<void> {
    await del(`/api/report-presets/${id}`)
  }

  function buildEngineQuery(filters: EngineReportFilters): string {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    }
    const q = params.toString()
    return q ? `?${q}` : ''
  }

  async function fetchReportSummary(filters: EngineReportFilters = {}): Promise<ReportSummary> {
    const response = await get<{ data: ReportSummary }>(
      `/api/v1/reports/summary${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchRequestsOverTime(filters: EngineReportFilters = {}): Promise<MonthlyRow[]> {
    const response = await get<{ data: MonthlyRow[] }>(
      `/api/v1/reports/requests-over-time${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchByWorkflowStage(filters: EngineReportFilters = {}): Promise<StageCount[]> {
    const response = await get<{ data: StageCount[] }>(
      `/api/v1/reports/by-workflow-stage${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchByBank(filters: EngineReportFilters = {}): Promise<BankBreakdown[]> {
    const response = await get<{ data: BankBreakdown[] }>(
      `/api/v1/reports/by-bank${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchByMerchant(filters: EngineReportFilters = {}): Promise<MerchantBreakdown[]> {
    const response = await get<{ data: MerchantBreakdown[] }>(
      `/api/v1/reports/by-merchant${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchBySector(filters: EngineReportFilters = {}): Promise<SectorBreakdown[]> {
    const response = await get<{ data: SectorBreakdown[] }>(
      `/api/v1/reports/by-sector${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchByCurrency(filters: EngineReportFilters = {}): Promise<CurrencyBreakdown[]> {
    const response = await get<{ data: CurrencyBreakdown[] }>(
      `/api/v1/reports/by-currency${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchStageDuration(filters: EngineReportFilters = {}): Promise<StageDuration[]> {
    const response = await get<{ data: StageDuration[] }>(
      `/api/v1/reports/stage-duration${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchSlaReport(filters: EngineReportFilters = {}): Promise<SlaReport[]> {
    const response = await get<{ data: SlaReport[] }>(
      `/api/v1/reports/sla${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function fetchTeamPerformance(
    filters: EngineReportFilters = {},
  ): Promise<TeamPerformance[]> {
    const response = await get<{ data: TeamPerformance[] }>(
      `/api/v1/reports/team-performance${buildEngineQuery(filters)}`,
    )
    return response.data
  }

  async function requestExport(
    reportType: string,
    filters: EngineReportFilters = {},
    format: 'csv' | 'pdf' = 'csv',
  ): Promise<ReportExportEntry> {
    const response = await post<{ data: ReportExportEntry }>('/api/v1/reports/exports', {
      report_type: reportType,
      filters,
      format,
    })
    return response.data
  }

  async function fetchExportStatus(exportId: number): Promise<ReportExportEntry> {
    const response = await get<{ data: ReportExportEntry }>(`/api/v1/reports/exports/${exportId}`)
    return response.data
  }

  async function pollExportUntilComplete(
    exportId: number,
    options: { intervalMs?: number; maxAttempts?: number } = {},
  ): Promise<ReportExportEntry> {
    const intervalMs = options.intervalMs ?? 500
    const maxAttempts = options.maxAttempts ?? 120

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      const entry = await fetchExportStatus(exportId)
      if (entry.status === 'COMPLETED' || entry.status === 'FAILED') {
        return entry
      }
      await sleep(intervalMs)
    }

    throw new Error('EXPORT_POLL_TIMEOUT')
  }

  async function fetchMyExports(): Promise<{ data: ReportExportEntry[]; meta: any }> {
    return get<{ data: ReportExportEntry[]; meta: any }>('/api/v1/reports/exports')
  }

  return {
    fetchWorkflowReport,
    fetchBankReport,
    exportReport,
    loadPresets,
    savePreset,
    deletePreset,
    fetchReportSummary,
    fetchRequestsOverTime,
    fetchByWorkflowStage,
    fetchByBank,
    fetchByMerchant,
    fetchBySector,
    fetchByCurrency,
    fetchStageDuration,
    fetchSlaReport,
    fetchTeamPerformance,
    requestExport,
    fetchExportStatus,
    pollExportUntilComplete,
    buildExportTruncationMessage,
    buildExportFailureMessage,
    isExportFailed,
    fetchMyExports,
  }
}
