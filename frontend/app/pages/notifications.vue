<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, VisibilityState } from '@tanstack/vue-table'
import { h } from 'vue'
import type { Component } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Archive,
  Bell,
  Check,
  CheckCheck,
  CheckCircle2,
  ExternalLink,
  FileText,
  Inbox,
  MoreHorizontal,
  SearchX,
  Vote,
  XCircle,
} from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useNotifications } from '@/composables/useNotifications'
import type { Notification } from '@/types/models'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/notifications'],
})

type Severity = 'critical' | 'warning' | 'success' | 'voting' | 'info'
type NotificationTableRow = { original: Notification }

const notificationsStore = useNotificationsStore()
const {
  fetchNotifications,
  notifications: fetchedNotifications,
  error: notificationsError,
  markRead,
  markAllRead,
} = useNotifications()

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const dataTableRef = ref<{ table: any } | null>(null)
const query = ref('')
const loading = ref(false)
const loadError = ref<string | null>(null)
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref<Record<string, boolean>>({})
const selectedNotification = ref<Notification | null>(null)
const notificationDialogOpen = ref(false)

async function loadNotifications() {
  loading.value = true
  loadError.value = null
  try {
    await fetchNotifications()
    if (notificationsError.value) {
      loadError.value = notificationsError.value
      return
    }
    notificationsStore.setItems(fetchedNotifications.value)
  }
  catch {
    loadError.value = 'تعذّر تحميل الإشعارات. تحقق من الاتصال وأعد المحاولة.'
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  loadNotifications()
})

