import type { ApiResponse, CustomsDeclaration, ImportRequest, PaginatedResponse, RequestDocument, RequestFormData, RequestStageHistory } from '../types/models'
import type { RequestStatus } from '../types/enums'
import { useApi } from './useApi'

export interface RequestsFilter {
  search?: string
  status?: RequestStatus | RequestStatus[] | string | ''
  bank_id?: number | ''
  currency?: string | ''
  from_date?: string | ''
  to_date?: string | ''
  claim_filter?: 'all' | 'available' | 'mine' | ''
  page?: number
  per_page?: number
}

export function useRequests() {
  const { get, post, put } = useApi()

  async function fetchRequests(
    filter: RequestsFilter = {},
  ): Promise<PaginatedResponse<ImportRequest>> {
    const params = new URLSearchParams()

    if (filter.search) params.set('search', filter.search)
    if (filter.status) {
      params.set(
        'status',
        Array.isArray(filter.status) ? filter.status.join(',') : filter.status,
      )
    }
    if (filter.bank_id) params.set('bank_id', String(filter.bank_id))
    if (filter.currency) params.set('currency', filter.currency)
    if (filter.from_date) params.set('from_date', filter.from_date)
    if (filter.to_date) params.set('to_date', filter.to_date)
    if (filter.claim_filter) params.set('claim_filter', filter.claim_filter)
    if (filter.page) params.set('page', String(filter.page))
    if (filter.per_page) params.set('per_page', String(filter.per_page))

    const query = params.toString()
    const path = query ? `/api/requests?${query}` : '/api/requests'

    const response = await get<ApiResponse<PaginatedResponse<ImportRequest>>>(path)
    return response.data
  }

  async function fetchRequest(id: number): Promise<ImportRequest> {
    const response = await get<ApiResponse<ImportRequest>>(`/api/requests/${id}`)
    return response.data
  }

  async function createRequest(data: RequestFormData): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>('/api/requests', data)
    return response.data
  }

  async function updateRequest(id: number, data: RequestFormData): Promise<ImportRequest> {
    const response = await put<ApiResponse<ImportRequest>>(`/api/requests/${id}`, data)
    return response.data
  }

  async function uploadDocument(
    requestId: number,
    file: File,
    label: string,
  ): Promise<void> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const form = new FormData()
    form.append('file', file)
    form.append('label', label)
    await $fetch(`/api/requests/${requestId}/documents`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      body: form,
    })
  }

  async function performWorkflowAction(
    id: number,
    action: string,
    reason?: string,
  ): Promise<ImportRequest> {
    const body: Record<string, string> = {}
    if (reason !== undefined) body.reason = reason
    const response = await post<ApiResponse<ImportRequest>>(`/api/workflow/${id}/${action}`, body)
    return response.data
  }

  async function fetchRequestDocuments(id: number): Promise<RequestDocument[]> {
    const response = await get<ApiResponse<ImportRequest>>(`/api/requests/${id}`)
    return response.data.documents ?? []
  }

  async function uploadSwift(requestId: number, file: File): Promise<void> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const form = new FormData()
    form.append('file', file)
    await $fetch(`/api/workflow/${requestId}/swift-upload`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      body: form,
      headers: { Accept: 'application/json' },
    })
  }

  async function generateCustomsDeclaration(requestId: number): Promise<CustomsDeclaration> {
    const response = await post<ApiResponse<CustomsDeclaration>>(`/api/customs/${requestId}/generate`)
    return response.data
  }

  async function downloadCustomsDeclaration(customsDeclarationId: number): Promise<Blob> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    return $fetch<Blob>(`/api/customs/${customsDeclarationId}/download`, {
      method: 'GET',
      baseURL,
      credentials: 'include',
      responseType: 'blob',
    })
  }

  async function fetchRequestHistory(id: number): Promise<RequestStageHistory[]> {
    const response = await get<ApiResponse<RequestStageHistory[]>>(`/api/requests/${id}/history`)
    return response.data
  }

  async function fetchCustomsPreview(requestId: number): Promise<CustomsDeclaration> {
    const response = await get<ApiResponse<CustomsDeclaration>>(`/api/requests/${requestId}/customs-preview`)
    return response.data
  }

  return {
    fetchRequests,
    fetchRequest,
    createRequest,
    updateRequest,
    uploadDocument,
    performWorkflowAction,
    fetchRequestDocuments,
    uploadSwift,
    generateCustomsDeclaration,
    downloadCustomsDeclaration,
    fetchRequestHistory,
    fetchCustomsPreview,
  }
}
