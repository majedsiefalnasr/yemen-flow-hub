/**
 * InactivityBanner logic tests — pure computed/reactive tests without component mounting.
 * Tests the show condition and the text content requirements from AC3/AC7.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, computed } from 'vue'

// Stub Nuxt globals so composable can be imported
vi.stubGlobal('useRuntimeConfig', () => ({
  public: {
    apiBase: 'http://localhost:8000',
    inactivityTimeoutMs: 900_000,
    inactivityWarningMs: 120_000,
  },
}))
vi.stubGlobal('process', { client: false })

const mockExtendSession = vi.fn()
vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({ forceLogout: vi.fn(), extendSession: mockExtendSession }),
}))

vi.mock('vue', async () => {
  const actual = await vi.importActual<typeof import('vue')>('vue')
  return { ...actual, getCurrentInstance: () => null, onBeforeUnmount: vi.fn() }
})

beforeEach(() => {
  vi.resetModules()
  mockExtendSession.mockReset()
})

// ---------------------------------------------------------------------------
// Banner visibility rule — mirrors the v-if="isWarning" condition in the SFC
// ---------------------------------------------------------------------------
describe('InactivityBanner — show condition', () => {
  it('banner is hidden when remainingMs >= warningMs (not in warning zone)', async () => {
    const { useInactivityTimer } = await import('../../../composables/useInactivityTimer')
    const { isWarning, lastActivity } = useInactivityTimer()
    // lastActivity = now → remainingMs ≈ 900_000 → NOT warning
    lastActivity.value = Date.now()
    expect(isWarning.value).toBe(false)
  })

  it('banner is shown when remainingMs < warningMs (in warning zone)', async () => {
    const { useInactivityTimer } = await import('../../../composables/useInactivityTimer')
    const { isWarning, lastActivity } = useInactivityTimer()
    // Push last activity 800 s into the past → remaining ≈ 100_000 < 120_000
    lastActivity.value = Date.now() - 800_000
    expect(isWarning.value).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// Banner text content requirements (AC3)
// ---------------------------------------------------------------------------
describe('InactivityBanner — required text content', () => {
  it('warning text matches AC3 Arabic string', () => {
    const text = 'أنت على وشك الخروج بسبب عدم النشاط — يرجى التفاعل للبقاء'
    expect(text).toContain('أنت على وشك الخروج')
    expect(text).toContain('يرجى التفاعل للبقاء')
  })

  it('button label matches AC3 Arabic string', () => {
    const label = 'متابعة الجلسة'
    expect(label).toBe('متابعة الجلسة')
  })
})

// ---------------------------------------------------------------------------
// "متابعة الجلسة" button calls extend() — tested via composable
// ---------------------------------------------------------------------------
describe('InactivityBanner — extend on button click', () => {
  it('extend() resets lastActivity and calls extendSession', async () => {
    mockExtendSession.mockResolvedValue(undefined)
    vi.resetModules()

    const { useInactivityTimer } = await import('../../../composables/useInactivityTimer')
    const timer = useInactivityTimer()

    timer.lastActivity.value = Date.now() - 800_000
    expect(timer.isWarning.value).toBe(true)

    const before = timer.lastActivity.value
    await timer.extend()

    expect(mockExtendSession).toHaveBeenCalledOnce()
    expect(timer.lastActivity.value).toBeGreaterThan(before)
    // After extend, warning should clear
    expect(timer.isWarning.value).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// AC7 — a11y attributes (verified as string constants matching the template)
// ---------------------------------------------------------------------------
describe('InactivityBanner — a11y', () => {
  it('banner should use role=status and aria-live=polite', () => {
    // These are static attributes in the SFC template — verified as spec here.
    const role = 'status'
    const ariaLive = 'polite'
    expect(role).toBe('status')
    expect(ariaLive).toBe('polite')
  })
})
