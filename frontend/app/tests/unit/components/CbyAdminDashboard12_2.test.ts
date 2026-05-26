/**
 * CbyAdminDashboard 12.2 — governance uplift logic tests.
 * Pure function tests, no component mounting.
 */
import { describe, it, expect } from 'vitest'
import type {
  CbyAdminDashboardStats,
  CbyAdminKpi,
  CbyAdminWorkflowPressureRow,
  CbyAdminVotingSession,
  CbyAdminComplianceSignal,
  CbyAdminCriticalEvent,
} from '../../../composables/useDashboard'

// ── Helpers mirrored from CbyAdminDashboard.vue ──────────────────────────────

function makeKpi(overrides: Partial<CbyAdminKpi> = {}): CbyAdminKpi {
  return { value: 0, delta: 0, severity: 'healthy', sparkline: [], drilldown_route: '', ...overrides }
}

function resolvedKpi(stats: CbyAdminDashboardStats, key: keyof CbyAdminDashboardStats): CbyAdminKpi {
  const kpi = stats[key] as CbyAdminKpi | undefined
  if (kpi && typeof kpi === 'object' && 'value' in kpi) return kpi
  return makeKpi()
}

function buildSparkLine(entries: Array<{ value: number }>): string {
  if (entries.length < 2) return ''
  const W = 80, H = 28, pad = 4
  const vals = entries.map(e => e.value)
  const max = Math.max(...vals, 1)
  const step = (W - pad * 2) / (entries.length - 1)
  return entries.map((e, i) => {
    const x = pad + i * step
    const y = pad + (1 - e.value / max) * (H - pad * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function riskScoreColor(score: number): string {
  if (score <= 33) return '#34c759'
  if (score <= 66) return '#ff9f0a'
  return '#ff3b30'
}

function severityBg(severity: string): string {
  if (severity === 'critical') return 'bg-red-50'
  if (severity === 'attention') return 'bg-amber-50'
  return ''
}

function severityColor(severity: string): string {
  if (severity === 'critical') return '#ff3b30'
  if (severity === 'attention') return '#ff9f0a'
  if (severity === 'healthy') return '#34c759'
  return '#0066cc'
}

function makeBaseStats(overrides: Partial<CbyAdminDashboardStats> = {}): CbyAdminDashboardStats {
  return {
    total: 0,
    approved: 0,
    in_process: 0,
    rejected: 0,
    compliance_alerts: { duplicate_suppliers: [], high_amount_requests: [], stale_pending_requests: [] },
    most_active_banks: [],
    ...overrides,
  }
}

// ── resolvedKpi ───────────────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — resolvedKpi()', () => {
  it('returns zero-fallback when KPI key is missing from stats', () => {
    const stats = makeBaseStats()
    const result = resolvedKpi(stats, 'active_workflow_requests')
    expect(result.value).toBe(0)
    expect(result.severity).toBe('healthy')
    expect(result.sparkline).toHaveLength(0)
  })

  it('returns actual KPI when key is present', () => {
    const kpi = makeKpi({ value: 42, delta: 5, severity: 'attention' })
    const stats = makeBaseStats({ active_workflow_requests: kpi })
    expect(resolvedKpi(stats, 'active_workflow_requests').value).toBe(42)
    expect(resolvedKpi(stats, 'active_workflow_requests').severity).toBe('attention')
  })

  it('returns fallback for sla_violations when absent', () => {
    const stats = makeBaseStats()
    expect(resolvedKpi(stats, 'sla_violations').value).toBe(0)
  })

  it('returns actual sla_violations KPI', () => {
    const kpi = makeKpi({ value: 7, severity: 'critical' })
    const stats = makeBaseStats({ sla_violations: kpi })
    expect(resolvedKpi(stats, 'sla_violations').value).toBe(7)
    expect(resolvedKpi(stats, 'sla_violations').severity).toBe('critical')
  })

  it('returns fallback for system_availability when absent', () => {
    const stats = makeBaseStats()
    expect(resolvedKpi(stats, 'system_availability').value).toBe(0)
  })

  it('returns open_voting_sessions KPI when present', () => {
    const kpi = makeKpi({ value: 3, severity: 'attention' })
    const stats = makeBaseStats({ open_voting_sessions: kpi })
    expect(resolvedKpi(stats, 'open_voting_sessions').value).toBe(3)
  })
})

// ── buildSparkLine ────────────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — buildSparkLine()', () => {
  it('returns empty string for empty array', () => {
    expect(buildSparkLine([])).toBe('')
  })

  it('returns empty string for single-entry array (< 2 points)', () => {
    expect(buildSparkLine([{ value: 5 }])).toBe('')
  })

  it('returns space-separated coordinate pairs for 2+ entries', () => {
    const pts = buildSparkLine([{ value: 10 }, { value: 20 }])
    expect(pts.split(' ')).toHaveLength(2)
  })

  it('produces 6 pairs for 6-entry sparkline', () => {
    const entries = Array.from({ length: 6 }, (_, i) => ({ value: i + 1 }))
    expect(buildSparkLine(entries).split(' ')).toHaveLength(6)
  })

  it('first point starts at left pad (x = 4)', () => {
    const pts = buildSparkLine([{ value: 5 }, { value: 8 }, { value: 3 }])
    const firstX = Number(pts.split(' ')[0]!.split(',')[0])
    expect(firstX).toBeCloseTo(4, 1)
  })

  it('does not produce NaN for all-zero values (max clamped to 1)', () => {
    const pts = buildSparkLine([{ value: 0 }, { value: 0 }, { value: 0 }])
    expect(pts).not.toContain('NaN')
    expect(pts).not.toContain('Infinity')
  })

  it('highest value maps to top (y near pad)', () => {
    const entries = [{ value: 100 }, { value: 0 }]
    const pts = buildSparkLine(entries)
    const firstY = Number(pts.split(' ')[0]!.split(',')[1])
    expect(firstY).toBeCloseTo(4, 0) // y = pad when value = max
  })
})

