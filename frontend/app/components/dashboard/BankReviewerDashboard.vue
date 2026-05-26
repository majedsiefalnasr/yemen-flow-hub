// @parity-evidence: Story 12.1 — docs/user-view/bank-reviewer.md#Dashboard
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, AlertTriangle, AlertCircle, Users, FileText, Zap, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { Card, CardContent } from '../ui/card'

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
    downstream_queue: Array.isArray((raw as any).downstream_queue) ? (raw as any).downstream_queue : [],
  }
})

const queue = computed(() => stats.value?.review_queue ?? [])
const downstreamQueue = computed(() => (stats.value as any)?.downstream_queue ?? [])
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
    green: 'text-green-700 bg-green-50/10',
    blue: 'text-primary bg-primary/10',
    amber: 'text-amber-600 bg-amber-50/10',
    rose: 'text-rose-600 bg-rose-50/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] || colors.gray
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
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-muted rounded mb-3" />
        <div class="h-8 w-10 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-destructive border-b border-border border-r bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-red-700" aria-hidden="true" />
        <span class="text-red-700 flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-background border border-destructive rounded-lg text-red-700 text-sm cursor-pointer hover:bg-red-700/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- Action-required strip: SUPPORT_REJECTED requests waiting for bank-side decision -->
      <Card
        v-if="supportRejectedCount > 0"
        class="border-0 border-s-4 border-s-amber-600 bg-amber-50/30 shadow-sm"
        role="alert"
        aria-label="طلبات رُفضت من لجنة المساندة"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <AlertTriangle class="h-5 w-5 flex-shrink-0 text-amber-600" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <span class="font-semibold text-foreground text-sm">{{ supportRejectedCount }} طلبات رفضتها لجنة المساندة وتنتظر قرارك</span>
          </div>
          <button
            class="flex-shrink-0 px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-xl hover:bg-amber-700 transition-colors"
            @click="router.push('/requests?tab=support_rejected')"
          >
            اتخاذ القرار
          </button>
        </CardContent>
      </Card>

      <!-- KPI grid (4 cards): Pending Review / Rejected by Support / At CBY / Approved-Completed -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow"
            :class="{
              'border-s-4 border-s-amber-600': kpi.variant === 'amber',
              'border-s-4 border-s-rose-600': kpi.variant === 'rose',
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
                kpi.variant === 'amber' && kpi.value > 0 ? 'text-amber-600'
                : kpi.variant === 'rose' && kpi.value > 0 ? 'text-rose-600'
                : kpi.variant === 'green' ? 'text-green-700'
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
          <button class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-colors" @click="router.push('/requests?tab=pending')">
            <Users class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75">{{ stats.pending_review }} طلب بانتظار المراجعة</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/requests')">
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">كل طلبات البنك</span>
            <span class="text-xs text-muted-foreground">عرض سائر الطلبات كاملاً</span>
          </button>
        </div>
      </section>

      <!-- Review queue table (max 8 rows) with Created By column + segregation enforcement -->
      <section aria-labelledby="queue-heading">
        <div class="flex items-center justify-between mb-4">
          <h2 id="queue-heading" class="text-sm font-semibold text-foreground">طابور المراجعة الحالي</h2>
          <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests?tab=pending')">عرض الكل</a>
        </div>

        <Card v-if="queue.length === 0" class="border-0 shadow">
          <CardContent class="pt-16 pb-16 flex flex-col items-center gap-3 text-center">
            <CheckCircle2 class="h-7 w-7 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm text-muted-foreground m-0">لا توجد طلبات في طابور المراجعة حالياً ✓</p>
          </CardContent>
        </Card>

        <Card v-else class="border-0 shadow">
          <CardContent class="p-4">
            <table class="w-full border-collapse text-xs" role="table" aria-label="طابور المراجعة الحالي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">أنشأه</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المورد</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in queue.slice(0, 8)"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2">
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                  </td>
                  <td class="py-2 px-2 text-foreground">
                    <span v-if="isCreatedByCurrentUser((req as any).created_by)" class="text-amber-600 font-medium">أنا</span>
                    <span v-else>{{ (req as any).created_by_name ?? '—' }}</span>
                  </td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></td>
                  <td class="py-2 px-2">
                    <template v-if="isCreatedByCurrentUser((req as any).created_by)">
                      <span
                        class="inline-flex px-2 py-1 bg-muted text-muted-foreground text-xs rounded cursor-not-allowed"
                        :title="'لا يمكنك مراجعة طلب أنشأته بنفسك'"
                        aria-label="لا يمكنك مراجعة طلب أنشأته بنفسك"
                      >
                        غير متاح
                      </span>
                    </template>
                    <template v-else>
                      <button
                        class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors"
                        :aria-label="`مراجعة الطلب ${req.reference_number}`"
                        @click.stop="router.push(`/requests/${req.id}`)"
                      >
                        بدء المراجعة
                      </button>
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </section>

      <!-- Downstream tracking table (max 5 rows, hidden when empty) -->
      <section v-if="downstreamQueue.length > 0" aria-labelledby="downstream-heading">
        <div class="flex items-center justify-between mb-4">
          <h2 id="downstream-heading" class="text-sm font-semibold text-foreground">متابعة الطلبات لدى البنك المركزي</h2>
          <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests?tab=at_cby')">عرض الكل</a>
        </div>
        <Card class="border-0 shadow">
          <CardContent class="p-4">
            <table class="w-full border-collapse text-xs" role="table" aria-label="متابعة الطلبات لدى البنك المركزي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرحلة الحالية</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in downstreamQueue.slice(0, 5)"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.stage_label ?? '—' }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </section>

    </template>
  </div>
</template>
