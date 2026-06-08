/**
 * Pure helpers shared by `BankAdminDashboard.vue` and its Vitest spec.
 *
 * Extracted from inline definitions so both production code and tests
 * exercise the same semantics (threshold for `repeated_support_returns`,
 * Monthly Trend chart geometry, health-strip rule set). Previously the
 * test file declared its own copies with drifted thresholds, masking
 * real divergence.
 */

import type {
  BankAdminDashboardStats,
  BankAdminDashboardStatsExtended,
} from '../composables/useDashboard'
import { NOT_ELIGIBLE_LABEL_AR } from '../constants/workflow'

// Monthly Trend chart geometry — used for SVG viewBox and point coords.
export const CHART_W = 480
export const CHART_H = 80
export const CHART_PAD = 8

// Bank Not-Eligible rate (%) above which the KPI gets a rose left-border
// highlight and the Operational Health strip considers the rate an
// alert condition.
export const REJECTION_THRESHOLD = 20

// Repeated support-return count above which the Operational Health strip
// flags the bank for a quality review. Must stay in sync with backend
// `repeated_support_returns` field semantics when wired up.
export const REPEATED_SUPPORT_RETURNS_THRESHOLD = 2

export interface DualEntry {
  month: string
  count: number
  approved?: number
}

export function buildLine(entries: DualEntry[], key: keyof DualEntry): string {
  if (!entries.length) return ''
  const vals = entries.map((e) => Number(e[key] ?? 0))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - CHART_PAD * 2) / Math.max(entries.length - 1, 1)
  return entries
    .map((e, i) => {
      const x = CHART_PAD + i * step
      const y = CHART_PAD + (1 - Number(e[key] ?? 0) / max) * (CHART_H - CHART_PAD * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

export function buildArea(entries: DualEntry[], key: keyof DualEntry): string {
  if (!entries.length) return ''
  const vals = entries.map((e) => Number(e[key] ?? 0))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - CHART_PAD * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = CHART_PAD + i * step
    const y = CHART_PAD + (1 - Number(e[key] ?? 0) / max) * (CHART_H - CHART_PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })
  const bottom = CHART_H - CHART_PAD
  const lastX = (CHART_PAD + (entries.length - 1) * step).toFixed(1)
  return `${CHART_PAD},${bottom} ${pts.join(' ')} ${lastX},${bottom}`
}

export function calcRejectionRate(
  stats: (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null,
): number {
  if (!stats) return 0
  if (stats.rejection_rate !== undefined) return stats.rejection_rate
  const total = stats.total || 1
  return Math.round((stats.rejected / total) * 100)
}

export function calcShowHealthStrip(
  stats: (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null,
): boolean {
  if (!stats) return false
  return (
    calcRejectionRate(stats) > REJECTION_THRESHOLD ||
    (stats.stalled_at_cby_count ?? 0) > 0 ||
    stats.missing_bank_reviewer_coverage === true ||
    (stats.repeated_support_returns ?? 0) > REPEATED_SUPPORT_RETURNS_THRESHOLD ||
    stats.suspended_staff_with_active === true
  )
}

export function calcHealthIssues(
  stats: (BankAdminDashboardStats & BankAdminDashboardStatsExtended) | null,
): string[] {
  if (!stats) return []
  const issues: string[] = []
  const rate = calcRejectionRate(stats)
  if (rate > REJECTION_THRESHOLD) issues.push(`معدل ${NOT_ELIGIBLE_LABEL_AR} مرتفع: ${rate}%`)
  if ((stats.stalled_at_cby_count ?? 0) > 0)
    issues.push(`${stats.stalled_at_cby_count} طلب متوقف لدى اللجنة الوطنية`)
  if (stats.missing_bank_reviewer_coverage) issues.push('لا يوجد مراجع بنك نشط لاستلام الطلبات')
  if ((stats.repeated_support_returns ?? 0) > REPEATED_SUPPORT_RETURNS_THRESHOLD) {
    issues.push(`${stats.repeated_support_returns} إعادة متكررة من لجنة المساندة`)
  }
  if (stats.suspended_staff_with_active) issues.push('موظف موقوف لديه مهام نشطة')
  return issues
}
