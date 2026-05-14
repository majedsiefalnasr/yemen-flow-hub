import { RequestStatus, UserRole } from '../types/enums'

export const ALL_ROLES: UserRole[] = Object.values(UserRole)

/** Arabic display labels for each role — used in header, sidebar chip, and tables */
export const ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'إدخال البيانات',
  [UserRole.BANK_REVIEWER]: 'مراجع البنك',
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
  [UserRole.SWIFT_OFFICER]: 'الطلبات الجاهزة لرفع SWIFT',
  [UserRole.SUPPORT_COMMITTEE]: 'الطلبات في انتظار لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'جلسات التصويت الفعّالة',
  [UserRole.COMMITTEE_DIRECTOR]: 'القرارات التنفيذية المعلقة',
  [UserRole.CBY_ADMIN]: 'لوحة إدارة النظام',
}

export const BANK_ROLES: UserRole[] = [
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
  UserRole.SWIFT_OFFICER,
]

export const CBY_ROLES: UserRole[] = [
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

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
      UserRole.BANK_REVIEWER,
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
    route: '/admin/entities',
    icon: 'landmark',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    label: 'مستخدمي النظام',
    route: '/admin/cby-staff',
    icon: 'users',
    roles: [UserRole.CBY_ADMIN],
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
    label: 'إعدادات النظام',
    route: '/settings',
    icon: 'settings',
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
export const PROTECTED_ROUTES = ['/dashboard', '/requests', '/voting', '/customs', '/audit', '/reports', '/notifications', '/admin', '/bank', '/settings', '/merchants']

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
    UserRole.BANK_REVIEWER,
  ],
  '/audit': [UserRole.CBY_ADMIN],
  '/admin': [UserRole.CBY_ADMIN],
  '/bank/users': [UserRole.BANK_REVIEWER],
  '/settings': [UserRole.CBY_ADMIN],
}
