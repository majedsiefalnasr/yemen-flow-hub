<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, SortingState, VisibilityState } from '@tanstack/vue-table'
import type { Component } from 'vue'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  CheckCheck,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Download,
  FileText,
  Inbox,
  MoreHorizontal,
  Printer,
  Search,
  SearchX,
  Undo2,
  Vote,
  X,
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
import { Input } from '@/components/ui/input'
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
import {
  Tabs,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useNotifications } from '@/composables/useNotifications'
import type { Notification } from '@/types/models'

type FilterMode = 'all' | 'unread' | 'read'
type Severity = 'critical' | 'warning' | 'success' | 'voting' | 'info'

const notificationsStore = useNotificationsStore()
const { fetchNotifications, markRead, markAllRead } = useNotifications()

const filter = ref<FilterMode>('all')
const query = ref('')
const loading = ref(false)

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
  warning: { icon: FileText, iconWrap: 'text-amber-600 bg-amber-50 ring-2 ring-amber-200', dotClass: 'bg-amber-500', label: 'مهم' },
  success: { icon: CheckCircle2, iconWrap: 'text-emerald-50 bg-emerald-600 ring-2 ring-emerald-200', dotClass: 'bg-emerald-600', label: 'إنجاز' },
  voting: { icon: Vote, iconWrap: 'text-violet-50 bg-violet-600 ring-2 ring-violet-200', dotClass: 'bg-violet-600', label: 'تصويت' },
  info: { icon: Bell, iconWrap: 'text-sky-50 bg-sky-600 ring-2 ring-sky-200', dotClass: 'bg-sky-500', label: 'إشعار' },
}

const notifications = computed(() => notificationsStore.items)
const unreadCount = computed(() => notificationsStore.unreadCount)

const filtered = computed(() => notifications.value.filter((n) => {
  if (filter.value === 'unread' && n.read_at) return false
  if (filter.value === 'read' && !n.read_at) return false
  if (query.value.trim()) {
    return (n.data?.message ?? '').toLowerCase().includes(query.value.trim().toLowerCase())
  }
  return true
}))

const tabOptions = computed(() => [
  { key: 'all' as FilterMode, label: 'الكل', count: notifications.value.length },
  { key: 'unread' as FilterMode, label: 'غير مقروء', count: unreadCount.value },
  { key: 'read' as FilterMode, label: 'مقروء', count: notifications.value.length - unreadCount.value },
])

// TanStack table state
const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref<Record<string, boolean>>({})

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearSelection() {
  table.resetRowSelection()
}

async function openNotification(notification: Notification) {
  if (!notification.read_at) await markRead(notification.id)
  if (notification.data?.request_id) {
    navigateTo(`/requests/${notification.data.request_id}`)
  }
}

async function handleMarkAllRead() {
  await markAllRead()
  notificationsStore.markAllRead()
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
    header: 'الحالة',
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
      })
    },
  },
]

const table = useVueTable({
  get data() { return filtered.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: updater =>
    sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater,
  onColumnFiltersChange: updater =>
    columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater,
  onColumnVisibilityChange: updater =>
    columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater,
  onRowSelectionChange: updater =>
    rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater,
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
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

    <Tabs v-model="filter" class="flex w-full flex-col gap-4">
      <!-- Row 1: tab filters (left) + page actions (right) -->
      <div class="flex items-center justify-between gap-4">
        <TabsList class="h-auto gap-1 rounded-full bg-muted p-1">
          <TabsTrigger
            v-for="tab in tabOptions"
            :key="tab.key"
            :value="tab.key"
            class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm"
          >
            {{ tab.label }}
            <Badge
              variant="secondary"
              class="h-5 min-w-5 rounded-full px-1 text-xs"
            >
              {{ tab.count }}
            </Badge>
          </TabsTrigger>
        </TabsList>
      </div>

      <!-- Row 2: bulk toolbar (when selected) OR search (default) -->
      <div v-if="selectedCount > 0" class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
        <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
        <div class="mx-2 h-4 w-px bg-border" />
        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
          <Download class="h-3.5 w-3.5" />
          تصدير
        </Button>
        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
          <Printer class="h-3.5 w-3.5" />
          طباعة
        </Button>
        <Button
          variant="ghost"
          size="sm"
          class="ms-auto h-7 gap-1 text-xs text-muted-foreground"
          @click="clearSelection"
        >
          <X class="h-3.5 w-3.5" />
          إلغاء التحديد
        </Button>
      </div>

      <div v-else class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="query"
            placeholder="ابحث في الإشعارات..."
            class="h-8 rounded-md pe-9 text-sm"
          />
        </div>
      </div>

      <!-- Table (hidden when empty and not loading) -->
      <div v-if="loading || table.getRowModel().rows.length > 0" class="overflow-hidden rounded-lg border">
        <Table>
          <TableHeader class="bg-muted sticky top-0 z-10">
            <TableRow
              v-for="headerGroup in table.getHeaderGroups()"
              :key="headerGroup.id"
              class="hover:bg-transparent"
            >
              <TableHead
                v-for="header in headerGroup.headers"
                :key="header.id"
                :col-span="header.colSpan"
                class="h-10 px-4 text-sm font-medium text-foreground"
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
              <TableRow v-for="i in 8" :key="i">
                <TableCell class="px-4 py-3">
                  <Skeleton class="size-4 rounded-sm" />
                </TableCell>
                <TableCell class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <Skeleton class="h-8 w-8 rounded-full" />
                    <Skeleton class="h-5 w-14 rounded-md" />
                  </div>
                </TableCell>
                <TableCell class="px-4 py-3">
                  <div class="flex flex-col gap-1.5">
                    <Skeleton class="h-4 w-64" />
                    <Skeleton class="h-3 w-28" />
                  </div>
                </TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-4 w-32" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-5 w-20 rounded-md" /></TableCell>
                <TableCell class="px-4 py-3"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
              </TableRow>
            </template>

            <!-- Data rows -->
            <template v-else>
              <TableRow
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                class="cursor-pointer transition-colors hover:bg-muted/30"
                :class="{ 'bg-primary/5': !row.original.read_at }"
                :data-state="row.getIsSelected() ? 'selected' : undefined"
                @click="openNotification(row.original)"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  class="px-4 py-3 align-middle"
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
            <Inbox class="size-5" />
          </div>
          <EmptyTitle>
            {{ notifications.length === 0 ? 'لا توجد إشعارات بعد' : 'لا توجد إشعارات مطابقة' }}
          </EmptyTitle>
        </EmptyHeader>
        <EmptyContent>
          <EmptyDescription>
            {{ notifications.length === 0 ? 'ستظهر الإشعارات هنا عند وصولها.' : 'جرّب تغيير الفلتر أو البحث.' }}
          </EmptyDescription>
        </EmptyContent>
      </Empty>

      <!-- Pagination footer -->
      <div class="flex items-center justify-between px-2">
        <p class="text-muted-foreground text-sm">
          {{ table.getFilteredSelectedRowModel().rows.length }} من
          {{ table.getFilteredRowModel().rows.length }} إشعار محدد
        </p>

        <div class="flex items-center gap-6">
          <div class="hidden items-center gap-2 lg:flex">
            <Label for="rows-per-page-notif" class="text-sm font-medium whitespace-nowrap">الصفوف لكل صفحة</Label>
            <Select
              :model-value="`${table.getState().pagination.pageSize}`"
              @update:model-value="(v) => table.setPageSize(Number(v))"
            >
              <SelectTrigger id="rows-per-page-notif" size="sm" class="w-16">
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
    </Tabs>
  </div>
</template>
