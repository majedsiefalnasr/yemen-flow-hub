import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'

const mockGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet }),
}))

describe('useEngineFormSchema', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('fetchSchema populates fieldGroups on success', async () => {
    mockGet.mockResolvedValue({
      data: { field_groups: [{ id: 1, name: 'g', label: 'مجموعة', sort_order: 0, fields: [] }] },
    })
    const { fieldGroups, fetchSchema } = useEngineFormSchema()

    await fetchSchema(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/form-schema')
    expect(fieldGroups.value).toHaveLength(1)
  })

  it('fetchSchema sets error and empties fieldGroups on failure', async () => {
    mockGet.mockRejectedValue({ data: { message: 'فشل' } })
    const { fieldGroups, error, fetchSchema } = useEngineFormSchema()

    await fetchSchema(5)

    expect(fieldGroups.value).toEqual([])
    expect(error.value).toBe('فشل')
  })
})
