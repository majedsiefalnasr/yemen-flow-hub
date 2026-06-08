// @parity-evidence: Story 12.1 — docs/user-view/bank-reviewer.md#Dashboard
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted, h } from 'vue'
import { useRouter } from 'vue-router'
import {
  CheckCircle2,
  Clock,
  RotateCcw,
  AlertCircle,
  Users,
  FileText,
  Zap,
  XCircle,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import {
  NOT_ELIGIBLE_LABEL_AR,
  NOT_ELIGIBLE_SUPPORT_LABEL,
  STATUS_LABELS,
} from '../../constants/workflow'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import ActionRequiredStrip from '../shared/ActionRequiredStrip.vue'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const currentUserId = computed(() => auth.user?.id ?? null)

const stats = computed<BankReviewerDashboardStats | null>(() => {
  const raw = store.stats as Partial<BankReviewerDashboardStats> | null
  if (!raw) return null
  return {
    pending_review: raw.pending_review ?? 0,
    at_cby: raw.at_cby ?? 0,
    returned_by_support: raw.returned_by_support ?? 0,
    approved_completed: raw.approved_completed ?? 0,
    review_queue: Array.isArray(raw.review_queue) ? raw.review_queue : [],
    downstream_queue: Array.isArray(raw.downstream_queue) ? raw.downstream_queue : [],
  }
})

const queue = computed(() => stats.value?.review_queue ?? [])
const downstreamQueue = computed(() => stats.value?.downstream_queue ?? [])
const supportRejectedCount = computed(() => stats.value?.returned_by_support ?? 0)

// Spec order: Pending Review / Not Eligible by Support / At CBY / Approved-Completed
const kpiConfig = computed(() => [
  {
    icon: Clock,
    value: stats.value?.pending_review ?? 0,
    label: 'بانتظار مراجعتي',
    variant: (stats.value?.pending_review ?? 0) > 0 ? 'amber' : 'gray',
    tab: 'pending',
  },
  {
    icon: XCircle,
    value: stats.value?.returned_by_support ?? 0,
    label: NOT_ELIGIBLE_SUPPORT_LABEL,
    variant: (stats.value?.returned_by_support ?? 0) > 0 ? 'rose' : 'gray',
    tab: 'support_rejected',
  },
  {
    icon: RotateCcw,
    value: stats.value?.at_cby ?? 0,
    label: 'قيد اللجنة الوطنية',
    variant: 'blue',
    tab: 'at_cby',
  },
  {
    icon: CheckCircle2,
    value: stats.value?.approved_completed ?? 0,
    label: 'مُعتمد / مكتمل',
    variant: 'green',
    tab: 'completed',
  },
])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

function isCreatedByCurrentUser(createdBy: number | null | undefined): boolean {
  return currentUserId.value !== null && createdBy === currentUserId.value
}

type ReviewerQueueRow = NonNullable<BankReviewerDashboardStats['review_queue']>[number]
type DownstreamQueueRow = NonNullable<BankReviewerDashboardStats['downstream_queue']>[number]

const reviewQueueColumns: ColumnDef<ReviewerQueueRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h(
        'a',
        {
          class: 'font-mono text-primary hover:underline',
          href: `/requests/${row.original.id}`,
          onClick: (event: MouseEvent) => {
            event.preventDefault()
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        row.original.reference_number,
      ),
  },
  {
    id: 'created_by',
    header: 'أنشأه',
    cell: ({ row }) =>
      isCreatedByCurrentUser(row.original.created_by)
        ? h('span', { class: 'text-[var(--severity-amber)] font-medium' }, 'أنا')
        : h('span', row.original.created_by_user?.name ?? 'غير متاح'),
  },
  { accessorKey: 'supplier_name', header: 'المورد' },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'direction-ltr font-tabular-nums' },
        formatAmount(row.original.amount, row.original.currency),
      ),
  },
  {
    id: 'status',
    header: 'الحالة',
    cell: ({ row }) =>
      h(StatusBadge, { status: row.original.status, role: UserRole.BANK_REVIEWER }),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) => {
      if (isCreatedByCurrentUser(row.original.created_by)) {
        return h(
          'span',
          {
            class:
              'inline-flex px-2 py-1 bg-muted text-muted-foreground text-xs rounded cursor-not-allowed',
            title: 'لا يمكنك مراجعة طلب أنشأته بنفسك',
            'aria-label': 'لا يمكنك مراجعة طلب أنشأته بنفسك',
            onClick: (event: MouseEvent) => event.stopPropagation(),
          },
          'غير متاح',
        )
      }
      return h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          onClick: (event: MouseEvent) => {
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        () => 'بدء المراجعة',
      )
    },
  },
]

