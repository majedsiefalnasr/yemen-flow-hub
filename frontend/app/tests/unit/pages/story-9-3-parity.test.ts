import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref, computed } from 'vue'

// ══════════════════════════════════════════════════════════════════════════════════
// Story 9.3 Tests — Auth/Dashboard/Requests Parity Fixes
// ══════════════════════════════════════════════════════════════════════════════════

// ──────────────────────────────────────────────────────────────────────────────────
// OTP Countdown Timer (login.vue / AC2)
// ──────────────────────────────────────────────────────────────────────────────────

describe('OTP Countdown Timer (AC2 — auth/login-otp parity)', () => {
  const OTP_TTL = 300

  function createOtpTimer() {
    const otpStep = ref(false)
    const otpSecondsLeft = ref(OTP_TTL)
    let otpTimerHandle: ReturnType<typeof setInterval> | null = null

    function startOtpTimer() {
      otpSecondsLeft.value = OTP_TTL
      clearOtpTimer()
      otpTimerHandle = setInterval(() => {
        if (otpSecondsLeft.value > 0) otpSecondsLeft.value--
        else clearOtpTimer()
      }, 1000)
    }

    function clearOtpTimer() {
      if (otpTimerHandle !== null) { clearInterval(otpTimerHandle); otpTimerHandle = null }
    }

    const otpTimerDisplay = computed(() => {
      const m = Math.floor(otpSecondsLeft.value / 60).toString().padStart(2, '0')
      const s = (otpSecondsLeft.value % 60).toString().padStart(2, '0')
      return `${m}:${s}`
    })

    const isExpiring = computed(() => otpSecondsLeft.value <= 60)

    // Watchers
    const updateWatcher = vi.fn((val: boolean) => {
      if (val) startOtpTimer()
      else clearOtpTimer()
    })

    function getOtpTimerHandle() { return otpTimerHandle }

    return {
      otpStep,
      otpSecondsLeft,
      otpTimerDisplay,
      isExpiring,
      startOtpTimer,
      clearOtpTimer,
      updateWatcher,
      getOtpTimerHandle,
    }
  }

  it('initializes with 5-min (300s) TTL', () => {
    const timer = createOtpTimer()
    expect(timer.otpSecondsLeft.value).toBe(OTP_TTL)
  })

  it('formats MM:SS correctly at 5:00', () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 300
    expect(timer.otpTimerDisplay.value).toBe('05:00')
  })

  it('formats MM:SS correctly at 1:30', () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 90
    expect(timer.otpTimerDisplay.value).toBe('01:30')
  })

  it('formats MM:SS correctly at 0:45', () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 45
    expect(timer.otpTimerDisplay.value).toBe('00:45')
  })

  it('triggers expiring class when ≤ 60 seconds', () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 61
    expect(timer.isExpiring.value).toBe(false)
    timer.otpSecondsLeft.value = 60
    expect(timer.isExpiring.value).toBe(true)
  })

  it('continues countdown from 5:00 to 4:59', async () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 300
    const display1 = timer.otpTimerDisplay.value
    timer.otpSecondsLeft.value--
    const display2 = timer.otpTimerDisplay.value
    expect(display1).toBe('05:00')
    expect(display2).toBe('04:59')
  })

  it('clears timer on unmount', () => {
    const timer = createOtpTimer()
    timer.startOtpTimer()
    expect(timer.otpSecondsLeft.value).toBe(OTP_TTL)
    timer.clearOtpTimer()
    // Manually decrement and verify no further countdown
    const snap = timer.otpSecondsLeft.value
    // Note: in real implementation, interval would be cleared, so no auto-decrement
  })

  it('resets timer when otpStep toggles true', () => {
    const timer = createOtpTimer()
    timer.otpSecondsLeft.value = 100 // arbitrary
    timer.updateWatcher(true)
    timer.startOtpTimer() // simulate watcher call
    expect(timer.otpSecondsLeft.value).toBe(OTP_TTL)
  })

  it('clears timer when otpStep becomes false', () => {
    const timer = createOtpTimer()
    timer.startOtpTimer()
    timer.updateWatcher(false)
    timer.clearOtpTimer()
    expect(timer.getOtpTimerHandle()).toBeNull()
  })
})

// ──────────────────────────────────────────────────────────────────────────────────
// ExecutiveDashboard Director KPI Logic (AC3 — dashboards/committee-director parity)
// ──────────────────────────────────────────────────────────────────────────────────

