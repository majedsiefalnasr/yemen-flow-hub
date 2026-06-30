import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { ref } from 'vue'
import { useEngineClaim } from '@/composables/useEngineClaim'

const mockPost = vi.fn()
const mockDel = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post: mockPost, del: mockDel }),
}))

describe('useEngineClaim', () => {
  beforeEach(() => {
    mockPost.mockReset()
    mockDel.mockReset()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('claim() posts to the claim endpoint and sets holder', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1, claimed_by: 7 } })
    const { claim, isHeldByMe } = useEngineClaim(ref(1), ref(7))

    await claim()

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests/1/claim')
    expect(isHeldByMe.value).toBe(true)
  })

  it('heldByOther is true when claimed by a different user', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1, claimed_by: 9 } })
    const { claim, isHeldByMe, heldByOther } = useEngineClaim(ref(1), ref(7))

    await claim()

    expect(isHeldByMe.value).toBe(false)
    expect(heldByOther.value).toBe(true)
  })

  it('release() deletes the claim and clears holder state', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1, claimed_by: 7 } })
    mockDel.mockResolvedValue({ success: true, data: { id: 1, claimed_by: null } })
    const { claim, release, isHeldByMe } = useEngineClaim(ref(1), ref(7))

    await claim()
    await release()

    expect(mockDel).toHaveBeenCalledWith('/api/v1/engine-requests/1/claim')
    expect(isHeldByMe.value).toBe(false)
  })

  it('heartbeat() posts to the heartbeat endpoint', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1, claimed_by: 7 } })
    const { heartbeat } = useEngineClaim(ref(1), ref(7))

    await heartbeat()

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests/1/claim/heartbeat')
  })

  it('starts a 60s heartbeat loop once held by me and stops on release', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1, claimed_by: 7 } })
    mockDel.mockResolvedValue({ success: true, data: { id: 1, claimed_by: null } })
    const { claim, release } = useEngineClaim(ref(1), ref(7))

    await claim()
    expect(mockPost).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockPost).toHaveBeenCalledTimes(2)
    expect(mockPost).toHaveBeenLastCalledWith('/api/v1/engine-requests/1/claim/heartbeat')

    await release()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockPost).toHaveBeenCalledTimes(2)
  })
})
