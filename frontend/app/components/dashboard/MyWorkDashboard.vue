// @parity-exempt — dynamic work dashboard; content is API-derived, not role-mapped
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, ClipboardList, Eye, Hand, Timer } from 'lucide-vue-next'
import { useDashboardWorkStore } from '../../stores/dashboardWork.store'
import type { WorkSection } from '../../composables/useDashboardWork'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '../ui/table'
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '../ui/empty'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'
import ErrorState from '../shared/ErrorState.vue'

/**
 * The single work dashboard for every workflow user (Phase D0). All content is
 * derived from /dashboard/work — the actionable section is the same record set
 * as /my-queue — so no role code selects what is shown here.
 */
const router = useRouter()
const store = useDashboardWorkStore()

const work = computed(() => store.work)
const actionable = computed<WorkSection>(
  () => work.value?.actionable ?? { count: 0, items: [], queue_url: '/workflows?queue=mine' },
)
const claimed = computed<WorkSection>(() => work.value?.claimed ?? { count: 0, items: [] })
const tracking = computed<WorkSection>(
  () => work.value?.tracking ?? { count: 0, items: [], queue_url: '/workflows?scope=all' },
)
const sla = computed(() => work.value?.sla ?? { near_due: 0, overdue: 0 })

const oldestActionable = computed(() => actionable.value.items[0] ?? null)

function formatAmount(amount: number | null, currency: string | null): string {
  if (amount === null) return '—'
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency: currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format(amount)
}

function openInstance(id: number): void {
  router.push(`/workflows/instances/${id}`)
}

function retry(): void {
  store.loadWork()
}

onMounted(() => {
  store.loadWork()
})
</script>

<template>
  <div class="flex flex-col gap-6 py-2">
    <!-- Loading -->
    <div
      v-if="store.loading"
      class="grid grid-cols-4 gap-4 sm:grid-cols-1 md:grid-cols-2"
      aria-busy="true"
    >
      <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-xl" />
    </div>

    <!-- Error / denial (403/404/429/500) with retry -->
    <ErrorState
      v-else-if="store.error"
      :code="store.errorStatus === 429 ? '429' : (store.errorStatus ?? 500)"
      :title="store.errorStatus === 429 ? 'تم إيقاف التحميل مؤقتاً' : undefined"
      :description="store.error"
      :actions="[{ label: 'إعادة المحاولة', variant: 'default', onClick: retry }]"
    />

    <template v-else-if="work">
      <!-- Action strip — requests awaiting the user's action -->
      <Card
        v-if="actionable.count > 0"
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
              {{ actionable.count }} طلبات تنتظر إجراءك
            </p>
            <p v-if="oldestActionable" class="text-muted-foreground truncate text-xs">
              {{ oldestActionable.reference_number }}
            </p>
          </div>
          <Button
            size="sm"
            class="flex-shrink-0"
            @click="router.push(actionable.queue_url ?? '/workflows?queue=mine')"
          >
            فتح الطابور
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: actionable / claimed / near-due / overdue -->
      <MetricGrid :columns="4">
        <MetricCard
          label="بانتظار إجراءك"
          :value="actionable.count"
          :icon="ClipboardList"
          tone="warning"
          :highlighted="actionable.count > 0"
          @click="router.push(actionable.queue_url ?? '/workflows?queue=mine')"
        />
        <MetricCard label="مطالَب بها من قِبلك" :value="claimed.count" :icon="Hand" tone="info" />
        <MetricCard
          label="يقترب استحقاقها"
          :value="sla.near_due"
          :icon="Timer"
          tone="warning"
          :highlighted="sla.near_due > 0"
        />
        <MetricCard
          label="متجاوِزة المهلة"
          :value="sla.overdue"
          :icon="AlertTriangle"
          tone="danger"
          :highlighted="sla.overdue > 0"
        />
      </MetricGrid>

      <!-- Actionable work queue -->
      <Card class="border-0 shadow" aria-labelledby="actionable-heading">
        <CardContent class="p-4">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="actionable-heading" class="text-foreground text-sm font-semibold">
              طابور مهامي
            </h2>
            <Button
              variant="link"
              size="sm"
              class="h-auto p-0 text-xs"
              @click="router.push(actionable.queue_url ?? '/workflows?queue=mine')"
            >
              عرض الكل
            </Button>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="text-start">المرجع</TableHead>
                <TableHead class="text-start">المستورد</TableHead>
                <TableHead class="text-start">المبلغ</TableHead>
                <TableHead class="text-start">المرحلة</TableHead>
                <TableHead class="text-start">إجراء</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="item in actionable.items"
                :key="item.id"
                class="cursor-pointer"
                @click="openInstance(item.id)"
              >
                <TableCell class="text-primary font-mono">{{ item.reference_number }}</TableCell>
                <TableCell>{{ item.merchant_name ?? 'غير متاح' }}</TableCell>
                <TableCell class="font-mono">{{
                  formatAmount(item.amount, item.currency)
                }}</TableCell>
                <TableCell>{{ item.stage_name ?? '—' }}</TableCell>
                <TableCell @click.stop>
                  <Button size="sm" @click="openInstance(item.id)">فتح</Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>

          <Empty v-if="actionable.items.length === 0" class="py-6">
            <EmptyHeader>
              <CheckCircle2 class="text-muted-foreground/50 h-8 w-8" />
            </EmptyHeader>
            <EmptyTitle>لا مهام تنتظر إجراءك</EmptyTitle>
            <EmptyDescription>أنت على اطّلاع بكل ما يخصّك حالياً ✓</EmptyDescription>
          </Empty>
        </CardContent>
      </Card>

      <!-- Tracking / view-only work (only shown when it exists) -->
      <Card v-if="tracking.count > 0" class="border-0 shadow" aria-labelledby="tracking-heading">
        <CardContent class="p-4">
          <div class="mb-4 flex items-center justify-between">
            <h2
              id="tracking-heading"
              class="text-foreground flex items-center gap-2 text-sm font-semibold"
            >
              <Eye class="text-muted-foreground h-4 w-4" aria-hidden="true" />
              قيد المتابعة (للاطّلاع فقط)
              <span class="text-muted-foreground font-normal">({{ tracking.count }})</span>
            </h2>
            <Button
              variant="link"
              size="sm"
              class="h-auto p-0 text-xs"
              @click="router.push(tracking.queue_url ?? '/workflows?scope=all')"
            >
              عرض الكل
            </Button>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="text-start">المرجع</TableHead>
                <TableHead class="text-start">المستورد</TableHead>
                <TableHead class="text-start">المرحلة</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="item in tracking.items"
                :key="item.id"
                class="cursor-pointer"
                @click="openInstance(item.id)"
              >
                <TableCell class="text-primary font-mono">{{ item.reference_number }}</TableCell>
                <TableCell>{{ item.merchant_name ?? 'غير متاح' }}</TableCell>
                <TableCell>{{ item.stage_name ?? '—' }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
