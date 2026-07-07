import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useAudit } = await import('../../../composables/useAudit')

const ENGINE_LOG_FIXTURE = {
  id: 1,
  actor: { id: 5, name: 'مدير النظام', email: 'admin@cby.ye' },
  actor_user_id: 5,
  actor_role: { id: 1, code: 'admin', name: 'مدير النظام' },
  actor_role_id: 1,
  user_role: 'CBY_ADMIN',
  event_code: 'STATUS_TRANSITION',
  entity_type: 'EngineRequest',
  entity_id: 42,
  request_id: 42,
  correlation_id: null,
  old_values: { from_status: 'BANK_REVIEW' },
  new_values: { to_status: 'BANK_APPROVED' },
  metadata: { reason: null, from_status: 'BANK_REVIEW', to_status: 'BANK_APPROVED' },
  ip_address: '127.0.0.1',
  user_agent: 'Test Agent',
  created_at: '2026-05-18T10:00:00.000Z',
}

const V1_PAGINATED_RESPONSE = {
  data: [ENGINE_LOG_FIXTURE],
  meta: { current_page: 1, last_page: 3, per_page: 30, total: 75 },
}

describe('useAudit — fetchAuditLogs (V1 audit-logs)', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/v1/audit-logs for the main audit table', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ page: 1 })
    expect(mockGet).toHaveBeenCalledWith('/api/v1/audit-logs?page=1')
  })

  it('calls GET /api/v1/audit-logs without filters by default', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/audit-logs')
  })

  it('maps action filter to event query param', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ action: 'STATUS_TRANSITION' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('event=STATUS_TRANSITION'))
  })

  it('maps from_date and to_date to from and to query params', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ from_date: '2026-05-01', to_date: '2026-05-18' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('from=2026-05-01'))
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('to=2026-05-18'))
  })

  it('maps user_id filter to user query param', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ user_id: 5 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('user=5'))
  })

  it('returns paginated response with data and meta', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs()
    expect(result.data).toHaveLength(1)
    expect(result.meta.total).toBe(75)
    expect(result.meta.last_page).toBe(3)
  })

  it('maps V1 engine audit log entries to AuditLog shape', async () => {
    mockGet.mockResolvedValueOnce(V1_PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs()
    const log = result.data[0]
    expect(log?.action).toBe('STATUS_TRANSITION')
    expect(log?.entity_type).toBe('EngineRequest')
    expect(log?.from_status).toBe('BANK_REVIEW')
    expect(log?.to_status).toBe('BANK_APPROVED')
    expect(log?.user?.name).toBe('مدير النظام')
  })

  it('returns empty data array when no logs match', async () => {
    mockGet.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 },
    })
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs({ action: 'nonexistent_action' })
    expect(result.data).toEqual([])
    expect(result.meta.total).toBe(0)
  })

  it('propagates API error', async () => {
    mockGet.mockRejectedValueOnce(new Error('Forbidden'))
    const { fetchAuditLogs } = useAudit()
    await expect(fetchAuditLogs()).rejects.toThrow('Forbidden')
  })
})

describe('useAudit — V1 engine audit (Story 18.6.1)', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchEngineAuditLogs calls GET /api/v1/audit-logs', async () => {
    mockGet.mockResolvedValueOnce({
      data: [ENGINE_LOG_FIXTURE],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    })
    const { fetchEngineAuditLogs } = useAudit()
    const result = await fetchEngineAuditLogs()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/audit-logs')
    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.event_code).toBe('STATUS_TRANSITION')
    expect(result.data[0]?.correlation_id).toBeNull()
  })

  it('fetchEngineAuditLogs passes all filters', async () => {
    mockGet.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 },
    })
    const { fetchEngineAuditLogs } = useAudit()
    await fetchEngineAuditLogs({ event: 'LOGIN', user: 5, from: '2026-06-01', ip: '10.0.0.1' })
    const url = mockGet.mock.calls[0]?.[0] as string
    expect(url).toContain('event=LOGIN')
    expect(url).toContain('user=5')
    expect(url).toContain('from=2026-06-01')
    expect(url).toContain('ip=10.0.0.1')
  })

  it('fetchEngineAuditLogDetail calls GET /api/v1/audit-logs/:id', async () => {
    mockGet.mockResolvedValueOnce({ data: ENGINE_LOG_FIXTURE })
    const { fetchEngineAuditLogDetail } = useAudit()
    const result = await fetchEngineAuditLogDetail(100)
    expect(mockGet).toHaveBeenCalledWith('/api/v1/audit-logs/100')
    expect(result.id).toBe(1)
    expect(result.old_values).toEqual({ from_status: 'BANK_REVIEW' })
    expect(result.new_values).toEqual({ to_status: 'BANK_APPROVED' })
  })

  it('fetchEngineAuditLogs filters by correlation_id', async () => {
    mockGet.mockResolvedValueOnce({
      data: [{ ...ENGINE_LOG_FIXTURE, correlation_id: 'abc-123' }],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    })
    const { fetchEngineAuditLogs } = useAudit()
    await fetchEngineAuditLogs({ correlation_id: 'abc-123' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('correlation_id=abc-123'))
  })
})
