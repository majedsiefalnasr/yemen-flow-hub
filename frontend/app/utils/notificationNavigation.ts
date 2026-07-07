import type { Notification, NotificationData } from '@/types/models'

export function resolveNotificationTargetUrl(
  data: NotificationData | null | undefined,
): string | null {
  if (!data) return null

  const actionUrl = data.action_url?.trim()
  if (actionUrl) return actionUrl

  const entityId = data.entity_id ?? data.request_id
  if (entityId == null) return null

  const entityType = data.entity_type
  if (
    entityType === 'engine_request' ||
    entityType === 'EngineRequest' ||
    data.request_id != null
  ) {
    return `/workflows/instances/${entityId}`
  }

  return null
}

export function resolveNotificationTarget(notification: Notification | null): string | null {
  return notification ? resolveNotificationTargetUrl(notification.data) : null
}
