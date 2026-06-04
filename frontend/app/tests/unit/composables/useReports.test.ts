import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockFetch = vi.fn()

const mockPost = vi.fn()
const mockDel = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDel }),
}))

vi.stubGlobal('$fetch', mockFetch)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

const { useReports } = await import('../../../composables/useReports')

const WORKFLOW_REPORT = {
  counts_by_status: { DRAFT: 2, SUBMITTED: 1 },
  counts_by_bank: [{ bank_id: 1, bank_name: 'بنك اليمن', total: 3 }],
  avg_time_per_stage_hours: { SUBMITTED: 4.5 },
  throughput: { completed: 1, approved: 0, rejected: 1 },
}

const BANK_REPORT = {
  total_requests: 5,
  approved_count: 3,
  rejected_count: 1,
  pending_count: 1,
  approval_rate: 60.0,
  rejection_rate: 20.0,
  avg_processing_hours: 12.5,
}

describe('useReports — fetchWorkflowReport()', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls GET /api/reports/workflow without filters', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: WORKFLOW_REPORT })

    const { fetchWorkflowReport } = useReports()
    const result = await fetchWorkflowReport()

    expect(mockGet).toHaveBeenCalledWith('/api/reports/workflow')
    expect(result.counts_by_status).toEqual({ DRAFT: 2, SUBMITTED: 1 })
  })

  it('appends from_date and to_date to query string', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: WORKFLOW_REPORT })

    const { fetchWorkflowReport } = useReports()
    await fetchWorkflowReport({ fromDate: '2026-01-01', toDate: '2026-03-31' })

    expect(mockGet).toHaveBeenCalledWith(
      '/api/reports/workflow?from_date=2026-01-01&to_date=2026-03-31',
    )
  })

  it('omits date params when not provided', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: WORKFLOW_REPORT })

    const { fetchWorkflowReport } = useReports()
    await fetchWorkflowReport({})

    expect(mockGet).toHaveBeenCalledWith('/api/reports/workflow')
  })
})

describe('useReports — fetchBankReport()', () => {
  beforeEach(() => vi.resetAllMocks())

  it('calls GET /api/reports/bank', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: BANK_REPORT })

    const { fetchBankReport } = useReports()
    const result = await fetchBankReport()

    expect(mockGet).toHaveBeenCalledWith('/api/reports/bank')
    expect(result.total_requests).toBe(5)
    expect(result.approval_rate).toBe(60.0)
  })

  it('appends date filter to bank report URL', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: BANK_REPORT })

    const { fetchBankReport } = useReports()
    await fetchBankReport({ fromDate: '2026-04-01' })

    expect(mockGet).toHaveBeenCalledWith('/api/reports/bank?from_date=2026-04-01')
  })
})

describe('useReports — exportReport()', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    // Stub DOM APIs not available in node environment
    const anchor = { href: '', download: '', click: vi.fn() }
    const bodyStub = {
      appendChild: vi.fn(),
      removeChild: vi.fn(),
    }
    vi.stubGlobal('document', {
      createElement: vi.fn(() => anchor),
      body: bodyStub,
    })
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:url'), revokeObjectURL: vi.fn() })
  })

  it('fetches blob from /api/reports/workflow/export for excel format', async () => {
    mockFetch.mockResolvedValueOnce(new Blob(['csv-content'], { type: 'text/csv' }))

    const { exportReport } = useReports()
    await exportReport('workflow', 'excel')

    expect(mockFetch).toHaveBeenCalledWith(
      'http://localhost/api/reports/workflow/export?format=excel',
      expect.objectContaining({ responseType: 'blob' }),
    )
  })

  it('fetches blob from /api/reports/bank/export for pdf format', async () => {
    mockFetch.mockResolvedValueOnce(new Blob(['pdf-content'], { type: 'application/pdf' }))

    const { exportReport } = useReports()
    await exportReport('bank', 'pdf', { fromDate: '2026-01-01' })

    expect(mockFetch).toHaveBeenCalledWith(
      'http://localhost/api/reports/bank/export?format=pdf&from_date=2026-01-01',
      expect.objectContaining({ responseType: 'blob' }),
    )
  })
})

