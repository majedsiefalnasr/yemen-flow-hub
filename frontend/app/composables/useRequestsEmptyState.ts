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
        description:
          'ابدأ بطلب تمويل واحد. بعد الحفظ ستظهر المسودة هنا، ثم تنتقل إلى مراجعة البنك عند التقديم.',
      }
    }
    if (input.role === UserRole.BANK_REVIEWER) {
      return {
        title: 'الطابور فارغ حالياً',
        description:
          'لا توجد طلبات بانتظار مراجعتك. عند تقديم مدخل البيانات طلباً جديداً سيظهر هنا مع إجراء المراجعة المناسب.',
      }
    }
    if (input.role === UserRole.BANK_ADMIN) {
      return {
        title: 'لا توجد طلبات لجهتك بعد',
        description:
          'لم يقدّم موظفو بنكك أي طلبات تمويل حتى الآن. ستظهر الطلبات هنا للمتابعة المؤسسية بعد أول تقديم.',
      }
    }
    if (input.role === UserRole.SUPPORT_COMMITTEE) {
      return {
        title: 'لا توجد طلبات دعم للجنة المساندة حالياً',
        description:
          'لم تصل أي طلبات معتمدة من البنوك إلى لجنة المساندة بعد. عند وصول أول طلب يمكنك حجزه وبدء المراجعة.',
      }
    }
    if (input.role === UserRole.EXECUTIVE_MEMBER) {
      return {
        title: 'لا توجد جلسات تصويت مرتبطة بك',
        description:
          'لم تُفتح جلسة تصويت تتطلب مشاركتك حالياً. عند تكليفك بجلسة ستظهر هنا مع خيار التصويت.',
      }
    }
    if (input.role === UserRole.COMMITTEE_DIRECTOR) {
      return {
        title: 'لا توجد طلبات في مرحلة التصويت',
        description:
          'لم تصل أي طلبات إلى مرحلة التصويت التنفيذي. بعد اعتماد لجنة المساندة والسويفت ستظهر هنا لإدارة الجلسة.',
      }
    }
    if (input.role === UserRole.SWIFT_OFFICER) {
      return {
        title: 'لا توجد طلبات بانتظار السويفت',
        description:
          'لم تصل طلبات تحتاج رفع مستندات السويفت. عند وصول أول طلب ستظهر هنا الوثائق المطلوبة وخيار الرفع.',
      }
    }
    if (input.role === UserRole.CBY_ADMIN) {
      return {
        title: 'لا توجد طلبات في النظام',
        description:
          'لم يتم تقديم أي طلبات تمويل عبر المنصة حتى الآن. بعد أول تقديم ستظهر هنا للرقابة والمتابعة.',
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
        description:
          'لا توجد طلبات محجوزة باسمك حالياً. انتقل إلى طابور المساندة العام لحجز طلب جديد عند توفره.',
      }
    }
    return {
      title: 'الطابور فارغ، أنجزت المهمة',
      description: 'لا توجد طلبات في انتظار مراجعتك حالياً. هذه حالة سليمة ولا تتطلب إجراء.',
    }
  }
  if (input.role === UserRole.EXECUTIVE_MEMBER) {
    if (input.tab === 'pending_my_vote') {
      return {
        title: 'لا توجد جلسات مخصصة لتصويتك',
        description: 'لا توجد جلسات مفتوحة تتطلب تصويتك حالياً. ستظهر الجلسة هنا فور تكليفك.',
      }
    }
    return {
      title: 'لا توجد جلسات مخصصة لتصويتك',
      description: 'لا توجد جلسات مفتوحة تتطلب تصويتك حالياً. ستظهر الجلسة هنا فور تكليفك.',
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
    description: 'لا توجد عناصر ضمن المرحلة المختارة حالياً. اختر طابوراً آخر أو امسح الفلاتر.',
  }
}
