<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, VisibilityState } from '@tanstack/vue-table'
import {
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { h } from 'vue'
import type { Component } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  CheckCheck,
  CheckCircle2,
  FileText,
  Inbox,
  MoreHorizontal,
  SearchX,
  Undo2,
  Vote,
  XCircle,
} from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import {
  DataTable,
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableToolbar,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useNotifications } from '@/composables/useNotifications'
import type { Notification } from '@/types/models'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/notifications'],
})

type Severity = 'critical' | 'warning' | 'success' | 'voting' | 'info'

const notificationsStore = useNotificationsStore()
const { fetchNotifications, markRead, markAllRead } = useNotifications()

const query = ref('')
const loading = ref(false)
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref<Record<string, boolean>>({})

onMounted(async () => {
  loading.value = true
  await fetchNotifications()
  loading.value = false
})

function severityFor(notification: Notification): Severity {
  const text = notification.data?.message?.toLowerCase() ?? ''
  if (text.includes('رفض') || text.includes('تنبيه')) return 'critical'
  if (text.includes('إعادة') || text.includes('معاد') || text.includes('نقص')) return 'warning'
  if (text.includes('اعتماد') || text.includes('صدر') || text.includes('مكتمل')) return 'success'
  if (text.includes('تصويت') || text.includes('اللجنة')) return 'voting'
  return 'info'
}

const SEVERITY_STYLES: Record<Severity, {
  icon: Component
  iconWrap: string
  dotClass: string
  label: string
}> = {
  critical: { icon: XCircle, iconWrap: 'text-rose-50 bg-rose-600 ring-2 ring-rose-200', dotClass: 'bg-rose-600', label: 'عاجل' },
  warning: { icon: FileText, iconWrap: 'text-[var(--color-text-warning)] bg-[var(--color-surface-warning)] ring-2 ring-[var(--color-border-warning)]', dotClass: 'bg-[var(--color-surface-warning)]0', label: 'مهم' },
  success: { icon: CheckCircle2, iconWrap: 'text-emerald-50 bg-emerald-600 ring-2 ring-emerald-200', dotClass: 'bg-emerald-600', label: 'إنجاز' },
  voting: { icon: Vote, iconWrap: 'text-violet-50 bg-violet-600 ring-2 ring-violet-200', dotClass: 'bg-violet-600', label: 'تصويت' },
  info: { icon: Bell, iconWrap: 'text-sky-50 bg-sky-600 ring-2 ring-sky-200', dotClass: 'bg-sky-500', label: 'إشعار' },
}

const SEVERITY_FILTER_OPTIONS = [
  { label: 'عاجل', value: 'critical' },
  { label: 'مهم', value: 'warning' },
  { label: 'إنجاز', value: 'success' },
  { label: 'تصويت', value: 'voting' },
  { label: 'إشعار', value: 'info' },
]

const READ_STATUS_OPTIONS = [
  { label: 'غير مقروء', value: 'unread' },
  { label: 'مقروء', value: 'read' },
]

const notifications = computed(() => notificationsStore.items)
const unreadCount = computed(() => notificationsStore.unreadCount)
const readCount = computed(() => notifications.value.length - unreadCount.value)

const hasActiveFilters = computed(() =>
  columnFilters.value.length > 0 || query.value.trim().length > 0,
)
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearBulkSelection() {
  table.resetRowSelection()
}

function handleReset() {
  query.value = ''
  table.resetColumnFilters()
}

async function openNotification(notification: Notification) {
  if (!notification.read_at) await markRead(notification.id)
  if (notification.data?.type === 'claim_released' && notification.data?.request_id) {
    navigateTo(`/requests/${notification.data.request_id}`)
  }
}

async function handleMarkAllRead() {
  await markAllRead()
  notificationsStore.markAllRead()
}

const NOTIF_COLUMN_LABELS: Record<string, string> = {
  severity: 'النوع',
  message: 'الرسالة',
  created_at: 'التاريخ',
  read_status: 'حالة القراءة',
}

const columns: ColumnDef<Notification>[] = [
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
          'aria-label': 'تحديد الإشعار',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'severity',
    header: 'النوع',
    accessorFn: (row) => severityFor(row),
    filterFn: (row, _id, value: string[]) => value.includes(severityFor(row.original)),
    cell: ({ row }) => {
      const sev = severityFor(row.original)
      const style = SEVERITY_STYLES[sev]
      return h('div', { class: 'flex items-center gap-2' }, [
        h('div', { class: `grid h-8 w-8 shrink-0 place-items-center rounded-full ${style.iconWrap}` }, [
          h(style.icon, { class: 'h-4 w-4' }),
        ]),
        h(Badge, { variant: 'secondary', class: `text-xs text-white ${style.dotClass}` }, () => style.label),
      ])
    },
  },
  {
    id: 'message',
    accessorFn: (row) => row.data?.message ?? '',
    header: 'الرسالة',
    cell: ({ row }) => {
      const n = row.original
      return h('div', { class: 'flex flex-col gap-0.5' }, [
        h('span', {
          class: `text-sm font-medium ${!n.read_at ? 'text-foreground' : 'text-muted-foreground'}`,
        }, n.data?.message ?? 'إشعار'),
        n.data?.reference_number
          ? h('span', { class: 'font-mono text-xs text-muted-foreground' }, `طلب: ${n.data.reference_number}`)
          : null,
      ])
    },
  },
  {
    accessorKey: 'created_at',
    header: 'التاريخ',
    cell: ({ row }) =>
      h('span', { class: 'text-sm tabular-nums text-muted-foreground' },
        new Date(row.original.created_at).toLocaleString('ar-EG'),
      ),
  },
  {
    id: 'read_status',
    header: 'حالة القراءة',
    accessorFn: (row) => row.read_at ? 'read' : 'unread',
    filterFn: (row, _id, value: string[]) => value.includes(row.original.read_at ? 'read' : 'unread'),
    cell: ({ row }) => {
      const n = row.original
      return n.read_at
        ? h(Badge, { variant: 'outline', class: 'text-xs' }, () => 'مقروء')
        : h('div', { class: 'flex items-center gap-1.5' }, [
            h('span', { class: 'inline-block h-2 w-2 rounded-full bg-primary' }),
            h(Badge, { variant: 'default', class: 'text-xs' }, () => 'غير مقروء'),
          ])
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const n = row.original
      return h('div', { class: 'flex items-center gap-1' }, [
        h(Button, {
          variant: 'ghost',
          size: 'icon',
          class: 'read-icon-btn h-7 w-7',
          'aria-label': n.read_at ? 'تعليم كغير مقروء' : 'تعليم كمقروء',
          onClick: (e: Event) => { e.stopPropagation(); markRead(n.id) },
        }, {
          default: () => [n.read_at ? h(Undo2, { class: 'h-3.5 w-3.5' }) : h(Check, { class: 'h-3.5 w-3.5' })],
        }),
        h(DropdownMenu, {}, {
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
                ...(n.data?.request_id
                  ? [h(DropdownMenuItem, {
                      onClick: (e: Event) => { e.stopPropagation(); openNotification(n) },
                    }, () => 'فتح الطلب')]
                  : []),
                h(DropdownMenuItem, {
                  onClick: (e: Event) => { e.stopPropagation(); markRead(n.id) },
                }, () => n.read_at ? 'تعليم كغير مقروء' : 'تعليم كمقروء'),
              ],
            }),
          ],
        }),
      ])
    },
  },
]

