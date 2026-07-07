import {
  CoverageType,
  CurrencySource,
  Incoterm,
  InvoiceType,
  PaymentTermsMode,
  PortOfArrival,
  RequestStatus,
  RequestType,
  UserRole,
} from '../types/enums'

import type { IconName } from '../utils/icon-map'
import { NAV_SURFACE_ROUTES, rolesForSurface } from './role-surfaces'

export const NOT_ELIGIBLE_LABEL_AR = 'غير مستوفي للشروط'
export const NOT_ELIGIBLE_LABEL = `${NOT_ELIGIBLE_LABEL_AR} / Not Eligible`
export const NOT_ELIGIBLE_BANK_LABEL = `${NOT_ELIGIBLE_LABEL} (البنك)`
export const NOT_ELIGIBLE_SUPPORT_LABEL = `${NOT_ELIGIBLE_LABEL} (لجنة المساندة)`
export const NOT_ELIGIBLE_EXECUTIVE_LABEL = `${NOT_ELIGIBLE_LABEL} (اللجنة التنفيذية)`
export const NOT_ELIGIBLE_REASON_LABEL = `سبب ${NOT_ELIGIBLE_LABEL_AR}`
export const NOT_ELIGIBLE_FINAL_REASON_LABEL = `سبب ${NOT_ELIGIBLE_LABEL_AR} النهائي`
export const NOT_ELIGIBLE_BEFORE_SWIFT_LABEL = `${NOT_ELIGIBLE_LABEL} قبل السويفت`
export const NOT_ELIGIBLE_REQUEST_LABEL = `طلب ${NOT_ELIGIBLE_LABEL_AR}`
export const NOT_ELIGIBLE_ROUTE_STOPPED_LABEL = `توقف المسار: ${NOT_ELIGIBLE_LABEL_AR}`
export const NOT_ELIGIBLE_REVIEW_ACTION_LABEL = `مراجعة سبب ${NOT_ELIGIBLE_LABEL_AR}`

/** Hex color for each status — matches UX-DR38 semantic color mapping */
export const STATUS_COLORS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: '#8e8e93',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: '#ff9f0a',
  [RequestStatus.SUBMITTED]: '#ff9f0a',
  [RequestStatus.BANK_REVIEW]: '#ff9f0a',
  [RequestStatus.BANK_RETURNED]: '#ff9f0a',
  [RequestStatus.SUPPORT_RETURNED]: '#ff9f0a',
  [RequestStatus.BANK_REJECTED]: '#ff3b30',
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
  [RequestStatus.FX_CONFIRMATION_PENDING]: '#32ade6',
  [RequestStatus.COMPLETED]: '#34c759',
}

/** Icon name for each status — used alongside color; never color-only (UX-DR38, NFR19) */
export const STATUS_ICONS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: 'file',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'rotate-ccw',
  [RequestStatus.SUBMITTED]: 'clock',
  [RequestStatus.BANK_REVIEW]: 'clock',
  [RequestStatus.BANK_RETURNED]: 'rotate-ccw',
  [RequestStatus.SUPPORT_RETURNED]: 'rotate-ccw',
  [RequestStatus.BANK_REJECTED]: 'x-circle',
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
  [RequestStatus.FX_CONFIRMATION_PENDING]: 'stamp',
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
  [RequestStatus.BANK_RETURNED]: RequestStatus.DRAFT_REJECTED_INTERNAL,
  [RequestStatus.SUPPORT_RETURNED]: RequestStatus.DRAFT_REJECTED_INTERNAL,
  [RequestStatus.BANK_REJECTED]: RequestStatus.BANK_REJECTED,
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
  [RequestStatus.FX_CONFIRMATION_PENDING]: RequestStatus.COMPLETED,
  [RequestStatus.COMPLETED]: RequestStatus.COMPLETED,
}

export const ALL_ROLES: UserRole[] = Object.values(UserRole)

/** Arabic display labels for each role — used in header, sidebar chip, and tables */
export const ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'إدخال البيانات',
  [UserRole.BANK_REVIEWER]: 'مراجع البنك',
  [UserRole.BANK_ADMIN]: 'مسؤول البنك',
  [UserRole.SWIFT_OFFICER]: 'مسؤول SWIFT',
  [UserRole.SUPPORT_COMMITTEE]: 'لجنة المساندة',
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
  [UserRole.SUPPORT_COMMITTEE]: 'الطلبات في انتظار لجنة المساندة',
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

export const BANK_ADMIN_MANAGED_ROLES: UserRole[] = [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER]

