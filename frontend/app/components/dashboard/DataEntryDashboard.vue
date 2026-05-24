<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, FileText, Zap, Bell, AlertCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { DataEntryDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getBusinessStatus } from '../../constants/workflow'
import { Card, CardContent } from '../ui/card'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as DataEntryDashboardStats | null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-success bg-success/10',
    blue: 'text-primary bg-primary/10',
    amber: 'text-warning bg-warning/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] || colors.gray
}

const kpiConfig = computed(() => [
  { icon: CheckCircle2, value: stats.value?.completed ?? 0, label: 'مكتمل / صدر البيان', variant: 'green' },
  { icon: Clock, value: stats.value?.under_cby_processing ?? 0, label: 'قيد المعالجة', variant: 'blue' },
  { icon: RotateCcw, value: stats.value?.returned ?? 0, label: 'بحاجة تعديل', variant: stats.value?.returned ?? 0 > 0 ? 'amber' : 'gray' },
  { icon: FileText, value: stats.value?.draft ?? 0, label: 'مسودات أم أفكار', variant: 'gray' },
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

      <!-- KPI grid: مكتمل / صدر البيان | قيد المعالجة | بحاجة تعديل | مسودات -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card class="border-0 p-4 shadow-card flex flex-col gap-1.5" :class="{ 'border-s-4 border-s-amber-600': kpi.variant === 'amber' }">
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span class="text-2xl font-semibold leading-none" :class="kpi.variant === 'amber' && kpi.value > 0 ? 'text-warning' : kpi.variant === 'green' ? 'text-success' : 'text-foreground'">
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
        <div class="grid grid-cols-3 max-md:grid-cols-1 gap-3">
          <!-- إنشاء طلب جديد -->
          <button class="flex flex-col items-start gap-1 p-4 bg-primary text-white border-0 rounded-lg cursor-pointer hover:hover:opacity-90 transition-colors" @click="router.push('/requests/new')">
            <FileText class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">إنشاء طلب جديد</span>
            <span class="text-xs opacity-75">لبدء طلب تمويل جديد</span>
          </button>

          <!-- متابعة طلباتك -->
          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-border text-foreground rounded-lg cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/requests')">
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">متابعة طلباتك</span>
            <span class="text-xs text-muted-foreground">كل ما قدّمت رأيناه</span>
          </button>

          <!-- الإشعارات -->
          <button class="flex flex-col items-start gap-1 p-4 bg-white border border-border text-foreground rounded-lg cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/notifications')">
            <Bell class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر التحديثات على طلباتك</span>
          </button>
        </div>
      </section>

      <!-- Returned requests attention card -->
      <Card v-if="stats.returned_requests.length > 0" class="border-l-4 border-l-amber-600 border-0 border-b border-border bg-white" role="alert" aria-label="طلبات تحتاج تعديل">
        <CardContent class="pt-4 flex flex-col gap-3">
          <div class="flex items-center gap-2">
            <AlertCircle class="h-4 w-4 flex-shrink-0 text-warning" aria-hidden="true" />
            <span class="font-semibold text-foreground text-sm">طلبات تستلزم منك تعديلاً ({{ stats.returned_requests.length }})</span>
          </div>
          <ul class="flex flex-col gap-2 border-t border-border pt-2">
            <li v-for="(req, i) in stats.returned_requests" :key="req.id" :class="i > 0 && 'border-t border-border pt-2'">
              <a
                :href="`/requests/${req.id}`"
                class="flex items-center gap-3 text-sm text-foreground hover:text-primary transition-colors"
                @click.prevent="router.push(`/requests/${req.id}`)"
              >
                <span class="font-mono text-primary hover:underline">{{ req.reference_number }}</span>
                <span class="text-muted-foreground">{{ req.supplier_name }}</span>
              </a>
            </li>
          </ul>
        </CardContent>
      </Card>

      <!-- Two-column: مسوداتي | آخر نشاطي -->
      <div class="grid grid-cols-2 max-lg:grid-cols-1 gap-4">

        <!-- مسوداتي (draft requests) -->
        <Card class="border-0 shadow-card" aria-labelledby="drafts-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="drafts-heading" class="text-sm font-semibold text-foreground">مسوداتي</h2>
              <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
            </div>
            <div v-if="stats.draft_requests.length === 0" class="py-6 text-center text-sm text-muted-foreground" role="status">لا توجد مسودات بعد</div>
            <table v-else class="w-full border-collapse text-xs" role="table" aria-label="مسوداتي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">التاجر</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in stats.draft_requests"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-white border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

        <!-- آخر نشاطي (recent requests) -->
        <Card class="border-0 shadow-card" aria-labelledby="recent-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="recent-heading" class="text-sm font-semibold text-foreground">آخر نشاطي</h2>
              <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests')">عرض الكل</a>
            </div>
            <div v-if="stats.recent_requests.length === 0" class="py-6 text-center text-sm text-muted-foreground" role="status">لا توجد طلبات بعد</div>
            <table v-else class="w-full border-collapse text-xs" role="table" aria-label="آخر نشاطي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">التاجر</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in stats.recent_requests"
                  :key="req.id"
                  class="border-t border-muted hover:bg-muted cursor-pointer transition-colors"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.DATA_ENTRY" /></td>
                  <td class="py-2 px-2"><button class="px-2 py-1 bg-white border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

      </div>

    </template>
  </div>
</template>
