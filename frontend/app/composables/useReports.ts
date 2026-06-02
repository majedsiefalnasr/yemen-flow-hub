import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

export interface ReportFilter {
  fromDate?: string
  toDate?: string
}

export interface WorkflowReport {
  counts_by_status: Record<string, number>
  counts_by_bank: Array<{ bank_id: number; bank_name: string; total: number }>
  bank_breakdown?: Array<{ bank_id?: number; bank_name: string; total: number; approved?: number; rejected?: number; total_value?: number }>
  avg_time_per_stage_hours: Record<string, number>
  throughput: { completed: number; approved: number; rejected: number }
  // New in 7.8
  monthly_trend: Array<{ month: string; total: number; approved: number; rejected: number }>
  category_distribution: Array<{ category: string; count: number }>
  amount_by_currency: Array<{ currency: string; amount: number }>
  submission_heatmap: Array<{ day: number; slot: number; count: number }>
  total_financing_value: number
  duplicate_invoice_count: number
  voting_analytics?: Array<{ user_id: number; name: string; sessions: number; approvals: number; rejections: number; avg_hours?: number | null }>
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

const PRESETS_KEY = 'reports_presets'

export function useReports() {
  const { get } = useApi()
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
    const response = await get<ApiResponse<WorkflowReport>>(`/api/reports/workflow${buildQuery(filter)}`)
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

  function loadPresets(): ReportPreset[] {
    try {
      const raw = localStorage.getItem(PRESETS_KEY)
      if (!raw) return []
      return JSON.parse(raw) as ReportPreset[]
    } catch {
      return []
    }
  }

  function savePreset(name: string, filter: ReportFilter): ReportPreset {
    const presets = loadPresets()
    const preset: ReportPreset = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      name: name.slice(0, 50),
      filter,
      createdAt: new Date().toISOString(),
    }
    presets.push(preset)
    localStorage.setItem(PRESETS_KEY, JSON.stringify(presets))
    return preset
  }

  function deletePreset(id: string): void {
    const presets = loadPresets().filter((p) => p.id !== id)
    localStorage.setItem(PRESETS_KEY, JSON.stringify(presets))
  }

  return {
    fetchWorkflowReport,
    fetchBankReport,
    exportReport,
    loadPresets,
    savePreset,
    deletePreset,
  }
}
