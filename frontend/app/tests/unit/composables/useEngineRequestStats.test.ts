import { describe, expect, it, vi } from 'vitest'
import { useEngineRequestStats } from '@/composables/useEngineRequestStats'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

describe('useEngineRequestStats', () => {
  it('fetchStats calls /api/v1/engine-requests/stats with scope and filters', async () => {
    mockGet.mockResolvedValue({
      data: {
        total: 42,
        active: 30,
        breached_sla: 2,
        nearing_sla: 1,
        unclaimed_active: 3,
        by_status: { ACTIVE: 30 },
      },
    })

    const { stats, fetchStats } = useEngineRequestStats()
    await fetchStats({ scope: 'all', search: 'INV-1' })

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/stats', {
      query: { scope: 'all', search: 'INV-1' },
    })
    expect(stats.value?.total).toBe(42)
  })
})
