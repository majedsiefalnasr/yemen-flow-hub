import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest'

// Mock $fetch globally before importing the composable
const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)

// Mock useRuntimeConfig
vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost' } }),
}))

// Import after mocks are set up
const { useClaimLifecycle } = await import('../../../composables/useClaimLifecycle')

describe('useClaimLifecycle — claimRequest', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('initial state is idle', () => {
    const { isClaiming, isReleasing, claimError } = useClaimLifecycle()
    expect(isClaiming.value).toBe(false)
    expect(isReleasing.value).toBe(false)
    expect(claimError.value).toBeNull()
  })

  it('returns true on successful claim', async () => {
    mockFetch.mockResolvedValueOnce({ success: true })
    const { claimRequest, isClaiming } = useClaimLifecycle()

    const result = await claimRequest(42)
    expect(result).toBe(true)
    expect(isClaiming.value).toBe(false)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/42/claim-support-review',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('returns false and sets claimError on 409', async () => {
    const error = { response: { status: 409 } }
    mockFetch.mockRejectedValueOnce(error)

    const { claimRequest, claimError } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(claimError.value).toBeTruthy()
    expect(claimError.value).toContain('محجوز')
  })

  it('returns false and sets generic claimError on other errors', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'))

    const { claimRequest, claimError } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(claimError.value).toBeTruthy()
  })

  it('clears claimError on subsequent successful claim', async () => {
    mockFetch.mockRejectedValueOnce({ response: { status: 409 } })
    mockFetch.mockResolvedValueOnce({ success: true })

    const { claimRequest, claimError } = useClaimLifecycle()
    await claimRequest(42)
    expect(claimError.value).toBeTruthy()

    await claimRequest(42)
    expect(claimError.value).toBeNull()
  })
})

describe('useClaimLifecycle — releaseRequest', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('calls DELETE endpoint on release', async () => {
    mockFetch.mockResolvedValueOnce({})
    const { releaseRequest } = useClaimLifecycle()
    await releaseRequest(42)

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/42/claim-support-review',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })

  it('does not throw if release fails (best-effort)', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'))
    const { releaseRequest } = useClaimLifecycle()
    await expect(releaseRequest(42)).resolves.toBeUndefined()
  })
})

describe('useClaimLifecycle — heartbeat', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('startHeartbeat fires POST every 60 seconds', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)

    // No call immediately (setInterval not setTimeout)
    expect(mockFetch).not.toHaveBeenCalled()

    // Advance 60s — first heartbeat
    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockFetch).toHaveBeenCalledTimes(1)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/42/claim-support-review/heartbeat',
      expect.objectContaining({ method: 'POST' }),
    )

    // Advance another 60s — second heartbeat
    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockFetch).toHaveBeenCalledTimes(2)

    stopHeartbeat()
  })

  it('stopHeartbeat prevents further heartbeat calls', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)
    stopHeartbeat()

    await vi.advanceTimersByTimeAsync(180_000)
    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('startHeartbeat clears previous interval before starting new one', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(10)
    startHeartbeat(20)

    await vi.advanceTimersByTimeAsync(60_000)
    // Only one heartbeat (for request 20) — previous was cleared
    expect(mockFetch).toHaveBeenCalledTimes(1)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/20/claim-support-review/heartbeat',
      expect.anything(),
    )

    stopHeartbeat()
  })

  it('heartbeat failure is silenced (no throw)', async () => {
    mockFetch.mockRejectedValue(new Error('network'))
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)
    // Advance time — heartbeat fires and fetch rejects; no unhandled rejection should propagate
    await vi.advanceTimersByTimeAsync(60_000)
    // If we reach here without an unhandled rejection, the test passes
    expect(true).toBe(true)

    stopHeartbeat()
  })
})
