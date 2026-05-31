import type { ApiResponse, Merchant, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface MerchantFilters {
  search?: string
  bank_id?: number
  is_active?: boolean
  page?: number
  per_page?: number
}

export interface CreateMerchantPayload {
  name: string
  bank_id?: number | null
  commercial_register?: string | null
  tax_number?: string | null
  national_id?: string | null
  owner_name?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  business_type?: string | null
  is_active?: boolean
}

export interface UpdateMerchantPayload {
  name?: string
  commercial_register?: string | null
  tax_number?: string | null
  national_id?: string | null
  owner_name?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  business_type?: string | null
  is_active?: boolean
}

export function useMerchants() {
  const { get, post, put } = useApi()

  // Merchants render analytics cards computed across the whole set; fetch all real rows.
  // The API may return the data field as a flat array (ResourceCollection strips
  // pagination meta) or as a wrapped { data, meta } object — handle both.
  async function fetchMerchants(filters: MerchantFilters = {}): Promise<Merchant[]> {
    const params = new URLSearchParams({ per_page: '200' })
    if (filters.search) params.set('search', filters.search)
    if (filters.bank_id != null) params.set('bank_id', String(filters.bank_id))
    if (filters.is_active != null) params.set('is_active', String(filters.is_active))
    const response = await get<ApiResponse<Merchant[] | PaginatedResponse<Merchant>>>(`/api/merchants?${params}`)
    const payload = response.data
    return Array.isArray(payload) ? payload : (payload.data ?? [])
  }

  // Server-side paginated fetch (same shape the requests page consumes).
  async function fetchMerchantsPaginated(filters: MerchantFilters = {}): Promise<PaginatedResponse<Merchant>> {
    const params = new URLSearchParams({ per_page: String(filters.per_page ?? 20) })
    if (filters.page) params.set('page', String(filters.page))
    if (filters.search) params.set('search', filters.search)
    if (filters.bank_id != null) params.set('bank_id', String(filters.bank_id))
    if (filters.is_active != null) params.set('is_active', String(filters.is_active))
    const response = await get<ApiResponse<PaginatedResponse<Merchant>>>(`/api/merchants?${params}`)
    return response.data
  }

  async function createMerchant(payload: CreateMerchantPayload): Promise<Merchant> {
    const response = await post<ApiResponse<Merchant>>('/api/merchants', payload)
    return response.data
  }

  async function updateMerchant(id: number, payload: UpdateMerchantPayload): Promise<Merchant> {
    const response = await put<ApiResponse<Merchant>>(`/api/merchants/${id}`, payload)
    return response.data
  }

  async function suspendMerchant(id: number, isActive: boolean): Promise<Merchant> {
    const response = await put<ApiResponse<Merchant>>(`/api/merchants/${id}`, { is_active: isActive })
    return response.data
  }

  return { fetchMerchants, fetchMerchantsPaginated, createMerchant, updateMerchant, suspendMerchant }
}
