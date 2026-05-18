import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockFetch = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
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

    expect(mockGet).toHaveBeenCalledWith('/api/reports/workflow?from_date=2026-01-01&to_date=2026-03-31')
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

// localStorage stub for node environment
const localStorageStore: Map<string, string> = new Map()
const localStorageStub = {
  getItem: (key: string) => localStorageStore.get(key) ?? null,
  setItem: (key: string, value: string) => { localStorageStore.set(key, value) },
  removeItem: (key: string) => { localStorageStore.delete(key) },
  clear: () => { localStorageStore.clear() },
}
vi.stubGlobal('localStorage', localStorageStub)

describe('useReports — preset management', () => {
  const STORAGE_KEY = 'reports_presets'

  beforeEach(() => {
    localStorageStore.clear()
  })

  it('loadPresets returns empty array when nothing stored', () => {
    const { loadPresets } = useReports()
    expect(loadPresets()).toEqual([])
  })

  it('savePreset stores a preset and returns it', () => {
    const { savePreset, loadPresets } = useReports()
    const preset = savePreset('Q1 2026', { fromDate: '2026-01-01', toDate: '2026-03-31' })

    expect(preset.name).toBe('Q1 2026')
    expect(preset.filter.fromDate).toBe('2026-01-01')
    expect(preset.id).toBeTruthy()

    const stored = loadPresets()
    expect(stored).toHaveLength(1)
    expect(stored[0].name).toBe('Q1 2026')
  })

  it('savePreset truncates name at 50 chars', () => {
    const { savePreset } = useReports()
    const longName = 'a'.repeat(60)
    const preset = savePreset(longName, {})
    expect(preset.name).toHaveLength(50)
  })

  it('deletePreset removes the preset by id', () => {
    const { savePreset, deletePreset, loadPresets } = useReports()
    const p1 = savePreset('Preset 1', {})
    const p2 = savePreset('Preset 2', {})

    deletePreset(p1.id)

    const remaining = loadPresets()
    expect(remaining).toHaveLength(1)
    expect(remaining[0].id).toBe(p2.id)
  })

  it('deletePreset on non-existent id leaves presets unchanged', () => {
    const { savePreset, deletePreset, loadPresets } = useReports()
    savePreset('Keep', {})
    deletePreset('non-existent-id')
    expect(loadPresets()).toHaveLength(1)
  })

  it('loadPresets returns empty array on corrupt localStorage', () => {
    localStorageStub.setItem(STORAGE_KEY, '{invalid json!!!}')
    const { loadPresets } = useReports()
    expect(loadPresets()).toEqual([])
  })
})
