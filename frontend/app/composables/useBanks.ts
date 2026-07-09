import type { ApiError, ApiResponse, Bank, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'
import { useReferenceCacheStore } from '../stores/referenceCache.store'

const BANKS_CACHE_KEY = 'banks:dropdown'

export interface FetchBanksParams {
  page?: number
  per_page?: number
  search?: string
}

export interface CreateBankPayload {
  organization_id: number
  name?: string
  name_ar: string
  name_en: string
  code: string
  license_number?: string | null
  entity_type?: string | null
  is_active: boolean
  adminName?: string
  adminEmail?: string
  adminPassword?: string
}

export interface UpdateBankPayload {
  name?: string
  name_ar?: string
  name_en?: string
  code?: string
  license_number?: string | null
  entity_type?: string | null
  is_active?: boolean
  adminName?: string
  adminEmail?: string
}

export function useBanks() {
  const { get, post, put, isApiError } = useApi()
  const referenceCache = useReferenceCacheStore()

  // FE-003: returns all banks (used for dropdowns / selectors that need the
  // full set) — cached across calls in the same session, since multiple
  // pages independently call this for the same unfiltered list. Invalidated
  // below whenever createBank()/updateBank() succeeds, so a caller that just
  // mutated a bank never sees a stale dropdown on next fetch.
  async function fetchBanks(): Promise<Bank[]> {
    return referenceCache.remember(BANKS_CACHE_KEY, async () => {
      const response = await get<ApiResponse<PaginatedResponse<Bank>>>('/api/v1/banks?per_page=200')
      return response.data.data ?? []
    })
  }

  // Server-side paginated fetch (same shape the requests page consumes).
  async function fetchBanksPaginated(
    params: FetchBanksParams = {},
  ): Promise<PaginatedResponse<Bank>> {
    const qs = new URLSearchParams()
    if (params.page) qs.set('page', String(params.page))
    if (params.per_page) qs.set('per_page', String(params.per_page))
    if (params.search) qs.set('search', params.search)
    const response = await get<ApiResponse<PaginatedResponse<Bank>>>(
      `/api/v1/banks?${qs.toString()}`,
    )
    return response.data
  }

  async function createBank(payload: CreateBankPayload): Promise<Bank> {
    const response = await post<ApiResponse<Bank>>('/api/v1/banks', payload)
    referenceCache.invalidate(BANKS_CACHE_KEY)
    return response.data
  }

  async function updateBank(id: number, payload: UpdateBankPayload): Promise<Bank> {
    const response = await put<ApiResponse<Bank>>(`/api/v1/banks/${id}`, payload)
    referenceCache.invalidate(BANKS_CACHE_KEY)
    return response.data
  }

  function extractFieldErrors(err: unknown): Record<string, string[]> {
    if (isApiError(err)) {
      return (err.data as ApiError).errors ?? {}
    }
    return {}
  }

  function extractMessage(err: unknown, fallback: string): string {
    if (isApiError(err)) {
      return err.data.message || fallback
    }
    return fallback
  }

  return {
    fetchBanks,
    fetchBanksPaginated,
    createBank,
    updateBank,
    extractFieldErrors,
    extractMessage,
  }
}
