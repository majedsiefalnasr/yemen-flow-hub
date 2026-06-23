import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

const { useCompliance } = await import('../../../composables/useCompliance')

describe('useCompliance', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchDuplicateInvoices calls correct endpoint', async () => {
    mockGet.mockResolvedValueOnce({
      data: [{ invoice_number: 'INV-001', count: 2, requests: [] }],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const { fetchDuplicateInvoices } = useCompliance()
    const result = await fetchDuplicateInvoices()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/compliance/duplicate-invoices')
    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.invoice_number).toBe('INV-001')
  })

  it('fetchExpiredDocuments calls correct endpoint', async () => {
    mockGet.mockResolvedValueOnce({
      data: [
        {
          merchant_id: 1,
          merchant_name: 'Test',
          bank: 'Bank',
          expired_documents: [{ type: 'tax_card', expired_at: '2026-01-01' }],
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const { fetchExpiredDocuments } = useCompliance()
    const result = await fetchExpiredDocuments()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/compliance/expired-documents')
    expect(result.data[0]?.expired_documents[0]?.type).toBe('tax_card')
  })

  it('fetchSlaBreaches calls correct endpoint', async () => {
    mockGet.mockResolvedValueOnce({
      data: [
        {
          id: 42,
          reference: 'REF-001',
          bank: 'Bank',
          stage: 'Intake',
          sla_status: 'breached',
          amount: 10000,
          currency: 'USD',
          created_at: '2026-06-23T10:00:00Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const { fetchSlaBreaches } = useCompliance()
    const result = await fetchSlaBreaches()
    expect(mockGet).toHaveBeenCalledWith('/api/v1/compliance/sla-breaches')
    expect(result.data[0]?.sla_status).toBe('breached')
  })

  it('passes bank_id filter', async () => {
    mockGet.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
    })
    const { fetchDuplicateInvoices } = useCompliance()
    await fetchDuplicateInvoices({ bank_id: 5 })
    expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('bank_id=5'))
  })
})
