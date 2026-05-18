import type { ApiResponse, AuditLog, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface AuditFilters {
  user_id?: number
  action?: string
  from_date?: string
  to_date?: string
  page?: number
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

  return { fetchAuditLogs }
}
