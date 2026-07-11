// @parity-exempt — dashboard sub-component; parity evidence captured at
dashboards/committee-director page level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, h, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, FileSignature, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type {
  CommitteeDirectorDashboardStats,
  DirectorQueueItem,
} from '../../composables/useDashboard'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'

const router = useRouter()
const store = useDashboardStore()

// UI-FX-001: the Director's actionable queue is the FINAL stage — the same
// records /customs and my-queue surface. The backend headline (final_pending)
// and this dashboard therefore agree with the dedicated /customs list. No
// voting UI: executive voting is out of V1 scope.
const stats = computed<CommitteeDirectorDashboardStats | null>(() => {
  const raw = store.stats as Partial<CommitteeDirectorDashboardStats> | null
  if (!raw) return null
  return {
    final_pending: raw.final_pending ?? 0,
    final_pending_queue: Array.isArray(raw.final_pending_queue) ? raw.final_pending_queue : [],
    finalized_approved: raw.finalized_approved ?? 0,
    finalized_rejected: raw.finalized_rejected ?? 0,
  }
})

const queue = computed(() =>
  [...(stats.value?.final_pending_queue ?? [])].sort((a, b) => {
    const at = new Date(a.created_at ?? 0).getTime()
    const bt = new Date(b.created_at ?? 0).getTime()
    return at - bt
  }),
)

const oldestPending = computed(() => queue.value[0] ?? null)

function formatAmount(amount: number | null, currency: string | null): string {
  if (amount === null) return '—'
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency: currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format(amount)
}

const finalQueueColumns: ColumnDef<DirectorQueueItem>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h('span', { class: 'font-mono text-primary' }, row.original.reference_number),
  },
  {
    id: 'merchant',
    header: 'المستورد',
    cell: ({ row }) => h('span', row.original.merchant_name ?? 'غير متاح'),
  },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h('span', { class: 'font-mono' }, formatAmount(row.original.amount, row.original.currency)),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          onClick: (event: MouseEvent) => {
            event.stopPropagation()
            router.push(`/workflows/instances/${row.original.id}`)
          },
        },
        () => 'الاعتماد النهائي',
      ),
  },
]

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <!-- Skeleton -->
    <div
      v-if="store.loading"
      class="grid grid-cols-4 gap-4 sm:grid-cols-1 md:grid-cols-2"
      aria-busy="true"
    >
      <Skeleton v-for="n in 3" :key="n" class="h-24 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Card
      v-else-if="store.error"
      class="bg-background border-0 border-[var(--severity-red)]"
      role="alert"
    >
      <CardContent class="flex items-center gap-3 pt-6">
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
      <!-- Action strip — requests awaiting final confirmation -->
      <Card
        v-if="stats.final_pending > 0"
        class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <CardContent class="flex items-center gap-3">
          <AlertTriangle
            class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <p class="text-foreground text-sm font-semibold">
              {{ stats.final_pending }} طلبات بانتظار الاعتماد النهائي
            </p>
            <p v-if="oldestPending" class="text-muted-foreground truncate text-xs">
              {{ oldestPending.reference_number }}
            </p>
          </div>
          <Button size="sm" class="flex-shrink-0" @click="router.push('/customs')">
            <FileSignature class="ms-1 h-3.5 w-3.5" />
            فتح الطابور
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: 3 cards (no voting) -->
      <MetricGrid :columns="3">
        <MetricCard
          label="بانتظار الاعتماد النهائي"
          :value="stats.final_pending"
          :icon="FileSignature"
          tone="warning"
          :highlighted="stats.final_pending > 0"
          @click="router.push('/customs')"
        />
        <MetricCard
          label="معتمد نهائياً"
          :value="stats.finalized_approved"
          :icon="CheckCircle2"
          tone="success"
          @click="router.push('/workflows?tab=completed')"
        />
        <MetricCard
          label="مرفوض نهائياً"
          :value="stats.finalized_rejected"
          :icon="XCircle"
          tone="danger"
          :highlighted="stats.finalized_rejected > 0"
          @click="router.push('/workflows?tab=rejected')"
        />
      </MetricGrid>

      <!-- Final-confirmation queue table -->
      <Card class="border-0 shadow" aria-labelledby="final-queue-heading">
        <CardContent class="p-4">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="final-queue-heading" class="text-foreground text-sm font-semibold">
              طابور الاعتماد النهائي
            </h2>
          </div>

          <DataTable :data="queue" :columns="finalQueueColumns">
            <template #empty>لا توجد طلبات بانتظار الاعتماد النهائي حالياً</template>
          </DataTable>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
