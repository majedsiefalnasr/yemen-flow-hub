import type {
  CreateTraderPayload,
  PaginatedTraders,
  Trader,
  TraderLookupResult,
  TradersFilter,
  UpdateTraderPayload,
} from '../types/trader'
import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

export function useTraders() {
  const { get, post, put } = useApi()

  function buildQuery(filter: TradersFilter = {}): string {
    const params = new URLSearchParams()

    if (filter.tax_number) params.set('tax_number', filter.tax_number)
    if (filter.trader_name) params.set('trader_name', filter.trader_name)
    if (filter.page) params.set('page', String(filter.page))
    if (filter.per_page) params.set('per_page', String(filter.per_page))

    return params.toString()
  }

  async function list(filter: TradersFilter = {}): Promise<PaginatedTraders> {
    const query = buildQuery(filter)
    const response = await get<ApiResponse<PaginatedTraders>>(
      query ? `/api/traders?${query}` : '/api/traders',
    )
    return response.data
  }

  async function create(payload: CreateTraderPayload): Promise<Trader> {
    const response = await post<ApiResponse<Trader>>('/api/traders', payload)
    return response.data
  }

  async function update(id: number, payload: UpdateTraderPayload): Promise<Trader> {
    const response = await put<ApiResponse<Trader>>(`/api/traders/${id}`, payload)
    return response.data
  }

  async function getById(id: number): Promise<Trader> {
    const response = await get<ApiResponse<Trader>>(`/api/traders/${id}`)
    return response.data
  }

  async function lookupByTaxNumber(taxNumber: string): Promise<TraderLookupResult> {
    const params = new URLSearchParams({ tax_number: taxNumber })
    const response = await get<ApiResponse<Trader>>(`/api/traders/lookup?${params.toString()}`)
    return response.data
  }

  return { list, create, update, getById, lookupByTaxNumber }
}
