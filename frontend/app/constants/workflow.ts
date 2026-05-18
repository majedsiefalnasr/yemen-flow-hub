import { RequestStatus, UserRole } from '../types/enums'

/** Hex color for each status — matches UX-DR38 semantic color mapping */
export const STATUS_COLORS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: '#8e8e93',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: '#ff9f0a',
  [RequestStatus.SUBMITTED]: '#ff9f0a',
  [RequestStatus.BANK_REVIEW]: '#ff9f0a',
  [RequestStatus.BANK_APPROVED]: '#5856d6',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: '#5856d6',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: '#5856d6',
  [RequestStatus.SUPPORT_APPROVED]: '#5856d6',
  [RequestStatus.SUPPORT_REJECTED]: '#ff3b30',
  [RequestStatus.WAITING_FOR_SWIFT]: '#32ade6',
  [RequestStatus.SWIFT_UPLOADED]: '#32ade6',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: '#5856d6',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: '#5856d6',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: '#5856d6',
  [RequestStatus.EXECUTIVE_APPROVED]: '#34c759',
  [RequestStatus.EXECUTIVE_REJECTED]: '#ff3b30',
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: '#34c759',
  [RequestStatus.COMPLETED]: '#34c759',
}

/** Icon name for each status — used alongside color; never color-only (UX-DR38, NFR19) */
export const STATUS_ICONS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: 'file',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'rotate-ccw',
  [RequestStatus.SUBMITTED]: 'clock',
  [RequestStatus.BANK_REVIEW]: 'clock',
  [RequestStatus.BANK_APPROVED]: 'check-circle',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 'users',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 'users',
  [RequestStatus.SUPPORT_APPROVED]: 'check-circle',
  [RequestStatus.SUPPORT_REJECTED]: 'x-circle',
  [RequestStatus.WAITING_FOR_SWIFT]: 'upload-cloud',
  [RequestStatus.SWIFT_UPLOADED]: 'upload-cloud',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 'vote',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 'vote',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 'lock',
  [RequestStatus.EXECUTIVE_APPROVED]: 'check-circle',
  [RequestStatus.EXECUTIVE_REJECTED]: 'x-circle',
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 'file-check',
  [RequestStatus.COMPLETED]: 'check-circle',
}

export interface BusinessStatus {
  label: string
  color: string
  icon: string
  /** The canonical status driving the badge (may be the original, or a simplified bucket for DATA_ENTRY) */
  canonicalStatus: RequestStatus
}

/**
 * Returns the display label, color, and icon for a request status scoped to the viewing role.
 * DATA_ENTRY receives simplified business statuses — never internal CBY operational stages.
 */
export function getBusinessStatus(status: RequestStatus, role: UserRole): BusinessStatus {
  if (role === UserRole.DATA_ENTRY) {
    const label = DATA_ENTRY_STATUS_LABELS[status] ?? STATUS_LABELS[status]
    // Map simplified label groups back to the canonical status that drives the color/icon
    const representativeStatus = DATA_ENTRY_REPRESENTATIVE_STATUS[status] ?? status
    return {
      label,
      color: STATUS_COLORS[representativeStatus],
      icon: STATUS_ICONS[representativeStatus],
      canonicalStatus: representativeStatus,
    }
  }

  return {
    label: STATUS_LABELS[status],
    color: STATUS_COLORS[status],
    icon: STATUS_ICONS[status],
    canonicalStatus: status,
  }
}

/**
 * Maps each status to the canonical status that best represents the DATA_ENTRY simplified bucket.
 * Used to select the right color/icon for simplified badge groups.
 */
const DATA_ENTRY_REPRESENTATIVE_STATUS: Record<RequestStatus, RequestStatus> = {
  [RequestStatus.DRAFT]: RequestStatus.DRAFT,
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: RequestStatus.DRAFT_REJECTED_INTERNAL,
  [RequestStatus.SUBMITTED]: RequestStatus.SUBMITTED,
  [RequestStatus.BANK_REVIEW]: RequestStatus.SUBMITTED,
  [RequestStatus.BANK_APPROVED]: RequestStatus.BANK_APPROVED,
  [RequestStatus.SUPPORT_REVIEW_PENDING]: RequestStatus.BANK_APPROVED,
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: RequestStatus.BANK_APPROVED,
  [RequestStatus.SUPPORT_APPROVED]: RequestStatus.BANK_APPROVED,
  [RequestStatus.SUPPORT_REJECTED]: RequestStatus.SUPPORT_REJECTED,
  [RequestStatus.WAITING_FOR_SWIFT]: RequestStatus.BANK_APPROVED,
  [RequestStatus.SWIFT_UPLOADED]: RequestStatus.BANK_APPROVED,
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: RequestStatus.BANK_APPROVED,
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: RequestStatus.BANK_APPROVED,
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: RequestStatus.BANK_APPROVED,
  [RequestStatus.EXECUTIVE_APPROVED]: RequestStatus.COMPLETED,
  [RequestStatus.EXECUTIVE_REJECTED]: RequestStatus.EXECUTIVE_REJECTED,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: RequestStatus.COMPLETED,
  [RequestStatus.COMPLETED]: RequestStatus.COMPLETED,
}

