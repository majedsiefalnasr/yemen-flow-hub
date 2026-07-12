import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

export interface BankAdminMonthlyEntry {
  month: string
  count: number
}

/**
 * A read-model queue row as emitted by EngineRequestReadModel::resourceCollection
 * (backend/app/Support/EngineRequestReadModel.php) — the shape every
 * `recent_requests`-style list on /api/dashboard/stats actually returns.
 * `status` is the 5-value runtime status (ACTIVE/CLOSED/REJECTED/CANCELLED/
 * ABANDONED). There is no nested `merchant.name` or `supplier_name` — only
 * the flat `merchant_name`.
 */
export interface DashboardQueueItem {
  id: number
  reference: string
  reference_number: string
  status: string
  stage_code: string | null
  stage_name: string | null
  bank_id: number | null
  bank_name: string | null
  merchant_id: number | null
  merchant_name: string | null
  amount: number | null
  currency: string | null
  created_by: number | null
  created_by_name: string | null
  created_at: string | null
}

export interface BankAdminDashboardStats {
  total: number
  pending: number
  approved: number
  rejected: number
  total_financed_amount: number
  monthly_requests: BankAdminMonthlyEntry[]
  recent_requests: DashboardQueueItem[]
}

export interface CbyAdminComplianceAlerts {
  duplicate_suppliers: Array<{ supplier_name: string; count: number }>
  high_amount_requests: Array<{
    id: number
    reference_number: string
    amount: number
    currency: string
    bank_name: string
  }>
  stale_pending_requests: Array<{
    id: number
    reference_number: string
    bank_name: string
    updated_at: string | null
  }>
}

export interface CbyAdminMonthlyEntry {
  month: string
  submitted: number
  approved: number
}

export interface CbyAdminCategoryEntry {
  label: string
  count: number
  color: string
}

export interface CbyAdminKpiSparkEntry {
  period: string
  value: number
}

export interface CbyAdminKpi {
  value: number
  delta: number
  severity: 'red' | 'amber' | 'green' | 'blue'
  sparkline: CbyAdminKpiSparkEntry[]
  drilldown_route: string
}

export interface CbyAdminWorkflowPressureRow {
  stage: string
  stage_label: string
  active_count: number
  avg_age_hours: number
  sla_risk: 'low' | 'medium' | 'high'
  trend: 'up' | 'stable' | 'down'
}

export interface CbyAdminBankRiskRow {
  bank_id: number
  bank_name: string
  request_volume: number
  approval_rate: number
  avg_sla_hours: number
  risk_score: number
  alerts: number
}

export interface CbyAdminComplianceSignal {
  id: string
  type: 'sla_breach' | 'duplicate_invoice' | 'high_risk_bank' | 'audit_anomaly'
  title: string
  description: string
  severity: 'red' | 'amber' | 'blue'
  link_route: string
  created_at: string
}

export interface CbyAdminCriticalEvent {
  id: number
  event_type: string
  summary: string
  actor_name: string
  created_at: string
  link_route?: string
}

export interface CbyAdminDashboardStats {
  total: number
  approved: number
  in_process: number
  rejected: number
  compliance_alerts: CbyAdminComplianceAlerts
  most_active_banks: Array<{ bank_id: number; bank_name: string; request_count: number }>
  monthly_requests?: CbyAdminMonthlyEntry[]
  recent_requests?: DashboardQueueItem[]
  category_distribution?: CbyAdminCategoryEntry[]
  // governance KPIs
  active_workflow_requests?: CbyAdminKpi
  sla_violations?: CbyAdminKpi
  fx_confirmation_pending?: CbyAdminKpi
  bank_risk_alerts?: CbyAdminKpi
  system_availability?: CbyAdminKpi
  // governance panels
  workflow_pressure_map?: CbyAdminWorkflowPressureRow[]
  bank_risk_intelligence?: CbyAdminBankRiskRow[]
  compliance_signals?: CbyAdminComplianceSignal[]
  critical_events?: CbyAdminCriticalEvent[]
}

export interface BankAdminDashboardStatsExtended extends BankAdminDashboardStats {
  in_process?: number
  rejection_rate?: number
  stalled_at_cby_count?: number
  missing_bank_reviewer_coverage?: boolean
  repeated_support_returns?: number
  suspended_staff_with_active?: boolean
}

export type DashboardStats = BankAdminDashboardStats | CbyAdminDashboardStats

export function useDashboard() {
  const { get } = useApi()

  async function fetchStats(): Promise<DashboardStats> {
    const response = await get<ApiResponse<DashboardStats>>('/api/dashboard/stats')
    return response.data
  }

  return { fetchStats }
}
