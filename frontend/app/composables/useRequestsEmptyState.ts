import { UserRole } from '@/types/enums'

export interface RequestsEmptyStateInput {
  role?: UserRole
  tab?: string
  hasAnyRequests: boolean
  hasActiveFilters?: boolean
  search?: string
  bankFilter?: string
  dateRangeFilter?: string
  createdByMeOnly?: boolean
  hideOthers?: boolean
  advancedFilterCount?: number
}

export interface RequestsEmptyState {
  title: string
  description: string
}

export function buildRequestsEmptyState(input: RequestsEmptyStateInput): RequestsEmptyState {
  const hasActiveFilters =
    input.hasActiveFilters ??
    Boolean(
      input.search?.trim() ||
      (input.bankFilter && input.bankFilter !== 'all') ||
      (input.dateRangeFilter && input.dateRangeFilter !== 'all') ||
      input.createdByMeOnly ||
      input.hideOthers ||
      (input.advancedFilterCount ?? 0) > 0,
    )

  if (!input.hasAnyRequests) {
    if (input.role === UserRole.DATA_ENTRY) {
      return {
        title: 'لا توجد طلبات بعد',
        description: 'لم تقدّم أي طلبات تمويل حتى الآن. استخدم زر "طلب جديد" لبدء أول طلب.',
      }
    }
    if (input.role === UserRole.BANK_REVIEWER) {
      return {
        title: 'الطابور فارغ حالياً',
        description: 'لا توجد طلبات بانتظار مراجعتك. ستظهر هنا عند تقديمها من مدخلي البيانات.',
      }
    }
    if (input.role === UserRole.BANK_ADMIN) {
      return {
        title: 'لا توجد طلبات لجهتك بعد',
        description: 'لم يقدّم موظفو بنكك أي طلبات تمويل حتى الآن.',
      }
    }
    if (input.role === UserRole.SUPPORT_COMMITTEE) {
      return {
        title: 'لا توجد طلبات دعم حالياً',
        description: 'لم تصل أي طلبات إلى لجنة الدعم بعد. ستظهر هنا عند اعتمادها من البنوك.',
      }
    }
    if (input.role === UserRole.EXECUTIVE_MEMBER) {
      return {
        title: 'لا توجد جلسات تصويت مرتبطة بك',
        description: 'لم يتم فتح جلسات تصويت تتطلب مشاركتك حالياً.',
      }
    }
    if (input.role === UserRole.COMMITTEE_DIRECTOR) {
      return {
        title: 'لا توجد طلبات في مرحلة التصويت',
        description: 'لم تصل أي طلبات إلى مرحلة التصويت التنفيذي حتى الآن.',
      }
    }
    if (input.role === UserRole.SWIFT_OFFICER) {
      return {
        title: 'لا توجد طلبات بانتظار السويفت',
        description: 'لم تصل طلبات تحتاج رفع مستندات السويفت حتى الآن.',
      }
    }
    if (input.role === UserRole.CBY_ADMIN) {
      return {
        title: 'لا توجد طلبات في النظام',
        description: 'لم يتم تقديم أي طلبات تمويل عبر المنصة حتى الآن.',
      }
    }
    return {
      title: 'لا توجد طلبات بعد',
      description: 'لم يتم تقديم أي طلبات حتى الآن.',
    }
  }

  if (hasActiveFilters) {
    return {
      title: 'لا توجد طلبات مطابقة',
      description: 'جرّب تخفيف معايير البحث أو إعادة ضبط الفلاتر لعرض المزيد من الطلبات.',
    }
  }

  if (input.role === UserRole.SUPPORT_COMMITTEE) {
    if (input.tab === 'my_claims') {
      return {
        title: 'لا توجد مطالبات نشطة',
        description: 'لا توجد طلبات محجوزة باسمك حالياً.',
      }
    }
    return {
      title: 'الطابور فارغ — أنجزت المهمة',
      description: 'لا توجد طلبات في انتظار مراجعتك حالياً.',
    }
  }
  if (input.role === UserRole.EXECUTIVE_MEMBER) {
    if (input.tab === 'pending_my_vote') {
      return {
        title: 'لا توجد جلسات مخصصة لتصويتك',
        description: 'لا توجد جلسات مفتوحة تتطلب تصويتك حالياً.',
      }
    }
    return {
      title: 'لا توجد جلسات مخصصة لتصويتك',
      description: 'لا توجد جلسات مفتوحة تتطلب تصويتك حالياً.',
    }
  }
  if (input.role === UserRole.SWIFT_OFFICER) {
    return {
      title: 'لا توجد طلبات بانتظار السويفت',
      description: 'كل الطلبات معالجة حالياً أو لم تصل بعد إلى هذه المرحلة.',
    }
  }

  return {
    title: 'لا توجد طلبات في هذا الطابور',
    description: 'لا توجد عناصر ضمن المرحلة المختارة حالياً.',
  }
}
