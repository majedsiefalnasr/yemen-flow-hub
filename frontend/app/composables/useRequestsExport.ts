import { STATUS_LABELS } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { ImportRequest } from '@/types/models'
import type { ExportColumn } from './useTableExport'

type RoleAwareExportColumn = ExportColumn<ImportRequest> & {
  roles?: UserRole[]
}

function formatDate(value: any): string {
  if (!value || typeof value !== 'string') return 'غير متاح'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'غير متاح'
  return date.toLocaleDateString('ar-EG', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function formatAmount(_: any, row: ImportRequest): string {
  return new Intl.NumberFormat('ar-EG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(row.amount)
}

const REQUESTS_EXPORT_COLUMNS: RoleAwareExportColumn[] = [
  { key: 'reference_number', label: 'المرجع' },
  {
    key: 'merchant',
    label: 'المستورد',
    format: (_, row) => row.merchant?.name ?? 'غير متاح',
  },
  { key: 'supplier_name', label: 'المورد' },
  {
    key: 'amount',
    label: 'المبلغ',
    format: formatAmount,
  },
  { key: 'currency', label: 'العملة' },
  {
    key: 'status',
    label: 'الحالة',
    format: (value) =>
      typeof value === 'string'
        ? (STATUS_LABELS[value as keyof typeof STATUS_LABELS] ?? value)
        : 'غير متاح',
  },
  {
    key: 'created_at',
    label: 'تاريخ الإنشاء',
    format: formatDate,
  },
  {
    key: 'updated_at',
    label: 'آخر تحديث',
    format: formatDate,
  },
  {
    key: 'bank_name',
    label: 'البنك',
    roles: [
      UserRole.SUPPORT_COMMITTEE,
      UserRole.EXECUTIVE_MEMBER,
      UserRole.COMMITTEE_DIRECTOR,
      UserRole.CBY_ADMIN,
    ],
    format: (_, row) => row.bank_name ?? 'غير متاح',
  },
  {
    key: 'current_owner_role',
    label: 'المالك الحالي',
    roles: [UserRole.SUPPORT_COMMITTEE, UserRole.COMMITTEE_DIRECTOR, UserRole.CBY_ADMIN],
    format: (value) => (typeof value === 'string' ? value : 'غير متاح'),
  },
  {
    key: 'claimed_by',
    label: 'حالة الحجز',
    roles: [UserRole.SUPPORT_COMMITTEE],
    format: (_, row) => {
      if (!row.is_claimed) return 'غير محجوز'
      if (row.is_claimed_by_me) return 'محجوز بواسطتي'
      return row.claimed_by?.name ? `محجوز: ${row.claimed_by.name}` : 'محجوز'
    },
  },
  {
    key: 'voting_session_status',
    label: 'حالة التصويت',
    roles: [UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR, UserRole.CBY_ADMIN],
    format: (value) => (typeof value === 'string' ? value : 'غير متاح'),
  },
]

export function buildRequestsExportColumns(role: UserRole): ExportColumn<ImportRequest>[] {
  return REQUESTS_EXPORT_COLUMNS.filter((column) => !column.roles || column.roles.includes(role))
}
