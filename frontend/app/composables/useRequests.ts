import type { ApiResponse, CustomsDeclaration, ImportRequest, PaginatedResponse, RequestDocument, RequestFormData, RequestStageHistory } from '../types/models'
import type { RequestStatus } from '../types/enums'
import { useApi } from './useApi'

export interface RequestsFilter {
  search?: string
  status?: RequestStatus | RequestStatus[] | string | ''
  bank_id?: number | ''
  currency?: string | ''
  /** Legacy aliases kept for backward compat — prefer created_from / created_to */
  from_date?: string | ''
  to_date?: string | ''
  created_from?: string | ''
  created_to?: string | ''
  amount_min?: number | ''
  amount_max?: number | ''
  assigned_reviewer_id?: number | ''
  claim_filter?: 'all' | 'available' | 'mine' | ''
  page?: number
  per_page?: number
  /** Request status_totals aggregate in the response meta */
  with_status_totals?: boolean
}

export interface SwiftUploadPayload {
  swiftReference?: string
  swiftFile?: File
  fxRequestFile?: File
  // legacy fallback
  file?: File
}

export function useRequests() {
  const { get, post, put } = useApi()

  function getXsrfToken(): string | null {
    if (!process.client) return null
    const raw = document.cookie
      .split(';')
      .map(cookie => cookie.trim())
      .find(cookie => cookie.startsWith('XSRF-TOKEN='))
      ?.split('=')
      .slice(1)
      .join('=')

    return raw ? decodeURIComponent(raw) : null
  }

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
    if (filter.created_from) params.set('created_from', filter.created_from)
    if (filter.created_to) params.set('created_to', filter.created_to)
    // Legacy aliases: only sent if new params absent
    if (!filter.created_from && filter.from_date) params.set('from_date', filter.from_date)
    if (!filter.created_to && filter.to_date) params.set('to_date', filter.to_date)
    if (filter.amount_min !== '' && filter.amount_min !== undefined) params.set('amount_min', String(filter.amount_min))
    if (filter.amount_max !== '' && filter.amount_max !== undefined) params.set('amount_max', String(filter.amount_max))
    if (filter.assigned_reviewer_id) params.set('assigned_reviewer_id', String(filter.assigned_reviewer_id))
    if (filter.claim_filter) params.set('claim_filter', filter.claim_filter)
    if (filter.page) params.set('page', String(filter.page))
    if (filter.per_page) params.set('per_page', String(filter.per_page))
    if (filter.with_status_totals) params.set('with_status_totals', '1')

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
    const xsrfToken = getXsrfToken()
    await $fetch(`/api/requests/${requestId}/documents`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      body: form,
      headers: {
        Accept: 'application/json',
        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      },
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

  async function uploadSwift(requestId: number, payload: SwiftUploadPayload): Promise<void> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const form = new FormData()

    if (payload.file) {
      // Legacy API mode
      form.append('file', payload.file)
    }
    else {
      if (payload.swiftReference) form.append('swift_reference', payload.swiftReference)
      if (payload.swiftFile) {
        form.append('swift_file', payload.swiftFile)
        // Backward compatibility: some backend paths still require the legacy single `file` key.
        form.append('file', payload.swiftFile)
      }
      if (payload.fxRequestFile) form.append('fx_request_file', payload.fxRequestFile)
    }

    const xsrfToken = getXsrfToken()
    await $fetch(`/api/workflow/${requestId}/swift-upload`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      body: form,
      headers: {
        Accept: 'application/json',
        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      },
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

  async function bankReturn(id: number, comment: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/workflow/${id}/bank-return`, { comment })
    return response.data
  }

  async function supportReturn(id: number, comment: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/workflow/${id}/support-return`, { comment })
    return response.data
  }

  async function bankRejectTerminal(id: number, comment: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/workflow/${id}/bank-reject-terminal`, { comment })
    return response.data
  }

  async function bankReturnAfterSupportReject(id: number, reason?: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(
      `/api/workflow/${id}/bank-return-after-support-reject`,
      reason ? { reason } : {},
    )
    return response.data
  }

  async function bankFinalizeRejection(id: number, reason?: string): Promise<ImportRequest> {
    const response = await post<ApiResponse<ImportRequest>>(
      `/api/workflow/${id}/bank-finalize-rejection`,
      reason ? { reason } : {},
    )
    return response.data
  }

  async function cloneRequest(sourceId: number): Promise<number> {
    const response = await post<ApiResponse<ImportRequest>>(`/api/requests/${sourceId}/clone`, {})
    return response.data.id
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
    bankReturn,
    supportReturn,
    bankRejectTerminal,
    bankReturnAfterSupportReject,
    bankFinalizeRejection,
    cloneRequest,
  }
}
