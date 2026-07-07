/**
 * Pure helpers shared by `CbyAdminDashboard.vue` and its Vitest spec.
 *
 * The original Story 12.2 test file (`CbyAdminDashboard12_2.test.ts`) declared
 * its own local copies of these functions, which silently drifted from the
 * production semantics (severity vocab, threshold cutoffs). Extracting here
 * so both component and test import the same code prevents that drift.
 *
 * Color values come from `DESIGN.md` / project palette only — no new tokens.
 */

import type {
  CbyAdminDashboardStats,
  CbyAdminKpi,
  CbyAdminWorkflowPressureRow,
} from '../composables/useDashboard'

// Mini sparkline geometry constants — used both for SVG viewBox and point coords.
export const SPARK_W = 80
export const SPARK_H = 28
export const SPARK_PAD = 3

export function makeKpi(overrides: Partial<CbyAdminKpi> = {}): CbyAdminKpi {
  return {
    value: 0,
    delta: 0,
    severity: 'blue',
    sparkline: [],
    drilldown_route: '/workflows',
    ...overrides,
  }
}

export function resolvedKpi(
  stats: CbyAdminDashboardStats | null | undefined,
  key: keyof CbyAdminDashboardStats,
): CbyAdminKpi {
  const raw = stats?.[key]
  if (raw && typeof raw === 'object' && 'value' in raw) return raw as CbyAdminKpi
  const fallbackValue = typeof raw === 'number' ? raw : 0
  return {
    value: fallbackValue,
    delta: 0,
    severity: 'blue',
    sparkline: [],
    drilldown_route: '/workflows',
  }
}

// Returns CSS `var()` references rather than raw hex so theming flows through
// the token system (`--severity-*` / `--brand-color` in `assets/css/main.css`).
// Pass these into `:style` bindings — the browser resolves the var at render.
export function severityColor(severity: CbyAdminKpi['severity']): string {
  return (
    (
      {
        red: 'var(--severity-red)',
        amber: 'var(--severity-amber)',
        green: 'var(--severity-green)',
        blue: 'var(--brand-color)',
      } as Record<string, string>
    )[severity] ?? 'var(--brand-color)'
  )
}

export function severityBg(severity: CbyAdminKpi['severity']): string {
  return (
    (
      {
        red: 'bg-red-50',
        amber: 'bg-amber-50',
        green: 'bg-green-50',
        blue: 'bg-blue-50',
      } as Record<string, string>
    )[severity] ?? ''
  )
}

export function buildSparkLine(entries: Array<{ value: number }>): string {
  if (entries.length < 2) return ''
  const vals = entries.map((e) => e.value)
  const max = Math.max(...vals, 1)
  const min = Math.min(...vals, 0)
  const range = max - min || 1
  const step = (SPARK_W - SPARK_PAD * 2) / Math.max(entries.length - 1, 1)
  return entries
    .map((e, i) => {
      const x = SPARK_PAD + i * step
      const y = SPARK_PAD + (1 - (e.value - min) / range) * (SPARK_H - SPARK_PAD * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

export function slaRiskColor(risk: CbyAdminWorkflowPressureRow['sla_risk']): string {
  return (
    (
      {
        low: 'var(--severity-green)',
        medium: 'var(--severity-amber)',
        high: 'var(--severity-red)',
      } as Record<string, string>
    )[risk] ?? 'var(--brand-color)'
  )
}

export function slaRiskLabel(risk: CbyAdminWorkflowPressureRow['sla_risk']): string {
  return ({ low: 'منخفض', medium: 'متوسط', high: 'مرتفع' } as Record<string, string>)[risk] ?? '—'
}

export function trendColor(trend: CbyAdminWorkflowPressureRow['trend']): string {
  return (
    (
      {
        up: 'var(--severity-red)',
        stable: 'var(--locked)',
        down: 'var(--severity-green)',
      } as Record<string, string>
    )[trend] ?? 'var(--locked)'
  )
}

export function riskScoreColor(score: number): string {
  if (score >= 70) return 'var(--severity-red)'
  if (score >= 40) return 'var(--severity-amber)'
  return 'var(--severity-green)'
}

export function formatDuration(hours: number): string {
  if (hours < 24) return `${Math.round(hours)} س`
  return `${Math.round(hours / 24)} ي`
}