const table = useVueTable({
  get data() { return notifications.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onColumnFiltersChange: updater =>
    (columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater),
  onColumnVisibilityChange: updater =>
    (columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater),
  onRowSelectionChange: updater =>
    (rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater),
  state: {
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
  initialState: { pagination: { pageSize: 20 } },
})
</script>

<template>
  <div>
    <PageHeader
      title="مركز الإشعارات"
      :subtitle="`${unreadCount} غير مقروء من ${notifications.length} إجمالاً`"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الإشعارات' }]"
    >
      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :disabled="unreadCount === 0"
          @click="handleMarkAllRead"
        >
          <CheckCheck class="ms-1 h-4 w-4" />
          تحديد الكل كمقروء
        </Button>
      </template>
    </PageHeader>

    <!-- KPI Cards — clicking sets read_status column filter -->
    <div class="mb-6">
      <MetricGrid :columns="3">
        <MetricCard
          label="إجمالي الإشعارات"
          :value="notifications.length"
          :icon="Bell"
          :active="columnFilters.length === 0"
          @click="table.resetColumnFilters()"
        />
        <MetricCard
          label="غير مقروء"
          :value="unreadCount"
          :icon="Bell"
          tone="warning"
          :active="columnFilters.some(f => f.id === 'read_status' && Array.isArray(f.value) && f.value.includes('unread') && f.value.length === 1)"
          @click="table.getColumn('read_status')?.setFilterValue(['unread'])"
        />
        <MetricCard
          label="مقروء"
          :value="readCount"
          :icon="CheckCircle2"
          tone="success"
          :active="columnFilters.some(f => f.id === 'read_status' && Array.isArray(f.value) && f.value.includes('read') && f.value.length === 1)"
          @click="table.getColumn('read_status')?.setFilterValue(['read'])"
        />
      </MetricGrid>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="notifications"
        :columns="columns"
        :loading="loading"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        @update:column-filters="(v) => columnFilters = v"
        @update:column-visibility="(v) => columnVisibility = v"
        @update:row-selection="(v) => rowSelection = v"
        :row-class="(row) => `notification-item ${!(row as Notification).read_at ? 'bg-primary/5' : ''}`"
        @row-click="openNotification"
      >
        <template #toolbar="{ table }">
          <DataTableToolbar
            :table="table"
            search-placeholder="ابحث في الإشعارات..."
            :has-filters="hasActiveFilters"
            :selected-count="selectedCount"
            @update:search="v => query = v"
            @reset="handleReset"
            @clear-selection="clearBulkSelection"
          >
            <template #filters>
              <DataTableFacetedFilter
                v-if="table.getColumn('read_status')"
                :column="table.getColumn('read_status')!"
                title="حالة القراءة"
                :options="READ_STATUS_OPTIONS"
              />
              <DataTableFacetedFilter
                v-if="table.getColumn('severity')"
                :column="table.getColumn('severity')!"
                title="النوع"
                :options="SEVERITY_FILTER_OPTIONS"
              />
            </template>
            <template #actions>
              <DataTableViewOptions :table="table" :column-labels="NOTIF_COLUMN_LABELS" />
            </template>
          </DataTableToolbar>
        </template>
        <template #empty>
          <Empty class="min-h-[280px] rounded-xl border border-dashed bg-muted/20">
            <EmptyHeader>
              <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <SearchX v-if="hasActiveFilters" class="size-5" />
                <Inbox v-else class="size-5" />
              </div>
              <EmptyTitle>
                {{ notifications.length === 0 ? 'لا توجد إشعارات بعد' : 'لا توجد إشعارات مطابقة' }}
              </EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>
                {{ notifications.length === 0
                  ? 'ستظهر إشعاراتك هنا عند وصولها — مثل تحديثات الطلبات والتنبيهات.'
                  : 'جرّب إزالة فلتر النوع أو حالة القراءة لعرض المزيد من الإشعارات.' }}
              </EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <DataTablePagination :table="table" />
        </template>
      </DataTable>
    </div>
  </div>
</template>
