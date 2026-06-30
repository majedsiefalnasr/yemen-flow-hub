import { ref } from 'vue'
import type { EngineRequestDocument } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineRequestDocuments() {
  const api = useApi()
  const documents = ref<EngineRequestDocument[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchDocuments = async (requestId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ success: boolean; data: EngineRequestDocument[] }>(
        `/api/v1/engine-requests/${requestId}/documents`,
      )
      documents.value = response.data
    } catch (cause: unknown) {
      documents.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل المرفقات.')
    } finally {
      loading.value = false
    }
  }

  const upload = async (
    requestId: number,
    file: File,
    fieldId: number | null,
  ): Promise<EngineRequestDocument> => {
    const formData = new FormData()
    formData.append('file', file)
    if (fieldId !== null) {
      formData.append('field_id', String(fieldId))
    }
    const response = await api.post<{ success: boolean; data: EngineRequestDocument }>(
      `/api/v1/engine-requests/${requestId}/documents`,
      formData,
    )
    return response.data
  }

  const remove = async (requestId: number, documentId: number): Promise<void> => {
    await api.del(`/api/v1/engine-requests/${requestId}/documents/${documentId}`)
  }

  const downloadUrl = (requestId: number, documentId: number): string =>
    `/api/v1/engine-requests/${requestId}/documents/${documentId}/download`

  return { documents, loading, error, fetchDocuments, upload, remove, downloadUrl }
}
