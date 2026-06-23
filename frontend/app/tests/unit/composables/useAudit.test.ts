import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useAudit } = await import('../../../composables/useAudit')

const LOG_FIXTURE = {
  id: 1,
  user: { id: 5, name: 'مدير النظام', email: 'admin@cby.ye', role: 'CBY_ADMIN' },
  user_id: 5,
  user_role: 'CBY_ADMIN',
  action: 'STATUS_TRANSITION',
  entity_type: 'ImportRequest',
  entity_id: 42,
  from_status: 'BANK_REVIEW',
  to_status: 'BANK_APPROVED',
  ip_address: '127.0.0.1',
  metadata: { reason: null },
  created_at: '2026-05-18T10:00:00.000Z',
}

const PAGINATED_RESPONSE = {
  success: true,
  message: 'OK',
  data: {
    data: [LOG_FIXTURE],
    meta: { current_page: 1, last_page: 3, per_page: 30, total: 75 },
  },
}

describe('useAudit — fetchAuditLogs', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/audit without filters by default', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs()
    expect(mockGet).toHaveBeenCalledWith('/api/audit')
  })

  it('appends action filter to query string', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ action: 'STATUS_TRANSITION' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('action=STATUS_TRANSITION'))
  })

  it('appends from_date and to_date filters', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ from_date: '2026-05-01', to_date: '2026-05-18' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('from_date=2026-05-01'))
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('to_date=2026-05-18'))
  })

  it('appends page parameter', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    await fetchAuditLogs({ page: 2 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('page=2'))
  })

  it('returns paginated response with data and meta', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs()
    expect(result.data).toHaveLength(1)
    expect(result.meta.total).toBe(75)
    expect(result.meta.last_page).toBe(3)
  })

  it('returns audit log entries with correct shape', async () => {
    mockGet.mockResolvedValueOnce(PAGINATED_RESPONSE)
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs()
    const log = result.data[0]
    expect(log?.action).toBe('STATUS_TRANSITION')
    expect(log?.entity_type).toBe('ImportRequest')
    expect(log?.from_status).toBe('BANK_REVIEW')
    expect(log?.to_status).toBe('BANK_APPROVED')
    expect(log?.user?.name).toBe('مدير النظام')
  })

  it('returns empty data array when no logs match', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 } },
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

describe('useAudit — new endpoints (Story 7.9)', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchAuditStats maps today_count and duplicate_invoice_count', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { today_count: 12, duplicate_invoice_count: 3 },
    })
    const { fetchAuditStats } = useAudit()
    const result = await fetchAuditStats()
    expect(result.today_count).toBe(12)
    expect(result.duplicate_invoice_count).toBe(3)
    expect(mockGet).toHaveBeenCalledWith('/api/audit/stats')
  })

  it('fetchDuplicates returns grouped DuplicateGroup array (Story 8.6)', async () => {
    const group = {
      invoice_number: 'INV-001',
      banks: ['بنك التضامن', 'بنك سبأ'],
      requests: [
        {
          id: 1,
          reference_number: 'YFH-2026-000001',
          bank_name: 'بنك التضامن',
          amount: 5000,
          currency: 'USD',
          created_at: '2026-05-18T10:00:00Z',
          status: 'DRAFT',
        },
        {
          id: 2,
          reference_number: 'YFH-2026-000002',
          bank_name: 'بنك سبأ',
          amount: 5000,
          currency: 'USD',
          created_at: '2026-05-18T11:00:00Z',
          status: 'SUBMITTED',
        },
      ],
    }
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: { data: [group] },
    })
    const { fetchDuplicates } = useAudit()
    const result = await fetchDuplicates()
    expect(result).toHaveLength(1)
    expect(result[0]?.invoice_number).toBe('INV-001')
    expect(result[0]?.banks).toEqual(['بنك التضامن', 'بنك سبأ'])
    expect(result[0]?.requests).toHaveLength(2)
    expect(mockGet).toHaveBeenCalledWith('/api/audit/duplicates')
  })

  it('fetchDuplicates returns empty array when backend has no groups', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: { data: [] } })
    const { fetchDuplicates } = useAudit()
    const result = await fetchDuplicates()
    expect(result).toEqual([])
  })

  it('fetchRiskIndicators returns array with title, body, level', async () => {
    const indicators = [
      { title: 'نمط غير عادي', body: 'تفصيل', level: 'عالية' as const },
      { title: 'محاولة مشبوهة', body: 'تفصيل 2', level: 'متوسطة' as const },
    ]
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: { data: indicators } })
    const { fetchRiskIndicators } = useAudit()
    const result = await fetchRiskIndicators()
    expect(result).toHaveLength(2)
    expect(result[0]?.level).toBe('عالية')
    expect(result[1]?.level).toBe('متوسطة')
    expect(mockGet).toHaveBeenCalledWith('/api/audit/risk-indicators')
  })

  it('fetchAuditLogs still works unchanged after composable extension', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      message: 'OK',
      data: {
        data: [LOG_FIXTURE],
        meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
      },
    })
    const { fetchAuditLogs } = useAudit()
    const result = await fetchAuditLogs()
    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.action).toBe('STATUS_TRANSITION')
  })
})

const ENGINE_LOG_FIXTURE = {
  id: 100,
  actor: { id: 5, name: 'Admin', email: 'admin@cby.ye' },
  actor_user_id: 5,
  actor_role: { id: 1, code: 'admin', name: 'مدير النظام' },
  actor_role_id: 1,
  user_role: 'CBY_ADMIN',
  event_code: 'STATUS_TRANSITION',
  entity_type: 'EngineRequest',
  entity_id: 42,
  request_id: 42,
  correlation_id: 'abc-123',
  old_values: { stage: 'INTAKE' },
  new_values: { stage: 'REVIEW' },
  metadata: {},
  ip_address: '10.0.0.1',
  user_agent: 'Test Agent',
  created_at: '2026-06-23T10:00:00.000Z',
}

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
    expect(result.data[0]?.correlation_id).toBe('abc-123')
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
    expect(result.id).toBe(100)
    expect(result.old_values).toEqual({ stage: 'INTAKE' })
    expect(result.new_values).toEqual({ stage: 'REVIEW' })
  })

  it('fetchEngineAuditLogs filters by correlation_id', async () => {
    mockGet.mockResolvedValueOnce({
      data: [ENGINE_LOG_FIXTURE],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    })
    const { fetchEngineAuditLogs } = useAudit()
    await fetchEngineAuditLogs({ correlation_id: 'abc-123' })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('correlation_id=abc-123'))
  })
})