export const ALL_ROLES: UserRole[] = Object.values(UserRole)

/** Arabic display labels for each role — used in header, sidebar chip, and tables */
export const ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'إدخال البيانات',
  [UserRole.BANK_REVIEWER]: 'مراجع البنك',
  [UserRole.BANK_ADMIN]: 'مسؤول البنك',
  [UserRole.SWIFT_OFFICER]: 'مسؤول SWIFT',
  [UserRole.SUPPORT_COMMITTEE]: 'لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو تنفيذي',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة',
  [UserRole.CBY_ADMIN]: 'مدير النظام',
}

/** Role-specific queue titles shown on the dashboard — each role sees their own work queue */
export const ROLE_QUEUE_TITLES: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'طلبات التمويل الخاصة بك',
  [UserRole.BANK_REVIEWER]: 'الطلبات المعلقة للمراجعة',
  [UserRole.BANK_ADMIN]: 'إدارة عمليات البنك',
  [UserRole.SWIFT_OFFICER]: 'الطلبات الجاهزة لرفع SWIFT',
  [UserRole.SUPPORT_COMMITTEE]: 'الطلبات في انتظار لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'جلسات التصويت الفعّالة',
  [UserRole.COMMITTEE_DIRECTOR]: 'القرارات التنفيذية المعلقة',
  [UserRole.CBY_ADMIN]: 'لوحة إدارة النظام',
}

export const BANK_ROLES: UserRole[] = [
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
  UserRole.SWIFT_OFFICER,
]

export const BANK_ADMIN_MANAGED_ROLES: UserRole[] = [
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
]

export const CBY_ROLES: UserRole[] = [
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

/** Roles that see simplified business statuses — never CBY operational internals */
export const DATA_ENTRY_ROLES: UserRole[] = [UserRole.DATA_ENTRY]

/** Operational roles with full internal status visibility on /requests */
export const CBY_OPERATIONAL_ROLES: UserRole[] = [
  UserRole.SWIFT_OFFICER,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

/** Roles that see operational filters (search + status dropdown) on /requests */
export const OPERATIONAL_FILTER_ROLES: UserRole[] = [
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
  ...CBY_OPERATIONAL_ROLES,
]

/** Statuses relevant to each role's filter dropdown — absent role means show all statuses */
export const ROLE_FILTER_STATUSES: Partial<Record<UserRole, RequestStatus[]>> = {
  [UserRole.BANK_REVIEWER]: [
    RequestStatus.SUBMITTED,
    RequestStatus.BANK_REVIEW,
    RequestStatus.BANK_APPROVED,
    RequestStatus.DRAFT_REJECTED_INTERNAL,
  ],
  [UserRole.BANK_ADMIN]: [
    RequestStatus.DRAFT,
    RequestStatus.DRAFT_REJECTED_INTERNAL,
    RequestStatus.SUBMITTED,
    RequestStatus.BANK_REVIEW,
    RequestStatus.BANK_APPROVED,
    RequestStatus.SUPPORT_REVIEW_PENDING,
    RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.SUPPORT_REJECTED,
    RequestStatus.WAITING_FOR_SWIFT,
    RequestStatus.SWIFT_UPLOADED,
    RequestStatus.WAITING_FOR_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_CLOSED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.EXECUTIVE_REJECTED,
    RequestStatus.CUSTOMS_DECLARATION_ISSUED,
    RequestStatus.COMPLETED,
  ],
  [UserRole.SWIFT_OFFICER]: [
    RequestStatus.BANK_APPROVED,
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.WAITING_FOR_SWIFT,
    RequestStatus.SWIFT_UPLOADED,
  ],
  [UserRole.SUPPORT_COMMITTEE]: [
    RequestStatus.BANK_APPROVED,
    RequestStatus.SUPPORT_REVIEW_PENDING,
    RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.SUPPORT_REJECTED,
  ],
  [UserRole.EXECUTIVE_MEMBER]: [
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.WAITING_FOR_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_CLOSED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.EXECUTIVE_REJECTED,
  ],
  [UserRole.COMMITTEE_DIRECTOR]: [
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.CUSTOMS_DECLARATION_ISSUED,
    RequestStatus.COMPLETED,
  ],
  // CBY_ADMIN: not listed → all statuses shown in filter
}

export interface NavItem {
  label: string
  route: string
  icon: string
  roles: UserRole[]
}

export const NAV_ITEMS: NavItem[] = [
  {
    label: 'اللوحة الرئيسية',
    route: '/dashboard',
    icon: 'home',
    roles: ALL_ROLES,
  },
  {
    label: 'طلبات التمويل',
    route: '/requests',
    icon: 'file-text',
    roles: ALL_ROLES,
  },
  {
    label: 'تقديم طلب جديد',
    route: '/requests/new',
    icon: 'plus-circle',
    roles: [UserRole.DATA_ENTRY],
  },
  {
    label: 'إدارة التجار',
    route: '/merchants',
    icon: 'building',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    label: 'البيان الجمركي',
    route: '/customs',
    icon: 'stamp',
    roles: [UserRole.COMMITTEE_DIRECTOR],
  },
  {
    label: 'التقارير والتحليلات',
    route: '/reports',
    icon: 'bar-chart-2',
    roles: [
      UserRole.CBY_ADMIN,
      UserRole.SUPPORT_COMMITTEE,
      UserRole.EXECUTIVE_MEMBER,
      UserRole.COMMITTEE_DIRECTOR,
      UserRole.DATA_ENTRY,
      UserRole.BANK_REVIEWER,
      UserRole.BANK_ADMIN,
    ],
  },
  {
    label: 'التدقيق والامتثال',
    route: '/audit',
    icon: 'shield-check',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    label: 'الإشعارات',
    route: '/notifications',
    icon: 'bell',
    roles: ALL_ROLES,
  },
  {
    label: 'إدارة البنوك',
    route: '/banks',
    icon: 'landmark',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    label: 'مستخدمي النظام',
    route: '/users',
    icon: 'users',
    roles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
  },
  {
    label: 'قواعد المستندات',
    route: '/admin/workflow-docs',
    icon: 'file-cog',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    label: 'موظفو الجهة',
    route: '/bank/users',
    icon: 'user-check',
    roles: [UserRole.BANK_REVIEWER],
  },
  {
    label: 'بيانات البنك',
    route: '/banks',
    icon: 'landmark',
    roles: [UserRole.BANK_ADMIN],
  },
  {
    label: 'الإعدادات',
    route: '/settings',
    icon: 'settings',
    roles: ALL_ROLES,
  },
  {
    label: 'إعدادات النظام',
    route: '/admin/settings',
    icon: 'sliders',
    roles: [UserRole.CBY_ADMIN],
  },
]

/** Business-status labels shown to DATA_ENTRY (simplified, no CBY internals) */
export const DATA_ENTRY_STATUS_LABELS: Partial<Record<RequestStatus, string>> = {
  [RequestStatus.DRAFT]: 'مسودة',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'معاد للتعديل',
  [RequestStatus.SUBMITTED]: 'مقدّم للمراجعة',
  [RequestStatus.BANK_REVIEW]: 'مقدّم للمراجعة',
  [RequestStatus.BANK_APPROVED]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_APPROVED]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REJECTED]: 'مرفوض',
  [RequestStatus.WAITING_FOR_SWIFT]: 'قيد معالجة CBY',
  [RequestStatus.SWIFT_UPLOADED]: 'قيد معالجة CBY',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_APPROVED]: 'مكتمل',
  [RequestStatus.EXECUTIVE_REJECTED]: 'مرفوض نهائياً',
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 'مكتمل',
  [RequestStatus.COMPLETED]: 'مكتمل',
}

