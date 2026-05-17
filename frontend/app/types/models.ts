import type { RequestStatus, UserRole, VoteType, VotingSessionStatus } from './enums'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  bank_id: number | null
  bank_name_ar: string | null
  bank_name_en: string | null
  is_active: boolean
}

export interface UserPreferences {
  language: string
  dashboard_view: string
  table_density: string
  page_size: number
  default_filters: Record<string, any>
  notification_preferences: Record<string, any>
}

export interface Bank {
  id: number
  name_ar: string
  name_en: string
  code: string
  is_active: boolean
}

export interface Merchant {
  id: number
  name: string
  commercial_register: string | null
  address: string | null
  bank_id: number
}

export interface ImportRequest {
  id: number
  reference_number: string
  bank_id: number
  bank_name: string | null
  merchant: { id: number; name: string; commercial_register: string | null } | null
  status: RequestStatus
  current_owner_role: UserRole
  currency: string
  amount: number
  supplier_name: string
  goods_description: string
  port_of_entry: string
  notes: string | null
  created_by: number
  submitted_by: number | null
  reviewed_by: number | null
  approved_by: number | null
  rejected_by: number | null
  resubmitted_by: number | null
  claimed_by: { id: number; name: string } | null
  claimed_until: string | null
  is_claimed: boolean
  is_claimed_by_me: boolean
  can_be_claimed: boolean
  submitted_at: string | null
  bank_approved_at: string | null
  support_approved_at: string | null
  swift_uploaded_by: number | null
  swift_uploaded_at: string | null
  voting_opened_by: number | null
  voting_opened_at: string | null
  voting_closed_by: number | null
  voting_closed_at: string | null
  voting_session_status: VotingSessionStatus | null
  executive_decided_at: string | null
  customs_issued_at: string | null
  customs_declaration?: CustomsDeclarationSummary | null
  revision_count: number
  created_at: string
  updated_at: string
  documents?: RequestDocument[]
}

export interface CustomsDeclarationSummary {
  id: number
  declaration_number: string
  issued_at: string
  issued_by: number | null
  issuer: { id: number; name: string } | null
  download_url: string
}

export interface CustomsDeclaration {
  id: number
  request_id: number
  declaration_number: string
  issued_by: number
  issuer: { id: number; name: string; email: string; role: UserRole } | null
  issued_at: string
  request: { id: number; reference_number: string; bank_name: string | null } | null
  metadata: Record<string, unknown> | null
  download_url: string
  created_at: string
}

export interface RequestDocument {
  id: number
  type: string | null
  original_filename: string
  mime_type: string | null
  size_bytes: number
  checksum: string
  uploaded_by: number
  uploaded_by_name: string | null
  uploaded_at: string
  download_url: string
}

/** Fields sent to POST /api/requests and PUT /api/requests/{id} */
export interface RequestFormData {
  merchant_id: number
  currency: string
  amount: number
  supplier_name: string
  goods_description: string
  port_of_entry: string
  notes: string
}

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  bank_id: number | null
  bank_name_ar: string | null
  bank_name_en: string | null
  is_active: boolean
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
}

export interface ApiError {
  success: false
  message: string
  error_code?: string
  current_status?: string
  errors?: Record<string, string[]>
}

export interface RequestVote {
  id: number
  request_id: number
  user_id: number
  user_name: string | null
  vote: VoteType
  justification: string | null
  is_director_override: boolean
  voted_at: string | null
  created_at: string
}

export interface VotingTally {
  approve_count: number
  reject_count: number
  abstain_count: number
  auto_abstain_count: number
  total_cast: number
  is_decided: boolean
  result: 'APPROVED' | 'REJECTED' | 'TIE' | 'PENDING'
}

export interface VotingDetail {
  request: ImportRequest
  tally: VotingTally
  votes: RequestVote[]
  total_members: number
  my_vote: RequestVote | null
}

export interface RequestStageHistory {
  id: number
  request_id: number
  from_status: string | null
  to_status: string | null
  from_owner_role: string | null
  to_owner_role: string | null
  actor_id: number
  actor_role: string | null
  performed_by: { id: number; name: string; role: string | null } | null
  action: string
  notes: string | null
  metadata: Record<string, unknown> | null
  created_at: string
}
