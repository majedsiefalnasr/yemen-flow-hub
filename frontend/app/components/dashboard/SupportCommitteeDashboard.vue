// @parity-exempt — dashboard sub-component; parity evidence captured at
dashboards/support-committee page level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted, h } from 'vue'
import { useRouter } from 'vue-router'
import {
  CheckCircle2,
  Users,
  Clock,
  Mail,
  Zap,
  AlertCircle,
  AlarmClock,
  Globe,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { SupportCommitteeDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Badge } from '../ui/badge'
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed<SupportCommitteeDashboardStats | null>(() => {
  const raw = store.stats as Partial<SupportCommitteeDashboardStats> | null
  if (!raw) return null
  return {
    waiting_for_claim: raw.waiting_for_claim ?? 0,
    active_by_me: raw.active_by_me ?? 0,
    claimed_by_others: raw.claimed_by_others ?? 0,
    recently_approved: raw.recently_approved ?? 0,
    support_queue: Array.isArray(raw.support_queue) ? raw.support_queue : [],
  }
})
const queue = computed(() => stats.value?.support_queue ?? [])
const currentUserId = computed(() => auth.user?.id ?? null)

// Active claims = rows in queue currently claimed by me
const myActiveClaims = computed(() =>
  queue.value.filter(
    (req) =>
      req.is_claimed_by_me ||
      (currentUserId.value != null && req.claimed_by?.id === currentUserId.value),
  ),
)
const hasActiveClaim = computed(() => myActiveClaims.value.length > 0)
// Oldest active claim — frontend defends the "oldest first" ordering so we
// don't silently break if backend ordering changes (claim age is the source
// of truth, not list position).
const oldestActiveClaim = computed(() => {
  const claims = [...myActiveClaims.value]
  claims.sort((a, b) => {
    const at = a.claimed_at ? new Date(a.claimed_at).getTime() : Number.POSITIVE_INFINITY
    const bt = b.claimed_at ? new Date(b.claimed_at).getTime() : Number.POSITIVE_INFINITY
    return at - bt
  })
  return claims[0] ?? null
})

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

function claimOwnerLabel(req: SupportCommitteeDashboardStats['support_queue'][number]): string {
  if (!req.claimed_by) return 'غير مطالب به'
  if (
    req.is_claimed_by_me ||
    (currentUserId.value != null && req.claimed_by.id === currentUserId.value)
  ) {
    return `${req.claimed_by.name} (أنت)`
  }
  return req.claimed_by.name
}

type SupportQueueRow = NonNullable<SupportCommitteeDashboardStats['support_queue']>[number]

const supportQueueColumns: ColumnDef<SupportQueueRow>[] = [
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
      h(StatusBadge, { status: row.original.status, role: UserRole.SUPPORT_COMMITTEE }),
  },
  {
    id: 'claim',
    header: 'الحجز',
    cell: ({ row }) =>
      h(
        'span',
        {
          class: [
            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium',
            row.original.is_claimed_by_me ||
            (currentUserId.value != null && row.original.claimed_by?.id === currentUserId.value)
              ? 'bg-[var(--voting)]/10 text-[var(--voting)]'
              : row.original.claimed_by
                ? 'bg-muted text-muted-foreground'
                : 'bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]',
          ],
        },
        claimOwnerLabel(row.original),
      ),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) => {
      if (!row.original.claimed_by) {
        return h(
          Button,
          {
            size: 'sm',
            onClick: (event: MouseEvent) => {
              event.stopPropagation()
              router.push(`/requests/${row.original.id}`)
            },
          },
          () => 'مطالبة',
        )
      }
      if (
        row.original.is_claimed_by_me ||
        (currentUserId.value != null && row.original.claimed_by?.id === currentUserId.value)
      ) {
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
          () => 'متابعة',
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
        () => 'عرض',
      )
    },
  },
]

