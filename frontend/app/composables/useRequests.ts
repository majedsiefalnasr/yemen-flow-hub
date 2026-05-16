import type { ApiResponse, ImportRequest, PaginatedResponse, RequestDocument, RequestFormData } from '../types/models'
import type { RequestStatus } from '../types/enums'
import { useApi } from './useApi'

export interface RequestsFilter {
  search?: string
  status?: RequestStatus | ''
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
    if (filter.status) params.set('status', filter.status)
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

  return { fetchRequests, fetchRequest, createRequest, updateRequest, uploadDocument, performWorkflowAction, fetchRequestDocuments, uploadSwift }
}
