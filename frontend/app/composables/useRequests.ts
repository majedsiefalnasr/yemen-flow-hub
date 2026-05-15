import type { ApiResponse, ImportRequest, PaginatedResponse } from '../types/models'
import type { RequestStatus } from '../types/enums'
import { useApi } from './useApi'

export interface RequestsFilter {
  search?: string
  status?: RequestStatus | ''
  page?: number
  per_page?: number
}

export function useRequests() {
  const { get } = useApi()

  async function fetchRequests(
    filter: RequestsFilter = {},
  ): Promise<PaginatedResponse<ImportRequest>> {
    const params = new URLSearchParams()

    if (filter.search) params.set('search', filter.search)
    if (filter.status) params.set('status', filter.status)
    if (filter.page) params.set('page', String(filter.page))
    if (filter.per_page) params.set('per_page', String(filter.per_page))

    const query = params.toString()
    const path = query ? `/api/requests?${query}` : '/api/requests'

    const response = await get<ApiResponse<PaginatedResponse<ImportRequest>>>(path)
    return response.data
  }

  return { fetchRequests }
}
