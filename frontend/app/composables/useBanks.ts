import type { ApiResponse, Bank, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface FetchBanksParams {
  page?: number
  per_page?: number
  search?: string
}

export interface CreateBankPayload {
  name?: string
  name_ar: string
  name_en: string
  code: string
  license_number?: string | null
  entity_type?: string | null
  is_active: boolean
}

export interface UpdateBankPayload {
  name?: string
  name_ar?: string
  name_en?: string
  code?: string
  license_number?: string | null
  entity_type?: string | null
  is_active?: boolean
}

export function useBanks() {
  const { get, post, put } = useApi()

  // Returns all banks (used for dropdowns / selectors that need the full set).
  async function fetchBanks(): Promise<Bank[]> {
    const response = await get<ApiResponse<PaginatedResponse<Bank>>>('/api/banks?per_page=200')
    return response.data.data ?? []
  }

  // Server-side paginated fetch (same shape the requests page consumes).
  async function fetchBanksPaginated(
    params: FetchBanksParams = {},
  ): Promise<PaginatedResponse<Bank>> {
    const qs = new URLSearchParams()
    if (params.page) qs.set('page', String(params.page))
    if (params.per_page) qs.set('per_page', String(params.per_page))
    if (params.search) qs.set('search', params.search)
    const response = await get<ApiResponse<PaginatedResponse<Bank>>>(`/api/banks?${qs.toString()}`)
    return response.data
  }

  async function createBank(payload: CreateBankPayload): Promise<Bank> {
    const response = await post<ApiResponse<Bank>>('/api/banks', payload)
    return response.data
  }

  async function updateBank(id: number, payload: UpdateBankPayload): Promise<Bank> {
    const response = await put<ApiResponse<Bank>>(`/api/banks/${id}`, payload)
    return response.data
  }

  return { fetchBanks, fetchBanksPaginated, createBank, updateBank }
}
