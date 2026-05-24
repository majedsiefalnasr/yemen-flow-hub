// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/bank-reviewer page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, Mail, Users, FileText, AlertCircle, Zap } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardContent } from '../ui/card'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<BankReviewerDashboardStats | null>(() => {
  const raw = store.stats as Partial<BankReviewerDashboardStats> | null
  if (!raw) return null
  return {
    pending_review: raw.pending_review ?? 0,
    at_cby: raw.at_cby ?? 0,
    returned_by_support: raw.returned_by_support ?? 0,
    approved_completed: raw.approved_completed ?? 0,
    review_queue: Array.isArray(raw.review_queue) ? raw.review_queue : [],
  }
})
const queue = computed(() => stats.value?.review_queue ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-green-700 bg-green-50/10',
    blue: 'text-blue-600 bg-blue-600/10',
    amber: 'text-amber-600 bg-amber-50/10',
    gray: 'text-gray-600 bg-gray-50',
  }
  return colors[variant] || colors.gray
}

const kpiConfig = computed(() => [
  { icon: CheckCircle2, value: stats.value?.approved_completed ?? 0, label: 'قُعَّد / مكتمل', variant: 'green' },
  { icon: Clock, value: stats.value?.at_cby ?? 0, label: 'قيد البنك المركزي', variant: 'blue' },
  { icon: RotateCcw, value: stats.value?.returned_by_support ?? 0, label: 'قيد للتعديل', variant: stats.value?.returned_by_support ?? 0 > 0 ? 'amber' : 'gray' },
  { icon: Mail, value: stats.value?.pending_review ?? 0, label: 'بانتظار المراجعة', variant: stats.value?.pending_review ?? 0 > 0 ? 'amber' : 'gray' },
])

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-gray-50 rounded mb-3" />
        <div class="h-8 w-10 bg-gray-50 rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-destructive border-b border-gray-200 border-r bg-white" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-red-700" aria-hidden="true" />
        <span class="text-red-700 flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-white border border-destructive rounded-lg text-red-700 text-sm cursor-pointer hover:bg-red-700/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card class="border-0 p-4 shadow flex flex-col gap-1.5" :class="{ 'border-s-4 border-s-amber-600': kpi.variant === 'amber' }">
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span class="text-2xl font-semibold leading-none" :class="kpi.variant === 'amber' && kpi.value > 0 ? 'text-amber-600' : kpi.variant === 'green' ? 'text-green-700' : 'text-gray-900'">
              {{ kpi.value }}
            </span>
            <span class="text-xs text-gray-600">{{ kpi.label }}</span>
          </Card>
        </template>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-2 max-md:grid-cols-1 gap-3">
          <button class="flex flex-col items-start gap-1 p-4 bg-blue-600 text-white border-0 rounded-lg cursor-pointer hover:hover:opacity-90 transition-colors" @click="router.push('/requests')">
            <Users class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75">{{ stats.pending_review }} طلب بانتظار المراجعة</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-gray-200 text-gray-900 rounded-lg cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/requests')">
            <FileText class="h-5 w-5 flex-shrink-0 text-blue-600 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">كل الطلبات</span>
            <span class="text-xs text-gray-600">عرض سائر الطلبات كاملاً</span>
          </button>
        </div>
      </section>

      <!-- Review queue table -->
      <section aria-labelledby="queue-heading">
        <div class="flex items-center justify-between mb-4">
          <h2 id="queue-heading" class="text-sm font-semibold text-gray-900">طابور المراجعة الحالي</h2>
          <a class="text-xs text-blue-600 hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
        </div>

        <Card v-if="queue.length === 0" class="border-0 shadow">
          <CardContent class="pt-16 pb-16 flex flex-col items-center gap-3 text-center">
            <CheckCircle2 class="h-7 w-7 text-gray-600" aria-hidden="true" />
            <p class="text-sm text-gray-600 m-0">لا توجد طلبات في طابور المراجعة حالياً</p>
          </CardContent>
        </Card>

        <Card v-else class="border-0 shadow">
          <CardContent class="p-4">
            <table class="w-full border-collapse text-xs" role="table" aria-label="طابور المراجعة الحالي">
              <thead>
                <tr class="border-b border-gray-200">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">المورد</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">التقدم</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in queue" :key="req.id" class="border-t border-muted hover:bg-gray-50 cursor-pointer transition-colors">
                  <td class="py-2 px-2"><a class="font-mono text-blue-600 hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-gray-900">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-gray-900 direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></td>
                  <td class="py-2 px-2">
                    <div class="flex items-center gap-2 min-w-24">
                      <div class="flex-1 h-1.5 bg-gray-50 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 transition-all" :style="{ width: `${getRequestProgress(req.status)}%` }" />
                      </div>
                      <span class="text-xs text-gray-600 whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                    </div>
                  </td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-white border border-gray-200 text-xs text-gray-900 rounded hover:border-primary hover:text-blue-600 transition-colors" :aria-label="`عرض الطلب ${req.reference_number}`" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </section>

    </template>
  </div>
</template>
