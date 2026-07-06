import { ref } from 'vue'
import type { CustomsDeclarationSummary } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export function useEngineFxConfirmation() {
  const api = useApi()
  const uploading = ref(false)
  const error = ref<string | null>(null)

  const declarationDownloadUrl = (requestId: number): string =>
    `/api/v1/engine-requests/${requestId}/customs-declaration/download`

  const signedFxDownloadUrl = (requestId: number): string =>
    `/api/v1/engine-requests/${requestId}/customs-declaration/signed-fx-download`

  const uploadSignedFx = async (
    requestId: number,
    file: File,
  ): Promise<CustomsDeclarationSummary> => {
    uploading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('signed_document', file)
      const response = await api.post<{
        success: boolean
        data: CustomsDeclarationSummary
      }>(`/api/v1/engine-requests/${requestId}/fx-confirmation-signed`, formData, {
        headers: { 'Content-Type': '' },
      })
      return response.data
    } catch (cause: unknown) {
      error.value = extractApiErrorMessage(cause, 'تعذّر رفع الوثيقة الموقعة.')
      throw cause
    } finally {
      uploading.value = false
    }
  }

  return {
    uploading,
    error,
    declarationDownloadUrl,
    signedFxDownloadUrl,
    uploadSignedFx,
  }
}
