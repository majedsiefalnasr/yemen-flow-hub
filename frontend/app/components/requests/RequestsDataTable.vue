<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, SortingState, VisibilityState } from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Clock,
  Download,
  Lock,
  MoreHorizontal,
  Printer,
  SearchX,
  Shield,
  TriangleAlert,
  Vote,
  X,
} from 'lucide-vue-next'
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
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { RequestStatus, UserRole } from '@/types/enums'
import type { ImportRequest } from '@/types/models'
import { useAuthStore } from '@/stores/auth.store'

const props = defineProps<{
  data: ImportRequest[]
  loading?: boolean
  noData?: boolean
  role: UserRole
  columnVisibility?: VisibilityState
}>()

const emit = defineEmits<{
  rowClick: [requestId: number]
  previewClick: [request: ImportRequest]
  'update:columnVisibility': [value: VisibilityState]
  'update:selectedCount': [count: number]
}>()

const router = useRouter()
const authStore = useAuthStore()
const currentUserId = computed(() => authStore.user?.id ?? null)

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

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)
watch(selectedCount, count => emit('update:selectedCount', count))

function clearSelection() {
  table.resetRowSelection()
}

defineExpose({ clearSelection })

const columns: ColumnDef<ImportRequest>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        'modelValue': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') => table.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          'modelValue': row.getIsSelected(),
          'onUpdate:modelValue': (v: boolean | 'indeterminate') => row.toggleSelected(!!v),
          'aria-label': `تحديد الطلب ${row.original.reference_number}`,
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
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
        && (props.role === UserRole.EXECUTIVE_MEMBER || props.role === UserRole.COMMITTEE_DIRECTOR)
      ) {
        badges.push(h(Badge, { variant: 'secondary', class: 'rounded-full text-voting' }, () => [
          h(Vote, { class: 'size-3.5 me-1' }),
          'التصويت مفتوح',
        ]))
      }
      if (request.is_claimed && !request.is_claimed_by_me && props.role === UserRole.SUPPORT_COMMITTEE) {
        badges.push(h(Badge, { variant: 'secondary', class: 'rounded-full text-amber-700' }, () => [
          h(Lock, { class: 'size-3.5 me-1' }),
          `محجوز: ${request.claimed_by?.name ?? '—'}`,
        ]))
      }
      else if (request.is_claimed_by_me) {
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
            onClick: (e: Event) => { e.stopPropagation(); emit('previewClick', request) },
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
    header: 'أنشأه',
    cell: ({ row }) => {
      const request = row.original
      // Story 12.2 AC 12: BANK_ADMIN sees the Created By chip too. Other roles
      // (CBY-side, SUPPORT, EXECUTIVE) get an em-dash — they don't act on the
      // creator identity from this surface.
      if (props.role !== UserRole.BANK_REVIEWER && props.role !== UserRole.BANK_ADMIN) {
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
    accessorFn: (row) => row.merchant?.name ?? '',
    header: 'التاجر / البنك',
    cell: ({ row }) => {
      const request = row.original
      return h('div', { class: 'flex flex-col gap-1' }, [
        h('span', { class: 'truncate text-sm font-semibold text-foreground' }, request.merchant?.name ?? '—'),
        h('span', { class: 'truncate text-xs text-muted-foreground' }, request.bank_name ?? '—'),
      ])
    },
  },
  {
    accessorKey: 'goods_description',
    header: 'نوع البضاعة',
    cell: ({ row }) =>
      h('span', { class: 'line-clamp-2 text-sm text-muted-foreground' }, row.original.goods_description ?? '—'),
  },
  {
    accessorKey: 'amount',
    header: 'المبلغ',
    cell: ({ row }) => {
      const request = row.original
      const formatted = request.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      return h('div', { class: 'flex items-baseline gap-1' }, [
        h('span', { class: 'font-mono text-sm font-semibold tabular-nums text-foreground' }, formatted),
        h('span', { class: 'text-xs text-muted-foreground' }, request.currency),
      ])
    },
  },
  {
    accessorKey: 'status',
    header: 'الحالة',
    cell: ({ row }) => h(StatusBadge, { status: row.original.status, role: props.role }),
  },
  {
    id: 'last_activity',
    header: 'النشاط الأخير',
    cell: ({ row }) => {
      const showFor = [UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.CBY_ADMIN]
      if (!showFor.includes(props.role)) {
        return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      }
      return h('span', {
        class: 'text-xs text-muted-foreground tabular-nums',
        title: row.original.updated_at,
      }, relativeTime(row.original.updated_at))
    },
  },
  // CBY Admin governance columns
  {
    id: 'cby_age',
    header: 'العمر',
    cell: ({ row }) => {
      if (props.role !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const hrs = ageHours(row.original.created_at)
      const days = Math.floor(hrs / 24)
      const label = days > 0 ? `${days} يوم` : `${Math.floor(hrs)} س`
      return h('span', { class: 'text-xs tabular-nums text-foreground' }, label)
    },
  },
  {
    id: 'cby_sla',
    header: 'SLA',
    cell: ({ row }) => {
      if (props.role !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const { label, color } = slaInfo(ageHours(row.original.created_at))
      return h('span', {
        class: 'inline-flex items-center gap-1 text-xs font-medium',
        style: { color },
      }, [h(Clock, { class: 'size-3 flex-shrink-0' }), label])
    },
  },
  {
    id: 'cby_voting',
    header: 'التصويت',
    cell: ({ row }) => {
      if (props.role !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const vs = row.original.voting_session_status
      if (!vs) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      return h('span', {
        class: 'inline-flex items-center gap-1 text-xs',
        style: { color: 'var(--voting)' },
      }, [h(Vote, { class: 'size-3 flex-shrink-0' }), vs])
    },
  },
  {
    id: 'cby_fx',
    header: 'المصارفة',
    cell: ({ row }) => {
      if (props.role !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const done = row.original.has_fx_request_document === true
      return h('span', {
        class: 'text-xs font-medium',
        style: { color: done ? 'var(--severity-green)' : 'var(--severity-amber)' },
      }, done ? 'مرفوع' : 'معلّق')
    },
  },
  {
    id: 'cby_risk',
    header: 'المخاطر',
    cell: ({ row }) => {
      if (props.role !== UserRole.CBY_ADMIN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
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
    header: 'جاهز للإغلاق',
    cell: ({ row }) => {
      if (props.role !== UserRole.COMMITTEE_DIRECTOR) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const request = row.original
      if (request.status !== RequestStatus.EXECUTIVE_VOTING_OPEN) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      if (request.ready_to_close) {
        return h('span', {
          class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
          style: { color: 'var(--severity-green)', backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)', borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)' },
        }, [h(Vote, { class: 'size-3' }), 'جاهز'])
      }
      const cast = request.votes_cast ?? 0
      const total = request.total_voters ?? 0
      return h('span', {
        class: 'text-xs text-muted-foreground',
      }, total > 0 ? `${cast}/${total} أصوات` : 'قيد التصويت')
    },
  },
  {
    id: 'director_fx_state',
    header: 'حالة المصارفة',
    cell: ({ row }) => {
      if (props.role !== UserRole.COMMITTEE_DIRECTOR) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      const request = row.original
      if (request.status === RequestStatus.FX_CONFIRMATION_PENDING) {
        const hasFx = request.has_fx_request_document === true
        if (hasFx) {
          return h('span', {
            class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
            style: { color: 'var(--severity-green)', backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)', borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)' },
          }, 'مرفوع')
        }
        return h('span', {
          class: 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium border',
          style: { color: 'var(--severity-amber)', backgroundColor: 'color-mix(in srgb, var(--severity-amber) 10%, transparent)', borderColor: 'color-mix(in srgb, var(--severity-amber) 30%, transparent)' },
        }, [h(Clock, { class: 'size-3' }), 'انتظار رفع'])
      }
      if (request.status === RequestStatus.CUSTOMS_DECLARATION_ISSUED || request.status === RequestStatus.COMPLETED) {
        return h('span', {
          class: 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border',
          style: { color: 'var(--severity-green)', backgroundColor: 'color-mix(in srgb, var(--severity-green) 10%, transparent)', borderColor: 'color-mix(in srgb, var(--severity-green) 30%, transparent)' },
        }, 'مكتمل')
      }
      return h('span', { class: 'text-xs text-muted-foreground' }, '—')
    },
  },
  {
    id: 'swift_documents',
    header: 'المستندات',
    cell: ({ row }) => {
      if (props.role !== UserRole.SWIFT_OFFICER) {
        return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      }

      const request = row.original
      const swiftDone = request.has_swift_document === true
      const fxDone = request.has_fx_request_document === true
      const pillClass = (active: boolean) =>
        active
          ? 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-50 text-green-700 border border-green-200'
          : 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-muted text-muted-foreground border border-border'

      return h('div', { class: 'flex items-center gap-1.5' }, [
        h('span', { class: pillClass(swiftDone) }, 'السويفت'),
        h('span', { class: pillClass(fxDone) }, 'طلب تأكيد المصارفة'),
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
      const isDraft = request.status === RequestStatus.DRAFT
      const isBankReviewerSelf = props.role === UserRole.BANK_REVIEWER
        && currentUserId.value != null
        && request.created_by === currentUserId.value

      if (props.role === UserRole.BANK_REVIEWER && (request.status === RequestStatus.SUBMITTED || request.status === RequestStatus.BANK_REVIEW)) {
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
          onClick: (e: Event) => { e.stopPropagation(); router.push(`/requests/${request.id}`) },
        }, () => 'بدء المراجعة')
      }

      if (props.role === UserRole.SUPPORT_COMMITTEE) {
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
          onClick: (e: Event) => { e.stopPropagation(); router.push(`/requests/${request.id}`) },
        }, () => label)
      }

      if (props.role === UserRole.SWIFT_OFFICER) {
        const canUpload = request.status === RequestStatus.WAITING_FOR_SWIFT
        return h(Button, {
          variant: canUpload ? 'default' : 'outline',
          size: 'sm',
          class: canUpload ? 'h-8 text-xs bg-info text-white hover:bg-info/90' : 'h-8 text-xs',
          onClick: (e: Event) => {
            e.stopPropagation()
            if (canUpload) router.push(`/requests/${request.id}/swift`)
            else router.push(`/requests/${request.id}`)
          },
        }, () => (canUpload ? 'رفع' : 'عرض'))
      }

      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, {
            default: () =>
              h(Button, {
                variant: 'ghost',
                size: 'icon',
                class: 'h-8 w-8',
                onClick: (e: Event) => e.stopPropagation(),
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
                onClick: (e: Event) => { e.stopPropagation(); router.push(`/requests/${request.id}`) },
              }, () => 'عرض'),
              ...(isEditable
                ? [h(DropdownMenuItem, {
                    onClick: (e: Event) => { e.stopPropagation(); router.push(`/requests/${request.id}/edit`) },
                  }, () => 'تعديل')]
                : []),
              h(DropdownMenuSeparator, {}),
              h(DropdownMenuItem, {
                onClick: (e: Event) => { e.stopPropagation(); router.push(`/requests/${request.id}/print`) },
              }, () => 'طباعة'),
            ],
          }),
        ],
      })
    },
  },
]

