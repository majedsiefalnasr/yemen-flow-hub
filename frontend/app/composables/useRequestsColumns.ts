import type { ColumnDef } from '@tanstack/vue-table'
import type { ComputedRef, Ref } from 'vue'
import { AlertTriangle, Clock, Lock, MoreHorizontal, Shield, TriangleAlert, Vote } from 'lucide-vue-next'
import { h } from 'vue'
import { STATUS_COLORS, STATUS_LABELS } from '@/constants/workflow'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { RequestStatus, UserRole } from '@/types/enums'
import type { ImportRequest } from '@/types/models'

export const REQUESTS_COLUMN_LABELS: Record<string, string> = {
  reference_number: 'رقم المرجع',
  created_by: 'أنشأه',
  merchant: 'التاجر / البنك',
  goods_description: 'نوع البضاعة',
  amount: 'المبلغ',
  status: 'الحالة',
  last_activity: 'النشاط الأخير',
  cby_age: 'العمر',
  cby_sla: 'SLA',
  cby_voting: 'التصويت',
  cby_fx: 'المصارفة',
  cby_risk: 'المخاطر',
  director_ready_to_close: 'جاهز للإغلاق',
  director_fx_state: 'حالة المصارفة',
  swift_documents: 'المستندات',
}

export const STATUS_FILTER_OPTIONS = Object.entries(STATUS_LABELS).map(([value, label]) => ({
  label,
  value,
  color: STATUS_COLORS[value as RequestStatus],
}))

function relativeTime(isoDate: string | null | undefined): string {
  if (!isoDate) return '—'
  const ms = Date.now() - new Date(isoDate).getTime()
  const mins = Math.floor(ms / 60000)
  if (mins < 60) return `منذ ${mins} دقيقة`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `منذ ${hrs} ساعة`
  const days = Math.floor(hrs / 24)
  if (days < 30) return `منذ ${days} يوم`
  const months = Math.floor(days / 30)
  return `منذ ${months} شهر`
}

function ageHours(isoDate: string): number {
  return (Date.now() - new Date(isoDate).getTime()) / 3600000
}

function slaInfo(ageH: number): { label: string; color: string } {
  if (ageH > 120) return { label: 'انتهاك', color: 'var(--severity-red)' }
  if (ageH > 72) return { label: 'خطر', color: 'var(--severity-amber)' }
  return { label: 'طبيعي', color: 'var(--severity-green)' }
}

