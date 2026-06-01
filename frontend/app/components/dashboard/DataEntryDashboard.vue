<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted } from 'vue'
import { h } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, FileText, PlusCircle, Zap, Bell, AlertCircle } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard.store'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import type { DataEntryDashboardStats } from '@/composables/useDashboard'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import ActionRequiredStrip from '@/components/shared/ActionRequiredStrip.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'

const router = useRouter()
const store = useDashboardStore()
const notificationsStore = useNotificationsStore()
const authStore = useAuthStore()

const authUser = computed(() => authStore.user)

const stats = computed(() => store.stats as DataEntryDashboardStats | null)
const unreadCount = computed(() => notificationsStore.unreadCount)

const hasAnyRequests = computed(() =>
  stats.value !== null && (
    (stats.value.completed ?? 0) > 0
    || (stats.value.under_cby_processing ?? 0) > 0
    || returnedCount.value > 0
    || (stats.value.draft ?? 0) > 0
    || (stats.value.recent_requests?.length ?? 0) > 0
    || (stats.value.draft_requests?.length ?? 0) > 0
  ),
)

// Prefer the backend's aggregate `returned` count when present; only fall back
// to `returned_requests.length` (a sample slice, max 5) when the aggregate is
// unset. Adding the two double-counted the same items in the action strip.
const returnedCount = computed(() =>
  stats.value?.returned ?? stats.value?.returned_requests?.length ?? 0,
)

const actionRequiredCount = computed(() => returnedCount.value)

// First returned request — used for the correction strip reference + reason snippet
const firstReturnedRequest = computed(() => stats.value?.returned_requests?.[0] ?? null)
const returnReasonSnippet = computed(() => {
  const req = firstReturnedRequest.value
  if (!req) return ''
  // bank_return_comment for BANK_RETURNED; support_return_comment for SUPPORT_RETURNED
  const reason = req.bank_return_comment ?? req.support_return_comment ?? req.notes ?? ''
  return reason.length > 80 ? reason.slice(0, 80) + '…' : reason
})

const actionStripDetail = computed(() => {
  if (!firstReturnedRequest.value) return undefined
  const ref = firstReturnedRequest.value.reference_number
  return returnReasonSnippet.value ? `${ref} · ${returnReasonSnippet.value}` : ref
})

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

type DraftRow = NonNullable<DataEntryDashboardStats['draft_requests']>[number]
type RecentRow = NonNullable<DataEntryDashboardStats['recent_requests']>[number]

const draftColumns: ColumnDef<DraftRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) => h('a', {
      class: 'font-mono text-primary hover:underline',
      href: `/requests/${row.original.id}/edit`,
      onClick: (event: MouseEvent) => {
        event.preventDefault()
        event.stopPropagation()
        router.push(`/requests/${row.original.id}/edit`)
      },
    }, row.original.reference_number),
  },
  { accessorKey: 'supplier_name', header: 'التاجر' },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) => h('span', { class: 'direction-ltr font-tabular-nums' }, formatAmount(row.original.amount, row.original.currency)),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) => h(Button, {
      size: 'sm',
      onClick: (event: MouseEvent) => {
        event.stopPropagation()
        router.push(`/requests/${row.original.id}/edit`)
      },
    }, () => 'متابعة'),
  },
]

const recentColumns: ColumnDef<RecentRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) => h('a', {
      class: 'font-mono text-primary hover:underline',
      href: `/requests/${row.original.id}`,
      onClick: (event: MouseEvent) => {
        event.preventDefault()
        event.stopPropagation()
        router.push(`/requests/${row.original.id}`)
      },
    }, row.original.reference_number),
  },
  { accessorKey: 'supplier_name', header: 'التاجر' },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) => h('span', { class: 'direction-ltr font-tabular-nums' }, formatAmount(row.original.amount, row.original.currency)),
  },
  { id: 'status', header: 'الحالة', cell: ({ row }) => h(StatusBadge, { status: row.original.status, role: UserRole.DATA_ENTRY }) },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) => h(Button, {
      size: 'sm',
      variant: 'outline',
      onClick: (event: MouseEvent) => {
        event.stopPropagation()
        router.push(`/requests/${row.original.id}`)
      },
    }, () => 'عرض'),
  },
]

const kpiConfig = computed(() => [
  {
    icon: CheckCircle2,
    value: stats.value?.completed ?? 0,
    label: 'مكتمل',
    variant: 'green',
    tab: 'completed',
  },
  {
    icon: Clock,
    value: stats.value?.under_cby_processing ?? 0,
    label: 'قيد معالجة CBY',
    variant: 'blue',
    tab: 'processing',
  },
  {
    icon: RotateCcw,
    value: returnedCount.value,
    label: 'بحاجة تعديل',
    variant: returnedCount.value > 0 ? 'amber' : 'gray',
    tab: 'returned',
  },
  {
    icon: FileText,
    value: stats.value?.draft ?? 0,
    label: 'مسودات',
    variant: 'gray',
    tab: 'draft',
  },
])

