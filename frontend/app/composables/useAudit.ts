import type { ApiResponse, AuditLog, EngineAuditLog, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface AuditFilters {
  user_id?: number
  action?: string
  from_date?: string
  to_date?: string
  page?: number
  per_page?: number
}

export interface EngineAuditFilters {
  user?: number
  role?: number
  event?: string
  entity?: string
  request?: number
  from?: string
  to?: string
  ip?: string
  correlation_id?: string
  page?: number
  per_page?: number
}

export interface AuditStats {
  today_count: number
  duplicate_invoice_count: number
}

export interface DuplicateRequest {
  id: number
  reference_number: string
  bank_name: string | null
  amount: number
  currency: string
  created_at: string
  status: string
}

export interface DuplicateGroup {
  invoice_number: string
  banks: string[]
  requests: DuplicateRequest[]
}

/** @deprecated use DuplicateGroup */
export type DuplicateInvoice = DuplicateGroup

export interface RiskIndicator {
  title: string
  body: string
  level: 'عالية' | 'متوسطة' | 'منخفضة'
}

export function useAudit() {
  const { get } = useApi()

  async function fetchAuditLogs(filters: AuditFilters = {}): Promise<PaginatedResponse<AuditLog>> {
    const params = new URLSearchParams()
    if (filters.user_id) params.set('user_id', String(filters.user_id))
    if (filters.action) params.set('action', filters.action)
    if (filters.from_date) params.set('from_date', filters.from_date)
    if (filters.to_date) params.set('to_date', filters.to_date)
    if (filters.page) params.set('page', String(filters.page))
    if (filters.per_page) params.set('per_page', String(filters.per_page))
    const query = params.toString()
    const path = query ? `/api/audit?${query}` : '/api/audit'
    const response = await get<ApiResponse<PaginatedResponse<AuditLog>>>(path)
    return response.data
  }

  async function fetchAuditStats(): Promise<AuditStats> {
    const response = await get<ApiResponse<AuditStats>>('/api/audit/stats')
    return response.data
  }

  async function fetchDuplicates(): Promise<DuplicateGroup[]> {
    const response = await get<ApiResponse<{ data: DuplicateGroup[] }>>('/api/audit/duplicates')
    return response.data.data ?? []
  }

  async function fetchRiskIndicators(): Promise<RiskIndicator[]> {
    const response = await get<ApiResponse<{ data: RiskIndicator[] }>>('/api/audit/risk-indicators')
    return response.data.data
  }

  async function fetchEngineAuditLogs(
    filters: EngineAuditFilters = {},
  ): Promise<PaginatedResponse<EngineAuditLog>> {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    }
    const query = params.toString()
    const path = query ? `/api/v1/audit-logs?${query}` : '/api/v1/audit-logs'
    const response = await get<{
      data: EngineAuditLog[]
      meta: PaginatedResponse<EngineAuditLog>['meta']
    }>(path)
    return { data: response.data, meta: response.meta }
  }

  async function fetchEngineAuditLogDetail(id: number): Promise<EngineAuditLog> {
    const response = await get<{ data: EngineAuditLog }>(`/api/v1/audit-logs/${id}`)
    return response.data
  }

  async function exportEngineAuditLogs(filters: EngineAuditFilters = {}): Promise<void> {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    }
    const query = params.toString()
    const path = query ? `/api/v1/audit-logs/export?${query}` : '/api/v1/audit-logs/export'
    // Fetch as a blob so the raw CSV bytes (including the server's BOM) are preserved.
    // useApi's get() lets $fetch content-negotiate and parse the body, corrupting the file.
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const blob = await $fetch<Blob>(`${baseURL}${path}`, {
      credentials: 'include',
      responseType: 'blob',
      headers: { Accept: 'text/csv' },
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  return {
    fetchAuditLogs,
    fetchAuditStats,
    fetchDuplicates,
    fetchRiskIndicators,
    fetchEngineAuditLogs,
    fetchEngineAuditLogDetail,
    exportEngineAuditLogs,
  }
}