export const CBY_ROLES: UserRole[] = [
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

/** Roles that see simplified business statuses — never CBY operational internals */
export const DATA_ENTRY_ROLES: UserRole[] = [UserRole.DATA_ENTRY]

/** Operational roles with full internal status visibility on /workflows */
export const CBY_OPERATIONAL_ROLES: UserRole[] = [
  UserRole.SWIFT_OFFICER,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

/** Roles that see operational filters (search + status dropdown) on /workflows */
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
    RequestStatus.BANK_RETURNED,
    RequestStatus.BANK_REJECTED,
  ],
  [UserRole.BANK_ADMIN]: [
    RequestStatus.DRAFT,
    RequestStatus.DRAFT_REJECTED_INTERNAL,
    RequestStatus.BANK_RETURNED,
    RequestStatus.SUPPORT_RETURNED,
    RequestStatus.SUBMITTED,
    RequestStatus.BANK_REVIEW,
    RequestStatus.BANK_APPROVED,
    RequestStatus.BANK_REJECTED,
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
    RequestStatus.FX_CONFIRMATION_PENDING,
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
    RequestStatus.FX_CONFIRMATION_PENDING,
    RequestStatus.COMPLETED,
  ],
  // CBY_ADMIN: not listed → all statuses shown in filter
}

export interface NavItem {
  label: string
  route: string
  icon: IconName
  roles: UserRole[]
}

export const NAV_ITEMS: NavItem[] = [
  {
    label: 'اللوحة الرئيسية',
    route: NAV_SURFACE_ROUTES['nav.dashboard'],
    icon: 'home',
    roles: rolesForSurface('nav.dashboard'),
  },
  {
    label: 'طلبات التمويل',
    route: NAV_SURFACE_ROUTES['nav.workflows'],
    icon: 'file-text',
    roles: rolesForSurface('nav.workflows'),
  },
  {
    label: 'تقديم طلب جديد',
    route: NAV_SURFACE_ROUTES['nav.workflows_new'],
    icon: 'plus-circle',
    roles: rolesForSurface('nav.workflows_new'),
  },
  {
    label: 'التجار',
    route: NAV_SURFACE_ROUTES['nav.merchants'],
    icon: 'building',
    roles: rolesForSurface('nav.merchants'),
  },

  {
    label: 'الموظفون',
    route: NAV_SURFACE_ROUTES['nav.staff'],
    icon: 'user-check',
    roles: rolesForSurface('nav.staff'),
  },
  {
    label: 'تأكيد المصارفة الخارجية',
    route: NAV_SURFACE_ROUTES['nav.external_fx_confirmation'],
    icon: 'stamp',
    roles: rolesForSurface('nav.external_fx_confirmation'),
  },
  {
    label: 'التقارير والتحليلات',
    route: NAV_SURFACE_ROUTES['nav.reports'],
    icon: 'bar-chart-2',
    roles: rolesForSurface('nav.reports'),
  },
  {
    label: 'التدقيق والامتثال',
    route: NAV_SURFACE_ROUTES['nav.audit'],
    icon: 'shield-check',
    roles: rolesForSurface('nav.audit'),
  },
  {
    label: 'الإشعارات',
    route: NAV_SURFACE_ROUTES['nav.notifications'],
    icon: 'bell',
    roles: rolesForSurface('nav.notifications'),
  },
  {
    label: 'إدارة المستخدمين',
    route: NAV_SURFACE_ROUTES['nav.admin.staff'],
    icon: 'users',
    roles: rolesForSurface('nav.admin.staff'),
  },
  {
    label: 'البنوك',
    route: NAV_SURFACE_ROUTES['nav.admin.banks'],
    icon: 'landmark',
    roles: rolesForSurface('nav.admin.banks'),
  },
  {
    label: 'الجهات',
    route: NAV_SURFACE_ROUTES['nav.admin.orgs'],
    icon: 'building-2',
    roles: rolesForSurface('nav.admin.orgs'),
  },
  {
    label: 'إعدادات النظام',
    route: NAV_SURFACE_ROUTES['nav.admin.system_settings'],
    icon: 'settings',
    roles: rolesForSurface('nav.admin.system_settings'),
  },
  {
    label: 'إدارة الأدوار',
    route: NAV_SURFACE_ROUTES['nav.admin.roles'],
    icon: 'shield-check',
    roles: rolesForSurface('nav.admin.roles'),
  },
  {
    label: 'مصمم سير العمل',
    route: NAV_SURFACE_ROUTES['nav.admin.workflow_designer'],
    icon: 'settings',
    roles: rolesForSurface('nav.admin.workflow_designer'),
  },
  {
    label: 'البيانات الأساسية',
    route: NAV_SURFACE_ROUTES['nav.admin.reference_data'],
    icon: 'file-text',
    roles: rolesForSurface('nav.admin.reference_data'),
  },
  {
    label: 'إدارة الفرق',
    route: NAV_SURFACE_ROUTES['nav.admin.teams'],
    icon: 'users',
    roles: rolesForSurface('nav.admin.teams'),
  },
  {
    label: 'صلاحيات الشاشات',
    route: NAV_SURFACE_ROUTES['nav.admin.screen_permissions'],
    icon: 'lock',
    roles: rolesForSurface('nav.admin.screen_permissions'),
  },
  {
    label: 'الإعدادات',
    route: NAV_SURFACE_ROUTES['nav.settings'],
    icon: 'settings',
    roles: rolesForSurface('nav.settings'),
  },
]