onMounted(() => {
  store.loadStats()
  notificationsStore.refreshUnreadCount()
})
</script>

<template>
  <div class="flex flex-col gap-6" >

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="h-3.5 w-[60px] mb-3" />
        <Skeleton class="h-8 w-[40px]" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-destructive bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="text-[var(--severity-red)] flex-1">{{ store.error }}</span>
        <Button variant="outline" size="sm" class="text-[var(--severity-red)] border-destructive" @click="store.loadStats()">
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <!-- Empty state — no requests at all: hide KPI grid entirely -->
    <template v-else-if="stats && !hasAnyRequests">
      <div class="flex flex-col items-center justify-center py-20 gap-4 text-center">
        <FileText class="h-12 w-12 text-muted-foreground" aria-hidden="true" />
        <p class="text-sm text-muted-foreground">لم تبدأ بعد. ابدأ بأول طلب تمويل واردات.</p>
        <Button @click="router.push('/requests/new')">طلب جديد</Button>
      </div>
    </template>

    <template v-else-if="stats">

      <!-- Action-required strip (above KPI grid, hidden when count = 0) -->
      <ActionRequiredStrip
        :count="actionRequiredCount"
        message="طلبات تحتاج تعديل"
        cta-label="ابدأ التعديل"
        cta-route="/requests?tab=returned"
        severity="amber"
        :detail="actionStripDetail"
      />

      <!-- KPI grid: 4 clickable cards -->
      <MetricGrid :columns="4">
        <MetricCard
          v-for="kpi in kpiConfig"
          :key="kpi.label"
          :label="kpi.label"
          :value="kpi.value"
          :icon="kpi.icon"
          :tone="kpi.variant === 'amber' && kpi.value > 0 ? 'warning' : kpi.variant === 'green' ? 'success' : 'default'"
          :highlighted="kpi.variant === 'amber' && kpi.value > 0"
          @click="router.push(`/requests?tab=${kpi.tab}`)"
        />
      </MetricGrid>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-3 max-md:grid-cols-1 gap-3">
          <!-- إنشاء طلب جديد -->
          <NuxtLink
            to="/requests/new"
            class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground border-0 rounded-2xl hover:opacity-90 transition-opacity focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            aria-label="إنشاء طلب جديد"
          >
            <FileText class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">إنشاء طلب جديد</span>
            <span class="text-xs opacity-75">لبدء طلب تمويل جديد</span>
          </NuxtLink>

          <!-- متابعة طلباتي -->
          <NuxtLink
            to="/requests"
            class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            aria-label="متابعة طلباتي"
          >
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">متابعة طلباتي</span>
            <span class="text-xs text-muted-foreground">استعراض جميع طلباتك المُقدَّمة</span>
          </NuxtLink>

          <!-- الإشعارات — with unread badge -->
          <NuxtLink
            to="/notifications"
            class="relative flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            :aria-label="unreadCount > 0 ? `الإشعارات — ${unreadCount} غير مقروء` : 'الإشعارات'"
          >
            <div class="relative mb-1">
              <Bell class="h-5 w-5 flex-shrink-0 text-primary" aria-hidden="true" />
              <span
                v-if="unreadCount > 0"
                class="absolute -top-1.5 -end-1.5 min-w-4 h-4 px-0.5 bg-destructive text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
                aria-hidden="true"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </div>
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر التحديثات على طلباتك</span>
          </NuxtLink>
        </div>
      </section>

      <!-- Two-column: مسوداتي | آخر نشاطي -->
      <div class="grid grid-cols-2 max-lg:grid-cols-1 gap-4">

        <!-- مسوداتي (draft requests — hidden when empty, no placeholder) -->
        <Card v-if="stats.draft_requests?.length > 0" class="border-0 shadow" aria-labelledby="drafts-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="drafts-heading" class="text-sm font-semibold text-foreground">مسوداتي</h2>
              <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests?tab=draft')">عرض الكل</Button>
            </div>
            <DataTable
              :data="stats.draft_requests.slice(0, 5)"
              :columns="draftColumns"
              @row-click="(row) => router.push(`/requests/${row.id}/edit`)"
            />
          </CardContent>
        </Card>

        <!-- آخر نشاطي (recent requests) -->
        <Card
          class="border-0 shadow"
          :class="{ 'col-span-2 max-lg:col-span-1': !stats.draft_requests?.length }"
          aria-labelledby="recent-heading"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="recent-heading" class="text-sm font-semibold text-foreground">آخر نشاطي</h2>
              <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests')">عرض الكل</Button>
            </div>
            <DataTable
              :data="stats.recent_requests.slice(0, 5)"
              :columns="recentColumns"
              @row-click="(row) => router.push(`/requests/${row.id}`)"
            >
              <template #empty>لا توجد طلبات بعد</template>
            </DataTable>
          </CardContent>
        </Card>

      </div>

    </template>
  </div>
</template>
