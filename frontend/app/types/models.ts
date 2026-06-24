import type {
  CoverageType,
  CurrencySource,
  Incoterm,
  InvoiceType,
  PaymentTermsMode,
  PortOfArrival,
  RequestStatus,
  RequestType,
  UserRole,
  VoteType,
  VotingSessionStatus,
} from './enums'
import type { Trader, TraderCompany, TraderOwner } from './trader'

export interface ProfileStats {
  total: number
  in_progress: number
  completed: number
  // BANK_REVIEWER-specific fields (optional — backend populates per role)
  reviews_performed?: number
  approvals?: number
  returns?: number
  terminal_rejections?: number
  // BANK_ADMIN-specific fields (optional — backend populates per role)
  staff_managed?: number
  merchants_managed?: number
  // COMMITTEE_DIRECTOR-specific fields (optional — backend populates per role)
  sessions_closed?: number
  decisions_finalized?: number
  fx_confirmations_completed?: number
  // EXECUTIVE_MEMBER-specific fields (optional — backend populates per role)
  sessions_participated?: number
  avg_time_to_vote_hours?: number
  approval_percentage?: number
  // SWIFT_OFFICER-specific fields (optional — backend populates per role)
  swift_uploads?: number
  avg_time_to_upload_hours?: number
}

export interface RecentActivity {
  id: number
  action: string
  ref: string | null
  ts: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  phone?: string | null
  role: UserRole
  bank_id: number | null
  bank_name_ar: string | null
  bank_name_en: string | null
  is_active: boolean
  must_change_password?: boolean
  mfa_enabled?: boolean
  mfa_required?: boolean
  totp_enabled?: boolean
  pin_enabled?: boolean
  avatar_variant?: string | null
  stats?: ProfileStats
  recent_activity?: RecentActivity[]
  organization?: GovernanceIdentity | null
  team?: GovernanceIdentity | null
  identity_role?: GovernanceIdentity | null
  bank?: GovernanceBank | null
}

export interface GovernanceIdentity {
  id: number
  organization_id?: number
  code: string
  name: string
}

