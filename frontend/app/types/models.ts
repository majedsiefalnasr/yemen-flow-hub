import type { RequestStatus, UserRole } from './enums'

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
  swift_uploaded_at: string | null
  executive_decided_at: string | null
  customs_issued_at: string | null
  revision_count: number
  created_at: string
  updated_at: string
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
