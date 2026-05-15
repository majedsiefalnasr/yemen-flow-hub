import type { ApiResponse, Merchant, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export function useMerchants() {
  const { get } = useApi()

  async function fetchMerchants(): Promise<Merchant[]> {
    const response = await get<ApiResponse<PaginatedResponse<Merchant>>>('/api/merchants?per_page=100')
    return response.data.data
  }

  return { fetchMerchants }
}
