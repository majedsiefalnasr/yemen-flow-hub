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
