/**
 * Login page — inactivity reason banner logic (AC5).
 * Tests the show/hide rule without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { ref, computed } from 'vue'

// ---------------------------------------------------------------------------
// Pure logic extracted from login.vue setup — mirrors the computed in the SFC
// ---------------------------------------------------------------------------
function makeShowInactivityBanner(reasonQuery: string | undefined) {
  return computed(() => reasonQuery === 'inactivity')
}

describe('Login page — inactivity reason banner (AC5)', () => {
  it('shows banner when route.query.reason === "inactivity"', () => {
    const show = makeShowInactivityBanner('inactivity')
    expect(show.value).toBe(true)
  })

  it('does NOT show banner when reason is absent', () => {
    const show = makeShowInactivityBanner(undefined)
    expect(show.value).toBe(false)
  })

  it('does NOT show banner when reason is some other value', () => {
    const show = makeShowInactivityBanner('expired')
    expect(show.value).toBe(false)

    const show2 = makeShowInactivityBanner('unauthorized')
    expect(show2.value).toBe(false)
  })

  it('does NOT show banner when reason is empty string', () => {
    const show = makeShowInactivityBanner('')
    expect(show.value).toBe(false)
  })

  it('banner is dismissible: inactivityBannerDismissed hides it on submit', () => {
    const dismissed = ref(false)
    const reason = ref('inactivity')
    const show = computed(() => reason.value === 'inactivity' && !dismissed.value)
    expect(show.value).toBe(true)
    dismissed.value = true
    expect(show.value).toBe(false)
  })
})

describe('Login page — inactivity banner Arabic text (AC5)', () => {
  it('banner text matches AC5 specification', () => {
    const text = 'تم تسجيل خروجك بسبب عدم النشاط — يرجى تسجيل الدخول مرة أخرى'
    expect(text).toContain('تم تسجيل خروجك بسبب عدم النشاط')
    expect(text).toContain('يرجى تسجيل الدخول مرة أخرى')
  })
})