function severityFor(notification: Notification): Severity {
  if (notification.data?.type === 'request_rejected') return 'critical'
  if (notification.data?.type === 'request_returned' || notification.data?.type === 'claim_released') return 'warning'
  if (notification.data?.type === 'request_approved' || notification.data?.type === 'customs_issued') return 'success'
  if (notification.data?.type === 'voting_opened') return 'voting'
  if (notification.data?.type === 'swift_upload_requested') return 'info'

  const text = (notification.data?.message_ar ?? notification.data?.message ?? '').toLowerCase()
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
  critical: { icon: XCircle, iconWrap: 'bg-[var(--severity-red)]/12 text-[var(--severity-red)] ring-1 ring-[var(--severity-red)]/25', dotClass: 'bg-[var(--severity-red)] text-primary-foreground', label: 'عاجل' },
  warning: { icon: FileText, iconWrap: 'bg-[var(--severity-amber)]/12 text-[var(--severity-amber)] ring-1 ring-[var(--severity-amber)]/25', dotClass: 'bg-[var(--severity-amber)] text-foreground', label: 'مهم' },
  success: { icon: CheckCircle2, iconWrap: 'bg-[var(--severity-green)]/12 text-[var(--success)] ring-1 ring-[var(--severity-green)]/25', dotClass: 'bg-[var(--severity-green)] text-foreground', label: 'إنجاز' },
  voting: { icon: Vote, iconWrap: 'bg-[var(--voting)]/12 text-[var(--voting)] ring-1 ring-[var(--voting)]/25', dotClass: 'bg-[var(--voting)] text-primary-foreground', label: 'تصويت' },
  info: { icon: Bell, iconWrap: 'bg-[var(--info)]/12 text-[var(--info)] ring-1 ring-[var(--info)]/25', dotClass: 'bg-[var(--info)] text-foreground', label: 'إشعار' },
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
const selectedNotificationSeverity = computed(() =>
  selectedNotification.value ? severityFor(selectedNotification.value) : 'info',
)
const selectedNotificationStyle = computed(() => SEVERITY_STYLES[selectedNotificationSeverity.value])
const selectedNotificationMessage = computed(() =>
  selectedNotification.value
    ? notificationMessage(selectedNotification.value)
    : '',
)
const selectedNotificationReference = computed(() =>
  selectedNotification.value
    ? notificationReference(selectedNotification.value)
    : null,
)
const selectedNotificationSummary = computed(() =>
  selectedNotification.value
    ? notificationSummary(selectedNotification.value)
    : '',
)

const hasActiveFilters = computed(() =>
  columnFilters.value.length > 0 || query.value.trim().length > 0,
)
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearBulkSelection() {
  dataTableRef.value?.table.resetRowSelection()
}

async function markSelectedRead() {
  const rows = (dataTableRef.value?.table.getSelectedRowModel().rows ?? []) as NotificationTableRow[]
  const unread = rows.map(row => row.original).filter(notification => !notification.read_at)
  await Promise.all(unread.map(async (n) => {
    const ok = await markRead(n.id)
    if (ok) markNotificationReadLocally(n)
  }))
  clearBulkSelection()
}

function markSelectedUnread() {
  const rows = (dataTableRef.value?.table.getSelectedRowModel().rows ?? []) as NotificationTableRow[]
  const read = rows.map(row => row.original).filter(notification => notification.read_at)
  read.forEach((n) => {
    n.read_at = null as unknown as string
    const storeItem = notificationsStore.items.find(item => item.id === n.id)
    if (storeItem) {
      storeItem.read_at = null as unknown as string
      notificationsStore.incrementUnread()
    }
  })
  clearBulkSelection()
}

function archiveSelected() {
  const rows = (dataTableRef.value?.table.getSelectedRowModel().rows ?? []) as NotificationTableRow[]
  const ids = new Set(rows.map(row => row.original.id))
  notificationsStore.removeItems(ids)
  clearBulkSelection()
}

function handleReset() {
  query.value = ''
  columnFilters.value = []
}

function notificationMessage(notification: Notification): string {
  return notification.data?.message_ar ?? notification.data?.message ?? 'إشعار'
}

function notificationReference(notification: Notification): string | null {
  return notification.data?.request_reference ?? notification.data?.reference_number ?? null
}

function notificationActionLabel(notification: Notification): string {
  switch (notification.data?.type) {
    case 'claim_released':
      return 'فتح الطلب ومراجعة المطالبة'
    case 'voting_opened':
      return 'فتح الطلب للتصويت'
    case 'swift_upload_requested':
      return 'فتح الطلب لرفع وثائق السويفت'
    case 'request_returned':
      return 'فتح الطلب لمعالجة الإعادة'
    case 'request_rejected':
      return 'فتح الطلب ومراجعة سبب الرفض'
    case 'request_approved':
    case 'customs_issued':
      return 'فتح الطلب المرتبط'
    default:
      return 'فتح الطلب'
  }
}

function notificationSummary(notification: Notification): string {
  switch (notification.data?.type) {
    case 'claim_released':
      return notification.data.reason === 'ttl_expired'
        ? 'تم تحرير مطالبة الدعم بسبب انتهاء مهلة النشاط. راجع الطلب إذا كنت بحاجة إلى استلامه مرة أخرى.'
        : `تم تحرير مطالبة الدعم${notification.data.released_by_name ? ` بواسطة ${notification.data.released_by_name}` : ''}. راجع الطلب قبل اتخاذ أي إجراء جديد.`
    case 'voting_opened':
      return 'تم فتح جلسة تصويت مرتبطة بهذا الطلب. افتح الطلب للاطلاع على التفاصيل واتخاذ إجراء التصويت إذا كان دورك يسمح بذلك.'
    case 'swift_upload_requested':
      return 'وصل الطلب إلى مرحلة وثائق السويفت. افتح الطلب أو صفحة السويفت لمراجعة المستندات المطلوبة.'
    case 'request_returned':
      return 'تمت إعادة الطلب للتصحيح. افتح الطلب لمعرفة المطلوب واستكمال المعالجة.'
    case 'request_rejected':
      return 'تم رفض الطلب. افتح الطلب لمراجعة السبب وسجل المراحل.'
    case 'request_approved':
      return 'تم اعتماد الطلب في إحدى مراحل سير العمل. افتح الطلب لمتابعة المرحلة التالية.'
    case 'customs_issued':
      return 'تم إصدار تأكيد المصارفة الخارجية أو استكمال المرحلة النهائية المرتبطة بالطلب.'
    case 'request_submitted':
      return 'تم تقديم طلب جديد وأصبح ضمن مسار المراجعة المناسب لدورك.'
    default:
      return 'راجع تفاصيل الإشعار والطلب المرتبط إذا كان متاحاً.'
  }
}

function markNotificationReadLocally(notification: Notification) {
  if (notification.read_at) return
  const now = new Date().toISOString()
  notification.read_at = now
  const storeItem = notificationsStore.items.find(item => item.id === notification.id)
  if (storeItem && !storeItem.read_at) storeItem.read_at = now
  notificationsStore.decrementUnread()
}

async function openNotification(notification: Notification) {
  selectedNotification.value = notification
  notificationDialogOpen.value = true

  if (!notification.read_at) {
    const ok = await markRead(notification.id)
    if (ok) markNotificationReadLocally(notification)
  }
}

function openLinkedRequest(notification = selectedNotification.value) {
  if (!notification?.data?.request_id) return
  navigateTo(`/requests/${notification.data.request_id}`)
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
        h(Badge, { variant: 'secondary', class: `text-xs ${style.dotClass}` }, () => style.label),
      ])
    },
  },
  {
    id: 'message',
    accessorFn: (row) => row.data?.message ?? '',
    header: 'الرسالة',
    cell: ({ row }) => {
      const n = row.original
      const msg = n.data?.message_ar ?? n.data?.message ?? 'إشعار'
      const ref = n.data?.request_reference ?? n.data?.reference_number ?? null
      return h('div', { class: 'flex flex-col gap-0.5' }, [
        h('span', {
          class: `text-sm font-medium ${!n.read_at ? 'text-foreground' : 'text-muted-foreground'}`,
        }, msg),
        ref
          ? h('span', { class: 'font-mono text-xs text-muted-foreground' }, `طلب: ${ref}`)
          : h('span', { class: 'text-xs text-muted-foreground' }, notificationSummary(n)),
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
          'aria-label': n.read_at ? 'الإشعار مقروء' : 'تعليم كمقروء',
          disabled: !!n.read_at,
          onClick: async (e: Event) => {
            e.stopPropagation()
            if (n.read_at) return
            const ok = await markRead(n.id)
            if (ok) markNotificationReadLocally(n)
          },
        }, {
          default: () => [h(Check, { class: 'h-3.5 w-3.5' })],
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
                h(DropdownMenuItem, {
                  onClick: (e: Event) => { e.stopPropagation(); openNotification(n) },
                }, () => 'عرض ملخص الإشعار'),
                ...(n.data?.request_id
                  ? [h(DropdownMenuItem, {
                      onClick: (e: Event) => { e.stopPropagation(); openLinkedRequest(n) },
                    }, () => notificationActionLabel(n))]
                  : []),
                ...(!n.read_at
                  ? [h(DropdownMenuItem, {
                      onClick: async (e: Event) => {
                        e.stopPropagation()
                        const ok = await markRead(n.id)
                        if (ok) markNotificationReadLocally(n)
                      },
                    }, () => 'تعليم كمقروء')]
                  : []),
              ],
            }),
          ],
        }),
      ])
    },
  },
]

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

    <LoadErrorAlert
      v-if="loadError"
      class="mb-4"
      :message="loadError"
      title="تعذّر تحميل الإشعارات"
      @retry="loadNotifications()"
    />

    <!-- KPI Cards — clicking sets read_status column filter -->
    <div class="mb-6">
      <MetricGrid :columns="3">
        <MetricCard
          label="إجمالي الإشعارات"
          :value="notifications.length"
          :icon="Bell"
          :active="columnFilters.length === 0"
          @click="columnFilters = []"
        />
        <MetricCard
          label="غير مقروء"
          :value="unreadCount"
          :icon="Bell"
          tone="warning"
          :active="columnFilters.some(f => f.id === 'read_status' && Array.isArray(f.value) && f.value.includes('unread') && f.value.length === 1)"
          @click="columnFilters = [{ id: 'read_status', value: ['unread'] }]"
        />
        <MetricCard
          label="مقروء"
          :value="readCount"
          :icon="CheckCircle2"
          tone="success"
          :active="columnFilters.some(f => f.id === 'read_status' && Array.isArray(f.value) && f.value.includes('read') && f.value.length === 1)"
          @click="columnFilters = [{ id: 'read_status', value: ['read'] }]"
        />
      </MetricGrid>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        ref="dataTableRef"
        :data="notifications"
        :columns="columns"
        :loading="loading"
        :column-filters="columnFilters"
        :column-visibility="columnVisibility"
        :row-selection="rowSelection"
        @update:column-filters="(v) => columnFilters = v"
        @update:column-visibility="(v) => columnVisibility = v"
        @update:row-selection="(v) => rowSelection = v"
        :row-class="(row) => `notification-item notification-row notification-row--${severityFor(row as Notification)} ${!(row as Notification).read_at ? 'notification-row--unread' : ''}`"
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
            <template #bulk-actions>
              <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs" @click="markSelectedRead">
                <CheckCheck class="size-3.5" />
                تعليم كمقروء
              </Button>
              <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs" @click="markSelectedUnread">
                <Bell class="size-3.5" />
                تعليم كغير مقروء
              </Button>
              <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs text-destructive hover:text-destructive" @click="archiveSelected">
                <Archive class="size-3.5" />
                أرشفة
              </Button>
            </template>
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
                  ? 'ستظهر إشعاراتك هنا عند وصولها. تابع طلباتك الآن وارجع إلى هذه الصفحة عند وصول أي تحديث.'
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

    <Dialog v-model:open="notificationDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <span :class="`grid h-8 w-8 shrink-0 place-items-center rounded-lg ${selectedNotificationStyle.iconWrap}`">
              <component :is="selectedNotificationStyle.icon" class="h-4 w-4" />
            </span>
            <span class="min-w-0 truncate">{{ selectedNotificationStyle.label }}: {{ selectedNotificationMessage }}</span>
          </DialogTitle>
          <DialogDescription>{{ selectedNotificationSummary }}</DialogDescription>
        </DialogHeader>

        <div v-if="selectedNotification" class="space-y-3 py-2 text-sm">
          <div class="flex items-center justify-between gap-4 border-b pb-2">
            <span class="text-muted-foreground">حالة القراءة</span>
            <span class="font-medium text-foreground">{{ selectedNotification.read_at ? 'مقروء' : 'غير مقروء' }}</span>
          </div>
          <div class="flex items-center justify-between gap-4 border-b pb-2">
            <span class="text-muted-foreground">وقت الوصول</span>
            <span class="tabular-nums text-foreground">{{ new Date(selectedNotification.created_at).toLocaleString('ar-EG') }}</span>
          </div>
          <div v-if="selectedNotificationReference" class="flex items-center justify-between gap-4 border-b pb-2">
            <span class="text-muted-foreground">مرجع الطلب</span>
            <span class="font-mono font-medium text-foreground">{{ selectedNotificationReference }}</span>
          </div>
          <div v-if="selectedNotification.data.reason" class="flex items-center justify-between gap-4 border-b pb-2">
            <span class="text-muted-foreground">سبب الإشعار</span>
            <span class="font-medium text-foreground">
              {{ selectedNotification.data.reason === 'ttl_expired' ? 'انتهاء مهلة النشاط' : 'تحرير يدوي' }}
            </span>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button
            v-if="selectedNotification?.data?.request_id"
            @click="openLinkedRequest()"
          >
            <ExternalLink class="ms-1 h-4 w-4" />
            {{ notificationActionLabel(selectedNotification) }}
          </Button>
          <Button variant="outline" @click="notificationDialogOpen = false">
            إغلاق
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.notification-row) {
  transition: background-color 160ms ease-out, box-shadow 160ms ease-out;
}

:deep(.notification-row--unread) {
  background:
    linear-gradient(90deg, color-mix(in oklch, var(--primary) 8%, transparent), transparent 42%),
    color-mix(in oklch, var(--muted) 22%, transparent);
}

:deep(.notification-row--critical.notification-row--unread) {
  box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--severity-red) 24%, transparent);
}

:deep(.notification-row--warning.notification-row--unread) {
  box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--severity-amber) 26%, transparent);
}

:deep(.notification-row--success.notification-row--unread) {
  box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--severity-green) 22%, transparent);
}

:deep(.notification-row--voting.notification-row--unread) {
  box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--voting) 24%, transparent);
}

:deep(.notification-row--info.notification-row--unread) {
  box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--info) 24%, transparent);
}
</style>