const downstreamQueueColumns: ColumnDef<DownstreamQueueRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h(
        'a',
        {
          class: 'font-mono text-primary hover:underline',
          href: `/requests/${row.original.id}`,
          onClick: (event: MouseEvent) => {
            event.preventDefault()
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        row.original.reference_number,
      ),
  },
  {
    id: 'stage',
    header: 'المرحلة الحالية',
    cell: ({ row }) => h('span', STATUS_LABELS[row.original.status] ?? 'غير متاح'),
  },
  {
    id: 'status',
    header: 'الحالة',
    cell: ({ row }) =>
      h(StatusBadge, { status: row.original.status, role: UserRole.BANK_REVIEWER }),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          onClick: (event: MouseEvent) => {
            event.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        () => 'عرض',
      ),
  },
]

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Skeleton -->
    <div
      v-if="store.loading"
      class="grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1"
      aria-busy="true"
      aria-label="جارٍ تحميل الإحصائيات"
    >
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="mb-3 h-3.5 w-[60px]" />
        <Skeleton class="h-8 w-[40px]" />
      </div>
    </div>

    <!-- Error -->
    <Card
      v-else-if="store.error"
      class="bg-background border-0 border-[var(--severity-red)]"
      role="alert"
    >
      <CardContent class="flex items-center gap-3 pt-6">
        <AlertCircle
          class="h-4.5 w-4.5 flex-shrink-0 text-[var(--severity-red)]"
          aria-hidden="true"
        />
        <span class="flex-1 text-[var(--severity-red)]">{{ store.error }}</span>
        <Button
          variant="outline"
          size="sm"
          class="text-destructive border-destructive"
          @click="store.loadStats()"
        >
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <template v-else-if="stats">
      <!-- Action-required strip: SUPPORT_REJECTED requests waiting for bank-side decision -->
      <ActionRequiredStrip
        :count="supportRejectedCount"
        :message="`طلبات صنّفتها لجنة المساندة ${NOT_ELIGIBLE_LABEL_AR} وتنتظر قرارك`"
        cta-label="اتخاذ القرار"
        cta-route="/requests?tab=support_rejected"
        severity="red"
      />

      <!-- KPI grid (4 cards): Pending Review / Not Eligible by Support / At CBY / Approved-Completed -->
      <MetricGrid :columns="4">
        <MetricCard
          v-for="kpi in kpiConfig"
          :key="kpi.label"
          :label="kpi.label"
          :value="kpi.value"
          :icon="kpi.icon"
          :tone="
            kpi.variant === 'amber' && kpi.value > 0
              ? 'warning'
              : kpi.variant === 'rose' && kpi.value > 0
                ? 'danger'
                : kpi.variant === 'green'
                  ? 'success'
                  : 'default'
          "
          :highlighted="(kpi.variant === 'amber' || kpi.variant === 'rose') && kpi.value > 0"
          @click="router.push(`/requests?tab=${kpi.tab}`)"
        />
      </MetricGrid>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2
          id="qa-heading"
          class="text-foreground mb-3 flex items-center gap-2 text-sm font-semibold"
        >
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-2 gap-3 max-md:grid-cols-1">
          <Card
            class="bg-primary text-primary-foreground focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-2xl border-0 p-4 transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="طابور المراجعة"
            @click="router.push('/requests?tab=pending')"
            @keydown.enter="router.push('/requests?tab=pending')"
            @keydown.space.prevent="router.push('/requests?tab=pending')"
          >
            <Users class="mb-1 h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75">{{ stats.pending_review }} طلب بانتظار المراجعة</span>
          </Card>

          <Card
            class="bg-background border-border text-foreground hover:border-primary focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-2xl border p-4 transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="كل طلبات البنك"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <FileText class="text-primary mb-1 h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">كل طلبات البنك</span>
            <span class="text-muted-foreground text-xs">عرض جميع طلبات البنك</span>
          </Card>
        </div>
      </section>

      <!-- Review queue table (max 8 rows) with Created By column + segregation enforcement -->
      <section aria-labelledby="queue-heading">
        <div class="mb-4 flex items-center justify-between">
          <h2 id="queue-heading" class="text-foreground text-sm font-semibold">
            طابور المراجعة الحالي
          </h2>
          <Button
            variant="link"
            size="sm"
            class="h-auto p-0 text-xs"
            @click="router.push('/requests?tab=pending')"
            >عرض الكل</Button
          >
        </div>

        <Card v-if="queue.length === 0" class="border-0 shadow">
          <CardContent class="flex flex-col items-center gap-3 pt-16 pb-16 text-center">
            <CheckCircle2 class="text-muted-foreground h-7 w-7" aria-hidden="true" />
            <p class="text-muted-foreground m-0 text-sm">لا توجد طلبات في طابور المراجعة حالياً</p>
          </CardContent>
        </Card>

        <DataTable
          :data="queue.slice(0, 8)"
          :columns="reviewQueueColumns"
          @row-click="(row) => router.push(`/requests/${row.id}`)"
        />
      </section>

      <!-- Downstream tracking table (max 5 rows, hidden when empty) -->
      <section v-if="downstreamQueue.length > 0" aria-labelledby="downstream-heading">
        <div class="mb-4 flex items-center justify-between">
          <h2 id="downstream-heading" class="text-foreground text-sm font-semibold">
            متابعة الطلبات لدى اللجنة الوطنية
          </h2>
          <Button
            variant="link"
            size="sm"
            class="h-auto p-0 text-xs"
            @click="router.push('/requests?tab=at_cby')"
            >عرض الكل</Button
          >
        </div>
        <DataTable
          :data="downstreamQueue.slice(0, 5)"
          :columns="downstreamQueueColumns"
          @row-click="(row) => router.push(`/requests/${row.id}`)"
        />
      </section>
    </template>
  </div>
</template>
