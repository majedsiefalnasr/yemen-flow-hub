// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { useTemporaryUploadLifecycle } from '@/composables/useTemporaryUploadLifecycle'

const mockUpload = vi.fn()
const mockStatus = vi.fn()

vi.mock('@/composables/useTemporaryUploads', () => ({
  useTemporaryUploads: () => ({ upload: mockUpload, status: mockStatus, release: vi.fn() }),
}))

describe('useTemporaryUploadLifecycle', () => {
  beforeEach(() => {
    mockUpload.mockReset()
    mockStatus.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('stops polling on unmount, so a status call never fires after the component is gone', async () => {
    vi.useFakeTimers()
    mockUpload.mockResolvedValue({ token: 'tok-1', expires_at: '2026-01-01T00:00:00Z' })
    mockStatus.mockResolvedValue({
      token: 'tok-1',
      scan_status: 'pending',
      original_name: 'file.pdf',
      size: 10,
      expires_at: '2026-01-01T00:00:00Z',
    })

    let lifecycle!: ReturnType<typeof useTemporaryUploadLifecycle>
    const Host = defineComponent({
      setup() {
        lifecycle = useTemporaryUploadLifecycle()
        return () => null
      },
    })
    const wrapper = mount(Host)

    await lifecycle.uploadAndTrack('supporting_doc', new File(['x'], 'file.pdf'), 10, 2, 'session')
    await vi.advanceTimersByTimeAsync(0)
    expect(mockStatus).toHaveBeenCalledTimes(0)

    await vi.advanceTimersByTimeAsync(2000)
    const callsBeforeUnmount = mockStatus.mock.calls.length
    expect(callsBeforeUnmount).toBeGreaterThan(0)

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(10000)

    expect(mockStatus.mock.calls.length).toBe(callsBeforeUnmount)
  })

  it('cleanTokens only ever returns clean tokens, never pending/infected/failed/error ones', async () => {
    vi.useFakeTimers()
    mockUpload
      .mockResolvedValueOnce({ token: 'clean-tok', expires_at: '2026-01-01T00:00:00Z' })
      .mockResolvedValueOnce({ token: 'infected-tok', expires_at: '2026-01-01T00:00:00Z' })
    mockStatus.mockImplementation(async (token: string) => ({
      token,
      scan_status: token === 'clean-tok' ? 'clean' : 'infected',
      original_name: 'file.pdf',
      size: 10,
      expires_at: '2026-01-01T00:00:00Z',
    }))

    let lifecycle!: ReturnType<typeof useTemporaryUploadLifecycle>
    const Host = defineComponent({
      setup() {
        lifecycle = useTemporaryUploadLifecycle()
        return () => null
      },
    })
    mount(Host)

    await lifecycle.uploadAndTrack('field_a', new File(['x'], 'a.pdf'), 10, 1, 'session')
    await lifecycle.uploadAndTrack('field_b', new File(['x'], 'b.pdf'), 10, 2, 'session')
    await vi.advanceTimersByTimeAsync(2000)

    expect(lifecycle.cleanTokens()).toEqual(['clean-tok'])
    expect(lifecycle.hasBlockingUpload()).toBe(true)
  })

  it('does not start a second status() call while one is still in flight (single-flight polling)', async () => {
    vi.useFakeTimers()
    mockUpload.mockResolvedValue({ token: 'tok-slow', expires_at: '2026-01-01T00:00:00Z' })

    // Each status() call hangs until its own resolver is invoked, so any
    // overlap would show up as a second in-flight call before the first
    // settles — which the old setInterval loop could produce if a check
    // took longer than SCAN_POLL_INTERVAL_MS (2s).
    const resolvers: Array<(value: unknown) => void> = []
    mockStatus.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvers.push(resolve)
        }),
    )

    let lifecycle!: ReturnType<typeof useTemporaryUploadLifecycle>
    const Host = defineComponent({
      setup() {
        lifecycle = useTemporaryUploadLifecycle()
        return () => null
      },
    })
    mount(Host)

    await lifecycle.uploadAndTrack('field_a', new File(['x'], 'a.pdf'), 10, 1, 'session')
    await vi.advanceTimersByTimeAsync(2000)
    expect(mockStatus).toHaveBeenCalledTimes(1)

    // Let far more time pass than the poll interval while the first request
    // is still unresolved: a second call must never start until the first
    // one settles.
    await vi.advanceTimersByTimeAsync(20000)
    expect(mockStatus).toHaveBeenCalledTimes(1)

    // Resolve the first (stale) call as 'pending' — the loop schedules its
    // next tick only now.
    resolvers[0]?.({
      token: 'tok-slow',
      scan_status: 'pending',
      original_name: 'a.pdf',
      size: 10,
      expires_at: '2026-01-01T00:00:00Z',
    })
    await vi.advanceTimersByTimeAsync(0)
    await vi.advanceTimersByTimeAsync(2000)
    expect(mockStatus).toHaveBeenCalledTimes(2)
  })

  it('discards a stale in-flight result once the field has been removed, never resurrecting the entry', async () => {
    vi.useFakeTimers()
    mockUpload.mockResolvedValue({ token: 'tok-y', expires_at: '2026-01-01T00:00:00Z' })

    let resolveStatus!: (value: unknown) => void
    mockStatus.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveStatus = resolve
        }),
    )

    let lifecycle!: ReturnType<typeof useTemporaryUploadLifecycle>
    const Host = defineComponent({
      setup() {
        lifecycle = useTemporaryUploadLifecycle()
        return () => null
      },
    })
    mount(Host)

    await lifecycle.uploadAndTrack('field_a', new File(['x'], 'y.pdf'), 10, 1, 'session')
    await vi.advanceTimersByTimeAsync(2000)
    expect(mockStatus).toHaveBeenCalledTimes(1)

    // The user removes the file while the status() check is still in
    // flight — the in-flight request has already captured this loop's
    // generation number and cannot be cancelled, but its result must still
    // never resurrect the entry once it eventually resolves.
    lifecycle.removeEntry('field_a')
    expect(lifecycle.entryFor('field_a')).toBeUndefined()

    resolveStatus({
      token: 'tok-y',
      scan_status: 'clean',
      original_name: 'y.pdf',
      size: 10,
      expires_at: '2026-01-01T00:00:00Z',
    })
    await vi.advanceTimersByTimeAsync(0)

    expect(lifecycle.entryFor('field_a')).toBeUndefined()
  })

  it('removeEntry stops polling and drops the entry, clearing hasBlockingUpload', async () => {
    vi.useFakeTimers()
    mockUpload.mockResolvedValue({ token: 'tok-x', expires_at: '2026-01-01T00:00:00Z' })
    mockStatus.mockResolvedValue({
      token: 'tok-x',
      scan_status: 'infected',
      original_name: 'bad.pdf',
      size: 10,
      expires_at: '2026-01-01T00:00:00Z',
    })

    let lifecycle!: ReturnType<typeof useTemporaryUploadLifecycle>
    const Host = defineComponent({
      setup() {
        lifecycle = useTemporaryUploadLifecycle()
        return () => null
      },
    })
    mount(Host)

    await lifecycle.uploadAndTrack('field_a', new File(['x'], 'bad.pdf'), 10, 1, 'session')
    await vi.advanceTimersByTimeAsync(2000)
    expect(lifecycle.hasBlockingUpload()).toBe(true)

    lifecycle.removeEntry('field_a')
    expect(lifecycle.hasBlockingUpload()).toBe(false)
    expect(lifecycle.entryFor('field_a')).toBeUndefined()
  })
})
