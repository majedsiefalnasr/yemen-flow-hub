// @parity-evidence: Story 12.1 — docs/user-view/bank-reviewer.md#Dashboard
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, AlertTriangle, AlertCircle, Users, FileText, Zap, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import { STATUS_LABELS } from '../../constants/workflow'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '../ui/table'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const currentUserId = computed(() => auth.user?.id ?? null)
const authUser = computed(() => auth.user)

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

// Spec order: Pending Review (amber) / Rejected by Support (rose) / At CBY (blue) / Approved-Completed (green)
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
    label: 'رُفض من المساندة',
    variant: (stats.value?.returned_by_support ?? 0) > 0 ? 'rose' : 'gray',
    tab: 'support_rejected',
  },
  {
    icon: RotateCcw,
    value: stats.value?.at_cby ?? 0,
    label: 'قيد البنك المركزي',
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
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-[var(--severity-green)] bg-[var(--severity-green)]/10',
    blue: 'text-primary bg-primary/10',
    amber: 'text-[var(--severity-amber)] bg-[var(--severity-amber)]/10',
    rose: 'text-[var(--severity-red)] bg-[var(--severity-red)]/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] ?? colors.gray!
}

function isCreatedByCurrentUser(createdBy: number | null | undefined): boolean {
  return currentUserId.value !== null && createdBy === currentUserId.value
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="h-3.5 w-[60px] mb-3" />
        <Skeleton class="h-8 w-[40px]" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-0 border-s-4 border-s-[var(--severity-red)] bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="text-[var(--severity-red)] flex-1">{{ store.error }}</span>
        <Button variant="outline" size="sm" class="text-[var(--severity-red)] border-[var(--severity-red)]" @click="store.loadStats()">
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- Greeting header -->
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-lg font-semibold text-foreground truncate">مرحباً، {{ authUser?.name }}</h1>
          <p v-if="authUser?.bank_name_ar" class="text-sm text-muted-foreground truncate">{{ authUser.bank_name_ar }} — مراجع الطلبات</p>
        </div>
      </div>

      <!-- Action-required strip: SUPPORT_REJECTED requests waiting for bank-side decision -->
      <Card
        v-if="supportRejectedCount > 0"
        class="border-0 border-s-4 border-s-[var(--severity-red)] bg-[var(--severity-red)]/5 shadow-sm"
        role="alert"
        aria-label="طلبات رُفضت من لجنة المساندة"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <AlertTriangle class="h-5 w-5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <span class="font-semibold text-foreground text-sm">{{ supportRejectedCount }} طلبات رفضتها لجنة المساندة وتنتظر قرارك</span>
          </div>
          <Button
            size="sm"
            class="flex-shrink-0 bg-[var(--severity-red)] text-white hover:opacity-90"
            @click="router.push('/requests?tab=support_rejected')"
          >
            اتخاذ القرار
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid (4 cards): Pending Review / Rejected by Support / At CBY / Approved-Completed -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow"
            :class="{
              'border-s-4 border-s-[var(--severity-amber)]': kpi.variant === 'amber',
              'border-s-4 border-s-[var(--severity-red)]': kpi.variant === 'rose',
            }"
            role="button"
            tabindex="0"
            :aria-label="`${kpi.label}: ${kpi.value}`"
            @click="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.enter="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.space.prevent="router.push(`/requests?tab=${kpi.tab}`)"
          >
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span
              class="text-2xl font-semibold leading-none"
              :class="
                kpi.variant === 'amber' && kpi.value > 0 ? 'text-[var(--severity-amber)]'
                : kpi.variant === 'rose' && kpi.value > 0 ? 'text-[var(--severity-red)]'
                : kpi.variant === 'green' ? 'text-[var(--severity-green)]'
                : 'text-foreground'
              "
            >
              {{ kpi.value }}
            </span>
            <span class="text-xs text-muted-foreground">{{ kpi.label }}</span>
          </Card>
        </template>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-2 max-md:grid-cols-1 gap-3">
          <Card
            class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-opacity focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="طابور المراجعة"
            @click="router.push('/requests?tab=pending')"
            @keydown.enter="router.push('/requests?tab=pending')"
            @keydown.space.prevent="router.push('/requests?tab=pending')"
          >
            <Users class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75">{{ stats.pending_review }} طلب بانتظار المراجعة</span>
          </Card>

          <Card
            class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="كل طلبات البنك"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">كل طلبات البنك</span>
            <span class="text-xs text-muted-foreground">عرض سائر الطلبات كاملاً</span>
          </Card>
        </div>
      </section>

      <!-- Review queue table (max 8 rows) with Created By column + segregation enforcement -->
      <section aria-labelledby="queue-heading">
        <div class="flex items-center justify-between mb-4">
          <h2 id="queue-heading" class="text-sm font-semibold text-foreground">طابور المراجعة الحالي</h2>
          <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests?tab=pending')">عرض الكل</Button>
        </div>

        <Card v-if="queue.length === 0" class="border-0 shadow">
          <CardContent class="pt-16 pb-16 flex flex-col items-center gap-3 text-center">
            <CheckCircle2 class="h-7 w-7 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm text-muted-foreground m-0">لا توجد طلبات في طابور المراجعة حالياً ✓</p>
          </CardContent>
        </Card>

        <Card v-else class="border-0 shadow">
          <CardContent class="p-4">
            <Table aria-label="طابور المراجعة الحالي">
              <TableHeader>
                <TableRow>
                  <TableHead>المرجع</TableHead>
                  <TableHead>أنشأه</TableHead>
                  <TableHead>المورد</TableHead>
                  <TableHead>المبلغ</TableHead>
                  <TableHead>الحالة</TableHead>
                  <TableHead>إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="req in queue.slice(0, 8)"
                  :key="req.id"
                  class="cursor-pointer"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <TableCell>
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.stop.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                  </TableCell>
                  <TableCell>
                    <span v-if="isCreatedByCurrentUser(req.created_by)" class="text-[var(--severity-amber)] font-medium">أنا</span>
                    <span v-else>{{ req.created_by_user?.name ?? '—' }}</span>
                  </TableCell>
                  <TableCell>{{ req.supplier_name }}</TableCell>
                  <TableCell class="direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                  <TableCell><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></TableCell>
                  <TableCell @click.stop>
                    <template v-if="isCreatedByCurrentUser(req.created_by)">
                      <span
                        class="inline-flex px-2 py-1 bg-muted text-muted-foreground text-xs rounded cursor-not-allowed"
                        :title="'لا يمكنك مراجعة طلب أنشأته بنفسك'"
                        aria-label="لا يمكنك مراجعة طلب أنشأته بنفسك"
                      >
                        غير متاح
                      </span>
                    </template>
                    <template v-else>
                      <Button
                        size="sm"
                        variant="outline"
                        :aria-label="`مراجعة الطلب ${req.reference_number}`"
                        @click="router.push(`/requests/${req.id}`)"
                      >
                        بدء المراجعة
                      </Button>
                    </template>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </section>

      <!-- Downstream tracking table (max 5 rows, hidden when empty) -->
      <section v-if="downstreamQueue.length > 0" aria-labelledby="downstream-heading">
        <div class="flex items-center justify-between mb-4">
          <h2 id="downstream-heading" class="text-sm font-semibold text-foreground">متابعة الطلبات لدى البنك المركزي</h2>
          <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests?tab=at_cby')">عرض الكل</Button>
        </div>
        <Card class="border-0 shadow">
          <CardContent class="p-4">
            <Table aria-label="متابعة الطلبات لدى البنك المركزي">
              <TableHeader>
                <TableRow>
                  <TableHead>المرجع</TableHead>
                  <TableHead>المرحلة الحالية</TableHead>
                  <TableHead>الحالة</TableHead>
                  <TableHead>إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="req in downstreamQueue.slice(0, 5)"
                  :key="req.id"
                  class="cursor-pointer"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <TableCell>
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.stop.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                  </TableCell>
                  <TableCell>{{ STATUS_LABELS[req.status] ?? '—' }}</TableCell>
                  <TableCell><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></TableCell>
                  <TableCell @click.stop>
                    <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">عرض</Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </section>

    </template>
  </div>
</template>
