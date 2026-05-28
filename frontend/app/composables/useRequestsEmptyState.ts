import { UserRole } from '@/types/enums'

export interface RequestsEmptyStateInput {
  role?: UserRole
  tab: string
  hasAnyRequests: boolean
  search: string
  bankFilter: string
  dateRangeFilter: 'all' | 'today' | '7d' | '30d' | '90d'
  createdByMeOnly: boolean
  hideOthers: boolean
  advancedFilterCount: number
}

export interface RequestsEmptyState {
  title: string
  description: string
}

export function buildRequestsEmptyState(input: RequestsEmptyStateInput): RequestsEmptyState {
  const hasQueryFilters
    = Boolean(input.search.trim())
      || input.bankFilter !== 'all'
      || input.dateRangeFilter !== 'all'
      || input.createdByMeOnly
      || input.hideOthers
      || input.advancedFilterCount > 0

  if (!input.hasAnyRequests) {
    if (input.role === UserRole.SUPPORT_COMMITTEE) {
      return {
        title: 'لا توجد طلبات دعم حالياً',
        description: 'لم تصل أي طلبات إلى لجنة الدعم بعد.',
      }
    }
    if (input.role === UserRole.EXECUTIVE_MEMBER) {
      return {
        title: 'لا توجد جلسات تصويت مرتبطة بك',
        description: 'لم يتم فتح جلسات تصويت تتطلب مشاركتك حالياً.',
      }
    }
    if (input.role === UserRole.SWIFT_OFFICER) {
      return {
        title: 'لا توجد طلبات بانتظار السويفت',
        description: 'لم تصل طلبات تحتاج رفع مستندات السويفت حتى الآن.',
      }
    }
    return {
      title: 'لا توجد طلبات بعد',
      description: 'لم يتم تقديم أي طلبات حتى الآن.',
    }
  }

  if (input.role === UserRole.SUPPORT_COMMITTEE && input.tab === 'my_claims') {
    return {
      title: 'لا توجد مطالبات نشطة لك',
      description: 'لم تقم بمطالبة أي طلب حالياً. يمكنك المطالبة من طابور الانتظار.',
    }
  }

  if (input.role === UserRole.SUPPORT_COMMITTEE && input.tab === 'waiting') {
    return {
      title: 'لا توجد طلبات غير محجوزة',
      description: 'كل الطلبات الحالية إما محجوزة أو خارج هذا الطابور.',
    }
  }

  if (input.role === UserRole.EXECUTIVE_MEMBER && input.tab === 'pending_my_vote') {
    return {
      title: 'لا توجد جلسات مخصصة لتصويتك',
      description: 'لا توجد جلسات مفتوحة تتطلب تصويتك حالياً.',
    }
  }

  if (hasQueryFilters) {
    return {
      title: 'لا توجد طلبات مطابقة',
      description: 'جرّب تخفيف معايير البحث أو إعادة ضبط الفلاتر.',
    }
  }

  return {
    title: 'لا توجد طلبات في هذا الطابور',
    description: 'لا توجد عناصر حالية ضمن المرحلة المختارة.',
  }
}
