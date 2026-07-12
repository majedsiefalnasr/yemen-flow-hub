/**
 * CbyAdminDashboard 12.2 — governance uplift logic tests.
 *
 * Pure function tests, no component mounting. Helpers imported directly from
 * the production module so any future drift in semantics is caught here.
 * Resolves code-review finding C1 (test reimplementation drift).
 */
import { describe, it, expect } from 'vitest'
import type {
  CbyAdminDashboardStats,
  CbyAdminKpi,
  CbyAdminWorkflowPressureRow,
  CbyAdminComplianceSignal,
  CbyAdminCriticalEvent,
} from '../../../composables/useDashboard'
import {
  SPARK_PAD,
  makeKpi,
  resolvedKpi,
  buildSparkLine,
  riskScoreColor,
  severityBg,
  severityColor,
} from '../../../utils/cby-admin-helpers'

function makeBaseStats(overrides: Partial<CbyAdminDashboardStats> = {}): CbyAdminDashboardStats {
  return {
    total: 0,
    approved: 0,
    in_process: 0,
    rejected: 0,
    compliance_alerts: {
      duplicate_suppliers: [],
      high_amount_requests: [],
      stale_pending_requests: [],
    },
    most_active_banks: [],
    ...overrides,
  }
}

// ── resolvedKpi ───────────────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — resolvedKpi()', () => {
  it('returns zero-fallback with blue severity when KPI key is missing', () => {
    const stats = makeBaseStats()
    const result = resolvedKpi(stats, 'active_workflow_requests')
    expect(result.value).toBe(0)
    expect(result.severity).toBe('blue')
    expect(result.sparkline).toHaveLength(0)
  })

  it('returns actual KPI when key is present', () => {
    const kpi = makeKpi({ value: 42, delta: 5, severity: 'amber' })
    const stats = makeBaseStats({ active_workflow_requests: kpi })
    expect(resolvedKpi(stats, 'active_workflow_requests').value).toBe(42)
    expect(resolvedKpi(stats, 'active_workflow_requests').severity).toBe('amber')
  })

  it('returns fallback for sla_violations when absent', () => {
    const stats = makeBaseStats()
    expect(resolvedKpi(stats, 'sla_violations').value).toBe(0)
  })

  it('returns actual sla_violations KPI with red severity', () => {
    const kpi = makeKpi({ value: 7, severity: 'red' })
    const stats = makeBaseStats({ sla_violations: kpi })
    expect(resolvedKpi(stats, 'sla_violations').value).toBe(7)
    expect(resolvedKpi(stats, 'sla_violations').severity).toBe('red')
  })

  it('returns fallback for system_availability when absent', () => {
    const stats = makeBaseStats()
    expect(resolvedKpi(stats, 'system_availability').value).toBe(0)
  })

  it('treats a plain numeric value (legacy shape) as fallback with blue severity', () => {
    // Some KPI keys can arrive as a bare number in legacy/partial backend
    // responses. resolvedKpi should still produce a CbyAdminKpi.
    const stats = { ...makeBaseStats(), system_availability: 99 as unknown as CbyAdminKpi }
    const result = resolvedKpi(stats, 'system_availability')
    expect(result.value).toBe(99)
    expect(result.severity).toBe('blue')
  })

  it('tolerates null stats argument', () => {
    const result = resolvedKpi(null, 'sla_violations')
    expect(result.value).toBe(0)
    expect(result.severity).toBe('blue')
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

  it(`first point starts at left pad (x = ${SPARK_PAD})`, () => {
    const pts = buildSparkLine([{ value: 5 }, { value: 8 }, { value: 3 }])
    const firstX = Number(pts.split(' ')[0]!.split(',')[0])
    expect(firstX).toBeCloseTo(SPARK_PAD, 1)
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
    expect(firstY).toBeCloseTo(SPARK_PAD, 0)
  })
})

// ── riskScoreColor ────────────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — riskScoreColor()', () => {
  it('returns the severity-red token for score ≥ 70', () => {
    expect(riskScoreColor(70)).toBe('var(--severity-red)')
    expect(riskScoreColor(100)).toBe('var(--severity-red)')
  })

  it('returns the severity-amber token for score 40–69', () => {
    expect(riskScoreColor(40)).toBe('var(--severity-amber)')
    expect(riskScoreColor(69)).toBe('var(--severity-amber)')
  })

  it('returns the severity-green token for score < 40', () => {
    expect(riskScoreColor(0)).toBe('var(--severity-green)')
    expect(riskScoreColor(39)).toBe('var(--severity-green)')
  })
})

