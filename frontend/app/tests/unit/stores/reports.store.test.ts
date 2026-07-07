import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockRequestExport = vi.fn()
const mockPollExportUntilComplete = vi.fn()
const mockBuildExportFailureMessage = vi.fn()
const mockBuildExportTruncationMessage = vi.fn()
const mockIsExportFailed = vi.fn()

vi.mock('../../../composables/useReports', () => ({
  useReports: () => ({
    fetchWorkflowReport: vi.fn(),
    fetchBankReport: vi.fn(),
    loadPresets: vi.fn(),
    savePreset: vi.fn(),
    deletePreset: vi.fn(),
    exportReport: vi.fn(),
    requestExport: mockRequestExport,
    pollExportUntilComplete: mockPollExportUntilComplete,
    buildExportFailureMessage: mockBuildExportFailureMessage,
    buildExportTruncationMessage: mockBuildExportTruncationMessage,
    isExportFailed: mockIsExportFailed,
  }),
}))

const { useReportsStore } = await import('../../../stores/reports.store')

describe('reports store — failed export UX', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    mockRequestExport.mockResolvedValue({ id: 9, status: 'PENDING' })
    mockBuildExportFailureMessage.mockReturnValue('تعذّر إكمال التصدير. يرجى المحاولة مرة أخرى.')
    mockBuildExportTruncationMessage.mockReturnValue(null)
  })

  it('sets failure notice when async export returns FAILED', async () => {
    mockPollExportUntilComplete.mockResolvedValue({
      id: 9,
      status: 'FAILED',
      report_type: 'summary',
      filters: {},
      format: 'csv',
      created_at: '2026-06-23T10:00:00Z',
    })
    mockIsExportFailed.mockReturnValue(true)

    const store = useReportsStore()
    await store.exportWorkflow('excel')

    expect(store.exportFailureNotice).toContain('تعذّر')
    expect(store.lastFailedExportRequest).toEqual({
      reportType: 'summary',
      filters: { from: undefined, to: undefined },
      format: 'csv',
    })
  })

  it('retryFailedExport re-requests export with saved filters', async () => {
    mockPollExportUntilComplete
      .mockResolvedValueOnce({
        id: 9,
        status: 'FAILED',
        report_type: 'summary',
        filters: {},
        format: 'csv',
        created_at: '2026-06-23T10:00:00Z',
      })
      .mockResolvedValueOnce({
        id: 10,
        status: 'COMPLETED',
        report_type: 'summary',
        filters: { from: '2026-01-01' },
        format: 'csv',
        created_at: '2026-06-23T10:00:00Z',
      })
    mockIsExportFailed.mockReturnValueOnce(true).mockReturnValueOnce(false)

    const store = useReportsStore()
    store.applyFilters({ fromDate: '2026-01-01' })
    await store.exportWorkflow('excel')
    await store.retryFailedExport()

    expect(mockRequestExport).toHaveBeenLastCalledWith(
      'summary',
      {
        from: '2026-01-01',
        to: undefined,
      },
      'csv',
    )
    expect(store.exportFailureNotice).toBeNull()
    expect(store.lastFailedExportRequest).toBeNull()
  })
})
