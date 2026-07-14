import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequests } from '@/composables/useEngineRequests'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPostWithMeta = vi.fn()
const mockPatch = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    postWithMeta: mockPostWithMeta,
    patch: mockPatch,
    put: vi.fn(),
    del: vi.fn(),
  }),
}))

describe('useEngineRequests', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockPostWithMeta.mockReset()
    mockPatch.mockReset()
  })

  it('fetchList populates instances and meta on success', async () => {
    mockGet.mockResolvedValue({
      data: [{ id: 1, reference: 'ENG-2026-000001' }],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const { instances, instancesMeta, fetchList } = useEngineRequests()

    await fetchList()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests', expect.any(Object))
    expect(instances.value).toHaveLength(1)
    expect(instancesMeta.value?.total).toBe(1)
  })

  it('fetchList sets error message on failure', async () => {
    mockGet.mockRejectedValue({ data: { message: 'فشل' } })
    const { instances, error, fetchList } = useEngineRequests()

    await fetchList()

    expect(instances.value).toEqual([])
    expect(error.value).toBe('فشل')
  })

  it('fetchQueue calls the my-queue endpoint', async () => {
    mockGet.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
    })
    const { fetchQueue } = useEngineRequests()

    await fetchQueue()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/my-queue', expect.any(Object))
  })

  it('fetchAvailableWorkflows populates availableWorkflows', async () => {
    mockGet.mockResolvedValue({
      data: [
        {
          id: 1,
          code: 'IMPORT_FINANCING',
          name: 'تمويل الواردات',
          version_id: 10,
          version_number: 1,
        },
      ],
    })
    const { availableWorkflows, fetchAvailableWorkflows } = useEngineRequests()

    await fetchAvailableWorkflows()

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/available-workflows')
    expect(availableWorkflows.value).toHaveLength(1)
  })

  it('submit posts payload with the idempotency key header and returns the created instance on 201', async () => {
    mockPostWithMeta.mockResolvedValue({
      status: 201,
      headers: new Headers(),
      data: {
        success: true,
        data: { id: 5, reference: 'ENG-2026-000005' },
        warnings: [],
      },
    })
    const { submit } = useEngineRequests()

    const result = await submit('idem-key-1', { workflow_version_id: 10, data: {} })

    expect(mockPostWithMeta).toHaveBeenCalledWith(
      '/api/v1/engine-requests',
      { workflow_version_id: 10, data: {} },
      { headers: { 'Idempotency-Key': 'idem-key-1' } },
    )
    expect(result.kind).toBe('completed')
    if (result.kind === 'completed') {
      expect(result.data.id).toBe(5)
      expect(result.warnings).toEqual([])
    }
  })

  it('submit returns an in_progress result with retryAfterSeconds on a 202', async () => {
    mockPostWithMeta.mockResolvedValue({
      status: 202,
      headers: new Headers({ 'Retry-After': '3' }),
      data: { status: 'processing' },
    })
    const { submit } = useEngineRequests()

    const result = await submit('idem-key-1', { workflow_version_id: 10, data: {} })

    expect(result).toEqual({ kind: 'in_progress', retryAfterSeconds: 3 })
  })

  it('show fetches a single instance by id', async () => {
    mockGet.mockResolvedValue({ success: true, data: { id: 5, reference: 'ENG-2026-000005' } })
    const { show, current } = useEngineRequests()

    const result = await show(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5')
    expect(result.id).toBe(5)
    expect(current.value?.id).toBe(5)
  })
})
