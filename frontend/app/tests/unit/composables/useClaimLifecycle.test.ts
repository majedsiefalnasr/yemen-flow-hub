import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest'

// Mock $fetch globally before importing the composable
const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)

// Mock navigateTo
const mockNavigateTo = vi.fn()
vi.stubGlobal('navigateTo', mockNavigateTo)

// Mock useRuntimeConfig
vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost' } }),
  navigateTo: mockNavigateTo,
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
    const { isClaiming, isReleasing, claimError, sessionExpired } = useClaimLifecycle()
    expect(isClaiming.value).toBe(false)
    expect(isReleasing.value).toBe(false)
    expect(claimError.value).toBeNull()
    expect(sessionExpired.value).toBe(false)
  })

  it('returns true on successful claim', async () => {
    mockFetch.mockResolvedValueOnce({ success: true })
    const { claimRequest, isClaiming, claimError } = useClaimLifecycle()

    const result = await claimRequest(42)
    expect(result).toBe(true)
    expect(isClaiming.value).toBe(false)
    expect(claimError.value).toBeNull()
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/42/claim-support-review',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('returns false and sets Arabic claimError on 409', async () => {
    const error = { response: { status: 409 } }
    mockFetch.mockRejectedValueOnce(error)

    const { claimRequest, claimError } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(claimError.value).toBeTruthy()
    expect(claimError.value).toContain('محجوز')
  })

  it('returns false and sets sessionExpired on 401', async () => {
    const error = { response: { status: 401 } }
    mockFetch.mockRejectedValueOnce(error)

    const { claimRequest, claimError, sessionExpired } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(sessionExpired.value).toBe(true)
    expect(claimError.value).toBeTruthy()
    expect(claimError.value).toContain('جلست')
  })

  it('returns false and sets sessionExpired on 403', async () => {
    const error = { response: { status: 403 } }
    mockFetch.mockRejectedValueOnce(error)

    const { claimRequest, claimError, sessionExpired } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(sessionExpired.value).toBe(true)
    expect(claimError.value).toBeTruthy()
  })

  it('returns false and sets generic claimError on network failure', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'))

    const { claimRequest, claimError, sessionExpired } = useClaimLifecycle()
    const result = await claimRequest(42)

    expect(result).toBe(false)
    expect(claimError.value).toBeTruthy()
    expect(sessionExpired.value).toBe(false)
  })

  it('resets isClaiming to false after success', async () => {
    mockFetch.mockResolvedValueOnce({})
    const { claimRequest, isClaiming } = useClaimLifecycle()

    const promise = claimRequest(42)
    await promise
    expect(isClaiming.value).toBe(false)
  })

  it('resets isClaiming to false after failure', async () => {
    mockFetch.mockRejectedValueOnce({ response: { status: 409 } })
    const { claimRequest, isClaiming } = useClaimLifecycle()

    await claimRequest(42)
    expect(isClaiming.value).toBe(false)
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

  it('resets isReleasing to false after success', async () => {
    mockFetch.mockResolvedValueOnce({})
    const { releaseRequest, isReleasing } = useClaimLifecycle()
    await releaseRequest(42)
    expect(isReleasing.value).toBe(false)
  })

  it('resets isReleasing to false after failure', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'))
    const { releaseRequest, isReleasing } = useClaimLifecycle()
    await releaseRequest(42)
    expect(isReleasing.value).toBe(false)
  })
})

describe('useClaimLifecycle — verifyClaimAlive', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns true when heartbeat POST succeeds', async () => {
    mockFetch.mockResolvedValueOnce({})
    const { verifyClaimAlive } = useClaimLifecycle()

    const alive = await verifyClaimAlive(42)
    expect(alive).toBe(true)
    expect(mockFetch).toHaveBeenCalledWith(
      '/api/workflow/42/claim-support-review/heartbeat',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('returns false and sets claimError on 404 (claim expired)', async () => {
    const error = { response: { status: 404 } }
    mockFetch.mockRejectedValueOnce(error)

    const { verifyClaimAlive, claimError } = useClaimLifecycle()
    const alive = await verifyClaimAlive(42)

    expect(alive).toBe(false)
    expect(claimError.value).toBeTruthy()
    expect(claimError.value).toContain('محجوز')
  })

  it('returns false and sets claimError on 422 (not the claim holder)', async () => {
    const error = { response: { status: 422 } }
    mockFetch.mockRejectedValueOnce(error)

    const { verifyClaimAlive, claimError, sessionExpired } = useClaimLifecycle()
    const alive = await verifyClaimAlive(42)

    expect(alive).toBe(false)
    expect(claimError.value).toBeTruthy()
    expect(sessionExpired.value).toBe(false)
  })

  it('returns false and sets sessionExpired on 401', async () => {
    const error = { response: { status: 401 } }
    mockFetch.mockRejectedValueOnce(error)

    const { verifyClaimAlive, sessionExpired } = useClaimLifecycle()
    const alive = await verifyClaimAlive(42)

    expect(alive).toBe(false)
    expect(sessionExpired.value).toBe(true)
  })

  it('returns false and sets sessionExpired on 403', async () => {
    const error = { response: { status: 403 } }
    mockFetch.mockRejectedValueOnce(error)

    const { verifyClaimAlive, sessionExpired } = useClaimLifecycle()
    const alive = await verifyClaimAlive(42)

    expect(alive).toBe(false)
    expect(sessionExpired.value).toBe(true)
  })
})

