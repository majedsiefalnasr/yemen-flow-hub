import type { ApiResponse, Merchant, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface MerchantFilters {
  search?: string
  bank_id?: number
  status?: string
  page?: number
  per_page?: number
}

export interface MerchantOwnerPayload {
  name: string
  ownership_percentage: number
}

export interface MerchantCompanyPayload {
  name: string
  commercial_registration_number: string
  commercial_registration_expiry?: string | null
  sector_reference_value_id?: number | null
  is_active?: boolean
}

export interface CreateMerchantPayload {
  name: string
  bank_id?: number | null
  tax_number: string
  tax_card_expiry?: string | null
  phone?: string | null
  address?: string | null
  status?: string
  owners?: MerchantOwnerPayload[]
  companies?: MerchantCompanyPayload[]
}

export interface UpdateMerchantPayload {
  version: number
  name?: string
  tax_number?: string
  tax_card_expiry?: string | null
  phone?: string | null
  address?: string | null
  status?: string
  owners?: MerchantOwnerPayload[]
  companies?: MerchantCompanyPayload[]
}

export interface MerchantBusinessError {
  code: string
  message: string
  fields?: Record<string, string>
  request_id?: string
}

export function useMerchants() {
  const { get, post, put, del } = useApi()

  // Server caps per_page at 100; requesting more is silently truncated. List-wide
  // analytics (stats/cross-bank/risk) are computed on whatever this returns, so
  // never request beyond the cap and treat the result as "first MERCHANTS_PAGE_CAP".
  const MERCHANTS_PAGE_CAP = 100

  async function fetchMerchants(filters: MerchantFilters = {}): Promise<Merchant[]> {
    const params = new URLSearchParams({ per_page: String(MERCHANTS_PAGE_CAP) })
    if (filters.search) params.set('search', filters.search)
    if (filters.bank_id != null) params.set('bank_id', String(filters.bank_id))
    if (filters.status) params.set('status', filters.status)
    const response = await get<ApiResponse<Merchant[] | PaginatedResponse<Merchant>>>(
      `/api/v1/merchants?${params}`,
    )
    const payload = response.data
    return Array.isArray(payload) ? payload : (payload.data ?? [])
  }

  async function fetchMerchantsPaginated(
    filters: MerchantFilters = {},
  ): Promise<PaginatedResponse<Merchant>> {
    const params = new URLSearchParams({ per_page: String(filters.per_page ?? 20) })
    if (filters.page) params.set('page', String(filters.page))
    if (filters.search) params.set('search', filters.search)
    if (filters.bank_id != null) params.set('bank_id', String(filters.bank_id))
    if (filters.status) params.set('status', filters.status)
    const response = await get<ApiResponse<PaginatedResponse<Merchant>>>(
      `/api/v1/merchants?${params}`,
    )
    return response.data
  }

  async function fetchMerchant(id: number): Promise<Merchant> {
    const response = await get<ApiResponse<Merchant>>(`/api/v1/merchants/${id}`)
    return response.data
  }

  async function createMerchant(payload: CreateMerchantPayload): Promise<Merchant> {
    const response = await post<ApiResponse<Merchant>>('/api/v1/merchants', payload)
    return response.data
  }

  async function updateMerchant(id: number, payload: UpdateMerchantPayload): Promise<Merchant> {
    const response = await put<ApiResponse<Merchant>>(`/api/v1/merchants/${id}`, payload)
    return response.data
  }

  async function deleteMerchant(id: number): Promise<void> {
    await del(`/api/v1/merchants/${id}`)
  }

  function isBusinessError(
    error: unknown,
  ): error is { response: { status: number; data: { error: MerchantBusinessError } } } {
    if (!error || typeof error !== 'object') return false
    const e = error as Record<string, any>
    return e.response?.data?.error?.code != null
  }

  // Laravel 422 validation envelope: { message, errors: { field: [msg, ...] } }.
  // It has no `error.code`, so without this it would fall through to a generic
  // "unexpected error" banner and drop every per-field message.
  function extractValidationError(error: unknown): MerchantBusinessError | null {
    if (!error || typeof error !== 'object') return null
    const e = error as Record<string, any>
    if (e.response?.status !== 422) return null
    const errors = e.response?.data?.errors as Record<string, string[]> | undefined
    const fields: Record<string, string> = {}
    if (errors) {
      for (const [key, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && messages[0]) fields[key] = messages[0]
      }
    }
    return {
      code: 'VALIDATION_ERROR',
      message: e.response?.data?.message ?? 'بيانات غير صالحة.',
      fields,
    }
  }

  function extractBusinessError(error: unknown): MerchantBusinessError | null {
    if (isBusinessError(error)) return error.response.data.error
    return extractValidationError(error)
  }

  return {
    fetchMerchants,
    fetchMerchantsPaginated,
    fetchMerchant,
    createMerchant,
    updateMerchant,
    deleteMerchant,
    isBusinessError,
    extractBusinessError,
  }
}
