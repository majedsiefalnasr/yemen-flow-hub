import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { RequestStageHistory } from '../../../types/models'

// Mock useApi at module level
const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: vi.fn(), put: vi.fn() }),
}))

vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost' } }),
}))

const { useRequests } = await import('../../../composables/useRequests')

const makeHistory = (overrides: Partial<RequestStageHistory> = {}): RequestStageHistory => ({
  id: 1,
  request_id: 10,
  from_status: 'DRAFT',
  to_status: 'SUBMITTED',
  from_owner_role: 'DATA_ENTRY',
  to_owner_role: 'BANK_REVIEWER',
  actor_id: 3,
  actor_role: 'DATA_ENTRY',
  performed_by: { id: 3, name: 'علي أحمد', role: 'DATA_ENTRY' },
  action: 'submit',
  notes: null,
  metadata: null,
  created_at: '2026-05-17T08:00:00.000Z',
  ...overrides,
})

describe('useRequests.fetchRequestHistory', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls GET /api/requests/{id}/history and returns the data array', async () => {
    const entry = makeHistory()
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: [entry] })

    const { fetchRequestHistory } = useRequests()
    const result = await fetchRequestHistory(10)

    expect(mockGet).toHaveBeenCalledWith('/api/requests/10/history')
    expect(result).toHaveLength(1)
    expect(result[0]!).toEqual(entry)
  })

  it('returns empty array when history is empty', async () => {
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: [] })

    const { fetchRequestHistory } = useRequests()
    const result = await fetchRequestHistory(5)

    expect(result).toEqual([])
  })

  it('returns multiple history entries in the order returned by the server', async () => {
    const entries = [
      makeHistory({ id: 1, action: 'submit', to_status: 'SUBMITTED' }),
      makeHistory({
        id: 2,
        action: 'bank_approve',
        from_status: 'SUBMITTED',
        to_status: 'BANK_APPROVED',
      }),
    ]
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: entries })

    const { fetchRequestHistory } = useRequests()
    const result = await fetchRequestHistory(10)

    expect(result).toHaveLength(2)
    expect(result[0]!.action).toBe('submit')
    expect(result[1]!.action).toBe('bank_approve')
  })

  it('propagates errors thrown by useApi.get', async () => {
    mockGet.mockRejectedValue(new Error('Network error'))

    const { fetchRequestHistory } = useRequests()
    await expect(fetchRequestHistory(10)).rejects.toThrow('Network error')
  })

  it('handles history entries with null from_status (first transition)', async () => {
    const entry = makeHistory({ from_status: null, to_status: 'DRAFT' })
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: [entry] })

    const { fetchRequestHistory } = useRequests()
    const result = await fetchRequestHistory(10)

    expect(result[0]!.from_status).toBeNull()
    expect(result[0]!.to_status).toBe('DRAFT')
  })

  it('handles history entries with null performed_by', async () => {
    const entry = makeHistory({ performed_by: null })
    mockGet.mockResolvedValue({ success: true, message: 'ok', data: [entry] })

    const { fetchRequestHistory } = useRequests()
    const result = await fetchRequestHistory(10)

    expect(result[0]!.performed_by).toBeNull()
    expect(result[0]!.actor_id).toBe(3)
  })
})
