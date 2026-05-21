import type { ApiResponse, AuditLog, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface AuditFilters {
  user_id?: number
  action?: string
  from_date?: string
  to_date?: string
  page?: number
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

  return { fetchAuditLogs, fetchAuditStats, fetchDuplicates, fetchRiskIndicators }
}
