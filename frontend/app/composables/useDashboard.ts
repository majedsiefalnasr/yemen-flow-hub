import type { ApiResponse, ImportRequest } from '../types/models'
import { useApi } from './useApi'

export interface DataEntryDashboardStats {
  draft: number
  returned: number
  under_cby_processing: number
  completed: number
  returned_requests: ImportRequest[]
  recent_requests: ImportRequest[]
}

export interface BankReviewerDashboardStats {
  pending_review: number
  at_cby: number
  returned_by_support: number
  approved_completed: number
  review_queue: ImportRequest[]
}

export interface SupportCommitteeDashboardStats {
  waiting_for_claim: number
  active_by_me: number
  claimed_by_others: number
  approved_last_7_days: number
  support_queue: ImportRequest[]
}

export interface SwiftOfficerDashboardStats {
  pending_swift_upload: number
  uploaded: number
  final_approved: number
  final_rejected: number
  swift_queue: ImportRequest[]
}

export interface ExecutiveDashboardStats {
  waiting_for_voting_open: number
  active_voting_sessions: number
  decisions_approved: number
  decisions_rejected: number
  voting_queue: ImportRequest[]
}

export type DashboardStats = DataEntryDashboardStats | BankReviewerDashboardStats | SupportCommitteeDashboardStats | SwiftOfficerDashboardStats | ExecutiveDashboardStats

export function useDashboard() {
  const { get } = useApi()

  async function fetchStats(): Promise<DashboardStats> {
    const response = await get<ApiResponse<DashboardStats>>('/api/dashboard/stats')
    return response.data
  }

  return { fetchStats }
}