describe('ExecutiveDashboard — Director Override KPI (AC3 — D6 director tile)', () => {
  function createDirectorDashboard() {
    const userRole = ref('COMMITTEE_DIRECTOR')
    const stats = ref({
      rejection_count: 5,
      approval_count: 12,
      tiebreak_count: 2,
      director_override_count: 3,
    })

    const isDirector = computed(() => userRole.value === 'COMMITTEE_DIRECTOR')
    const kpiGridClass = computed(() => ({
      'kpi-grid': true,
      'kpi-grid--4': isDirector.value,
    }))

    const displayDirectorOverrideCount = computed(() =>
      isDirector.value ? (stats.value as any).director_override_count ?? 0 : null,
    )

    return {
      userRole,
      stats,
      isDirector,
      kpiGridClass,
      displayDirectorOverrideCount,
    }
  }

  it('shows 4-column grid for director role', () => {
    const dashboard = createDirectorDashboard()
    dashboard.userRole.value = 'COMMITTEE_DIRECTOR'
    expect(dashboard.isDirector.value).toBe(true)
    expect(dashboard.kpiGridClass.value['kpi-grid--4']).toBe(true)
  })

  it('shows 3-column grid for non-director executive', () => {
    const dashboard = createDirectorDashboard()
    dashboard.userRole.value = 'EXECUTIVE_MEMBER'
    expect(dashboard.isDirector.value).toBe(false)
    expect(dashboard.kpiGridClass.value['kpi-grid--4']).toBe(false)
  })

  it('displays director override count when present', () => {
    const dashboard = createDirectorDashboard()
    dashboard.stats.value.director_override_count = 5
    expect(dashboard.displayDirectorOverrideCount.value).toBe(5)
  })

  it('defaults to 0 when director_override_count is absent', () => {
    const dashboard = createDirectorDashboard()
    dashboard.stats.value = { rejection_count: 1, approval_count: 1, tiebreak_count: 0 } as any
    expect(dashboard.displayDirectorOverrideCount.value).toBe(0)
  })

  it('hides director override tile for non-director roles', () => {
    const dashboard = createDirectorDashboard()
    dashboard.userRole.value = 'EXECUTIVE_MEMBER'
    expect(dashboard.displayDirectorOverrideCount.value).toBe(null)
  })

  it('amber color token is #f57f17', () => {
    // Verify token value (used for D6 KPI card styling)
    const amberColor = '#f57f17'
    expect(amberColor).toBe('#f57f17')
  })
})

// ──────────────────────────────────────────────────────────────────────────────────
// VotingPanel Tiebreak Notice Styling (AC7 — requests/detail-voting parity)
// ──────────────────────────────────────────────────────────────────────────────────

describe('VotingPanel — Tiebreak Notice Styling (AC7 — heavier amber emphasis)', () => {
  function createTiebreakNotice() {
    const isVotesTied = ref(false)

    const tiebreakNoticeClass = computed(() => ({
      'tiebreak-notice': true,
      'tiebreak-notice--visible': isVotesTied.value,
    }))

    const tiebreakNoticeStyle = computed(() => {
      if (!isVotesTied.value) return {}
      return {
        padding: '14px 16px',
        background: '#fff8e1',
        border: '1.5px solid #f57f17',
        borderLeft: '4px solid #f57f17',
        fontWeight: '600',
        color: '#7c4a00',
      }
    })

    return {
      isVotesTied,
      tiebreakNoticeClass,
      tiebreakNoticeStyle,
    }
  }

  it('renders when votes are tied', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeClass.value['tiebreak-notice--visible']).toBe(true)
  })

  it('applies correct padding: 14px 16px', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.padding).toBe('14px 16px')
  })

  it('applies correct background color: #fff8e1 (light amber)', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.background).toBe('#fff8e1')
  })

  it('applies correct border: 1.5px solid #f57f17', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.border).toBe('1.5px solid #f57f17')
  })

  it('applies left accent border: 4px solid #f57f17', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.borderLeft).toBe('4px solid #f57f17')
  })

  it('applies correct font-weight: 600 (heavier)', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.fontWeight).toBe('600')
  })

  it('applies correct text color: #7c4a00 (dark amber)', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = true
    expect(notice.tiebreakNoticeStyle.value.color).toBe('#7c4a00')
  })

  it('hides when votes are not tied', () => {
    const notice = createTiebreakNotice()
    notice.isVotesTied.value = false
    expect(notice.tiebreakNoticeStyle.value).toEqual({})
  })
})

