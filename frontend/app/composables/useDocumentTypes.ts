import type { ApiResponse, DocumentType } from '../types/models'
import { useApi } from './useApi'

export interface CreateDocumentTypePayload {
  slug: string
  name_ar: string
  name_en: string
  is_required?: boolean
  is_active?: boolean
  sort_order?: number
}

export interface UpdateDocumentTypePayload {
  slug: string
  name_ar: string
  name_en: string
  is_required: boolean
  is_active: boolean
  sort_order: number
}

export function useDocumentTypes() {
  const { get, post, put } = useApi()

  async function fetchDocumentTypes(): Promise<DocumentType[]> {
    const response = await get<ApiResponse<DocumentType[]>>('/api/document-types')
    return response.data
  }

  async function createDocumentType(payload: CreateDocumentTypePayload): Promise<DocumentType> {
    const response = await post<ApiResponse<DocumentType>>('/api/document-types', payload)
    return response.data
  }

  async function updateDocumentType(id: number, payload: UpdateDocumentTypePayload): Promise<DocumentType> {
    const response = await put<ApiResponse<DocumentType>>(`/api/document-types/${id}`, payload)
    return response.data
  }

  return { fetchDocumentTypes, createDocumentType, updateDocumentType }
}
