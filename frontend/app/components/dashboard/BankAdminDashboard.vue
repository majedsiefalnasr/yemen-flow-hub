// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/bank-admin page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, AlertCircle, FileText, Building2, Users, BarChart3, Zap } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankAdminDashboardStats, BankAdminMonthlyEntry } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardContent } from '../ui/card'

const router = useRouter()
const store = useDashboardStore()
const stats = computed(() => store.stats as BankAdminDashboardStats | null)

function formatAmount(amount: number): string {
  return new Intl.NumberFormat('ar-YE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(iso))
}

// ── SVG sparkline ──────────────────────────────────────────────────────────
const CHART_W = 480
const CHART_H = 80
const PAD = 8

function buildLine(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map(e => e.count)
  const max = Math.max(...counts, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  return entries.map((e, i) => {
    const x = PAD + i * step
    const y = PAD + (1 - e.count / max) * (CHART_H - PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function buildArea(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map(e => e.count)
  const max = Math.max(...counts, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = PAD + i * step
    const y = PAD + (1 - e.count / max) * (CHART_H - PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })
  const bottom = CHART_H - PAD
  const lastX = (PAD + (entries.length - 1) * step).toFixed(1)
  return `${PAD},${bottom} ${pts.join(' ')} ${lastX},${bottom}`
}

function monthLabel(ym: string): string {
  const [y, m] = ym.split('-')
  return new Intl.DateTimeFormat('ar-YE', { month: 'short' }).format(new Date(Number(y), Number(m) - 1, 1))
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-green-700 bg-green-50',
    indigo: 'text-indigo-600 bg-indigo-50',
    amber: 'text-amber-600 bg-amber-50',
    gray: 'text-gray-600 bg-gray-50',
  }
  return colors[variant] || colors.gray
}

const kpiConfig = computed(() => [
  { icon: CheckCircle2, value: stats.value?.approved ?? 0, label: 'مُعتمد', variant: 'green' },
  { icon: AlertCircle, value: stats.value?.pending ?? 0, label: 'مراجعة داخلية مُعلقة', variant: 'indigo' },
  { icon: Clock, value: stats.value?.rejected ?? 0, label: 'مرفوض', variant: 'amber' },
  { icon: FileText, value: stats.value?.total ?? 0, label: 'إجمالي طلبات البنك', variant: 'gray' },
])

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow-card animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-gray-100 rounded mb-3" />
        <div class="h-8 w-10 bg-gray-100 rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-red-700 border-b border-gray-300 border-r bg-white" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-red-700" aria-hidden="true" />
        <span class="text-red-700 flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-white border border-red-700 rounded-lg text-red-700 text-sm cursor-pointer hover:bg-red-50 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card class="border-0 p-4 shadow-card flex flex-col gap-1.5" :class="{ 'border-s-4 border-s-amber-600': kpi.variant === 'amber' }">
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span class="text-2xl font-semibold leading-none" :class="kpi.variant === 'amber' && kpi.value > 0 ? 'text-amber-600' : kpi.variant === 'green' ? 'text-green-700' : kpi.variant === 'indigo' ? 'text-indigo-600' : 'text-gray-900'">
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
        <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-3">
          <button class="flex flex-col items-start gap-1 p-4 bg-blue-600 text-white border-0 rounded-lg cursor-pointer hover:bg-blue-700 transition-colors" @click="router.push('/requests')">
            <FileText class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طلبات البنك</span>
            <span class="text-xs opacity-75">عرض جميع طلبات البنك</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-gray-300 text-gray-900 rounded-lg cursor-pointer hover:border-blue-600 hover:shadow-md transition-all" @click="router.push('/merchants')">
            <Building2 class="h-5 w-5 flex-shrink-0 text-blue-600 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">إدارة التجار</span>
            <span class="text-xs text-gray-600">إدارة بيانات التجار</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-gray-300 text-gray-900 rounded-lg cursor-pointer hover:border-blue-600 hover:shadow-md transition-all" @click="router.push('/staff')">
            <Users class="h-5 w-5 flex-shrink-0 text-blue-600 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">مستخدمو البنك</span>
            <span class="text-xs text-gray-600">إدارة موظفي البنك</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-gray-300 text-gray-900 rounded-lg cursor-pointer hover:border-blue-600 hover:shadow-md transition-all" @click="router.push('/reports')">
            <BarChart3 class="h-5 w-5 flex-shrink-0 text-blue-600 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">التقارير</span>
            <span class="text-xs text-gray-600">تقارير وتحليلات البنك</span>
          </button>
        </div>
      </section>

      <!-- Monthly chart -->
      <Card v-if="stats.monthly_requests.length" class="border-0 shadow-card" aria-labelledby="chart-heading">
        <CardContent class="p-4">
          <h2 id="chart-heading" class="text-sm font-semibold text-gray-900 mb-2">حركة طلبات البنك الشهرية</h2>
          <p class="text-xs text-gray-600 mb-3">تتابع ملك الشهر المُقدَّم</p>
          <div class="flex flex-col gap-1.5">
            <svg
              :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
              class="w-full h-20"
              aria-label="مخطط الطلبات الشهرية"
              role="img"
              preserveAspectRatio="none"
            >
              <polygon :points="buildArea(stats.monthly_requests)" fill="#0066cc" opacity="0.08" />
              <polyline
                :points="buildLine(stats.monthly_requests)"
                fill="none"
                stroke="#0066cc"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
              />
            </svg>
            <div class="flex justify-between px-2">
              <span v-for="entry in stats.monthly_requests" :key="entry.month" class="text-xs text-gray-600">
                {{ monthLabel(entry.month) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Recent requests -->
      <Card class="border-0 shadow-card" aria-labelledby="recent-heading">
        <CardContent class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 id="recent-heading" class="text-sm font-semibold text-gray-900">أحدث الطلبات</h2>
            <a class="text-xs text-blue-600 hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
          </div>

          <div v-if="stats.recent_requests.length === 0" class="py-8 text-center text-sm text-gray-600" role="status">لا توجد طلبات بعد</div>

          <table v-else class="w-full border-collapse text-xs" role="table" aria-label="أحدث طلبات البنك">
            <thead>
              <tr class="border-b border-gray-200">
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">المرجع</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">التاجر</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">المبلغ</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">الحالة</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">التقدم</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-gray-600">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in stats.recent_requests" :key="req.id" class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                <td class="py-2 px-2"><a class="font-mono text-blue-600 hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td class="py-2 px-2 text-gray-900">{{ req.merchant?.name ?? req.supplier_name }}</td>
                <td class="py-2 px-2 text-gray-900 direction-ltr font-tabular-nums">{{ formatAmount(req.amount) }} {{ req.currency }}</td>
                <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.BANK_ADMIN" /></td>
                <td class="py-2 px-2">
                  <div class="flex items-center gap-2 min-w-24">
                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                      <div class="h-full bg-blue-600 transition-all" :style="{ width: `${getRequestProgress(req.status)}%` }" />
                    </div>
                    <span class="text-xs text-gray-600 whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                  </div>
                </td>
                <td class="py-2 px-2"><button class="px-2 py-1 bg-white border border-gray-300 text-xs text-gray-900 rounded hover:border-blue-600 hover:text-blue-600 transition-colors" :aria-label="`عرض الطلب ${req.reference_number}`" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

    </template>
  </div>
</template>