// ── severityBg & severityColor ────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — severity helpers', () => {
  it('red severity → red bg class and severity-red token', () => {
    expect(severityBg('red')).toBe('bg-red-50')
    expect(severityColor('red')).toBe('var(--severity-red)')
  })

  it('amber severity → amber bg class and severity-amber token', () => {
    expect(severityBg('amber')).toBe('bg-amber-50')
    expect(severityColor('amber')).toBe('var(--severity-amber)')
  })

  it('green severity → green bg class and severity-green token', () => {
    expect(severityBg('green')).toBe('bg-green-50')
    expect(severityColor('green')).toBe('var(--severity-green)')
  })

  it('blue severity → blue bg class and brand-color token', () => {
    expect(severityBg('blue')).toBe('bg-blue-50')
    expect(severityColor('blue')).toBe('var(--brand-color)')
  })

  it('unknown severity → empty bg class and brand-color fallback', () => {
    // The TypeScript type-system rules these out at compile time, but the
    // helpers must still be defensive at runtime against malformed backend
    // payloads (resolves code-review H12).
    expect(severityBg('unknown' as 'red')).toBe('')
    expect(severityColor('unknown' as 'red')).toBe('var(--brand-color)')
  })
})

// ── Governance stats shape ────────────────────────────────────────────────────

describe('CbyAdminDashboard 12.2 — governance stats optional fields', () => {
  it('all 5 KPI slots are optional on CbyAdminDashboardStats', () => {
    const stats = makeBaseStats()
    expect(stats.active_workflow_requests).toBeUndefined()
    expect(stats.sla_violations).toBeUndefined()
    expect(stats.fx_confirmation_pending).toBeUndefined()
    expect(stats.bank_risk_alerts).toBeUndefined()
    expect(stats.system_availability).toBeUndefined()
  })

  it('governance panel fields are optional on CbyAdminDashboardStats', () => {
    const stats = makeBaseStats()
    expect(stats.workflow_pressure_map).toBeUndefined()
    expect(stats.bank_risk_intelligence).toBeUndefined()
    expect(stats.compliance_signals).toBeUndefined()
    expect(stats.critical_events).toBeUndefined()
  })

  it('all 5 KPI slots can be populated', () => {
    const kpi = makeKpi({ value: 10, severity: 'amber' })
    const stats = makeBaseStats({
      active_workflow_requests: kpi,
      sla_violations: kpi,
      fx_confirmation_pending: kpi,
      bank_risk_alerts: kpi,
      system_availability: kpi,
    })
    const keys: (keyof CbyAdminDashboardStats)[] = [
      'active_workflow_requests',
      'sla_violations',
      'fx_confirmation_pending',
      'bank_risk_alerts',
      'system_availability',
    ]
    for (const k of keys) {
      expect(resolvedKpi(stats, k).value).toBe(10)
    }
  })

  it('workflow_pressure_map rows carry sla_risk and trend fields', () => {
    const row: CbyAdminWorkflowPressureRow = {
      stage: 'BANK_REVIEW',
      stage_label: 'مراجعة البنك',
      active_count: 12,
      avg_age_hours: 84,
      sla_risk: 'medium',
      trend: 'up',
    }
    expect(row.sla_risk).toBe('medium')
    expect(row.trend).toBe('up')
  })

  it('compliance_signal carries link_route for drilldown', () => {
    const signal: CbyAdminComplianceSignal = {
      id: 'dup-suppliers-2026-05-26',
      type: 'duplicate_invoice',
      title: 'موردون مكررون',
      description: 'تم رصد 3 مطابقات لفواتير من موردين متطابقين خلال 30 يوماً',
      severity: 'amber',
      link_route: '/workflows?flag=duplicate_suppliers',
      created_at: '2026-05-26T08:30:00.000000Z',
    }
    expect(signal.link_route).toContain('duplicate_suppliers')
    expect(signal.severity).toBe('amber')
  })

  it('critical_event carries event_type and summary', () => {
    const event: CbyAdminCriticalEvent = {
      id: 1,
      event_type: 'fx_completed',
      summary: 'تم إصدار تأكيد المصارفة الخارجية للطلب YFH-2026-000005',
      actor_name: 'مدير اللجنة',
      created_at: '2026-05-26T10:00:00.000000Z',
      link_route: '/workflows/instances/5',
    }
    expect(event.event_type).toBe('fx_completed')
    expect(event.summary).toContain('YFH-2026')
  })
})