describe('useClaimLifecycle — startHeartbeat / stopHeartbeat', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    // Clean up any lingering heartbeats
    const { stopHeartbeat } = useClaimLifecycle()
    stopHeartbeat()
    vi.useRealTimers()
  })

  it('startHeartbeat fires POST every 60 seconds', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)

    // No call immediately — setInterval, not setTimeout
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

    // Advance another 60s — third heartbeat
    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockFetch).toHaveBeenCalledTimes(3)

    stopHeartbeat(42)
  })

  it('stopHeartbeat(id) prevents further heartbeat calls', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)
    stopHeartbeat(42)

    await vi.advanceTimersByTimeAsync(180_000)
    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('stopHeartbeat() with no id clears all active heartbeats', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(10)
    startHeartbeat(20)
    stopHeartbeat()

    await vi.advanceTimersByTimeAsync(180_000)
    expect(mockFetch).not.toHaveBeenCalled()
  })

  it('second startHeartbeat(id) replaces first — singleton per id', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(42)
    startHeartbeat(42) // should replace the first

    await vi.advanceTimersByTimeAsync(60_000)
    // Only one heartbeat fires — the first interval was cleared
    expect(mockFetch).toHaveBeenCalledTimes(1)

    stopHeartbeat(42)
  })

  it('startHeartbeat with different ids maintain independent intervals', async () => {
    mockFetch.mockResolvedValue({})
    const { startHeartbeat, stopHeartbeat } = useClaimLifecycle()

    startHeartbeat(10)
    startHeartbeat(20)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(mockFetch).toHaveBeenCalledTimes(2)

    const calls = mockFetch.mock.calls.map(c => c[0] as string)
    expect(calls).toContain('/api/workflow/10/claim-support-review/heartbeat')
    expect(calls).toContain('/api/workflow/20/claim-support-review/heartbeat')

    stopHeartbeat()
  })

  it('heartbeat 401 stops interval and calls onSessionExpired', async () => {
    const error = { response: { status: 401 } }
    mockFetch.mockRejectedValue(error)

    const onSessionExpired = vi.fn()
    const { startHeartbeat, sessionExpired, claimError } = useClaimLifecycle()

    startHeartbeat(42, onSessionExpired)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(onSessionExpired).toHaveBeenCalledTimes(1)
    expect(sessionExpired.value).toBe(true)
    expect(claimError.value).toBeTruthy()

    // Advance more time — interval should be dead
    await vi.advanceTimersByTimeAsync(180_000)
    expect(onSessionExpired).toHaveBeenCalledTimes(1) // still just 1
  })

  it('heartbeat 403 stops interval and calls onSessionExpired', async () => {
    const error = { response: { status: 403 } }
    mockFetch.mockRejectedValue(error)

    const onSessionExpired = vi.fn()
    const { startHeartbeat } = useClaimLifecycle()

    startHeartbeat(42, onSessionExpired)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(onSessionExpired).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(180_000)
    expect(onSessionExpired).toHaveBeenCalledTimes(1)
  })

  it('heartbeat transient network errors do not stop interval', async () => {
    // First two beats fail with network error, third succeeds
    mockFetch
      .mockRejectedValueOnce(new Error('network'))
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValue({})

    const onSessionExpired = vi.fn()
    const { startHeartbeat, stopHeartbeat, sessionExpired } = useClaimLifecycle()

    startHeartbeat(42, onSessionExpired)

    await vi.advanceTimersByTimeAsync(60_000) // first — network error
    await vi.advanceTimersByTimeAsync(60_000) // second — network error
    await vi.advanceTimersByTimeAsync(60_000) // third — success

    expect(onSessionExpired).not.toHaveBeenCalled()
    expect(sessionExpired.value).toBe(false)
    expect(mockFetch).toHaveBeenCalledTimes(3)

    stopHeartbeat(42)
  })

  it('heartbeat 5xx errors do not stop interval', async () => {
    const error = { response: { status: 503 } }
    mockFetch
      .mockRejectedValueOnce(error)
      .mockRejectedValueOnce(error)
      .mockResolvedValue({})

    const onSessionExpired = vi.fn()
    const { startHeartbeat, stopHeartbeat, sessionExpired } = useClaimLifecycle()

    startHeartbeat(42, onSessionExpired)

    await vi.advanceTimersByTimeAsync(60_000)
    await vi.advanceTimersByTimeAsync(60_000)
    await vi.advanceTimersByTimeAsync(60_000)

    expect(onSessionExpired).not.toHaveBeenCalled()
    expect(sessionExpired.value).toBe(false)

    stopHeartbeat(42)
  })
})