export interface Organization extends GovernanceIdentity {
  is_system: boolean
  is_active: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface GovernanceTeam extends GovernanceIdentity {
  organization_id: number
  organization: Organization
  is_system: boolean
  is_active: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export type GovernanceRole = GovernanceTeam

export interface GovernanceUser {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  mfa_enabled: boolean
  organization: Organization
  team: GovernanceTeam
  role: GovernanceRole
  bank: Bank | null
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface GovernanceBank {
  id: number
  code: string
  name: string
}

export type ScreenCapability = 'VIEW' | 'CREATE' | 'UPDATE' | 'DELETE' | 'EXPORT' | 'MANAGE'
export type ScreenPermissions = Record<string, ScreenCapability[]>

export interface AuthMeData {
  user: AuthUser
  organization: GovernanceIdentity | null
  team: GovernanceIdentity | null
  role: GovernanceIdentity | null
  bank: GovernanceBank | null
  screen_permissions: ScreenPermissions
  capabilities: Record<string, boolean>
}

export interface NotificationPreferences {
  request_approved?: boolean
  request_rejected?: boolean
  request_returned?: boolean
  voting_opened?: boolean
  request_submitted?: boolean
  swift_upload_requested?: boolean
  claim_released?: boolean
}

export interface UserPreferences {
  language: string
  dashboard_view: string
  table_density: string
  page_size: number
  default_filters: Record<string, any>
  notification_preferences: NotificationPreferences
  email_notifications?: boolean
  theming?: Record<string, any>
}

export interface Bank {
  id: number
  organization_id?: number | null
  organization?: Organization | null
  name_ar: string
  name_en: string
  code: string
  license_number?: string | null
  swift_code?: string | null
  status?: 'ACTIVE' | 'SUSPENDED'
  version?: number
  entity_type?: string | null
  user_count?: number | null
  admin?: User | null
  is_active: boolean
}

export type MerchantStatus = 'ACTIVE' | 'SUSPENDED'

export interface MerchantOwner {
  id: number
  name: string
  ownership_percentage: number
}

export interface MerchantCompany {
  id: number
  name: string
  commercial_registration_number: string
  commercial_registration_expiry: string | null
  sector_reference_value_id: number | null
  is_active: boolean
}

export interface Merchant {
  id: number
  bank_id: number
  bank_name: string | null
  name: string
  tax_number: string
  tax_card_expiry: string | null
  phone: string | null
  address: string | null
  status: MerchantStatus
  version: number
  transaction_count: number
  owners: MerchantOwner[]
  companies: MerchantCompany[]
  created_by: number | null
  created_at: string | null
  updated_at: string | null
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
  goods_type: string | null
  payment_terms: string | null
  due_date: string | null
  invoice_number: string | null
  invoice_date: string | null
  origin_country: string | null
  arrival_port: string | null
  shipping_port: string | null
  customs_office: string | null
  bl_number: string | null
  trader_id: number | null
  request_type: RequestType | null
  coverage_type: CoverageType | null
  currency_source: CurrencySource | null
  payment_terms_mode: PaymentTermsMode | null
  request_percentage: string | null
  request_currency: string | null
  requested_amount: string | null
  invoice_type: InvoiceType | null
  invoice_currency: string | null
  unit_of_measure: string | null
  total_invoice_amount: string | null
  commodity: string | null
  exporting_company_name: string | null
  exporting_company_location: string | null
  country_of_origin: string | null
  port_of_loading: string | null
  port_of_arrival: PortOfArrival | null
  incoterm: Incoterm | null
  final_destination: string | null
  shipping_date: string | null
  arrival_date: string | null
  trader_snapshot_name: string | null
  trader_snapshot_tax_number: string | null
  trader_snapshot_tax_card_expiry: string | null
  trader_snapshot_commercial_registration_number: string | null
  trader_snapshot_commercial_registration_expiry: string | null
  voting_rule_version: number
  created_by: number
  created_by_user?: { id: number; name: string } | null
  last_updated_by?: number | null
  last_updated_by_user?: { id: number; name: string } | null
  submitted_by: number | null
  submitted_by_user?: { id: number; name: string } | null
  reviewed_by: number | null
  reviewed_by_user?: { id: number; name: string } | null
  internal_reviewer?: { id: number; name: string } | null
  approved_by: number | null
  approved_by_user?: { id: number; name: string } | null
  rejected_by: number | null
  rejected_by_user?: { id: number; name: string } | null
  resubmitted_by: number | null
  resubmitted_by_user?: { id: number; name: string } | null
  support_reviewed_by?: number | null
  support_reviewed_by_user?: { id: number; name: string } | null
  support_reviewer?: { id: number; name: string } | null
  claimed_by: { id: number; name: string } | null
  support_claimed_by?: { id: number; name: string } | null
  claimed_at?: string | null
  claimed_until: string | null
  is_claimed: boolean
  is_claimed_by_me: boolean
  can_be_claimed: boolean
  submitted_at: string | null
  bank_approved_at: string | null
  support_approved_at: string | null
  swift_uploaded_by: number | null
  swift_uploaded_by_user?: { id: number; name: string } | null
  swift_uploaded_at: string | null
  voting_opened_by: number | null
  voting_opened_at: string | null
  voting_closed_by: number | null
  voting_closed_at: string | null
  voting_session_status: VotingSessionStatus | null
  executive_decided_at: string | null
  customs_issued_at: string | null
  customs_declaration?: CustomsDeclarationSummary | null
  bank_return_comment: string | null
  bank_reject_comment: string | null
  support_return_comment: string | null
  revision_count: number
  created_at: string
  updated_at: string
  documents?: RequestDocument[]
  duplicate_warnings?: DuplicateWarning[]
  votes_cast?: number
  total_voters?: number
  ready_to_close?: boolean
  is_tie?: boolean
  has_swift_document?: boolean
  has_fx_request_document?: boolean
}

export interface DuplicateWarning {
  bank_name: string | null
  id?: number
  reference_number?: string
  bank_id?: number
  amount?: number
  currency?: string
  created_at?: string
  status?: string
}

export interface CustomsDeclarationSummary {
  id: number
  declaration_number: string
  issued_at: string
  issued_by: number | null
  issuer: { id: number; name: string } | null
  signed_fx_doc_uploaded_at?: string | null
  signed_fx_doc_uploaded_by?: number | null
  has_signed_fx_doc?: boolean
}

export interface CustomsDeclaration {
  id: number
  request_id: number
  declaration_number: string
  issued_by: number
  issuer: { id: number; name: string; email: string; role: UserRole } | null
  issued_at: string
  signed_fx_doc_path?: string | null
  signed_fx_doc_uploaded_at?: string | null
  signed_fx_doc_uploaded_by?: number | null
  has_signed_fx_doc?: boolean
  request: { id: number; reference_number: string; bank_name: string | null } | null
  metadata: Record<string, any> | null
  created_at: string
}

export interface RequestDocument {
  id: number
  type: string | null
  document_sub_type?: string | null
  title?: string | null
  original_filename: string
  mime_type: string | null
  size_bytes: number
  checksum: string
  uploaded_by: number
  uploaded_by_name: string | null
  uploaded_at: string | null
}

/** Fields sent to POST /api/requests and PUT /api/requests/{id} */
export interface RequestFormData {
  merchant_id?: number | null
  currency: string
  amount: number
  supplier_name?: string | null
  goods_description?: string | null
  port_of_entry?: string | null
  notes?: string | null
  // Wizard step 1 extended fields
  goods_type?: string | null
  payment_terms?: string | null
  due_date?: string | null
  // Wizard step 2 fields
  invoice_number?: string | null
  invoice_date?: string | null
  origin_country?: string | null
  arrival_port?: string | null
  shipping_port?: string | null
  customs_office?: string | null
  bl_number?: string | null
  trader_id?: number | null
  request_type?: RequestType | null
  coverage_type?: CoverageType | null
  currency_source?: CurrencySource | null
  payment_terms_mode?: PaymentTermsMode | null
  request_percentage?: number | string | null
  request_currency?: string | null
  requested_amount?: number | string | null
  invoice_type?: InvoiceType | null
  invoice_currency?: string | null
  unit_of_measure?: string | null
  total_invoice_amount?: number | string | null
  commodity?: string | null
  exporting_company_name?: string | null
  exporting_company_location?: string | null
  country_of_origin?: string | null
  port_of_loading?: string | null
  port_of_arrival?: PortOfArrival | null
  incoterm?: Incoterm | null
  final_destination?: string | null
  shipping_date?: string | null
  arrival_date?: string | null
  trader_snapshot_name?: string | null
  trader_snapshot_tax_number?: string | null
  trader_snapshot_tax_card_expiry?: string | null
  trader_snapshot_commercial_registration_number?: string | null
  trader_snapshot_commercial_registration_expiry?: string | null
}

export interface TraderLookupResult {
  trader: Trader
  companies: TraderCompany[]
  owners: TraderOwner[]
}

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  bank_id: number | null
  bank_name?: string | null
  bank_name_ar: string | null
  bank_name_en: string | null
  last_login_at?: string | null
  last_seen_at?: string | null
  is_active: boolean
  must_change_password?: boolean
  mfa_enabled?: boolean
  totp_enabled?: boolean
  pin_enabled?: boolean
  avatar_variant?: string | null
  created_at?: string | null
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    status_totals?: Partial<Record<RequestStatus, number>>
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
  metadata: Record<string, any> | null
  created_at: string
}

export type NotificationType =
  | 'request_submitted'
  | 'request_approved'
  | 'request_rejected'
  | 'request_returned'
  | 'swift_upload_requested'
  | 'voting_opened'
  | 'customs_issued'
  | 'claim_released'

export interface NotificationData {
  type?: NotificationType
  message?: string
  message_ar?: string
  message_en?: string
  request_id?: number | null
  reference_number?: string | null
  request_reference?: string | null
  reason?: 'manual' | 'ttl_expired' | null
  released_by_user_id?: number | null
  released_by_name?: string | null
  title?: string
  body?: string
  severity?: string
  entity_type?: string | null
  entity_id?: number | null
  action_url?: string | null
}

export interface Notification {
  id: string
  type: string
  data: NotificationData
  read_at: string | null
  created_at: string
}

export type SearchEntityType = 'requests' | 'users' | 'banks' | 'customs'

export interface SearchRequestResult {
  id: number
  reference_number: string
  bank_id: number
  bank_name: string | null
  status: string | null
  supplier_name: string
  amount: number
  currency: string
  created_at: string | null
}

export interface SearchUserResult {
  id: number
  name: string
  email: string
  role: string
  role_label: string
  bank_id: number | null
  bank_name: string | null
  is_active: boolean
}

export interface SearchBankResult {
  id: number
  name: string
  code: string
  is_active: boolean
}

export interface SearchCustomsResult {
  id: number
  declaration_number: string
  issued_at: string | null
  request_id: number
  reference_number: string | null
}

export interface SearchResults {
  requests: SearchRequestResult[]
  users: SearchUserResult[]
  banks: SearchBankResult[]
  customs: SearchCustomsResult[]
}

export interface AuditLog {
  id: number
  user: { id: number; name: string; email: string; role: string } | null
  user_id: number | null
  user_role: string | null
  action: string
  entity_type: string | null
  entity_id: number | null
  entity_reference?: string | null
  from_status: string | null
  to_status: string | null
  ip_address: string | null
  user_agent?: string | null
  metadata: Record<string, any> | null
  created_at: string
}

export interface EngineAuditLog {
  id: number
  actor: { id: number; name: string; email: string } | null
  actor_user_id: number | null
  actor_role: { id: number; code: string; name: string } | null
  actor_role_id: number | null
  user_role: string | null
  event_code: string
  entity_type: string | null
  entity_id: number | null
  request_id: number | null
  correlation_id: string | null
  old_values: Record<string, any> | null
  new_values: Record<string, any> | null
  metadata: Record<string, any> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface DocumentType {
  id: number
  slug: string
  name_ar: string
  name_en: string
  is_required: boolean
  is_active: boolean
  sort_order: number
}

export interface ReferenceTable {
  id: number
  key: string
  label: string
  sort_order: number
  is_system: boolean
  is_active: boolean
  is_in_use: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface ReferenceValue {
  id: number
  reference_table_id: number
  key: string
  label: string
  sort_order: number
  is_system: boolean
  is_active: boolean
  is_in_use: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export type WorkflowVersionState = 'DRAFT' | 'PUBLISHED' | 'ARCHIVED'

export interface WorkflowVersion {
  id: number
  workflow_definition_id: number
  version_number: number
  state: WorkflowVersionState
  is_editable: boolean
  published_at: string | null
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface WorkflowDefinition {
  id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
  versions: WorkflowVersion[]
  created_at: string | null
  updated_at: string | null
  version: number
}

export type WorkflowStageStatus = 'ACTIVE' | 'INACTIVE'

export interface WorkflowStage {
  id: number
  workflow_version_id: number
  code: string
  name: string
  description: string | null
  sort_order: number
  is_initial: boolean
  is_final: boolean
  sla_duration_minutes: number | null
  status: WorkflowStageStatus
  created_at: string | null
  updated_at: string | null
  version: number
}

export type WorkflowActionKind =
  | 'DRAFT'
  | 'APPROVE'
  | 'REJECT'
  | 'RETURN'
  | 'CLOSE'
  | 'INFO'
  | 'CUSTOM'

export interface WorkflowAction {
  id: number
  code: string
  name: string
  kind: WorkflowActionKind
  is_active: boolean
  is_system: boolean
  is_in_use: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface WorkflowTransition {
  id: number
  workflow_version_id: number
  from_stage_id: number
  action_id: number
  to_stage_id: number
  requires_comment: boolean
  confirmation_message: string | null
  created_at: string | null
  updated_at: string | null
  version: number
}

export type FieldType =
  | 'TEXT'
  | 'NUMBER'
  | 'DATE'
  | 'SELECT'
  | 'DYNAMIC_SELECT'
  | 'TEXTAREA'
  | 'FILE'
  | 'CURRENCY'
  | 'CHECKBOX'

export type DynamicFieldSource = 'MERCHANTS' | 'MERCHANT_COMPANIES' | 'REFERENCE_DATA'

export interface FieldGroup {
  id: number
  workflow_version_id: number
  name: string
  label: string
  sort_order: number
  fields: FieldDefinition[]
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface FieldDefinition {
  id: number
  workflow_version_id: number
  field_group_id: number
  key: string
  label: string
  type: FieldType
  placeholder: string | null
  help_text: string | null
  default_value: string | null
  min_value: number | null
  max_value: number | null
  min_length: number | null
  max_length: number | null
  regex_pattern: string | null
  options: Array<{ value: string; label: string }> | null
  reference_table_id: number | null
  dynamic_source: DynamicFieldSource | null
  allowed_file_types: string[] | null
  max_file_size: number | null
  multiple: boolean
  is_required: boolean
  is_system: boolean
  sort_order: number
  created_at: string | null
  updated_at: string | null
  version: number
}

export interface WorkflowValidationError {
  code: string
  target: string
  message: string
}

export interface WorkflowGraphNode {
  id: number
  code: string
  name: string
  display_label: string | null
  is_initial: boolean
  is_final: boolean
  sort_order: number
}

export interface WorkflowGraphEdge {
  id: number
  from_stage_id: number
  to_stage_id: number
  action_id: number
  action_code: string | null
  action_name: string | null
  requires_comment: boolean
  is_self_loop: boolean
  is_return: boolean
}

export interface WorkflowGraph {
  nodes: WorkflowGraphNode[]
  edges: WorkflowGraphEdge[]
}

export interface StageFieldRule {
  id: number
  stage_id: number
  field_id: number
  is_visible: boolean
  is_editable: boolean
  is_required: boolean
  created_at: string | null
  updated_at: string | null
  version: number
}

export type StageAccessLevel = 'VIEW' | 'EXECUTE'

export interface StagePermission {
  id: number
  stage_id: number
  organization_id: number | null
  team_id: number | null
  role_id: number | null
  user_id: number | null
  access_level: StageAccessLevel
  display_label: string
  created_at: string | null
  updated_at: string | null
  version: number
}
