export type GovernanceEntityType =
  | 'organization'
  | 'team'
  | 'role'
  | 'user'
  | 'reference_table'
  | 'reference_value'

export type GovernanceLifecycleAction = 'delete' | 'deactivate'

export interface GovernanceImpactAffectedEntry {
  workflow_definition?: { id: number; code: string; name: string } | null
  workflow_version?: { id: number; version_number: number; state: string } | null
  stage?: { id: number; code: string; name: string; is_final?: boolean }
  field?: { id: number; key: string; label: string }
  executor_count?: number
  executor_count_after?: number
}

export interface GovernanceImpactPayload {
  entity_type: GovernanceEntityType
  entity_id: number
  referenced_by_published: boolean
  referenced_by_draft_only: boolean
  would_break_executor: boolean
  affected: GovernanceImpactAffectedEntry[]
  warnings: string[]
}

export interface BankLifecycleImpactPayload {
  entity_type: 'bank'
  entity_id: number
  referenced_by_published: boolean
  would_break_executor: boolean
  usage: {
    users: number
    merchants: number
    engine_requests_total: number
    engine_requests_in_flight: number
    engine_requests_closed: number
  }
  warnings: string[]
  can_suspend: boolean
  can_delete: boolean
}

export function isGovernanceActionBlocked(
  impact: GovernanceImpactPayload,
  action: GovernanceLifecycleAction,
): boolean {
  if (action === 'delete') {
    return impact.referenced_by_published
  }

  return impact.would_break_executor
}
