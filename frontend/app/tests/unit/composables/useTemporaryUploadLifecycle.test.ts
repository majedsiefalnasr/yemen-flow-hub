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
