import type { TemporaryUploadResult, TemporaryUploadStatus } from '@/types/models'
import { useApi } from '@/composables/useApi'

/**
 * Pre-submission file uploads for the deferred-creation wizard: no
 * EngineRequest exists yet, so a file is stored on the isolated `private-tmp`
 * disk and identified by an opaque token, not a document id. The token is
 * threaded through the wizard's accumulated form data and sent as
 * upload_tokens on the final atomic POST /api/v1/engine-requests call, which
 * is the only place the file is ever promoted into a real
 * EngineRequestDocument.
 */
export function useTemporaryUploads() {
  const api = useApi()

  const upload = async (
    file: File,
    workflowVersionId: number,
    fieldId: number,
    uploadSessionToken: string,
  ): Promise<TemporaryUploadResult> => {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('workflow_version_id', String(workflowVersionId))
    formData.append('field_id', String(fieldId))
    formData.append('upload_session_token', uploadSessionToken)

    const response = await api.post<{ success: boolean; data: TemporaryUploadResult }>(
      '/api/v1/temporary-uploads',
      formData,
    )
    return response.data
  }

  const status = async (token: string): Promise<TemporaryUploadStatus> => {
    const response = await api.get<{ success: boolean; data: TemporaryUploadStatus }>(
      `/api/v1/temporary-uploads/${token}`,
    )
    return response.data
  }

  const release = async (token: string): Promise<void> => {
    await api.del(`/api/v1/temporary-uploads/${token}`)
  }

  return { upload, status, release }
}
