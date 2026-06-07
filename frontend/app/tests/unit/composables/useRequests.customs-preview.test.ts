import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: vi.fn(), put: vi.fn() }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const DECLARATION_FIXTURE = {
  id: 7,
  request_id: 42,
  declaration_number: 'CD-2026-000001',
  issued_by: 99,
  issuer: { id: 99, name: 'مدير اللجنة', email: 'director@cby.ye', role: 'COMMITTEE_DIRECTOR' },
  issued_at: '2026-05-18T10:00:00.000000Z',
  request: { id: 42, reference_number: 'YFH-2026-000042', bank_name: 'بنك اليمن' },
  metadata: {
    reference_number: 'YFH-2026-000042',
    bank: { id: 1, name: 'بنك اليمن', code: 'YCB' },
    supplier_name: 'Supplier Co.',
    amount: 10000,
    currency: 'USD',
    goods_description: 'Industrial equipment',
    port_of_entry: 'Aden Port',
    bank_approved_at: '2026-05-10T10:00:00.000000Z',
    support_approved_at: '2026-05-12T10:00:00.000000Z',
    executive_decided_at: '2026-05-14T10:00:00.000000Z',
  },
  created_at: '2026-05-18T10:00:00.000000Z',
}

describe('useRequests — fetchCustomsPreview()', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/requests/{id}/customs-preview and returns declaration', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: DECLARATION_FIXTURE })

    const { fetchCustomsPreview } = useRequests()
    const result = await fetchCustomsPreview(42)

    expect(mockGet).toHaveBeenCalledWith('/api/requests/42/customs-preview')
    expect(result.id).toBe(7)
    expect(result.declaration_number).toBe('CD-2026-000001')
    expect(result.issuer?.name).toBe('مدير اللجنة')
  })

  it('returns declaration with metadata containing supplier and bank info', async () => {
    mockGet.mockResolvedValueOnce({ success: true, message: 'OK', data: DECLARATION_FIXTURE })

    const { fetchCustomsPreview } = useRequests()
    const result = await fetchCustomsPreview(42)
    const meta = result.metadata as Record<string, unknown>

    expect(meta.supplier_name).toBe('Supplier Co.')
    expect((meta.bank as { code: string }).code).toBe('YCB')
    expect(meta.amount).toBe(10000)
    expect(meta.currency).toBe('USD')
  })

  it('propagates API error (403) without swallowing', async () => {
    const err = Object.assign(new Error('Forbidden'), { statusCode: 403 })
    mockGet.mockRejectedValueOnce(err)

    const { fetchCustomsPreview } = useRequests()
    await expect(fetchCustomsPreview(42)).rejects.toThrow('Forbidden')
  })

  it('propagates API error (404) without swallowing', async () => {
    const err = Object.assign(new Error('Not Found'), { statusCode: 404 })
    mockGet.mockRejectedValueOnce(err)

    const { fetchCustomsPreview } = useRequests()
    await expect(fetchCustomsPreview(42)).rejects.toThrow('Not Found')
  })
})