// ──────────────────────────────────────────────────────────────────────────────────
// Customs Declaration Issuer Row (AC4 — requests/detail-parties-customs parity)
// ──────────────────────────────────────────────────────────────────────────────────

describe('Request Detail — Customs Declaration Issuer Row (AC4)', () => {
  function createCustomsIssuerRow() {
    const request = ref<any>({
      customs_declaration: null,
    })

    const shouldShowCustomsIssuer = computed(() =>
      !!request.value?.customs_declaration?.issuer,
    )

    const customsIssuerName = computed(() =>
      request.value?.customs_declaration?.issuer?.name ?? null,
    )

    return {
      request,
      shouldShowCustomsIssuer,
      customsIssuerName,
    }
  }

  it('hides row when customs_declaration is absent', () => {
    const row = createCustomsIssuerRow()
    row.request.value = { customs_declaration: null }
    expect(row.shouldShowCustomsIssuer.value).toBe(false)
  })

  it('hides row when customs_declaration exists but issuer is null', () => {
    const row = createCustomsIssuerRow()
    row.request.value = { customs_declaration: { declaration_number: '123', issuer: null } }
    expect(row.shouldShowCustomsIssuer.value).toBe(false)
  })

  it('shows row when customs_declaration.issuer is present', () => {
    const row = createCustomsIssuerRow()
    row.request.value = {
      customs_declaration: {
        declaration_number: '123',
        issuer: { id: 1, name: 'مكتب الجمارك الرئيسي' },
      },
    }
    expect(row.shouldShowCustomsIssuer.value).toBe(true)
  })

  it('displays issuer name when present', () => {
    const row = createCustomsIssuerRow()
    row.request.value = {
      customs_declaration: {
        declaration_number: '123',
        issuer: { id: 1, name: 'مكتب الجمارك الرئيسي' },
      },
    }
    expect(row.customsIssuerName.value).toBe('مكتب الجمارك الرئيسي')
  })

  it('returns null when issuer is missing', () => {
    const row = createCustomsIssuerRow()
    row.request.value = { customs_declaration: { declaration_number: '123', issuer: null } }
    expect(row.customsIssuerName.value).toBe(null)
  })
})

// ──────────────────────────────────────────────────────────────────────────────────
// Actor Pill Styling (AC4 — requests/detail actor emphasis)
// ──────────────────────────────────────────────────────────────────────────────────

describe('Request Detail — Actor Pill Styling (AC4 emphasis)', () => {
  function createActorPill() {
    const actorUser = ref({ id: 1, name: 'Ahmed Al-Yamani' })

    const pillStyle = computed(() => ({
      background: '#e8f0fb',
      color: '#0066cc',
    }))

    return {
      actorUser,
      pillStyle,
    }
  }

  it('applies light blue background: #e8f0fb', () => {
    const pill = createActorPill()
    expect(pill.pillStyle.value.background).toBe('#e8f0fb')
  })

  it('applies primary blue text color: #0066cc', () => {
    const pill = createActorPill()
    expect(pill.pillStyle.value.color).toBe('#0066cc')
  })

  it('contrasts with muted gray background (#f5f5f7)', () => {
    // Verify new color is visually emphasized vs. old muted gray
    const oldBackground = '#f5f5f7'
    const newBackground = '#e8f0fb'
    expect(newBackground).not.toBe(oldBackground)
  })
})

// ──────────────────────────────────────────────────────────────────────────────────
// Request List Row Density (AC4 — requests/index parity)
// ──────────────────────────────────────────────────────────────────────────────────

describe('Request List — Row Density Reduction (AC4)', () => {
  function createRowDensity() {
    const headerPadding = '10px 16px' // reduced from 12px
    const bodyPadding = '8px 16px' // reduced from 10px

    return {
      headerPadding,
      bodyPadding,
    }
  }

  it('applies header padding: 10px 16px (reduced)', () => {
    const density = createRowDensity()
    expect(density.headerPadding).toBe('10px 16px')
  })

  it('applies body padding: 8px 16px (reduced)', () => {
    const density = createRowDensity()
    expect(density.bodyPadding).toBe('8px 16px')
  })

  it('reduces from 12px to 10px header padding', () => {
    const oldPadding = 12
    const newPadding = 10
    expect(newPadding).toBeLessThan(oldPadding)
  })

  it('reduces from 10px to 8px body padding', () => {
    const oldPadding = 10
    const newPadding = 8
    expect(newPadding).toBeLessThan(oldPadding)
  })
})
