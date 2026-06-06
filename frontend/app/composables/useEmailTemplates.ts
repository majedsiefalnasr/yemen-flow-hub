import type { ApiError, ApiResponse } from '@/types/models'
import type {
  NotificationTemplate,
  NotificationTemplatePayload,
  NotificationTemplatePreview,
} from '@/types/notifications'
import { useApi } from './useApi'

const ADMIN_TEMPLATES_PATH = '/api/admin/notification-templates'

export function useEmailTemplates() {
  const { get, put, post, isApiError } = useApi()

  async function fetchTemplates(): Promise<NotificationTemplate[]> {
    const response = await get<ApiResponse<NotificationTemplate[]>>(ADMIN_TEMPLATES_PATH)
    return response.data
  }

  async function fetchTemplate(type: string): Promise<NotificationTemplate> {
    const response = await get<ApiResponse<NotificationTemplate>>(
      `${ADMIN_TEMPLATES_PATH}/${encodeURIComponent(type)}`,
    )
    return response.data
  }

  async function updateTemplate(
    type: string,
    payload: NotificationTemplatePayload,
  ): Promise<NotificationTemplate> {
    const response = await put<ApiResponse<NotificationTemplate>>(
      `${ADMIN_TEMPLATES_PATH}/${encodeURIComponent(type)}`,
      payload,
    )
    return response.data
  }

  async function previewTemplate(
    type: string,
    payload: NotificationTemplatePayload,
  ): Promise<NotificationTemplatePreview> {
    const response = await post<ApiResponse<NotificationTemplatePreview>>(
      `${ADMIN_TEMPLATES_PATH}/${encodeURIComponent(type)}/preview`,
      payload,
    )
    return response.data
  }

  function extractFieldErrors(err: unknown): Record<string, string[]> {
    if (isApiError(err)) {
      return (err.data as ApiError).errors ?? {}
    }
    return {}
  }

  function extractMessage(err: unknown, fallback: string): string {
    if (isApiError(err)) {
      return err.data.message || fallback
    }
    return fallback
  }

  return {
    fetchTemplates,
    fetchTemplate,
    updateTemplate,
    previewTemplate,
    extractFieldErrors,
    extractMessage,
  }
}