export function useRequestsColumns(opts: {
  role: Ref<UserRole>
  currentUserId: ComputedRef<number | null>
  onPreviewClick: (req: ImportRequest) => void
}): { columns: ColumnDef<ImportRequest>[] } {
  const router = useRouter()
  const { role, currentUserId, onPreviewClick } = opts

  const columns: ColumnDef<ImportRequest>[] = [
    {
      id: 'select',
      header: ({ table }) =>
        h(Checkbox, {
          modelValue: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
          'onUpdate:modelValue': (value: boolean | 'indeterminate') => table.toggleAllPageRowsSelected(!!value),
          'aria-label': 'تحديد الكل',
        }),
      cell: ({ row }) =>
        h('div', { onClick: (event: Event) => event.stopPropagation() }, [
          h(Checkbox, {
            modelValue: row.getIsSelected(),
            'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
            'aria-label': `تحديد الطلب ${row.original.reference_number}`,
          }),
        ]),
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'reference_number',
      header: REQUESTS_COLUMN_LABELS.reference_number,
      enableHiding: false,
      cell: ({ row }) => {
        const request = row.original
        const badges: ReturnType<typeof h>[] = []

        if (request.duplicate_warnings?.length) {
          badges.push(h(Badge, { variant: 'destructive', class: 'rounded-full' }, () => [
            h(TriangleAlert, { class: 'size-3.5 me-1' }),
            'مكرر',
          ]))
        }
        if (
          request.status === RequestStatus.EXECUTIVE_VOTING_OPEN
          && (role.value === UserRole.EXECUTIVE_MEMBER || role.value === UserRole.COMMITTEE_DIRECTOR)
        ) {
          badges.push(h(Badge, { variant: 'secondary', class: 'rounded-full text-voting' }, () => [
            h(Vote, { class: 'size-3.5 me-1' }),
            'التصويت مفتوح',
          ]))
        }
        if (request.is_claimed && !request.is_claimed_by_me && role.value === UserRole.SUPPORT_COMMITTEE) {
          badges.push(h(Badge, { variant: 'secondary', class: 'rounded-full text-amber-700' }, () => [
            h(Lock, { class: 'size-3.5 me-1' }),
            `محجوز: ${request.claimed_by?.name ?? '—'}`,
          ]))
        } else if (request.is_claimed_by_me) {
          badges.push(h(Badge, { variant: 'secondary', class: 'rounded-full text-amber-700' }, () => [
            h(Lock, { class: 'size-3.5 me-1' }),
            'محجوز لك',
          ]))
        }

        return h('div', { class: 'flex flex-col gap-2' }, [
          h('div', { class: 'flex flex-wrap items-center gap-2' }, [
            h('button', {
              type: 'button',
              class: 'font-mono text-base font-semibold text-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:underline cursor-pointer',
              title: 'معاينة سريعة',
              'aria-label': `معاينة الطلب ${request.reference_number}`,
              onClick: (event: Event) => {
                event.stopPropagation()
                onPreviewClick(request)
              },
            }, request.reference_number),
            ...badges,
          ]),
          request.invoice_number
            ? h('span', { class: 'text-xs text-muted-foreground' }, request.invoice_number)
            : null,
        ])
      },
    },
    {
      id: 'created_by',
      header: REQUESTS_COLUMN_LABELS.created_by,
      cell: ({ row }) => {
        const request = row.original
        if (role.value !== UserRole.BANK_REVIEWER && role.value !== UserRole.BANK_ADMIN) {
          return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        }
        const isSelf = currentUserId.value != null && request.created_by === currentUserId.value
        return h('span', {
          class: isSelf ? 'text-sm font-semibold text-amber-600' : 'text-sm text-foreground',
        }, isSelf ? 'أنا' : (request.created_by_user?.name ?? '—'))
      },
    },
    {
      id: 'merchant',
      accessorFn: row => row.merchant?.name ?? '',
      header: REQUESTS_COLUMN_LABELS.merchant,
      cell: ({ row }) => h('div', { class: 'flex flex-col gap-1' }, [
        h('span', { class: 'truncate text-sm font-semibold text-foreground' }, row.original.merchant?.name ?? '—'),
        h('span', { class: 'truncate text-xs text-muted-foreground' }, row.original.bank_name ?? '—'),
      ]),
    },
    {
      accessorKey: 'goods_description',
      header: REQUESTS_COLUMN_LABELS.goods_description,
      cell: ({ row }) => h('span', { class: 'line-clamp-2 text-sm text-muted-foreground' }, row.original.goods_description ?? '—'),
    },
    {
      accessorKey: 'amount',
      header: REQUESTS_COLUMN_LABELS.amount,
      cell: ({ row }) => {
        const request = row.original
        return h('div', { class: 'flex items-baseline gap-1' }, [
          h('span', { class: 'font-mono text-sm font-semibold tabular-nums text-foreground' }, request.amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })),
          h('span', { class: 'text-xs text-muted-foreground' }, request.currency),
        ])
      },
    },
    {
      accessorKey: 'status',
      header: REQUESTS_COLUMN_LABELS.status,
      filterFn: (row, columnId, filterValue) => !Array.isArray(filterValue) || filterValue.length === 0 || filterValue.includes(String(row.getValue(columnId))),
      cell: ({ row }) => h(StatusBadge, { status: row.original.status, role: role.value }),
    },
    {
      id: 'last_activity',
      header: REQUESTS_COLUMN_LABELS.last_activity,
      cell: ({ row }) => {
        if (![UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.CBY_ADMIN].includes(role.value)) {
          return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        }
        return h('span', {
          class: 'text-xs text-muted-foreground tabular-nums',
          title: row.original.updated_at,
        }, relativeTime(row.original.updated_at))
      },
    },
    {
      id: 'cby_age',
      header: REQUESTS_COLUMN_LABELS.cby_age,
      cell: ({ row }) => {
        if (role.value !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const hrs = ageHours(row.original.created_at)
        const days = Math.floor(hrs / 24)
        return h('span', { class: 'text-xs tabular-nums text-foreground' }, days > 0 ? `${days} يوم` : `${Math.floor(hrs)} س`)
      },
    },
    {
      id: 'cby_sla',
      header: REQUESTS_COLUMN_LABELS.cby_sla,
      cell: ({ row }) => {
        if (role.value !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const { label, color } = slaInfo(ageHours(row.original.created_at))
        return h('span', {
          class: 'inline-flex items-center gap-1 text-xs font-medium',
          style: { color },
        }, [h(Clock, { class: 'size-3 flex-shrink-0' }), label])
      },
    },
    {
      id: 'cby_voting',
      header: REQUESTS_COLUMN_LABELS.cby_voting,
      cell: ({ row }) => {
        if (role.value !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const votingStatus = row.original.voting_session_status
        if (!votingStatus) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        return h('span', {
          class: 'inline-flex items-center gap-1 text-xs',
          style: { color: 'var(--voting)' },
        }, [h(Vote, { class: 'size-3 flex-shrink-0' }), votingStatus])
      },
    },
    {
      id: 'cby_fx',
      header: REQUESTS_COLUMN_LABELS.cby_fx,
      cell: ({ row }) => {
        if (role.value !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const done = row.original.has_fx_request_document === true
        return h('span', {
          class: 'text-xs font-medium',
          style: { color: done ? 'var(--severity-green)' : 'var(--severity-amber)' },
        }, done ? 'مرفوع' : 'معلّق')
      },
    },
    {
      id: 'cby_risk',
      header: REQUESTS_COLUMN_LABELS.cby_risk,
      cell: ({ row }) => {
        if (role.value !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const flags = row.original.duplicate_warnings?.length ?? 0
        if (flags === 0) return h(Shield, { class: 'size-4 text-[var(--severity-green)]' })
        return h('span', {
          class: 'inline-flex items-center gap-1 text-xs font-semibold',
          style: { color: 'var(--severity-amber)' },
        }, [h(AlertTriangle, { class: 'size-3.5 flex-shrink-0' }), `${flags} تكرار`])
      },
    },
    {
      id: 'director_ready_to_close',
      header: REQUESTS_COLUMN_LABELS.director_ready_to_close,
      cell: ({ row }) => {
        if (role.value !== UserRole.COMMITTEE_DIRECTOR) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const request = row.original
        if (request.status !== RequestStatus.EXECUTIVE_VOTING_OPEN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        if (request.ready_to_close) {
          return h('span', {
            class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
            style: {
              color: 'var(--severity-green)',
              backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)',
              borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)',
            },
          }, [h(Vote, { class: 'size-3' }), 'جاهز'])
        }
        const cast = request.votes_cast ?? 0
        const total = request.total_voters ?? 0
        return h('span', { class: 'text-xs text-muted-foreground' }, total > 0 ? `${cast}/${total} أصوات` : 'قيد التصويت')
      },
    },
    {
      id: 'director_fx_state',
      header: REQUESTS_COLUMN_LABELS.director_fx_state,
      cell: ({ row }) => {
        if (role.value !== UserRole.COMMITTEE_DIRECTOR) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const request = row.original
        if (request.status === RequestStatus.FX_CONFIRMATION_PENDING) {
          if (request.has_fx_request_document === true) {
            return h('span', {
              class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
              style: {
                color: 'var(--severity-green)',
                backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)',
                borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)',
              },
            }, 'مرفوع')
          }
          return h('span', {
            class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
            style: {
              color: 'var(--severity-amber)',
              backgroundColor: 'color-mix(in srgb, var(--severity-amber) 10%, transparent)',
              borderColor: 'color-mix(in srgb, var(--severity-amber) 30%, transparent)',
            },
          }, [h(Clock, { class: 'size-3' }), 'انتظار رفع'])
        }
        if (request.status === RequestStatus.CUSTOMS_DECLARATION_ISSUED || request.status === RequestStatus.COMPLETED) {
          return h('span', {
            class: 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border',
            style: {
              color: 'var(--severity-green)',
              backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)',
              borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)',
            },
          }, 'مكتمل')
        }
        return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      },
    },
    {
      id: 'swift_documents',
      header: REQUESTS_COLUMN_LABELS.swift_documents,
      cell: ({ row }) => {
        if (role.value !== UserRole.SWIFT_OFFICER) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
        const pillClass = (active: boolean) => active
          ? 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-50 text-green-700 border border-green-200'
          : 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-muted text-muted-foreground border border-border'
        return h('div', { class: 'flex items-center gap-1.5' }, [
          h('span', { class: pillClass(row.original.has_swift_document === true) }, 'السويفت'),
          h('span', { class: pillClass(row.original.has_fx_request_document === true) }, 'طلب تأكيد المصارفة'),
        ])
      },
    },
    {
      id: 'actions',
      enableHiding: false,
      cell: ({ row }) => {
        const request = row.original
        const isEditable = [
          RequestStatus.DRAFT,
          RequestStatus.BANK_RETURNED,
          RequestStatus.SUPPORT_RETURNED,
          RequestStatus.DRAFT_REJECTED_INTERNAL,
        ].includes(request.status)
        const isBankReviewerSelf = role.value === UserRole.BANK_REVIEWER
          && currentUserId.value != null
          && request.created_by === currentUserId.value

        if (role.value === UserRole.BANK_REVIEWER && [RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW].includes(request.status)) {
          if (isBankReviewerSelf) {
            return h('span', {
              class: 'inline-flex rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground cursor-not-allowed',
              title: 'لا يمكنك مراجعة طلب أنشأته بنفسك',
              'aria-label': 'لا يمكنك مراجعة طلب أنشأته بنفسك',
            }, 'غير متاح')
          }
          return h(Button, {
            variant: 'outline',
            size: 'sm',
            class: 'h-8 text-xs',
            onClick: (event: Event) => {
              event.stopPropagation()
              router.push(`/requests/${request.id}`)
            },
          }, () => 'بدء المراجعة')
        }

        if (role.value === UserRole.SUPPORT_COMMITTEE) {
          const mine = request.is_claimed_by_me || (currentUserId.value != null && request.claimed_by?.id === currentUserId.value)
          const label = !request.claimed_by ? 'مطالبة' : mine ? 'متابعة' : 'عرض'
          const className = !request.claimed_by
            ? 'h-8 bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90 text-xs'
            : mine
              ? 'h-8 border-[var(--voting)] text-[var(--voting)] hover:bg-[var(--voting)]/10 text-xs'
              : 'h-8 text-xs'
          return h(Button, {
            variant: !request.claimed_by ? 'default' : 'outline',
            size: 'sm',
            class: className,
            onClick: (event: Event) => {
              event.stopPropagation()
              router.push(`/requests/${request.id}`)
            },
          }, () => label)
        }

        if (role.value === UserRole.SWIFT_OFFICER) {
          const canUpload = request.status === RequestStatus.WAITING_FOR_SWIFT
          return h(Button, {
            variant: canUpload ? 'default' : 'outline',
            size: 'sm',
            class: canUpload ? 'h-8 text-xs bg-info text-white hover:bg-info/90' : 'h-8 text-xs',
            onClick: (event: Event) => {
              event.stopPropagation()
              router.push(canUpload ? `/requests/${request.id}/swift` : `/requests/${request.id}`)
            },
          }, () => (canUpload ? 'رفع' : 'عرض'))
        }

        return h(DropdownMenu, {}, {
          default: () => [
            h(DropdownMenuTrigger, { asChild: true }, {
              default: () => h(Button, {
                variant: 'ghost',
                size: 'icon',
                class: 'h-8 w-8',
                onClick: (event: Event) => event.stopPropagation(),
              }, {
                default: () => [
                  h('span', { class: 'sr-only' }, 'فتح القائمة'),
                  h(MoreHorizontal, { class: 'h-4 w-4' }),
                ],
              }),
            }),
            h(DropdownMenuContent, { align: 'end' }, {
              default: () => [
                h(DropdownMenuItem, {
                  onClick: (event: Event) => {
                    event.stopPropagation()
                    router.push(`/requests/${request.id}`)
                  },
                }, () => 'عرض'),
                ...(isEditable
                  ? [h(DropdownMenuItem, {
                      onClick: (event: Event) => {
                        event.stopPropagation()
                        router.push(`/requests/${request.id}/edit`)
                      },
                    }, () => 'تعديل')]
                  : []),
                h(DropdownMenuSeparator),
                h(DropdownMenuItem, {
                  onClick: (event: Event) => {
                    event.stopPropagation()
                    router.push(`/requests/${request.id}/print`)
                  },
                }, () => 'طباعة'),
              ],
            }),
          ],
        })
      },
    },
  ]

  return { columns }
}
