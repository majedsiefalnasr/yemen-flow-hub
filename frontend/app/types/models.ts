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

export interface ImportRequest {
  id: number
  reference_number: string
  bank_id: number
  status: RequestStatus
  current_owner_role: UserRole
  currency: string
  amount: string
  supplier_name: string
  goods_description: string
  port_of_entry: string
  notes: string | null
  created_by: number
  claimed_by: number | null
  claim_expires_at: string | null
  submitted_at: string | null
  bank_approved_at: string | null
  created_at: string
  updated_at: string
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
