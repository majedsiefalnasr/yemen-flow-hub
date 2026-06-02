import type { ApiResponse, ImportRequest } from '../types/models'
import { useApi } from './useApi'

export interface DataEntryDashboardStats {
  draft: number
  returned: number
  under_cby_processing: number
  completed: number
  draft_requests: ImportRequest[]
  returned_requests: ImportRequest[]
  recent_requests: ImportRequest[]
}

export interface BankReviewerDashboardStats {
  pending_review: number
  at_cby: number
  returned_by_support: number
  approved_completed: number
  review_queue: ImportRequest[]
  downstream_queue?: ImportRequest[]
}

export interface BankAdminMonthlyEntry {
  month: string
  count: number
}

export interface BankAdminDashboardStats {
  total: number
  pending: number
  approved: number
  rejected: number
  total_financed_amount: number
  monthly_requests: BankAdminMonthlyEntry[]
  recent_requests: ImportRequest[]
}

export interface SupportCommitteeDashboardStats {
  waiting_for_claim: number
  active_by_me: number
  claimed_by_others: number
  recently_approved: number
  support_queue: ImportRequest[]
}

export interface SwiftOfficerDashboardStats {
  pending_swift_upload: number
  uploaded: number
  final_approved: number
  final_rejected: number
  swift_queue: ImportRequest[]
}

export interface VotingQueueItem extends ImportRequest {
  my_vote?: 'approve' | 'reject' | null
  votes_cast?: number
  total_voters?: number
}

export interface ExecutiveDashboardStats {
  waiting_for_voting_open: number
  active_voting_sessions: number
  decisions_approved: number
  decisions_rejected: number
  finalized_decisions: number
  // pending_my_vote: sessions where EXECUTIVE_VOTING_OPEN and I have not voted
  pending_my_vote?: number
  voting_queue: VotingQueueItem[]
  customs_declaration_pending?: ImportRequest[]
  sessions_ready_to_close?: number
  sessions_with_tie?: number
  fx_confirmation_pending?: number
  finalized_approved?: number
  finalized_rejected?: number
  voting_lifecycle_queue?: VotingQueueItem[]
  fx_confirmation_queue?: ImportRequest[]
}

export interface CbyAdminComplianceAlerts {
  duplicate_suppliers: Array<{ supplier_name: string; count: number }>
  high_amount_requests: Array<{ id: number; reference_number: string; amount: number; currency: string; bank_name: string }>
  stale_pending_requests: Array<{ id: number; reference_number: string; bank_name: string; updated_at: string | null }>
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

export interface CbyAdminVotingSession {
  id: number
  reference_number: string
  bank_name: string
  amount: number
  currency: string
  opened_at: string
  waiting_for: string[]
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
  type: 'sla_breach' | 'duplicate_invoice' | 'high_risk_bank' | 'stale_vote' | 'audit_anomaly'
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
  recent_requests?: ImportRequest[]
  category_distribution?: CbyAdminCategoryEntry[]
  // governance KPIs
  active_workflow_requests?: CbyAdminKpi
  sla_violations?: CbyAdminKpi
  open_voting_sessions?: CbyAdminKpi
  fx_confirmation_pending?: CbyAdminKpi
  bank_risk_alerts?: CbyAdminKpi
  system_availability?: CbyAdminKpi
  // governance panels
  workflow_pressure_map?: CbyAdminWorkflowPressureRow[]
  executive_voting_sessions?: CbyAdminVotingSession[]
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

export type DashboardStats = DataEntryDashboardStats | BankReviewerDashboardStats | BankAdminDashboardStats | SupportCommitteeDashboardStats | SwiftOfficerDashboardStats | ExecutiveDashboardStats | CbyAdminDashboardStats

export function useDashboard() {
  const { get } = useApi()

  async function fetchStats(): Promise<DashboardStats> {
    const response = await get<ApiResponse<DashboardStats>>('/api/dashboard/stats')
    return response.data
  }

  return { fetchStats }
}