// ── riskScoreColor ────────────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — riskScoreColor()', () => {
  it('returns green (#34c759) for score ≤ 33', () => {
    expect(riskScoreColor(0)).toBe('#34c759')
    expect(riskScoreColor(33)).toBe('#34c759')
  })

  it('returns amber (#ff9f0a) for score 34–66', () => {
    expect(riskScoreColor(34)).toBe('#ff9f0a')
    expect(riskScoreColor(66)).toBe('#ff9f0a')
  })

  it('returns red (#ff3b30) for score > 66', () => {
    expect(riskScoreColor(67)).toBe('#ff3b30')
    expect(riskScoreColor(100)).toBe('#ff3b30')
  })
})

// ── severityBg & severityColor ────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — severity helpers', () => {
  it('critical severity → red bg and red color', () => {
    expect(severityBg('critical')).toBe('bg-red-50')
    expect(severityColor('critical')).toBe('#ff3b30')
  })

  it('attention severity → amber bg and amber color', () => {
    expect(severityBg('attention')).toBe('bg-amber-50')
    expect(severityColor('attention')).toBe('#ff9f0a')
  })

  it('healthy severity → no bg class and green color', () => {
    expect(severityBg('healthy')).toBe('')
    expect(severityColor('healthy')).toBe('#34c759')
  })

  it('unknown severity → no bg class and blue color', () => {
    expect(severityBg('unknown')).toBe('')
    expect(severityColor('unknown')).toBe('#0066cc')
  })
})

// ── Governance stats shape ────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — governance stats optional fields', () => {
  it('all 6 KPI slots are optional on CbyAdminDashboardStats', () => {
    const stats = makeBaseStats()
    expect(stats.active_workflow_requests).toBeUndefined()
    expect(stats.sla_violations).toBeUndefined()
    expect(stats.open_voting_sessions).toBeUndefined()
    expect(stats.fx_confirmation_pending).toBeUndefined()
    expect(stats.bank_risk_alerts).toBeUndefined()
    expect(stats.system_availability).toBeUndefined()
  })

  it('governance panel fields are optional on CbyAdminDashboardStats', () => {
    const stats = makeBaseStats()
    expect(stats.workflow_pressure_map).toBeUndefined()
    expect(stats.executive_voting_sessions).toBeUndefined()
    expect(stats.bank_risk_intelligence).toBeUndefined()
    expect(stats.compliance_signals).toBeUndefined()
    expect(stats.critical_events).toBeUndefined()
  })

  it('all 6 KPI slots can be populated', () => {
    const kpi = makeKpi({ value: 10, severity: 'attention' })
    const stats = makeBaseStats({
      active_workflow_requests: kpi,
      sla_violations: kpi,
      open_voting_sessions: kpi,
      fx_confirmation_pending: kpi,
      bank_risk_alerts: kpi,
      system_availability: kpi,
    })
    const keys: (keyof CbyAdminDashboardStats)[] = [
      'active_workflow_requests', 'sla_violations', 'open_voting_sessions',
      'fx_confirmation_pending', 'bank_risk_alerts', 'system_availability',
    ]
    for (const k of keys) {
      expect(resolvedKpi(stats, k).value).toBe(10)
    }
  })

  it('workflow_pressure_map rows carry sla_risk field', () => {
    const row: CbyAdminWorkflowPressureRow = {
      stage: 'BANK_REVIEW',
      stage_label: 'مراجعة البنك',
      count: 12,
      avg_days: 3.5,
      sla_risk: 'attention',
    }
    expect(row.sla_risk).toBe('attention')
  })

  it('executive_voting_session carries waiting_for member list', () => {
    const session: CbyAdminVotingSession = {
      request_id: 5,
      reference_number: 'YFH-2026-000005',
      total_members: 4,
      votes_cast: 2,
      waiting_for: ['Ahmed', 'Sara'],
    }
    expect(session.waiting_for).toHaveLength(2)
    expect(session.waiting_for[0]).toBe('Ahmed')
  })

  it('compliance_signal carries link_path for drilldown', () => {
    const signal: CbyAdminComplianceSignal = {
      key: 'duplicate_suppliers',
      label: 'موردون مكررون',
      count: 3,
      severity: 'attention',
      link_path: '/requests?flag=duplicate_suppliers',
    }
    expect(signal.link_path).toContain('duplicate_suppliers')
  })

  it('critical_event carries occurred_at timestamp', () => {
    const event: CbyAdminCriticalEvent = {
      id: 1,
      type: 'sla_breach',
      message: 'تجاوز SLA في مرحلة المراجعة',
      occurred_at: '2026-05-26T10:00:00.000000Z',
      severity: 'critical',
    }
    expect(event.occurred_at).toBeTruthy()
    expect(event.severity).toBe('critical')
  })
})
