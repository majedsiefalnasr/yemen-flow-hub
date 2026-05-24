// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/support-committee page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Users, Clock, Mail, Zap, AlertCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { SupportCommitteeDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardContent } from '../ui/card'

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

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function claimOwnerLabel(req: SupportCommitteeDashboardStats['support_queue'][number]): string {
  if (!req.claimed_by) return 'غير مطالب به'
  if (req.is_claimed_by_me || (currentUserId.value != null && req.claimed_by.id === currentUserId.value)) {
    return `${req.claimed_by.name} (أنت)`
  }
  return req.claimed_by.name
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-success bg-success/10',
    indigo: 'text-indigo-600 bg-indigo-50',
    amber: 'text-warning bg-warning/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] || colors.gray
}

const kpiConfig = computed(() => [
  { icon: CheckCircle2, value: stats.value?.recently_approved ?? 0, label: 'اعتُمِدت مؤخراً', variant: 'green' },
  { icon: Users, value: stats.value?.claimed_by_others ?? 0, label: 'محجوزة لأعضاء آخرين', variant: 'gray' },
  { icon: Clock, value: stats.value?.active_by_me ?? 0, label: 'أعمل عليها الآن', variant: stats.value?.active_by_me ?? 0 > 0 ? 'indigo' : 'gray' },
  { icon: Mail, value: stats.value?.waiting_for_claim ?? 0, label: 'بانتظار المطالبة', variant: stats.value?.waiting_for_claim ?? 0 > 0 ? 'amber' : 'gray' },
])

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow-card animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-muted rounded mb-3" />
        <div class="h-8 w-10 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-destructive border-b border-border border-r bg-white" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-destructive" aria-hidden="true" />
        <span class="text-destructive flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-white border border-destructive rounded-lg text-destructive text-sm cursor-pointer hover:bg-destructive/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card class="border-0 p-4 shadow-card flex flex-col gap-1.5" :class="{ 'border-s-4 border-s-amber-600': kpi.variant === 'amber', 'border-s-4 border-s-indigo-600': kpi.variant === 'indigo' }">
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span class="text-2xl font-semibold leading-none" :class="kpi.variant === 'amber' && kpi.value > 0 ? 'text-warning' : kpi.variant === 'indigo' && kpi.value > 0 ? 'text-indigo-600' : kpi.variant === 'green' ? 'text-success' : 'text-foreground'">
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
          <button class="flex flex-col items-start gap-1 p-4 bg-primary text-white border-0 rounded-lg cursor-pointer hover:hover:opacity-90 transition-colors" @click="router.push('/requests')">
            <Users class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المساندة</span>
            <span class="text-xs opacity-75">{{ stats.waiting_for_claim }} طلب جاهز للمراجعة</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-border text-foreground rounded-lg cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/notifications')">
            <Mail class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر تحديثات الطابور والقرارات</span>
          </button>
        </div>
      </section>

      <!-- Support queue -->
      <Card class="border-0 shadow-card" aria-labelledby="queue-heading">
        <CardContent class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 id="queue-heading" class="text-sm font-semibold text-foreground">طابور عملي</h2>
            <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
          </div>

          <div v-if="queue.length === 0" class="py-8 text-center text-sm text-muted-foreground" role="status">لا توجد طلبات في طابور لجنة الدعم حالياً</div>

          <table v-else class="w-full border-collapse text-xs" role="table" aria-label="طابور عملي">
            <thead>
              <tr class="border-b border-border">
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المورد</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحجز</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">التقدم</th>
                <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="req in queue"
                :key="req.id"
                class="border-t border-muted hover:bg-muted cursor-pointer transition-colors"
                :class="{
                  'bg-primary/10 hover:bg-primary/10': req.is_claimed_by_me,
                  'bg-warning/10 hover:bg-warning/10': !!req.claimed_by && !req.is_claimed_by_me,
                }"
              >
                <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.SUPPORT_COMMITTEE" /></td>
                <td class="py-2 px-2">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                    :class="{
                      'bg-indigo-100 text-indigo-700': req.is_claimed_by_me,
                      'bg-warning/10 text-warning': !!req.claimed_by && !req.is_claimed_by_me,
                      'bg-muted text-foreground': !req.claimed_by,
                    }"
                  >
                    {{ claimOwnerLabel(req) }}
                  </span>
                </td>
                <td class="py-2 px-2">
                  <div class="flex items-center gap-2 min-w-24">
                    <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                      <div class="h-full transition-all" :style="{ width: `${getRequestProgress(req.status)}%`, backgroundColor: req.is_claimed_by_me ? '#5856d6' : '#0066cc' }" />
                    </div>
                    <span class="text-xs text-muted-foreground whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                  </div>
                </td>
                <td class="py-2 px-2"><button class="px-2 py-1 bg-white border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" :aria-label="`عرض الطلب ${req.reference_number}`" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

    </template>
  </div>
</template>