/** Full internal status labels for bank/CBY roles */
export const STATUS_LABELS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: 'مسودة',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'مُعاد للتعديل',
  [RequestStatus.SUBMITTED]: 'مُقدَّم',
  [RequestStatus.BANK_REVIEW]: 'قيد مراجعة البنك',
  [RequestStatus.BANK_APPROVED]: 'موافقة البنك',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 'انتظار لجنة الدعم',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 'قيد مراجعة الدعم',
  [RequestStatus.SUPPORT_APPROVED]: 'موافقة لجنة الدعم',
  [RequestStatus.SUPPORT_REJECTED]: 'رفض لجنة الدعم',
  [RequestStatus.WAITING_FOR_SWIFT]: 'انتظار رفع SWIFT',
  [RequestStatus.SWIFT_UPLOADED]: 'تم رفع SWIFT',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 'انتظار فتح التصويت',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 'التصويت جارٍ',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 'التصويت مغلق',
  [RequestStatus.EXECUTIVE_APPROVED]: 'موافقة تنفيذية',
  [RequestStatus.EXECUTIVE_REJECTED]: 'رفض نهائي',
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 'بيان جمركي صادر',
  [RequestStatus.COMPLETED]: 'مكتمل',
}

/** Routes that require authentication (all except login) */
export const PROTECTED_ROUTES = ['/dashboard', '/requests', '/voting', '/customs', '/audit', '/reports', '/notifications', '/admin', '/admin/settings', '/bank', '/settings', '/merchants', '/banks', '/users']

/** Route → allowed roles mapping for role middleware */
export const ROUTE_ROLE_MAP: Record<string, UserRole[]> = {
  '/requests/new': [UserRole.DATA_ENTRY],
  '/merchants': [UserRole.CBY_ADMIN],
  '/customs': [UserRole.COMMITTEE_DIRECTOR, UserRole.CBY_ADMIN],
  '/reports': [
    UserRole.CBY_ADMIN,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.DATA_ENTRY,
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
  ],
  '/audit': [UserRole.CBY_ADMIN],
  '/admin': [UserRole.CBY_ADMIN],
  '/admin/settings': [UserRole.CBY_ADMIN],
  '/banks': [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
  '/users': [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
  '/bank/users': [UserRole.BANK_REVIEWER],
  '/settings': ALL_ROLES,
}