/** Business-status labels shown to DATA_ENTRY (simplified, no CBY internals) */
export const DATA_ENTRY_STATUS_LABELS: Partial<Record<RequestStatus, string>> = {
  [RequestStatus.DRAFT]: 'مسودة',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'معاد للتعديل',
  [RequestStatus.BANK_RETURNED]: 'معادة',
  [RequestStatus.SUPPORT_RETURNED]: 'معادة',
  [RequestStatus.SUBMITTED]: 'مقدّم للمراجعة',
  [RequestStatus.BANK_REVIEW]: 'مقدّم للمراجعة',
  [RequestStatus.BANK_APPROVED]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_APPROVED]: 'قيد معالجة CBY',
  [RequestStatus.SUPPORT_REJECTED]: NOT_ELIGIBLE_SUPPORT_LABEL,
  [RequestStatus.WAITING_FOR_SWIFT]: 'قيد معالجة CBY',
  [RequestStatus.SWIFT_UPLOADED]: 'قيد معالجة CBY',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 'قيد معالجة CBY',
  [RequestStatus.EXECUTIVE_APPROVED]: 'مكتمل',
  [RequestStatus.EXECUTIVE_REJECTED]: NOT_ELIGIBLE_EXECUTIVE_LABEL,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 'مكتمل',
  [RequestStatus.FX_CONFIRMATION_PENDING]: 'مكتمل',
  [RequestStatus.COMPLETED]: 'مكتمل',
  [RequestStatus.BANK_REJECTED]: NOT_ELIGIBLE_BANK_LABEL,
}

/** Full internal status labels for bank/CBY roles */
export const STATUS_LABELS: Record<RequestStatus, string> = {
  [RequestStatus.DRAFT]: 'مسودة',
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 'معاد للتعديل',
  [RequestStatus.SUBMITTED]: 'مقدم',
  [RequestStatus.BANK_REVIEW]: 'قيد مراجعة البنك',
  // Story 17-E.4: single source for the "Returned to Data Entry" display label.
  [RequestStatus.BANK_RETURNED]: 'أُعيد إلى مدخل البيانات',
  [RequestStatus.SUPPORT_RETURNED]: 'إعادة من المساندة',
  [RequestStatus.BANK_APPROVED]: 'موافقة البنك',
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 'بانتظار المراجعة',
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 'قيد المراجعة',
  [RequestStatus.SUPPORT_APPROVED]: 'موافقة لجنة المساندة',
  [RequestStatus.SUPPORT_REJECTED]: NOT_ELIGIBLE_SUPPORT_LABEL,
  [RequestStatus.WAITING_FOR_SWIFT]: 'انتظار رفع SWIFT',
  [RequestStatus.SWIFT_UPLOADED]: 'تم رفع SWIFT',
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 'انتظار فتح التصويت',
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 'التصويت جارٍ',
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 'التصويت مغلق',
  [RequestStatus.EXECUTIVE_APPROVED]: 'موافقة تنفيذية',
  [RequestStatus.EXECUTIVE_REJECTED]: NOT_ELIGIBLE_EXECUTIVE_LABEL,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 'تأكيد المصارفة الخارجية صادر',
  [RequestStatus.FX_CONFIRMATION_PENDING]: 'بانتظار تأكيد المصارفة الخارجية',
  [RequestStatus.COMPLETED]: 'مكتمل',
  [RequestStatus.BANK_REJECTED]: NOT_ELIGIBLE_BANK_LABEL,
}

