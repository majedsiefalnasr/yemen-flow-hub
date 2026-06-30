import { vi, describe, it, expect, beforeEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const PAGINATED_FIXTURE = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}

describe('useRequests – extended list filters (Story 7.3)', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockGet.mockResolvedValue({ success: true, message: 'OK', data: PAGINATED_FIXTURE })
  })

  it('appends bank_id param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ bank_id: 3 })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('bank_id=3')
  })

  it('omits bank_id when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ bank_id: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('appends currency param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ currency: 'USD' })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('currency=USD')
  })

  it('omits currency when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ currency: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('combines bank_id, currency, search, and status together', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({
      bank_id: 2,
      currency: 'EUR',
      search: 'YFH',
      status: RequestStatus.SUBMITTED,
      page: 1,
    })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('bank_id=2')
    expect(calledWith).toContain('currency=EUR')
    expect(calledWith).toContain('search=YFH')
    expect(calledWith).toContain('status=SUBMITTED')
    expect(calledWith).toContain('page=1')
  })

  it('serializes multiple statuses as a comma-separated query value', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({
      status: [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW],
    })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('status=SUBMITTED%2CBANK_REVIEW')
  })

  it('appends advanced filter params when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({
      from_date: '2026-05-01',
      to_date: '2026-05-31',
      claim_filter: 'available',
    })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('from_date=2026-05-01')
    expect(calledWith).toContain('to_date=2026-05-31')
    expect(calledWith).toContain('claim_filter=available')
  })

  it('uses correct API path without trailing ?', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({})

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  // ── Story 8.9: advanced filters ──────────────────────────────────────────────

  it('appends created_from param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ created_from: '2026-01-01' })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('created_from=2026-01-01')
  })

  it('appends created_to param when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ created_to: '2026-12-31' })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('created_to=2026-12-31')
  })

  it('omits created_from when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ created_from: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('appends amount_min when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ amount_min: 5000 })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('amount_min=5000')
  })

  it('appends amount_max when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ amount_max: 99999 })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('amount_max=99999')
  })

  it('omits amount_min when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ amount_min: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('omits amount_max when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ amount_max: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('appends assigned_reviewer_id when provided', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ assigned_reviewer_id: 42 })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('assigned_reviewer_id=42')
  })

  it('omits assigned_reviewer_id when empty string', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ assigned_reviewer_id: '' })

    expect(mockGet).toHaveBeenCalledWith('/api/requests')
  })

  it('combines all advanced filters with existing filters', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({
      currency: 'USD',
      created_from: '2026-01-01',
      created_to: '2026-12-31',
      amount_min: 1000,
      amount_max: 50000,
      assigned_reviewer_id: 7,
    })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('currency=USD')
    expect(calledWith).toContain('created_from=2026-01-01')
    expect(calledWith).toContain('created_to=2026-12-31')
    expect(calledWith).toContain('amount_min=1000')
    expect(calledWith).toContain('amount_max=50000')
    expect(calledWith).toContain('assigned_reviewer_id=7')
  })

  it('prefers created_from over legacy from_date when both set', async () => {
    const { fetchRequests } = useRequests()
    await fetchRequests({ created_from: '2026-03-01', from_date: '2026-01-01' })

    const calledWith = (mockGet.mock.calls[0] as [string])[0]
    expect(calledWith).toContain('created_from=2026-03-01')
    expect(calledWith).not.toContain('from_date=')
  })
})
