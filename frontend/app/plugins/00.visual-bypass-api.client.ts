import { UserRole } from '../types/enums'

type AnyRecord = Record<string, any>

function apiOk<T>(data: T) {
  return { success: true, message: 'OK', data }
}

function paginated<T>(items: T[]) {
  return {
    data: items,
    meta: { current_page: 1, last_page: 1, per_page: 25, total: items.length },
  }
}

function resolveUrl(request: RequestInfo | URL, baseURL?: string): URL | null {
  try {
    if (request instanceof URL) return request
    if (typeof request === 'string') return new URL(request, baseURL ?? window.location.origin)
    return new URL(request.url, baseURL ?? window.location.origin)
  } catch {
    return null
  }
}

function isVisualApiTarget(url: URL): boolean {
  if (url.pathname === '/sanctum/csrf-cookie') return true
  if (!url.pathname.startsWith('/api/')) return false
  return ['localhost:8000', '127.0.0.1:8000'].includes(url.host)
}

function mockUser(role: UserRole) {
  const bankRoles = new Set<UserRole>([
    UserRole.DATA_ENTRY,
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
    UserRole.SWIFT_OFFICER,
  ])
  const isBankRole = bankRoles.has(role)
  return {
    id: 999001,
    name: 'Visual Bypass User',
    email: `visual-${role.toLowerCase()}@cby.local`,
    role,
    bank_id: isBankRole ? 11 : null,
    bank_name_ar: isBankRole ? 'البنك اليمني للتجارة والاستثمار' : null,
    bank_name_en: isBankRole ? 'YBTI' : null,
    is_active: true,
  }
}

function mockForPath(path: string, method: string, role: UserRole) {
  const emptyRequest: AnyRecord = {
    id: 1,
    reference_number: 'YFH-2026-000001',
    status: 'SUBMITTED',
    amount: 0,
    currency: 'USD',
    merchant: null,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }

  if (path === '/sanctum/csrf-cookie') return null
  if (path === '/api/auth/me') return apiOk(mockUser(role))
  if (path === '/api/dashboard/stats') return apiOk({})
  if (path === '/api/v1/notifications/unread-count') return apiOk({ count: 0 })
  if (path === '/api/v1/notifications') return apiOk(paginated([]))
  if (path === '/api/v1/banks') return apiOk([])
  if (path === '/api/v1/users') return apiOk([])
  if (path === '/api/merchants') return apiOk(paginated([]))
  if (path === '/api/document-types') return apiOk([])
  if (path === '/api/v1/audit-logs' || path.startsWith('/api/v1/audit-logs/')) {
    return { data: [], meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 } }
  }
  if (path === '/api/reports/workflow') {
    return apiOk({
      counts_by_status: {},
      counts_by_bank: [],
      avg_time_per_stage_hours: {},
      throughput: { completed: 0, approved: 0, rejected: 0 },
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      submission_heatmap: [],
      total_financing_value: 0,
      duplicate_invoice_count: 0,
    })
  }
  if (path === '/api/reports/bank') {
    return apiOk({
      total_requests: 0,
      approved_count: 0,
      rejected_count: 0,
      pending_count: 0,
      approval_rate: 0,
      rejection_rate: 0,
      avg_processing_hours: 0,
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      submission_heatmap: [],
      per_bank: [],
    })
  }
  if (path === '/api/requests') return apiOk(paginated([]))
  if (/^\/api\/requests\/\d+$/.test(path)) return apiOk(emptyRequest)
  if (/^\/api\/requests\/\d+\/history$/.test(path)) return apiOk([])
  if (/^\/api\/requests\/\d+\/customs-preview$/.test(path))
    return apiOk({
      id: 1,
      request_id: 1,
      declaration_number: 'FX-0001',
      issued_by: 1,
      issuer: null,
      issued_at: new Date().toISOString(),
      request: null,
      metadata: null,
      created_at: new Date().toISOString(),
    })

  if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
    return apiOk({})
  }

  return apiOk([])
}

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  if (!config.public.visualBypass) return

  const roleInput = String(config.public.visualBypassRole ?? 'CBY_ADMIN')
    .trim()
    .toUpperCase()
  const role = (Object.values(UserRole).find((r) => r === roleInput) ??
    UserRole.CBY_ADMIN) as UserRole

  const wrapFetch = (baseFetch: typeof globalThis.$fetch): typeof globalThis.$fetch => {
    const wrapped = (async (request: RequestInfo | URL, options?: any) => {
      const url = resolveUrl(request, options?.baseURL)
      if (!url || !isVisualApiTarget(url)) {
        return baseFetch(request as any, options)
      }

      const method = String(options?.method ?? 'GET').toUpperCase()
      const mocked = mockForPath(url.pathname, method, role)
      return mocked as any
    }) as typeof globalThis.$fetch

    Object.assign(wrapped, baseFetch)
    wrapped.create = (defaults?: any) =>
      wrapFetch(baseFetch.create(defaults) as typeof globalThis.$fetch)
    return wrapped
  }

  globalThis.$fetch = wrapFetch(globalThis.$fetch)
})