/**
 * Story 17-E.4 (D8): SWIFT stage DISPLAY merge. `WAITING_FOR_SWIFT` and
 * `SWIFT_UPLOADED` collapse into a single timeline node labeled "تم رفع السويفت".
 * Display-only — the underlying statuses remain distinct in STATUS_PROGRESS,
 * STATUS_LABELS, API responses, queries, and audit. The merged node renders
 * in-progress while at `WAITING_FOR_SWIFT` and completed at `SWIFT_UPLOADED`.
 */
export const SWIFT_DISPLAY_GROUP = {
  label: 'تم رفع السويفت',
  statuses: [RequestStatus.WAITING_FOR_SWIFT, RequestStatus.SWIFT_UPLOADED] as RequestStatus[],
} as const

/** Progress percentage per status — role-aware, derived from canonical RequestStatus */
export const STATUS_PROGRESS: Record<RequestStatus, number> = {
  [RequestStatus.DRAFT]: 5,
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: 5,
  [RequestStatus.BANK_RETURNED]: 18,
  [RequestStatus.SUPPORT_RETURNED]: 20,
  [RequestStatus.SUBMITTED]: 15,
  [RequestStatus.BANK_REVIEW]: 25,
  [RequestStatus.BANK_APPROVED]: 35,
  [RequestStatus.SUPPORT_REVIEW_PENDING]: 45,
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: 55,
  [RequestStatus.SUPPORT_APPROVED]: 65,
  [RequestStatus.SUPPORT_REJECTED]: 65,
  [RequestStatus.WAITING_FOR_SWIFT]: 70,
  [RequestStatus.SWIFT_UPLOADED]: 75,
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: 80,
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: 85,
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: 88,
  [RequestStatus.EXECUTIVE_APPROVED]: 92,
  [RequestStatus.EXECUTIVE_REJECTED]: 92,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: 97,
  [RequestStatus.FX_CONFIRMATION_PENDING]: 97,
  [RequestStatus.COMPLETED]: 100,
  [RequestStatus.BANK_REJECTED]: 25,
}

export function getStatusProgress(status: RequestStatus, role: UserRole): number {
  const representativeStatus = getBusinessStatus(status, role).canonicalStatus
  return STATUS_PROGRESS[representativeStatus] ?? 0
}

export interface StageBucket {
  key: string
  label: string
  statuses: RequestStatus[]
  matches?: (
    request: {
      status: RequestStatus
      created_by?: number | null
      is_claimed_by_me?: boolean
      claimed_by?: { id: number; name: string } | null
      my_vote?: 'approve' | 'reject' | null
      ready_to_close?: boolean
      is_tie?: boolean
    },
    currentUserId?: number | null,
  ) => boolean
}

