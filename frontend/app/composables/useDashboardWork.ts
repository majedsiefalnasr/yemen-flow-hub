import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

/**
 * A read-model queue row as emitted by EngineRequestReadModel::resourceCollection
 * — the shape every /dashboard/work section item uses.
 */
export interface WorkQueueItem {
  id: number
  reference: string
  reference_number: string
  status: string
  stage_code: string | null
  stage_name: string | null
  bank_name: string | null
  merchant_name: string | null
  amount: number | null
  currency: string | null
  created_at: string | null
}

export interface WorkSection {
  count: number
  items: WorkQueueItem[]
  queue_url?: string
}

/**
 * The generic work-dashboard contract (Phase D0). Derived entirely from the
 * user's authorization + workflow metadata on the backend; the frontend renders
 * a fixed set of sections and shows only those that carry data.
 */
export interface DashboardWork {
  actionable: WorkSection
  claimed: WorkSection
  tracking: WorkSection
  sla: { near_due: number; overdue: number }
  recent_activity: unknown[]
  metrics: unknown[]
}

export function useDashboardWork() {
  const { get } = useApi()

  async function fetchWork(): Promise<DashboardWork> {
    const response = await get<ApiResponse<DashboardWork>>('/api/dashboard/work')
    return response.data
  }

  return { fetchWork }
}
