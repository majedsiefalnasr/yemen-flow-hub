<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { CbyAdminDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'

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

// ── Monthly trend chart (SVG) ─────────────────────────────────────────────
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

// ── Category distribution donut ───────────────────────────────────────────
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

    <!-- Skeleton -->
    <div v-if="store.loading">
      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
        <div v-for="n in 4" :key="n" class="bg-white border border-border rounded-md p-5 flex flex-col gap-3 animate-pulse" aria-hidden="true">
          <div class="h-3.5 w-2/5 bg-gray-200 rounded" />
          <div class="h-8 w-1/2 bg-gray-200 rounded" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="bg-white border border-border rounded-md p-5 text-error-text flex items-center gap-3" role="alert">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <span>{{ store.error }}</span>
      <button class="ml-auto px-4 py-1.5 bg-white border border-error-text rounded text-error-text text-xs hover:bg-red-50 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1">
        <!-- بنوك مشاركة -->
        <div class="bg-white border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-gray-100 flex items-center justify-center text-muted-foreground mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="22" x2="21" y2="22" /><rect x="2" y="6" width="20" height="16" rx="2" /><path d="M6 6V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" /><line x1="12" y1="12" x2="12" y2="18" /><line x1="9" y1="15" x2="15" y2="15" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-primary-text leading-none">{{ stats.most_active_banks.length }}</span>
          <span class="text-xs text-muted-foreground">بنوك مشاركة</span>
        </div>

        <!-- كل الطلبات -->
        <div class="bg-white border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-blue-50 flex items-center justify-center text-primary-blue mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-primary-text leading-none">{{ stats.total }}</span>
          <span class="text-xs text-muted-foreground">كل الطلبات</span>
        </div>

        <!-- طلبات معلقة -->
        <div class="border-l-4 border-l-warning-text bg-white border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-yellow-50 flex items-center justify-center text-warning-text mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-warning-text leading-none">{{ stats.in_process }}</span>
          <span class="text-xs text-muted-foreground">طلبات معلقة</span>
        </div>

        <!-- إجمالي الطلبات -->
        <div class="bg-white border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-green-50 flex items-center justify-center text-success-text mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-success-text leading-none">{{ stats.approved }}</span>
          <span class="text-xs text-muted-foreground">إجمالي الطلبات</span>
        </div>
      </div>

      <!-- Quick actions (4 cards) -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-bold text-primary-text mb-3">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
          </svg>
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-4 gap-3 md:grid-cols-2 sm:grid-cols-1">
          <button class="flex flex-col items-start gap-1 p-5 bg-primary-blue border border-primary-blue rounded-md hover:opacity-90 transition-opacity cursor-pointer text-white" @click="router.push('/requests')">
            <div class="w-8 h-8 flex items-center justify-center mb-1" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              </svg>
            </div>
            <span class="text-sm font-bold">سجل الطلبات</span>
            <span class="text-xs opacity-75">كل طلبات المنصة</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-5 bg-white border border-border rounded-md hover:border-primary-blue hover:text-primary-blue transition-colors cursor-pointer text-primary-text" @click="router.push('/reports')">
            <div class="w-8 h-8 flex items-center justify-center mb-1 text-primary-blue" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="text-sm font-bold">التقارير</span>
            <span class="text-xs text-muted-foreground">تحليلات وإحصاءات المنصة</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-5 bg-white border border-border rounded-md hover:border-primary-blue hover:text-primary-blue transition-colors cursor-pointer text-primary-text" @click="router.push('/admin/cby-staff')">
            <div class="w-8 h-8 flex items-center justify-center mb-1 text-primary-blue" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
              </svg>
            </div>
            <span class="text-sm font-bold">مستخدمو النظام</span>
            <span class="text-xs text-muted-foreground">إدارة الصلاحيات والمستخدمين</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-5 bg-white border border-border rounded-md hover:border-primary-blue hover:text-primary-blue transition-colors cursor-pointer text-primary-text" @click="router.push('/audit')">
            <div class="w-8 h-8 flex items-center justify-center mb-1 text-primary-blue" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <span class="text-sm font-bold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر أحداث المنصة</span>
          </button>
        </div>
      </section>

      <!-- Charts row: monthly trend + category distribution -->
      <div v-if="hasChartContent" class="grid grid-cols-2fr-1fr gap-4 lg:grid-cols-1">

        <!-- Monthly trend chart -->
        <section v-if="monthlyRequests.length" class="bg-white border border-border rounded-md p-6" aria-labelledby="trend-heading">
          <h2 id="trend-heading" class="text-sm font-bold text-primary-text mb-1">حركة الطلبات الشهرية</h2>
          <p class="text-xs text-muted-foreground mb-3">تتابع مُقدَّم مقابل مُعتمَد</p>
          <div class="flex flex-col gap-1.5">
            <svg :viewBox="`0 0 ${CHART_W} ${CHART_H}`" class="w-full h-24" role="img" aria-label="مخطط الطلبات الشهرية" preserveAspectRatio="none">
              <!-- submitted area -->
              <polygon :points="buildArea(monthlyRequests as MonthlyEntry[], 'submitted')" fill="#0066cc" opacity="0.08" />
              <polyline :points="buildLine(monthlyRequests as MonthlyEntry[], 'submitted')" fill="none" stroke="#0066cc" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <!-- approved area -->
              <polygon :points="buildArea(monthlyRequests as MonthlyEntry[], 'approved')" fill="#1b5e20" opacity="0.08" />
              <polyline :points="buildLine(monthlyRequests as MonthlyEntry[], 'approved')" fill="none" stroke="#1b5e20" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="4 2" />
            </svg>
            <div class="flex justify-between px-3 text-xs text-muted-foreground">
              <span v-for="e in (monthlyRequests as MonthlyEntry[])" :key="e.month">{{ monthLabel(e.month) }}</span>
            </div>
            <div class="flex gap-4 mt-1.5">
              <span class="flex items-center gap-1 text-xs text-muted-foreground"><span class="w-2.5 h-2.5 rounded-full" style="background: #0066cc" />مُقدَّم</span>
              <span class="flex items-center gap-1 text-xs text-muted-foreground"><span class="w-2.5 h-2.5 rounded-full" style="background: #1b5e20" />مُعتمَد</span>
            </div>
          </div>
        </section>

        <!-- Category distribution donut -->
        <section v-if="categoryDistribution.length" class="bg-white border border-border rounded-md p-6" aria-labelledby="cat-heading">
          <h2 id="cat-heading" class="text-sm font-bold text-primary-text mb-1">توزيع فئات الواردات</h2>
          <p class="text-xs text-muted-foreground mb-3">حسب نوع البضاعة</p>
          <div class="flex items-center gap-4">
            <svg viewBox="0 0 100 100" class="w-24 h-24 flex-shrink-0" role="img" aria-label="توزيع فئات الواردات">
              <circle cx="50" cy="50" r="38" fill="#f5f5f5" />
              <path
                v-for="(entry, i) in (categoryDistribution as CategoryEntry[])"
                :key="entry.label"
                :d="buildDonutPath(categoryDistribution as CategoryEntry[], i, 50, 50, 38)"
                :fill="entry.color"
              />
              <circle cx="50" cy="50" r="25" fill="#ffffff" />
            </svg>
            <ul class="flex flex-col gap-1.5">
              <li v-for="entry in (categoryDistribution as CategoryEntry[])" :key="entry.label" class="flex items-center gap-1.5 text-xs">
                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: entry.color }" />
                <span class="text-primary-text">{{ entry.label }}</span>
                <span class="text-muted-foreground">{{ Math.round(entry.count / (categoryDistribution as CategoryEntry[]).reduce((s, e) => s + e.count, 0) * 100) }}%</span>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <!-- Two-column: أحدث الطلبات + أنشط البنوك -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">

        <!-- أحدث الطلبات -->
        <section class="bg-white border border-border rounded-md p-6 flex flex-col gap-4" aria-labelledby="recent-heading">
          <div class="flex items-center justify-between">
            <h2 id="recent-heading" class="text-sm font-bold text-primary-text">أحدث الطلبات</h2>
            <a class="text-xs text-primary-blue hover:underline" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
          </div>
          <div v-if="!stats.recent_requests?.length" class="py-6 text-center text-sm text-muted-foreground" role="status">
            <p>لا توجد طلبات بعد</p>
          </div>
          <table v-else class="w-full border-collapse text-xs" aria-label="أحدث الطلبات">
            <thead>
              <tr class="bg-gray-50">
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">المرجع</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">البنك</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">المبلغ</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">الحالة</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">التقدم</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-gray-200 whitespace-nowrap">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in stats.recent_requests" :key="req.id" class="hover:bg-gray-50 border-t border-gray-200 cursor-pointer" @click="router.push(`/requests/${req.id}`)">
                <td class="text-right py-2.5 px-3.5 text-primary-text"><a class="font-mono text-primary-blue hover:underline" href="#" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td class="text-right py-2.5 px-3.5 text-primary-text">{{ req.bank_name ?? '—' }}</td>
                <td class="text-right py-2.5 px-3.5 text-primary-text ltr tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                <td class="text-right py-2.5 px-3.5"><StatusBadge :status="req.status" :role="UserRole.CBY_ADMIN" /></td>
                <td class="text-right py-2.5 px-3.5">
                  <div class="flex items-center gap-1.5 min-w-24">
                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                      <div class="h-full rounded-full" :style="{ width: `${getRequestProgress(req.status)}%`, backgroundColor: '#0066cc' }" />
                    </div>
                    <span class="text-xs text-muted-foreground whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                  </div>
                </td>
                <td class="text-right py-2.5 px-3.5"><button class="px-3.5 py-1.5 bg-white border border-border rounded text-xs text-primary-text hover:border-primary-blue hover:text-primary-blue transition-colors" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- أنشط البنوك -->
        <section class="bg-white border border-border rounded-md p-6 flex flex-col gap-4" aria-labelledby="banks-heading">
          <h2 id="banks-heading" class="text-sm font-bold text-primary-text">تصنيف الامتثال</h2>

          <!-- compliance alerts compact -->
          <div class="flex flex-col gap-1.5 py-3 border-b border-gray-200">
            <h3 class="text-xs font-bold text-muted-foreground">فاتورة مكررة خارجياً</h3>
            <div v-if="!stats.compliance_alerts.duplicate_suppliers.length" class="flex items-center gap-1.5 text-xs text-success-text">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
              لا توجد تنبيهات
            </div>
            <ul v-else class="flex flex-col gap-1">
              <li v-for="item in stats.compliance_alerts.duplicate_suppliers" :key="item.supplier_name" class="flex items-center justify-between gap-2 py-1.5 px-2 bg-gray-50 rounded text-xs">
                <span class="text-primary-text">{{ item.supplier_name }}</span>
                <span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-warning-text rounded-full text-xs font-medium">{{ item.count }} طلب</span>
              </li>
            </ul>
          </div>

          <div class="flex flex-col gap-1.5 py-3 border-b border-gray-200">
            <h3 class="text-xs font-bold text-muted-foreground">طلبات بمبالغ مرتفعة</h3>
            <div v-if="!stats.compliance_alerts.high_amount_requests.length" class="flex items-center gap-1.5 text-xs text-success-text">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
              لا توجد تنبيهات
            </div>
            <ul v-else class="flex flex-col gap-1">
              <li
                v-for="req in stats.compliance_alerts.high_amount_requests"
                :key="req.id"
                class="flex items-center justify-between gap-2 py-1.5 px-2 bg-gray-50 rounded text-xs cursor-pointer hover:bg-gray-100"
                @click="router.push(`/requests/${req.id}`)"
              >
                <div class="flex flex-col gap-0.5">
                  <span class="font-mono text-primary-blue">{{ req.reference_number }}</span>
                  <span class="text-primary-text">{{ req.bank_name }}</span>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-error-text rounded-full text-xs font-medium whitespace-nowrap">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: req.currency, maximumFractionDigits: 0 }).format(req.amount) }}</span>
              </li>
            </ul>
          </div>

          <div class="flex flex-col gap-1.5 py-3 border-b border-gray-200">
            <h3 class="text-xs font-bold text-muted-foreground">طلبات معلقة منذ أكثر من 14 يوماً</h3>
            <div v-if="!stats.compliance_alerts.stale_pending_requests.length" class="flex items-center gap-1.5 text-xs text-success-text">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
              لا توجد تنبيهات
            </div>
            <ul v-else class="flex flex-col gap-1">
              <li
                v-for="req in stats.compliance_alerts.stale_pending_requests"
                :key="req.id"
                class="flex items-center justify-between gap-2 py-1.5 px-2 bg-gray-50 rounded text-xs cursor-pointer hover:bg-gray-100"
                @click="router.push(`/requests/${req.id}`)"
              >
                <div class="flex flex-col gap-0.5">
                  <span class="font-mono text-primary-blue">{{ req.reference_number }}</span>
                  <span class="text-primary-text">{{ req.bank_name }}</span>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-warning-text rounded-full text-xs font-medium whitespace-nowrap">{{ formatUpdatedAt(req.updated_at) }}</span>
              </li>
            </ul>
          </div>

          <!-- Most active banks bar list -->
          <div v-if="stats.most_active_banks.length" class="flex flex-col gap-1.5">
            <h3 class="text-xs font-bold text-muted-foreground">أنشط البنوك</h3>
            <ul class="flex flex-col gap-1.5">
              <li v-for="(bank, index) in stats.most_active_banks" :key="bank.bank_id" class="flex items-center gap-2">
                <span class="w-5 h-5 bg-primary-blue text-white rounded-full text-xs font-bold flex items-center justify-center flex-shrink-0">{{ index + 1 }}</span>
                <span class="text-xs text-primary-text w-20 truncate">{{ bank.bank_name }}</span>
                <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :style="{ width: `${Math.round(bank.request_count / (stats.most_active_banks[0]?.request_count || 1) * 100)}%`, backgroundColor: '#0066cc' }" />
                </div>
                <span class="text-xs text-muted-foreground whitespace-nowrap">{{ bank.request_count }}</span>
              </li>
            </ul>
          </div>

        </section>
      </div>

    </template>
  </div>
</template>

<style scoped>
.grid-cols-2fr-1fr { grid-template-columns: 2fr 1fr; }
@media (max-width: 900px) { .grid-cols-2fr-1fr { grid-template-columns: 1fr; } }
</style>