// Spec order: Waiting for Claim (amber) / Active by Me (indigo) / Claimed by Others (gray) / Recently Approved (green)
const kpiConfig = computed(() => [
  {
    icon: Mail,
    value: stats.value?.waiting_for_claim ?? 0,
    label: 'بانتظار المطالبة',
    variant: (stats.value?.waiting_for_claim ?? 0) > 0 ? 'amber' : 'gray',
    tab: 'waiting',
  },
  {
    icon: Clock,
    value: stats.value?.active_by_me ?? 0,
    label: 'أعمل عليها الآن',
    variant: (stats.value?.active_by_me ?? 0) > 0 ? 'indigo' : 'gray',
    tab: 'my_claims',
  },
  {
    icon: Users,
    value: stats.value?.claimed_by_others ?? 0,
    label: 'محجوزة لأعضاء آخرين',
    variant: 'gray',
    tab: 'in_progress',
  },
  {
    icon: CheckCircle2,
    value: stats.value?.recently_approved ?? 0,
    label: 'اعتُمِدت مؤخراً',
    variant: 'green',
    tab: 'approved',
  },
])

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- CBY-global scope chip -->
    <div class="mb-2">
      <Badge
        variant="outline"
        class="text-muted-foreground border-border gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
      >
        <Globe class="size-3" aria-hidden="true" />
        نطاق عبر البنوك
      </Badge>
    </div>

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
        <span class="text-destructive flex-1">{{ store.error }}</span>
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
      <!-- Active-claim strip (highest priority, indigo) — hidden when no active claims -->
      <Card
        v-if="hasActiveClaim"
        class="border-0 border-[var(--voting)] bg-[var(--voting)]/5 shadow-sm"
        role="status"
        aria-label="طلبات نشطة محجوزة باسمك"
      >
        <CardContent class="flex items-center gap-3">
          <AlarmClock class="h-5 w-5 flex-shrink-0 text-[var(--voting)]" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <span class="text-foreground text-sm font-semibold"
              >لديك {{ myActiveClaims.length }} طلب نشط محجوز باسمك</span
            >
            <p v-if="oldestActiveClaim" class="text-muted-foreground mt-0.5 truncate text-xs">
              {{ oldestActiveClaim.reference_number }}
            </p>
          </div>
          <Button
            v-if="oldestActiveClaim"
            size="sm"
            class="flex-shrink-0"
            @click="router.push(`/requests/${oldestActiveClaim.id}`)"
          >
            متابعة المراجعة
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: 4 clickable cards — spec order: waiting / active / others / approved -->
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
              : kpi.variant === 'indigo' && kpi.value > 0
                ? 'voting'
                : kpi.variant === 'green'
                  ? 'success'
                  : 'default'
          "
          :highlighted="(kpi.variant === 'amber' || kpi.variant === 'indigo') && kpi.value > 0"
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
            class="focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-2xl border-0 bg-[var(--voting)] p-4 text-white transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="طابور المراجعة"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <Users class="mb-1 h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75"
              >{{ stats.waiting_for_claim }} طلب بانتظار المطالبة</span
            >
          </Card>

          <Card
            class="bg-background border-border text-foreground hover:border-primary focus-visible:ring-primary flex cursor-pointer flex-col items-start gap-1 rounded-2xl border p-4 transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="الإشعارات"
            @click="router.push('/notifications')"
            @keydown.enter="router.push('/notifications')"
            @keydown.space.prevent="router.push('/notifications')"
          >
            <Mail class="text-primary mb-1 h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-muted-foreground text-xs">آخر تحديثات الطابور والقرارات</span>
          </Card>
        </div>
      </section>

      <!-- Support queue table (max 8 rows) -->
      <Card class="border-0 shadow" aria-labelledby="queue-heading">
        <CardContent class="p-4">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="queue-heading" class="text-foreground text-sm font-semibold">طابور المراجعة</h2>
            <Button
              variant="link"
              size="sm"
              class="h-auto p-0 text-xs"
              @click="router.push('/requests')"
              >عرض الكل</Button
            >
          </div>

          <DataTable
            :data="queue.slice(0, 8)"
            :columns="supportQueueColumns"
            @row-click="(row) => router.push(`/requests/${row.id}`)"
          >
            <template #empty>لا توجد طلبات بانتظار المراجعة حالياً</template>
          </DataTable>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