/** Role-aware stage buckets — maps each role to the stage buckets it sees. */
export const ROLE_BUCKETS: Partial<Record<UserRole, StageBucket[]>> = {
  // Spec order: returned first (most actionable), then draft, submitted, processing, completed, rejected, all
  [UserRole.DATA_ENTRY]: [
    {
      key: 'returned',
      label: 'معادة',
      statuses: [
        RequestStatus.BANK_RETURNED,
        RequestStatus.SUPPORT_RETURNED,
        RequestStatus.DRAFT_REJECTED_INTERNAL,
      ],
    },
    { key: 'draft', label: 'مسودة', statuses: [RequestStatus.DRAFT] },
    {
      key: 'submitted',
      label: 'مقدّم',
      statuses: [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW],
    },
    {
      key: 'processing',
      label: 'قيد معالجة CBY',
      statuses: [
        RequestStatus.BANK_APPROVED,
        RequestStatus.SUPPORT_REVIEW_PENDING,
        RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
        RequestStatus.SUPPORT_APPROVED,
        RequestStatus.WAITING_FOR_SWIFT,
        RequestStatus.SWIFT_UPLOADED,
        RequestStatus.WAITING_FOR_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
      ],
    },
    {
      key: 'completed',
      label: 'مكتمل',
      statuses: [
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_LABEL,
      statuses: [
        RequestStatus.SUPPORT_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.BANK_REJECTED,
      ],
    },
  ],
  // Spec order: pending, support_rejected, bank_returned, support_returned, at_cby, completed, rejected, all
  [UserRole.BANK_REVIEWER]: [
    {
      key: 'pending',
      label: 'قيد المراجعة',
      statuses: [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW],
    },
    {
      key: 'support_rejected',
      label: NOT_ELIGIBLE_SUPPORT_LABEL,
      statuses: [RequestStatus.SUPPORT_REJECTED],
    },
    {
      key: 'bank_returned',
      label: 'أُعيد إلى مدخل البيانات',
      statuses: [RequestStatus.BANK_RETURNED],
    },
    {
      key: 'support_returned',
      label: 'أُعيد للمدخل من المساندة',
      statuses: [RequestStatus.SUPPORT_RETURNED],
    },
    {
      key: 'at_cby',
      label: 'لدى اللجنة الوطنية',
      statuses: [
        RequestStatus.BANK_APPROVED,
        RequestStatus.SUPPORT_REVIEW_PENDING,
        RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
        RequestStatus.SUPPORT_APPROVED,
        RequestStatus.WAITING_FOR_SWIFT,
        RequestStatus.SWIFT_UPLOADED,
        RequestStatus.WAITING_FOR_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
      ],
    },
    {
      key: 'completed',
      label: 'مكتمل',
      statuses: [
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_LABEL,
      statuses: [RequestStatus.BANK_REJECTED, RequestStatus.EXECUTIVE_REJECTED],
    },
  ],
  // Spec order: pending (includes DRAFT_REJECTED_INTERNAL) / at_cby / swift_fx / completed / rejected / all
  [UserRole.BANK_ADMIN]: [
    {
      key: 'pending',
      label: 'معلّق',
      statuses: [
        RequestStatus.DRAFT_REJECTED_INTERNAL,
        RequestStatus.SUBMITTED,
        RequestStatus.BANK_REVIEW,
        RequestStatus.BANK_RETURNED,
        RequestStatus.SUPPORT_RETURNED,
      ],
    },
    {
      key: 'at_cby',
      label: 'لدى اللجنة الوطنية',
      statuses: [
        RequestStatus.BANK_APPROVED,
        RequestStatus.SUPPORT_REVIEW_PENDING,
        RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
        RequestStatus.SUPPORT_APPROVED,
        RequestStatus.WAITING_FOR_SWIFT,
        RequestStatus.SWIFT_UPLOADED,
        RequestStatus.WAITING_FOR_VOTING_OPEN,
      ],
    },
    {
      key: 'swift_fx',
      label: 'SWIFT / تأكيد المصارفة',
      statuses: [
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
        RequestStatus.EXECUTIVE_APPROVED,
      ],
    },
    {
      key: 'completed',
      label: 'مكتمل',
      statuses: [
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_LABEL,
      statuses: [
        RequestStatus.SUPPORT_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.BANK_REJECTED,
      ],
    },
  ],
  [UserRole.SWIFT_OFFICER]: [
    {
      key: 'pending_swift',
      label: 'انتظار رفع SWIFT',
      statuses: [RequestStatus.EXECUTIVE_APPROVED, RequestStatus.WAITING_FOR_SWIFT],
    },
    {
      key: 'swift_done',
      label: 'تم رفع السويفت',
      statuses: [RequestStatus.SWIFT_UPLOADED, RequestStatus.WAITING_FOR_VOTING_OPEN],
    },
    {
      key: 'completed',
      label: 'مكتمل',
      statuses: [
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_BEFORE_SWIFT_LABEL,
      statuses: [
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.SUPPORT_REJECTED,
        RequestStatus.BANK_REJECTED,
      ],
    },
  ],
  // Spec order: waiting (unclaimed) first, my_claims, in_progress, approved, rejected, all
  [UserRole.SUPPORT_COMMITTEE]: [
    { key: 'waiting', label: 'بانتظار المراجعة', statuses: [RequestStatus.SUPPORT_REVIEW_PENDING] },
    {
      key: 'my_claims',
      label: 'أعمل عليها',
      statuses: [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS],
      matches: (request, currentUserId) =>
        request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS &&
        (request.is_claimed_by_me === true ||
          (currentUserId != null && request.claimed_by?.id === currentUserId)),
    },
    {
      key: 'in_progress',
      label: 'محجوزة لأعضاء آخرين',
      statuses: [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS],
      matches: (request, currentUserId) =>
        request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS &&
        !!request.claimed_by &&
        request.is_claimed_by_me !== true &&
        request.claimed_by.id !== currentUserId,
    },
    { key: 'approved', label: 'اعتُمدت', statuses: [RequestStatus.SUPPORT_APPROVED] },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_SUPPORT_LABEL,
      statuses: [RequestStatus.SUPPORT_REJECTED],
    },
  ],
  // Spec order: pending_my_vote first (most actionable), voted_by_me, pending_open, voting_open, voting_closed, approved, rejected, post_approval, all
  [UserRole.EXECUTIVE_MEMBER]: [
    {
      key: 'pending_my_vote',
      label: 'يحتاج صوتي',
      statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN],
      matches: (request) =>
        request.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !request.my_vote,
    },
    {
      key: 'voted_by_me',
      label: 'صوّتُّ عليها',
      statuses: [
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.EXECUTIVE_REJECTED,
      ],
      matches: (request) =>
        !!request.my_vote ||
        [RequestStatus.EXECUTIVE_APPROVED, RequestStatus.EXECUTIVE_REJECTED].includes(
          request.status,
        ),
    },
    {
      key: 'pending_open',
      label: 'انتظار فتح التصويت',
      statuses: [RequestStatus.SUPPORT_APPROVED, RequestStatus.WAITING_FOR_VOTING_OPEN],
    },
    { key: 'voting_open', label: 'التصويت مفتوح', statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN] },
    {
      key: 'voting_closed',
      label: 'التصويت مغلق',
      statuses: [RequestStatus.EXECUTIVE_VOTING_CLOSED],
    },
    { key: 'approved', label: 'معتمد', statuses: [RequestStatus.EXECUTIVE_APPROVED] },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_EXECUTIVE_LABEL,
      statuses: [RequestStatus.EXECUTIVE_REJECTED],
    },
    {
      key: 'post_approval',
      label: 'ما بعد الاعتماد',
      statuses: [
        RequestStatus.WAITING_FOR_SWIFT,
        RequestStatus.SWIFT_UPLOADED,
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
  ],
  [UserRole.COMMITTEE_DIRECTOR]: [
    {
      key: 'ready_to_close',
      label: 'جاهزة للإغلاق',
      statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN],
      matches: (request: any) =>
        request.status === RequestStatus.EXECUTIVE_VOTING_OPEN && request.ready_to_close === true,
    },
    {
      key: 'ready_to_finalize',
      label: 'جاهزة للإصدار النهائي',
      statuses: [RequestStatus.EXECUTIVE_VOTING_CLOSED],
    },
    {
      key: 'tie_break',
      label: 'تعادل يحتاج إلى حسم',
      statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN],
      matches: (request: any) =>
        request.status === RequestStatus.EXECUTIVE_VOTING_OPEN && request.is_tie === true,
    },
    {
      key: 'fx_pending',
      label: 'بانتظار تأكيد المصارفة',
      statuses: [RequestStatus.EXECUTIVE_APPROVED, RequestStatus.FX_CONFIRMATION_PENDING],
    },
    { key: 'active_voting', label: 'تصويت نشط', statuses: [RequestStatus.EXECUTIVE_VOTING_OPEN] },
    {
      key: 'finalized',
      label: 'منجز',
      statuses: [
        RequestStatus.EXECUTIVE_APPROVED,
        RequestStatus.EXECUTIVE_REJECTED,
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.COMPLETED,
      ],
    },
  ],
  // Operational tabs (not internal workflow stages) — per docs/user-view/cby-admin.md#Requests List
  [UserRole.CBY_ADMIN]: [
    {
      key: 'active',
      label: 'نشط',
      statuses: [
        RequestStatus.SUBMITTED,
        RequestStatus.BANK_REVIEW,
        RequestStatus.BANK_RETURNED,
        RequestStatus.BANK_APPROVED,
        RequestStatus.SUPPORT_REVIEW_PENDING,
        RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
        RequestStatus.SUPPORT_APPROVED,
        RequestStatus.SUPPORT_RETURNED,
        RequestStatus.WAITING_FOR_SWIFT,
        RequestStatus.SWIFT_UPLOADED,
        RequestStatus.WAITING_FOR_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
      ],
    },
    {
      key: 'needs_attention',
      label: 'يحتاج متابعة',
      statuses: [
        RequestStatus.DRAFT_REJECTED_INTERNAL,
        RequestStatus.BANK_RETURNED,
        RequestStatus.SUPPORT_RETURNED,
        RequestStatus.SUPPORT_REJECTED,
      ],
    },
    {
      key: 'executive_voting',
      label: 'تصويت تنفيذي',
      statuses: [
        RequestStatus.WAITING_FOR_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_OPEN,
        RequestStatus.EXECUTIVE_VOTING_CLOSED,
      ],
    },
    { key: 'fx_pending', label: 'تأكيد المصارفة', statuses: [RequestStatus.EXECUTIVE_APPROVED] },
    {
      key: 'rejected',
      label: NOT_ELIGIBLE_LABEL,
      statuses: [
        RequestStatus.BANK_REJECTED,
        RequestStatus.SUPPORT_REJECTED,
        RequestStatus.EXECUTIVE_REJECTED,
      ],
    },
    {
      key: 'completed',
      label: 'مكتمل',
      statuses: [
        RequestStatus.CUSTOMS_DECLARATION_ISSUED,
        RequestStatus.FX_CONFIRMATION_PENDING,
        RequestStatus.COMPLETED,
      ],
    },
  ],
}

