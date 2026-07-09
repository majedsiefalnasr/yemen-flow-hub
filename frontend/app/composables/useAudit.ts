import type { AuditLog, EngineAuditLog, PaginatedResponse } from '../types/models'
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

function mapEngineAuditLogToAuditLog(log: EngineAuditLog): AuditLog {
  const metadata =
    log.metadata ??
    (log.old_values || log.new_values
      ? { before: log.old_values ?? {}, after: log.new_values ?? {} }
      : null)

  const metaRecord = (log.metadata ?? {}) as Record<string, unknown>
  const oldRecord = (log.old_values ?? {}) as Record<string, unknown>
  const newRecord = (log.new_values ?? {}) as Record<string, unknown>

  return {
    id: log.id,
    user: log.actor
      ? {
          id: log.actor.id,
          name: log.actor.name,
          email: log.actor.email,
          role: log.user_role ?? '',
        }
      : null,
    user_id: log.actor_user_id,
    user_role: log.user_role,
    action: log.event_code,
    entity_type: log.entity_type,
    entity_id: log.entity_id,
    from_status:
      (metaRecord.from_status as string | undefined) ??
      (oldRecord.from_status as string | undefined) ??
      null,
    to_status:
      (metaRecord.to_status as string | undefined) ??
      (newRecord.to_status as string | undefined) ??
      null,
    ip_address: log.ip_address,
    user_agent: log.user_agent,
    metadata,
    created_at: log.created_at,
  }
}

function toEngineAuditFilters(filters: AuditFilters): EngineAuditFilters {
  return {
    user: filters.user_id,
    event: filters.action,
    from: filters.from_date,
    to: filters.to_date,
    page: filters.page,
    per_page: filters.per_page,
  }
}

interface AuditLogExportEntry {
  id: number
  report_type: string
  filters: Record<string, unknown> | null
  format: string
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED'
  created_at: string
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export function useAudit() {
  const { get, post } = useApi()

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

  async function fetchAuditLogs(filters: AuditFilters = {}): Promise<PaginatedResponse<AuditLog>> {
    const result = await fetchEngineAuditLogs(toEngineAuditFilters(filters))
    return {
      data: result.data.map(mapEngineAuditLogToAuditLog),
      meta: result.meta,
    }
  }

  async function fetchEngineAuditLogDetail(id: number): Promise<EngineAuditLog> {
    const response = await get<{ data: EngineAuditLog }>(`/api/v1/audit-logs/${id}`)
    return response.data
  }

  /**
   * API-004: the export endpoint is async — it creates a ReportExport row
   * and processes it on a queue worker instead of building the CSV
   * synchronously in the request. This polls until the export completes
   * (or fails), then downloads the finished file.
   */
  async function requestAuditLogExport(
    filters: EngineAuditFilters = {},
  ): Promise<AuditLogExportEntry> {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    }
    const query = params.toString()
    const path = query ? `/api/v1/audit-logs/export?${query}` : '/api/v1/audit-logs/export'
    const response = await post<{ data: AuditLogExportEntry }>(path)
    return response.data
  }

  async function fetchAuditLogExportStatus(exportId: number): Promise<AuditLogExportEntry> {
    const response = await get<{ data: AuditLogExportEntry }>(
      `/api/v1/audit-logs/export/${exportId}`,
    )
    return response.data
  }

  async function pollAuditLogExportUntilComplete(
    exportId: number,
    options: { intervalMs?: number; maxAttempts?: number } = {},
  ): Promise<AuditLogExportEntry> {
    const intervalMs = options.intervalMs ?? 500
    const maxAttempts = options.maxAttempts ?? 120

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      const entry = await fetchAuditLogExportStatus(exportId)
      if (entry.status === 'COMPLETED' || entry.status === 'FAILED') {
        return entry
      }
      await sleep(intervalMs)
    }

    throw new Error('EXPORT_POLL_TIMEOUT')
  }

  async function downloadAuditLogExport(exportId: number): Promise<void> {
    // Fetch as a blob so the raw CSV bytes (including the server's BOM) are preserved.
    // useApi's get() lets $fetch content-negotiate and parse the body, corrupting the file.
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const blob = await $fetch<Blob>(`${baseURL}/api/v1/audit-logs/export/${exportId}/download`, {
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

  async function exportEngineAuditLogs(filters: EngineAuditFilters = {}): Promise<void> {
    const entry = await requestAuditLogExport(filters)
    const completed = await pollAuditLogExportUntilComplete(entry.id)
    if (completed.status === 'FAILED') {
      throw new Error('AUDIT_LOG_EXPORT_FAILED')
    }
    await downloadAuditLogExport(completed.id)
  }

  return {
    fetchAuditLogs,
    fetchEngineAuditLogs,
    fetchEngineAuditLogDetail,
    exportEngineAuditLogs,
    requestAuditLogExport,
    fetchAuditLogExportStatus,
    pollAuditLogExportUntilComplete,
    downloadAuditLogExport,
  }
}
