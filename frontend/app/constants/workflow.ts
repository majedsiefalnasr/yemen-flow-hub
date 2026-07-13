import {
  CoverageType,
  CurrencySource,
  Incoterm,
  InvoiceType,
  PaymentTermsMode,
  PortOfArrival,
  RequestType,
  UserRole,
} from '../types/enums'

import type { ScreenCapability } from '../types/models'
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

export interface NavItem {
  label: string
  route: string
  icon: IconName
  roles: UserRole[]
  /** Screen-permissions matrix key gating this item, if it is delegable. */
  screen?: string
  /** Capability required on `screen`. Defaults to VIEW when `screen` is set. */
  capability?: ScreenCapability
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
    screen: 'merchants',
  },

  {
    label: 'الموظفون',
    route: NAV_SURFACE_ROUTES['nav.staff'],
    icon: 'user-check',
    roles: rolesForSurface('nav.staff'),
    screen: 'staff',
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
    screen: 'reports',
  },
  {
    label: 'التدقيق والامتثال',
    route: NAV_SURFACE_ROUTES['nav.audit'],
    icon: 'shield-check',
    roles: rolesForSurface('nav.audit'),
    screen: 'audit',
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
    screen: 'users',
  },
  {
    label: 'البنوك',
    route: NAV_SURFACE_ROUTES['nav.admin.banks'],
    icon: 'landmark',
    roles: rolesForSurface('nav.admin.banks'),
    screen: 'banks',
  },
  {
    label: 'الجهات',
    route: NAV_SURFACE_ROUTES['nav.admin.orgs'],
    icon: 'building-2',
    roles: rolesForSurface('nav.admin.orgs'),
    screen: 'organizations',
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
    screen: 'roles',
  },
  {
    label: 'مصمم سير العمل',
    route: NAV_SURFACE_ROUTES['nav.admin.workflow_designer'],
    icon: 'settings',
    roles: rolesForSurface('nav.admin.workflow_designer'),
    screen: 'workflow_designer',
  },
  {
    label: 'البيانات الأساسية',
    route: NAV_SURFACE_ROUTES['nav.admin.reference_data'],
    icon: 'file-text',
    roles: rolesForSurface('nav.admin.reference_data'),
    screen: 'reference_data',
  },
  {
    label: 'إدارة الفرق',
    route: NAV_SURFACE_ROUTES['nav.admin.teams'],
    icon: 'users',
    roles: rolesForSurface('nav.admin.teams'),
    screen: 'teams',
  },
  {
    label: 'صلاحيات الشاشات',
    route: NAV_SURFACE_ROUTES['nav.admin.screen_permissions'],
    icon: 'lock',
    roles: rolesForSurface('nav.admin.screen_permissions'),
    screen: 'screen_permissions',
  },
  {
    label: 'الإعدادات',
    route: NAV_SURFACE_ROUTES['nav.settings'],
    icon: 'settings',
    roles: rolesForSurface('nav.settings'),
  },
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