describe('useReports — WorkflowReport new 7.8 fields', () => {
  beforeEach(() => vi.resetAllMocks())

  it('WorkflowReport interface accepts all 6 new analytics fields', async () => {
    const report = {
      counts_by_status: { DRAFT: 1 },
      counts_by_bank: [],
      avg_time_per_stage_hours: {},
      throughput: { completed: 0, approved: 0, rejected: 0 },
      monthly_trend: [{ month: '2026-01', total: 5, approved: 3, rejected: 1 }],
      category_distribution: [{ category: 'Electronics', count: 5 }],
      amount_by_currency: [{ currency: 'USD', amount: 10000 }],
      submission_heatmap: [{ day: 1, slot: 8, count: 3 }],
      total_financing_value: 50000,
      duplicate_invoice_count: 2,
    }
    mockGet.mockResolvedValueOnce({ success: true, data: report })

    const { fetchWorkflowReport } = useReports()
    const result = await fetchWorkflowReport()

    expect(result.monthly_trend).toHaveLength(1)
    expect(result.monthly_trend[0]).toMatchObject({
      month: '2026-01',
      total: 5,
      approved: 3,
      rejected: 1,
    })
    expect(result.category_distribution).toHaveLength(1)
    expect(result.amount_by_currency[0]).toMatchObject({ currency: 'USD', amount: 10000 })
    expect(result.total_financing_value).toBe(50000)
    expect(result.duplicate_invoice_count).toBe(2)
  })

  it('fetchWorkflowReport maps monthly_trend response correctly', async () => {
    const trend = [
      { month: '2026-03', total: 10, approved: 7, rejected: 2 },
      { month: '2026-04', total: 15, approved: 11, rejected: 3 },
    ]
    mockGet.mockResolvedValueOnce({
      success: true,
      data: {
        counts_by_status: {},
        counts_by_bank: [],
        avg_time_per_stage_hours: {},
        throughput: { completed: 0, approved: 0, rejected: 0 },
        monthly_trend: trend,
        category_distribution: [],
        amount_by_currency: [],
        submission_heatmap: [],
        total_financing_value: 0,
        duplicate_invoice_count: 0,
      },
    })

    const { fetchWorkflowReport } = useReports()
    const result = await fetchWorkflowReport()

    expect(result.monthly_trend).toHaveLength(2)
    expect(result.monthly_trend[1]!.month).toBe('2026-04')
    expect(result.monthly_trend[1]!.approved).toBe(11)
  })

  it('submission_heatmap entries have day, slot, count keys', async () => {
    mockGet.mockResolvedValueOnce({
      success: true,
      data: {
        counts_by_status: {},
        counts_by_bank: [],
        avg_time_per_stage_hours: {},
        throughput: { completed: 0, approved: 0, rejected: 0 },
        monthly_trend: [],
        category_distribution: [],
        amount_by_currency: [],
        submission_heatmap: [{ day: 2, slot: 10, count: 5 }],
        total_financing_value: 0,
        duplicate_invoice_count: 0,
      },
    })

    const { fetchWorkflowReport } = useReports()
    const result = await fetchWorkflowReport()

    expect(result.submission_heatmap[0]).toHaveProperty('day')
    expect(result.submission_heatmap[0]).toHaveProperty('slot')
    expect(result.submission_heatmap[0]).toHaveProperty('count')
    expect(result.submission_heatmap[0]!.day).toBe(2)
  })

  it('BankReport interface still passes with existing fields unchanged', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: BANK_REPORT })

    const { fetchBankReport } = useReports()
    const result = await fetchBankReport()

    expect(result).toMatchObject({
      total_requests: 5,
      approved_count: 3,
      rejected_count: 1,
      pending_count: 1,
      approval_rate: 60.0,
      rejection_rate: 20.0,
      avg_processing_hours: 12.5,
    })
  })

  it('BankReport interface accepts optional analytics fields from 7.8 bank endpoint', async () => {
    const bankReportWithAnalytics = {
      ...BANK_REPORT,
      monthly_trend: [{ month: '2026-05', total: 3, approved: 2, rejected: 0 }],
      category_distribution: [{ category: 'Electronics', count: 3 }],
      amount_by_currency: [{ currency: 'USD', amount: 5000 }],
      submission_heatmap: [{ day: 2, slot: 10, count: 2 }],
    }
    mockGet.mockResolvedValueOnce({ success: true, data: bankReportWithAnalytics })

    const { fetchBankReport } = useReports()
    const result = await fetchBankReport()

    expect(result.monthly_trend).toHaveLength(1)
    expect(result.category_distribution![0]).toMatchObject({ category: 'Electronics', count: 3 })
    expect(result.amount_by_currency![0]).toMatchObject({ currency: 'USD', amount: 5000 })
    expect(result.submission_heatmap![0]).toMatchObject({ day: 2, slot: 10, count: 2 })
  })
})

describe('useReports — preset management (API-backed)', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockDel.mockReset()
  })

  it('loadPresets returns data from API', async () => {
    const stored = [{ id: '1', name: 'Q1', filter: {}, createdAt: '2026-01-01T00:00:00.000Z' }]
    mockGet.mockResolvedValue({ data: stored })
    const { loadPresets } = useReports()
    const result = await loadPresets()
    expect(mockGet).toHaveBeenCalledWith('/api/report-presets')
    expect(result).toEqual(stored)
  })

  it('loadPresets returns empty array on API error', async () => {
    mockGet.mockRejectedValue(new Error('network'))
    const { loadPresets } = useReports()
    expect(await loadPresets()).toEqual([])
  })

  it('savePreset posts to API and returns preset', async () => {
    mockPost.mockResolvedValue({ data: [] })
    const { savePreset } = useReports()
    const preset = await savePreset('Q1 2026', { fromDate: '2026-01-01', toDate: '2026-03-31' })
    expect(mockPost).toHaveBeenCalledWith(
      '/api/report-presets',
      expect.objectContaining({ name: 'Q1 2026' }),
    )
    expect(preset.name).toBe('Q1 2026')
    expect(preset.filter.fromDate).toBe('2026-01-01')
    expect(preset.id).toBeTruthy()
  })

  it('savePreset truncates name at 50 chars', async () => {
    mockPost.mockResolvedValue({ data: [] })
    const { savePreset } = useReports()
    const preset = await savePreset('a'.repeat(60), {})
    expect(preset.name).toHaveLength(50)
  })

  it('deletePreset calls DELETE endpoint', async () => {
    mockDel.mockResolvedValue({ data: [] })
    const { deletePreset } = useReports()
    await deletePreset('abc-123')
    expect(mockDel).toHaveBeenCalledWith('/api/report-presets/abc-123')
  })
})