/**
 * Default "attention-needed" statuses per role — applied as the initial filter
 * when the user lands on /workflows with no explicit status filter in the URL.
 * Roles not listed here (DATA_ENTRY, BANK_ADMIN, CBY_ADMIN) see all requests
 * by default because their queue is already organisation-scoped by the backend.
 */
export const ROLE_ATTENTION_STATUSES: Partial<Record<UserRole, RequestStatus[]>> = {
  [UserRole.BANK_REVIEWER]: [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW],
  [UserRole.SWIFT_OFFICER]: [RequestStatus.WAITING_FOR_SWIFT],
  [UserRole.SUPPORT_COMMITTEE]: [
    RequestStatus.SUPPORT_REVIEW_PENDING,
    RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  ],
  [UserRole.EXECUTIVE_MEMBER]: [RequestStatus.EXECUTIVE_VOTING_OPEN],
  [UserRole.COMMITTEE_DIRECTOR]: [
    RequestStatus.EXECUTIVE_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_CLOSED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.FX_CONFIRMATION_PENDING,
  ],
}

/** CBY/global roles that see the bank filter dropdown */
export const CBY_BANK_FILTER_ROLES: UserRole[] = [
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

/** Supported currencies for the currency filter */
export const CURRENCY_OPTIONS = ['USD', 'EUR', 'SAR', 'AED', 'CNY'] as const

export interface EnumOption<T extends string = string> {
  value: T
  label: string
  hint?: string
}

export const REQUEST_TYPE_OPTIONS: EnumOption<RequestType>[] = [
  { value: RequestType.GOODS_IMPORT, label: 'استيراد بضائع', hint: 'Goods Import' },
  {
    value: RequestType.RAW_MATERIAL_IMPORT,
    label: 'استيراد مواد خام',
    hint: 'Raw Material Import',
  },
  { value: RequestType.EQUIPMENT_IMPORT, label: 'استيراد معدات', hint: 'Equipment Import' },
]

export const COVERAGE_TYPE_OPTIONS: EnumOption<CoverageType>[] = [
  { value: CoverageType.FULL, label: 'تغطية كاملة', hint: 'Full Coverage' },
  { value: CoverageType.PARTIAL, label: 'تغطية جزئية', hint: 'Partial Coverage' },
]

export const CURRENCY_SOURCE_OPTIONS: EnumOption<CurrencySource>[] = [
  { value: CurrencySource.OWN_FUNDS, label: 'موارد ذاتية', hint: 'Own Funds' },
  { value: CurrencySource.BANK_FINANCING, label: 'تمويل بنكي', hint: 'Bank Financing' },
  { value: CurrencySource.EXTERNAL_FINANCING, label: 'تمويل خارجي', hint: 'External Financing' },
]

export const PAYMENT_TERMS_MODE_OPTIONS: EnumOption<PaymentTermsMode>[] = [
  { value: PaymentTermsMode.ADVANCE_PAYMENT, label: 'دفع مقدم', hint: 'Advance Payment' },
  { value: PaymentTermsMode.LETTER_OF_CREDIT, label: 'اعتماد مستندي', hint: 'Letter of Credit' },
  {
    value: PaymentTermsMode.DOCUMENTARY_COLLECTION,
    label: 'تحصيل مستندي',
    hint: 'Documentary Collection',
  },
  { value: PaymentTermsMode.DEFERRED_PAYMENT, label: 'دفع مؤجل', hint: 'Deferred Payment' },
]

export const INVOICE_TYPE_OPTIONS: EnumOption<InvoiceType>[] = [
  { value: InvoiceType.PROFORMA, label: 'فاتورة مبدئية', hint: 'Proforma Invoice' },
  { value: InvoiceType.COMMERCIAL, label: 'فاتورة تجارية', hint: 'Commercial Invoice' },
  { value: InvoiceType.FINAL, label: 'فاتورة نهائية', hint: 'Final Invoice' },
]

export const PORT_OF_ARRIVAL_OPTIONS: EnumOption<PortOfArrival>[] = [
  { value: PortOfArrival.ADEN, label: 'ميناء عدن', hint: 'Aden Port' },
  { value: PortOfArrival.HODEIDAH, label: 'ميناء الحديدة', hint: 'Hodeidah Port' },
  { value: PortOfArrival.MUKALLA, label: 'ميناء المكلا', hint: 'Mukalla Port' },
  { value: PortOfArrival.MOKHA, label: 'ميناء المخا', hint: 'Mokha Port' },
  { value: PortOfArrival.NISHTUN, label: 'ميناء نشطون', hint: 'Nishtun Port' },
]

export const INCOTERM_OPTIONS: EnumOption<Incoterm>[] = [
  { value: Incoterm.EXW, label: 'تسليم في المصنع', hint: 'EXW' },
  { value: Incoterm.FCA, label: 'تسليم للناقل', hint: 'FCA' },
  { value: Incoterm.CPT, label: 'النقل مدفوع إلى', hint: 'CPT' },
  { value: Incoterm.CIP, label: 'النقل والتأمين مدفوعان إلى', hint: 'CIP' },
  { value: Incoterm.DAP, label: 'تسليم في المكان', hint: 'DAP' },
  { value: Incoterm.DPU, label: 'تسليم في المكان مع التفريغ', hint: 'DPU' },
  { value: Incoterm.DDP, label: 'تسليم خالص الرسوم', hint: 'DDP' },
  { value: Incoterm.FAS, label: 'تسليم جانب السفينة', hint: 'FAS' },
  { value: Incoterm.FOB, label: 'تسليم على ظهر السفينة', hint: 'FOB' },
  { value: Incoterm.CFR, label: 'التكلفة والشحن', hint: 'CFR' },
  { value: Incoterm.CIF, label: 'التكلفة والتأمين والشحن', hint: 'CIF' },
]

/** Routes that require authentication (all except login) */
export const PROTECTED_ROUTES = [
  '/dashboard',
  '/workflows',
  '/workflows/new',
  '/customs',
  '/audit',
  '/reports',
  '/notifications',
  '/admin',
  '/bank',
  '/settings',
  '/settings/system',
  '/settings/bank',
  '/merchants',
  '/staff',
]

/** Route → allowed roles mapping for role middleware */
export const ROUTE_ROLE_MAP: Record<string, UserRole[]> = {
  '/dashboard': rolesForSurface('nav.dashboard'),
  '/workflows': rolesForSurface('nav.workflows'),
  '/workflows/new': rolesForSurface('nav.workflows_new'),
  '/workflows/:id': rolesForSurface('nav.workflows'),
  '/merchants': rolesForSurface('nav.merchants'),
  '/staff': rolesForSurface('nav.staff'),
  '/customs': rolesForSurface('nav.external_fx_confirmation'),
  '/reports': rolesForSurface('nav.reports'),
  '/audit': rolesForSurface('nav.audit'),
  '/notifications': rolesForSurface('nav.notifications'),
  '/admin': [UserRole.CBY_ADMIN],
  '/admin/staff': rolesForSurface('nav.admin.staff'),
  '/admin/banks': rolesForSurface('nav.admin.banks'),
  '/admin/orgs': rolesForSurface('nav.admin.orgs'),
  '/admin/settings': rolesForSurface('nav.admin.system_settings'),
  '/admin/health': [UserRole.CBY_ADMIN],
  '/admin/roles': rolesForSurface('nav.admin.roles'),
  '/admin/workflows': rolesForSurface('nav.admin.workflow_designer'),
  '/admin/teams': rolesForSurface('nav.admin.teams'),
  '/admin/reference-data': rolesForSurface('nav.admin.reference_data'),
  '/admin/screen-permissions': rolesForSurface('nav.admin.screen_permissions'),
  '/settings': rolesForSurface('nav.settings'),
  '/settings/system': [UserRole.CBY_ADMIN],
  '/settings/bank': [UserRole.BANK_ADMIN],
}

const ROUTE_ROLE_ENTRIES = Object.entries(ROUTE_ROLE_MAP).map(([pattern, roles]) => {
  const escaped = pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const regex = new RegExp(`^${escaped.replace(/:([^/]+)/g, '[^/]+')}$`)
  return { pattern, regex, roles }
})

export function resolveRouteRoles(path: string): UserRole[] | undefined {
  for (const entry of ROUTE_ROLE_ENTRIES) {
    if (entry.regex.test(path)) {
      return entry.roles
    }
  }
  return undefined
}
