// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/swift-officer
page level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted, h } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, Clock3, UploadCloud, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type { SwiftOfficerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { UserRole } from '../../types/enums'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<SwiftOfficerDashboardStats | null>(() => {
  const raw = store.stats as Partial<SwiftOfficerDashboardStats> | null
  if (!raw) return null
  return {
    pending_swift_upload: raw.pending_swift_upload ?? 0,
    uploaded: raw.uploaded ?? 0,
    final_approved: raw.final_approved ?? 0,
    final_rejected: raw.final_rejected ?? 0,
    swift_queue: Array.isArray(raw.swift_queue) ? raw.swift_queue : [],
  }
})

const queue = computed(() =>
  [...(stats.value?.swift_queue ?? [])].sort((a, b) => {
    const at = new Date(a.updated_at ?? a.created_at ?? 0).getTime()
    const bt = new Date(b.updated_at ?? b.created_at ?? 0).getTime()
    return at - bt
  }),
)

const oldestPending = computed(() => queue.value[0] ?? null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

function hoursInStage(updatedAt: string): number {
  const updated = new Date(updatedAt).getTime()
  if (Number.isNaN(updated)) return 0
  return Math.max(0, Math.floor((Date.now() - updated) / (1000 * 60 * 60)))
}

type SwiftQueueRow = NonNullable<SwiftOfficerDashboardStats['swift_queue']>[number]

const swiftQueueColumns: ColumnDef<SwiftQueueRow>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h('span', { class: 'font-mono text-primary' }, row.original.reference_number),
  },
  {
    id: 'merchant',
    header: 'المستورد',
    cell: ({ row }) => h('span', row.original.merchant?.name ?? 'غير متاح'),
  },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h('span', { class: 'font-mono' }, formatAmount(row.original.amount, row.original.currency)),
  },
  {
    id: 'status',
    header: 'الحالة',
    cell: ({ row }) =>
      h(StatusBadge, { status: row.original.status, role: UserRole.SWIFT_OFFICER }),
  },
  {
    id: 'age',
    header: 'العمر بالمرحلة',
    cell: ({ row }) =>
      h(
        'span',
        {
          class:
            hoursInStage(row.original.updated_at) > 24
              ? 'text-[var(--severity-amber)]'
              : 'text-muted-foreground',
        },
        `${hoursInStage(row.original.updated_at)} ساعة`,
      ),
  },
  {
    id: 'docs',
    header: 'المستندات',
    cell: ({ row }) =>
      h('div', { class: 'flex items-center gap-1.5' }, [
        h(
          'span',
          {
            class: row.original.has_swift_document
              ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-xs text-[var(--severity-green)]'
              : 'rounded-full border border-border bg-muted px-2 py-0.5 text-xs text-muted-foreground',
          },
          'السويفت',
        ),
        h(
          'span',
          {
            class: row.original.has_fx_request_document
              ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-xs text-[var(--severity-green)]'
              : 'rounded-full border border-border bg-muted px-2 py-0.5 text-xs text-muted-foreground',
          },
          'طلب تأكيد المصارفة',
        ),
      ]),
  },
  {
    id: 'actions',
    header: 'إجراء',
    cell: ({ row }) =>
      h('div', { class: 'flex items-center gap-2' }, [
        h(
          Button,
          {
            size: 'sm',
            onClick: (event: MouseEvent) => {
              event.stopPropagation()
              router.push(`/requests/${row.original.id}/swift`)
            },
          },
          () => 'رفع وثائق السويفت',
        ),
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
          () => 'عرض الطلب',
        ),
      ]),
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
      class="grid grid-cols-4 gap-4 sm:grid-cols-1 md:grid-cols-2"
      aria-busy="true"
    >
      <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-xl" />
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
      <!-- Action strip — pending uploads -->
      <Card
        v-if="stats.pending_swift_upload > 0"
        class="border-0 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <CardContent class="flex items-center gap-3">
          <AlertTriangle
            class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <p class="text-foreground text-sm font-semibold">
              {{ stats.pending_swift_upload }} طلبات بانتظار رفع وثائق السويفت
            </p>
            <p v-if="oldestPending" class="text-muted-foreground truncate text-xs">
              {{ oldestPending.reference_number }} · منذ
              {{ hoursInStage(oldestPending.updated_at) }} ساعة
            </p>
          </div>
          <Button
            size="sm"
            class="flex-shrink-0"
            @click="router.push('/requests?tab=pending_swift')"
          >
            ابدأ الرفع
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: 4 clickable cards -->
      <MetricGrid :columns="4">
        <MetricCard
          label="بانتظار رفع السويفت"
          :value="stats.pending_swift_upload"
          :icon="Clock3"
          tone="warning"
          :highlighted="stats.pending_swift_upload > 0"
          @click="router.push('/requests?tab=pending_swift')"
        />
        <MetricCard
          label="تم رفع السويفت"
          :value="stats.uploaded"
          :icon="UploadCloud"
          tone="info"
          @click="router.push('/requests?tab=swift_done')"
        />
        <MetricCard
          label="مكتمل"
          :value="stats.final_approved"
          :icon="CheckCircle2"
          tone="success"
          @click="router.push('/requests?tab=completed')"
        />
        <MetricCard
          label="مرفوض من اللجنة"
          :value="stats.final_rejected"
          :icon="XCircle"
          tone="danger"
          :highlighted="stats.final_rejected > 0"
          @click="router.push('/requests?tab=rejected')"
        />
      </MetricGrid>

      <!-- SWIFT queue table -->
      <Card class="border-0 shadow" aria-labelledby="swift-queue-heading">
        <CardContent class="p-4">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="swift-queue-heading" class="text-foreground text-sm font-semibold">
              طابور السويفت
            </h2>
          </div>

          <DataTable :data="queue" :columns="swiftQueueColumns">
            <template #empty>لا توجد طلبات بانتظار رفع وثائق السويفت حالياً</template>
          </DataTable>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
