<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { CbyAdminDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AlertCircle, FileText, Users, Clock, CheckCircle2, TrendingUp } from 'lucide-vue-next'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as CbyAdminDashboardStats | null)
const monthlyRequests = computed(() => stats.value?.monthly_requests ?? [])
const categoryDistribution = computed(() => stats.value?.category_distribution ?? [])
const hasChartContent = computed(() => monthlyRequests.value.length > 0 || categoryDistribution.value.length > 0)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function formatUpdatedAt(iso: string | null): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('ar-YE', { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(iso))
}

interface MonthlyEntry { month: string; submitted: number; approved: number }
const CHART_W = 600
const CHART_H = 100
const PAD = 12

function buildLine(entries: MonthlyEntry[], key: keyof MonthlyEntry): string {
  if (!entries.length) return ''
  const vals = entries.map(e => Number(e[key]))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  return entries.map((e, i) => {
    const x = PAD + i * step
    const y = PAD + (1 - Number(e[key]) / max) * (CHART_H - PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function buildArea(entries: MonthlyEntry[], key: keyof MonthlyEntry): string {
  if (!entries.length) return ''
  const vals = entries.map(e => Number(e[key]))
  const max = Math.max(...vals, 1)
  const step = (CHART_W - PAD * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = PAD + i * step
    const y = PAD + (1 - Number(e[key]) / max) * (CHART_H - PAD * 2)
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

interface CategoryEntry { label: string; count: number; color: string }

function buildDonutPath(entries: CategoryEntry[], index: number, cx: number, cy: number, r: number): string {
  const total = entries.reduce((s, e) => s + e.count, 0)
  if (!total) return ''
  let startAngle = -Math.PI / 2
  for (let i = 0; i < index; i++) {
    startAngle += (entries[i]!.count / total) * 2 * Math.PI
  }
  const angle = (entries[index]!.count / total) * 2 * Math.PI
  const endAngle = startAngle + angle
  const x1 = cx + r * Math.cos(startAngle)
  const y1 = cy + r * Math.sin(startAngle)
  const x2 = cx + r * Math.cos(endAngle)
  const y2 = cy + r * Math.sin(endAngle)
  const largeArc = angle > Math.PI ? 1 : 0
  return `M ${cx} ${cy} L ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${largeArc} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} Z`
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">
    <!-- Loading state -->
    <div v-if="store.loading" class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <Card v-for="n in 4" :key="n" aria-hidden="true">
        <CardHeader class="pb-3">
          <Skeleton class="h-3 w-2/5" />
        </CardHeader>
        <CardContent>
          <Skeleton class="h-8 w-1/2" />
        </CardContent>
      </Card>
    </div>

    <!-- Error state -->
    <Alert v-else-if="store.error" variant="destructive" role="alert">
      <AlertCircle class="size-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ store.error }}</span>
        <Button variant="outline" size="sm" @click="store.loadStats()">إعادة المحاولة</Button>
      </AlertDescription>
    </Alert>

    <template v-else-if="stats">
      <!-- KPI grid -->
      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1">
        <Card>
          <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm font-medium">بنوك مشاركة</CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold">{{ stats.most_active_banks.length }}</div>
            <p class="text-xs text-muted-foreground mt-1">البنوك النشطة</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="text-sm font-medium">كل الطلبات</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold">{{ stats.total }}</div>
            <p class="text-xs text-muted-foreground mt-1">إجمالي الطلبات</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="text-sm font-medium">طلبات معلقة</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold text-orange-600">{{ stats.in_process }}</div>
            <p class="text-xs text-muted-foreground mt-1">قيد المراجعة</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="text-sm font-medium">معتمدة</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold text-green-600">{{ stats.approved }}</div>
            <p class="text-xs text-muted-foreground mt-1">الطلبات المعتمدة</p>
          </CardContent>
        </Card>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="text-sm font-semibold mb-4 flex items-center gap-2">
          <TrendingUp class="size-4" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-4 gap-3 md:grid-cols-2 sm:grid-cols-1">
          <Button variant="default" class="h-auto flex flex-col items-start justify-start p-4" @click="router.push('/requests')">
            <FileText class="size-5 mb-2" />
            <span class="text-sm font-semibold">سجل الطلبات</span>
            <span class="text-xs opacity-80">كل طلبات المنصة</span>
          </Button>

          <Button variant="outline" class="h-auto flex flex-col items-start justify-start p-4" @click="router.push('/reports')">
            <TrendingUp class="size-5 mb-2" />
            <span class="text-sm font-semibold">التقارير</span>
            <span class="text-xs text-muted-foreground">تحليلات وإحصاءات</span>
          </Button>

          <Button variant="outline" class="h-auto flex flex-col items-start justify-start p-4" @click="router.push('/admin/cby-staff')">
            <Users class="size-5 mb-2" />
            <span class="text-sm font-semibold">مستخدمو النظام</span>
            <span class="text-xs text-muted-foreground">إدارة الصلاحيات</span>
          </Button>

          <Button variant="outline" class="h-auto flex flex-col items-start justify-start p-4" @click="router.push('/audit')">
            <AlertCircle class="size-5 mb-2" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر أحداث المنصة</span>
          </Button>
        </div>
      </section>

      <!-- Charts row: monthly trend + category distribution -->
      <div v-if="hasChartContent" class="grid grid-cols-2fr-1fr gap-4 lg:grid-cols-1">
        <!-- Monthly trend chart -->
        <Card v-if="monthlyRequests.length" aria-labelledby="trend-heading">
          <CardHeader>
            <CardTitle id="trend-heading">حركة الطلبات الشهرية</CardTitle>
            <CardDescription>مُقدَّم مقابل مُعتمَد</CardDescription>
          </CardHeader>
          <CardContent class="flex flex-col gap-3">
            <svg :viewBox="`0 0 ${CHART_W} ${CHART_H}`" class="w-full h-24" role="img" aria-label="مخطط الطلبات الشهرية" preserveAspectRatio="none">
              <polygon :points="buildArea(monthlyRequests as MonthlyEntry[], 'submitted')" fill="currentColor" class="text-primary" opacity="0.08" />
              <polyline :points="buildLine(monthlyRequests as MonthlyEntry[], 'submitted')" fill="none" stroke="currentColor" class="text-primary" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <polygon :points="buildArea(monthlyRequests as MonthlyEntry[], 'approved')" fill="currentColor" class="text-green-600" opacity="0.08" />
              <polyline :points="buildLine(monthlyRequests as MonthlyEntry[], 'approved')" fill="none" stroke="currentColor" class="text-green-600" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="4 2" />
            </svg>
            <div class="flex justify-between px-3 text-xs text-muted-foreground">
              <span v-for="e in (monthlyRequests as MonthlyEntry[])" :key="e.month">{{ monthLabel(e.month) }}</span>
            </div>
            <div class="flex gap-4">
              <Badge variant="secondary" class="w-fit">
                <span class="size-2 rounded-full bg-primary mr-2" />
                مُقدَّم
              </Badge>
              <Badge variant="secondary" class="w-fit">
                <span class="size-2 rounded-full bg-green-600 mr-2" />
                مُعتمَد
              </Badge>
            </div>
          </CardContent>
        </Card>

        <!-- Category distribution donut -->
        <Card v-if="categoryDistribution.length" aria-labelledby="cat-heading">
          <CardHeader>
            <CardTitle id="cat-heading">توزيع فئات الواردات</CardTitle>
            <CardDescription>حسب نوع البضاعة</CardDescription>
          </CardHeader>
          <CardContent class="flex items-center gap-6">
            <svg viewBox="0 0 100 100" class="size-24 flex-shrink-0" role="img" aria-label="توزيع فئات الواردات">
              <circle cx="50" cy="50" r="38" fill="hsl(var(--muted))" />
              <path
                v-for="(entry, i) in (categoryDistribution as CategoryEntry[])"
                :key="entry.label"
                :d="buildDonutPath(categoryDistribution as CategoryEntry[], i, 50, 50, 38)"
                :fill="entry.color"
              />
              <circle cx="50" cy="50" r="25" fill="white" />
            </svg>
            <ul class="flex flex-col gap-2">
              <li v-for="entry in (categoryDistribution as CategoryEntry[])" :key="entry.label" class="flex items-center gap-2 text-xs">
                <span class="size-2 rounded-full flex-shrink-0" :style="{ background: entry.color }" />
                <span class="text-foreground">{{ entry.label }}</span>
                <Badge variant="secondary">{{ Math.round(entry.count / (categoryDistribution as CategoryEntry[]).reduce((s, e) => s + e.count, 0) * 100) }}%</Badge>
              </li>
            </ul>
          </CardContent>
        </Card>
      </div>

      <!-- Two-column: أحدث الطلبات + أنشط البنوك -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
        <!-- أحدث الطلبات -->
        <Card aria-labelledby="recent-heading">
          <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle id="recent-heading">أحدث الطلبات</CardTitle>
            <Button variant="link" size="sm" @click="router.push('/requests')">عرض الكل</Button>
          </CardHeader>
          <CardContent>
            <div v-if="!stats.recent_requests?.length" class="py-6 text-center text-sm text-muted-foreground" role="status">
              لا توجد طلبات بعد
            </div>
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead class="text-right">المرجع</TableHead>
                  <TableHead class="text-right">البنك</TableHead>
                  <TableHead class="text-right">المبلغ</TableHead>
                  <TableHead class="text-right">الحالة</TableHead>
                  <TableHead class="text-right">التقدم</TableHead>
                  <TableHead class="text-right">إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="req in stats.recent_requests" :key="req.id" @click="router.push(`/requests/${req.id}`)" class="cursor-pointer">
                  <TableCell class="text-right font-mono text-sm">{{ req.reference_number }}</TableCell>
                  <TableCell class="text-right">{{ req.bank_name ?? '—' }}</TableCell>
                  <TableCell class="text-right ltr tabular-nums">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                  <TableCell class="text-right"><StatusBadge :status="req.status" :role="UserRole.CBY_ADMIN" /></TableCell>
                  <TableCell class="text-right">
                    <div class="flex items-center gap-2 min-w-24">
                      <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${getRequestProgress(req.status)}%` }" />
                      </div>
                      <span class="text-xs text-muted-foreground whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                    </div>
                  </TableCell>
                  <TableCell class="text-right">
                    <Button variant="outline" size="sm" @click.stop="router.push(`/requests/${req.id}`)">عرض</Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- Compliance alerts & Most active banks -->
        <Card aria-labelledby="banks-heading">
          <CardHeader>
            <CardTitle id="banks-heading">تصنيف الامتثال</CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Duplicate suppliers -->
            <div class="space-y-2">
              <h3 class="text-xs font-semibold text-muted-foreground">فاتورة مكررة خارجياً</h3>
              <div v-if="!stats.compliance_alerts.duplicate_suppliers.length" class="flex items-center gap-2 text-xs text-green-600">
                <CheckCircle2 class="size-4" />
                لا توجد تنبيهات
              </div>
              <ul v-else class="space-y-1">
                <li v-for="item in stats.compliance_alerts.duplicate_suppliers" :key="item.supplier_name" class="flex items-center justify-between p-2 bg-muted rounded text-xs">
                  <span>{{ item.supplier_name }}</span>
                  <Badge variant="secondary">{{ item.count }} طلب</Badge>
                </li>
              </ul>
            </div>

            <!-- High amount requests -->
            <div class="space-y-2 border-t pt-4">
              <h3 class="text-xs font-semibold text-muted-foreground">طلبات بمبالغ مرتفعة</h3>
              <div v-if="!stats.compliance_alerts.high_amount_requests.length" class="flex items-center gap-2 text-xs text-green-600">
                <CheckCircle2 class="size-4" />
                لا توجد تنبيهات
              </div>
              <ul v-else class="space-y-1">
                <li
                  v-for="req in stats.compliance_alerts.high_amount_requests"
                  :key="req.id"
                  class="flex items-center justify-between gap-2 p-2 bg-muted rounded text-xs cursor-pointer hover:bg-muted/80"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <div>
                    <div class="font-mono">{{ req.reference_number }}</div>
                    <div class="text-muted-foreground">{{ req.bank_name }}</div>
                  </div>
                  <Badge variant="destructive" class="whitespace-nowrap">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: req.currency, maximumFractionDigits: 0 }).format(req.amount) }}</Badge>
                </li>
              </ul>
            </div>

            <!-- Stale requests -->
            <div class="space-y-2 border-t pt-4">
              <h3 class="text-xs font-semibold text-muted-foreground">طلبات معلقة &gt; 14 يوم</h3>
              <div v-if="!stats.compliance_alerts.stale_pending_requests.length" class="flex items-center gap-2 text-xs text-green-600">
                <CheckCircle2 class="size-4" />
                لا توجد تنبيهات
              </div>
              <ul v-else class="space-y-1">
                <li
                  v-for="req in stats.compliance_alerts.stale_pending_requests"
                  :key="req.id"
                  class="flex items-center justify-between gap-2 p-2 bg-muted rounded text-xs cursor-pointer hover:bg-muted/80"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <div>
                    <div class="font-mono">{{ req.reference_number }}</div>
                    <div class="text-muted-foreground">{{ req.bank_name }}</div>
                  </div>
                  <Badge variant="secondary">{{ formatUpdatedAt(req.updated_at) }}</Badge>
                </li>
              </ul>
            </div>

            <!-- Most active banks -->
            <div v-if="stats.most_active_banks.length" class="space-y-2 border-t pt-4">
              <h3 class="text-xs font-semibold text-muted-foreground">أنشط البنوك</h3>
              <ul class="space-y-2">
                <li v-for="(bank, index) in stats.most_active_banks" :key="bank.bank_id" class="flex items-center gap-2">
                  <Badge class="size-6 flex items-center justify-center rounded-full p-0">{{ index + 1 }}</Badge>
                  <span class="text-xs truncate w-20">{{ bank.bank_name }}</span>
                  <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.round(bank.request_count / (stats.most_active_banks[0]?.request_count || 1) * 100)}%` }" />
                  </div>
                  <span class="text-xs text-muted-foreground whitespace-nowrap">{{ bank.request_count }}</span>
                </li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>

    </template>
  </div>
</template>

<style scoped>
.grid-cols-2fr-1fr { @apply lg:grid-cols-1; grid-template-columns: 2fr 1fr; }
</style>
