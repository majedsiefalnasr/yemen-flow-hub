export enum NotificationTemplateType {
  REQUEST_APPROVED = 'REQUEST_APPROVED',
  REQUEST_REJECTED = 'REQUEST_REJECTED',
  REQUEST_RETURNED = 'REQUEST_RETURNED',
}

export const NOTIFICATION_TEMPLATE_LABELS: Record<NotificationTemplateType, string> = {
  [NotificationTemplateType.REQUEST_APPROVED]: 'إشعار موافقة الطلب',
  [NotificationTemplateType.REQUEST_REJECTED]: 'إشعار رفض الطلب',
  [NotificationTemplateType.REQUEST_RETURNED]: 'إشعار إعادة الطلب للتعديل',
}

export const NOTIFICATION_TEMPLATE_DESCRIPTIONS: Record<NotificationTemplateType, string> = {
  [NotificationTemplateType.REQUEST_APPROVED]: 'يرسل عند اعتماد طلب التمويل في مسار العمل.',
  [NotificationTemplateType.REQUEST_REJECTED]: 'يرسل عند رفض طلب التمويل مع إغلاق مسار المعالجة.',
  [NotificationTemplateType.REQUEST_RETURNED]:
    'يرسل عند إعادة الطلب للجهة المصرفية لاستكمال التعديل.',
}

export function notificationTemplateLabel(type: NotificationTemplateType | string): string {
  return NOTIFICATION_TEMPLATE_LABELS[type as NotificationTemplateType] ?? type
}

export function formatNotificationTemplateDate(value: string | null): string {
  if (!value) return 'لم يسجل'
  return new Intl.DateTimeFormat('ar-YE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

export interface NotificationTemplateVersion {
  id: number
  changed_by: number | null
  changed_by_name: string | null
  changed_at: string | null
  is_active_version: boolean
}

export interface NotificationTemplateActiveVersion {
  id: number
  subject: string
  body: string
  changed_by: number | null
  changed_by_name: string | null
  changed_at: string | null
}

export interface NotificationTemplate {
  type: NotificationTemplateType
  admin_editable: boolean
  is_active: boolean
  allowed_variables: string[]
  active: NotificationTemplateActiveVersion | null
  versions: NotificationTemplateVersion[]
}

export interface NotificationTemplatePayload {
  subject: string
  body: string
}

export interface NotificationTemplatePreview {
  source: NotificationTemplatePayload
  rendered: {
    subject: string
    html: string
    text: string
    source: 'preview'
    template_version_id: null
    locale: string
  }
}