const table = useVueTable({
  get data() { return props.data },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: updater =>
    sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater,
  onColumnFiltersChange: updater =>
    columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater,
  onColumnVisibilityChange: (updater) => {
    const next = typeof updater === 'function' ? updater(props.columnVisibility ?? {}) : updater
    emit('update:columnVisibility', next)
  },
  onRowSelectionChange: updater =>
    rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater,
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return props.columnVisibility ?? {} },
    get rowSelection() { return rowSelection.value },
  },
})

function supportCommitteeRowClass(request: ImportRequest): string {
  if (props.role !== UserRole.SUPPORT_COMMITTEE) return 'hover:bg-muted/30'
  const mine = request.is_claimed_by_me || (currentUserId.value != null && request.claimed_by?.id === currentUserId.value)
  if (mine) return 'bg-[var(--voting)]/8 hover:bg-[var(--voting)]/12'
  if (request.claimed_by) return 'bg-muted/40 hover:bg-muted/60'
  return 'hover:bg-muted/30'
}
</script>

<template>
  <div class="relative flex flex-col gap-4">
    <!-- Table (hidden when empty and not loading) -->
    <div v-if="loading || table.getRowModel().rows.length > 0" class="rounded-lg border overflow-x-auto">
      <Table class="min-w-max w-full">
        <TableHeader class="bg-muted sticky top-0 z-30">
          <TableRow
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
            class="hover:bg-transparent"
          >
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              :col-span="header.colSpan"
              class="h-10 text-sm font-medium text-foreground"
              :class="header.column.id === 'actions'
                ? 'sticky end-0 z-20 bg-muted w-12 px-2'
                : 'px-4'"
            >
              <FlexRender
                v-if="!header.isPlaceholder"
                :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <!-- Loading skeletons -->
          <template v-if="loading">
            <TableRow v-for="i in 10" :key="i">
              <TableCell class="px-4 py-3">
                <Skeleton class="size-4 rounded-sm" />
              </TableCell>
              <TableCell class="px-4 py-3">
                <div class="flex flex-col gap-1.5">
                  <Skeleton class="h-4 w-40" />
                  <Skeleton class="h-3 w-28" />
                </div>
              </TableCell>
              <TableCell class="px-4 py-3">
                <div class="flex flex-col gap-1.5">
                  <Skeleton class="h-4 w-48" />
                  <Skeleton class="h-3 w-32" />
                </div>
              </TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-6 w-24 rounded-md" /></TableCell>
              <TableCell class="px-2 py-3 w-12"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
            </TableRow>
          </template>

          <!-- Data rows -->
          <template v-else>
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              class="group/row transition-colors hover:bg-muted/30"
              :class="supportCommitteeRowClass(row.original)"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="py-3 align-middle"
                :class="cell.column.id === 'actions'
                  ? 'sticky end-0 z-10 bg-background w-12 px-2 group-hover/row:bg-muted/30'
                  : 'px-4'"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
        </TableBody>
      </Table>
    </div>

    <!-- Empty state (outside table) -->
    <Empty
      v-if="!loading && !table.getRowModel().rows.length"
      class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
    >
      <EmptyHeader>
        <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
          <SearchX class="size-5" />
        </div>
        <EmptyTitle>{{ noData ? 'لا توجد طلبات بعد' : 'لا توجد طلبات مطابقة' }}</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <EmptyDescription>
          {{ noData ? 'لم يتم تقديم أي طلبات حتى الآن.' : 'جرّب تغيير البحث أو الفلاتر لعرض الطلبات المتاحة.' }}
        </EmptyDescription>
      </EmptyContent>
    </Empty>

    <!-- Pagination footer -->
    <div class="flex items-center justify-between px-2">
      <p class="text-muted-foreground text-sm">
        {{ table.getFilteredSelectedRowModel().rows.length }} من
        {{ table.getFilteredRowModel().rows.length }} طلب محدد
      </p>

      <div class="flex items-center gap-6">
        <div class="hidden items-center gap-2 lg:flex">
          <Label for="rows-per-page" class="text-sm font-medium whitespace-nowrap">الصفوف لكل صفحة</Label>
          <Select
            :model-value="`${table.getState().pagination.pageSize}`"
            @update:model-value="(v) => table.setPageSize(Number(v))"
          >
            <SelectTrigger id="rows-per-page" size="sm" class="w-16">
              <SelectValue />
            </SelectTrigger>
            <SelectContent side="top">
              <SelectItem v-for="size in ['10', '20', '30', '40', '50']" :key="size" :value="size">
                {{ size }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <p class="text-sm font-medium whitespace-nowrap">
          صفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
        </p>

        <div class="flex items-center gap-1">
          <Button
            variant="outline"
            size="icon"
            class="hidden h-8 w-8 lg:flex"
            :disabled="!table.getCanPreviousPage()"
            @click="table.setPageIndex(0)"
          >
            <span class="sr-only">الصفحة الأولى</span>
            <ChevronsRight class="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="h-8 w-8"
            :disabled="!table.getCanPreviousPage()"
            @click="table.previousPage()"
          >
            <span class="sr-only">الصفحة السابقة</span>
            <ChevronRight class="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="h-8 w-8"
            :disabled="!table.getCanNextPage()"
            @click="table.nextPage()"
          >
            <span class="sr-only">الصفحة التالية</span>
            <ChevronLeft class="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="hidden h-8 w-8 lg:flex"
            :disabled="!table.getCanNextPage()"
            @click="table.setPageIndex(table.getPageCount() - 1)"
          >
            <span class="sr-only">الصفحة الأخيرة</span>
            <ChevronsLeft class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
