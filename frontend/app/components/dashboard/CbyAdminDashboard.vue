<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { CbyAdminDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as CbyAdminDashboardStats | null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
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
  <div class="cby-dashboard" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading">
      <div class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
        <div v-for="n in 4" :key="n" class="kpi-card kpi-skeleton" aria-hidden="true">
          <div class="skel skel--label" />
          <div class="skel skel--value" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="error-card" role="alert">
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="kpi-grid">
        <!-- بنوك مشاركة -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--gray" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="22" x2="21" y2="22" /><rect x="2" y="6" width="20" height="16" rx="2" /><path d="M6 6V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" /><line x1="12" y1="12" x2="12" y2="18" /><line x1="9" y1="15" x2="15" y2="15" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.most_active_banks.length }}</span>
          <span class="kpi-card__label">بنوك مشاركة</span>
        </div>

        <!-- كل الطلبات -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--blue" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.total }}</span>
          <span class="kpi-card__label">كل الطلبات</span>
        </div>

        <!-- طلبات معلقة -->
        <div class="kpi-card kpi-card--amber">
          <div class="kpi-card__icon kpi-icon--amber" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.in_process }}</span>
          <span class="kpi-card__label">طلبات معلقة</span>
        </div>

        <!-- إجمالي الطلبات -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.approved }}</span>
          <span class="kpi-card__label">إجمالي الطلبات</span>
        </div>
      </div>

      <!-- Quick actions (4 cards) -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="section-heading">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
          </svg>
          إجراءات سريعة
        </h2>
        <div class="quick-actions">
          <button class="qa-card qa-card--primary" @click="router.push('/requests')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              </svg>
            </div>
            <span class="qa-card__label">سجل الطلبات</span>
            <span class="qa-card__sub">كل طلبات المنصة</span>
          </button>

          <button class="qa-card" @click="router.push('/reports')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="qa-card__label">التقارير</span>
            <span class="qa-card__sub">تحليلات وإحصاءات المنصة</span>
          </button>

          <button class="qa-card" @click="router.push('/admin/cby-staff')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
              </svg>
            </div>
            <span class="qa-card__label">مستخدمو النظام</span>
            <span class="qa-card__sub">إدارة الصلاحيات والمستخدمين</span>
          </button>

          <button class="qa-card" @click="router.push('/audit')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <span class="qa-card__label">الإشعارات</span>
            <span class="qa-card__sub">آخر أحداث المنصة</span>
          </button>
        </div>
      </section>

      <!-- Charts row: monthly trend + category distribution -->
      <div v-if="stats.monthly_requests?.length" class="charts-row">

        <!-- Monthly trend chart -->
        <section class="chart-card chart-card--wide" aria-labelledby="trend-heading">
          <h2 id="trend-heading" class="section-title">حركة الطلبات الشهرية</h2>
          <p class="chart-subtitle">تتابع مُقدَّم مقابل مُعتمَد</p>
          <div class="chart-wrap">
            <svg :viewBox="`0 0 ${CHART_W} ${CHART_H}`" class="sparkline" role="img" aria-label="مخطط الطلبات الشهرية" preserveAspectRatio="none">
              <!-- submitted area -->
              <polygon :points="buildArea(stats.monthly_requests as MonthlyEntry[], 'submitted')" fill="#0066cc" opacity="0.08" />
              <polyline :points="buildLine(stats.monthly_requests as MonthlyEntry[], 'submitted')" fill="none" stroke="#0066cc" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <!-- approved area -->
              <polygon :points="buildArea(stats.monthly_requests as MonthlyEntry[], 'approved')" fill="#1b5e20" opacity="0.08" />
              <polyline :points="buildLine(stats.monthly_requests as MonthlyEntry[], 'approved')" fill="none" stroke="#1b5e20" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="4 2" />
            </svg>
            <div class="chart-labels">
              <span v-for="e in (stats.monthly_requests as MonthlyEntry[])" :key="e.month" class="chart-label">{{ monthLabel(e.month) }}</span>
            </div>
            <div class="chart-legend">
              <span class="legend-item"><span class="legend-dot legend-dot--blue" />مُقدَّم</span>
              <span class="legend-item"><span class="legend-dot legend-dot--green" />مُعتمَد</span>
            </div>
          </div>
        </section>

        <!-- Category distribution donut -->
        <section v-if="stats.category_distribution?.length" class="chart-card chart-card--narrow" aria-labelledby="cat-heading">
          <h2 id="cat-heading" class="section-title">توزيع فئات الواردات</h2>
          <p class="chart-subtitle">حسب نوع البضاعة</p>
          <div class="donut-wrap">
            <svg viewBox="0 0 100 100" class="donut-svg" role="img" aria-label="توزيع فئات الواردات">
              <circle cx="50" cy="50" r="38" fill="#f5f5f5" />
              <path
                v-for="(entry, i) in (stats.category_distribution as CategoryEntry[])"
                :key="entry.label"
                :d="buildDonutPath(stats.category_distribution as CategoryEntry[], i, 50, 50, 38)"
                :fill="entry.color"
              />
              <circle cx="50" cy="50" r="25" fill="#ffffff" />
            </svg>
            <ul class="donut-legend">
              <li v-for="entry in (stats.category_distribution as CategoryEntry[])" :key="entry.label" class="donut-legend__item">
                <span class="donut-legend__dot" :style="{ background: entry.color }" />
                <span class="donut-legend__label">{{ entry.label }}</span>
                <span class="donut-legend__pct">{{ Math.round(entry.count / (stats.category_distribution as CategoryEntry[]).reduce((s, e) => s + e.count, 0) * 100) }}%</span>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <!-- Two-column: أحدث الطلبات + أنشط البنوك -->
      <div class="two-col">

        <!-- أحدث الطلبات -->
        <section class="section-card" aria-labelledby="recent-heading">
          <div class="section-card__header">
            <h2 id="recent-heading" class="section-title">أحدث الطلبات</h2>
            <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
          </div>
          <div v-if="!stats.recent_requests?.length" class="empty-state" role="status">
            <p>لا توجد طلبات بعد</p>
          </div>
          <table v-else class="req-table" aria-label="أحدث الطلبات">
            <thead>
              <tr>
                <th scope="col">المرجع</th>
                <th scope="col">البنك</th>
                <th scope="col">المبلغ</th>
                <th scope="col">الحالة</th>
                <th scope="col">التقدم</th>
                <th scope="col">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in stats.recent_requests" :key="req.id" class="req-table__row" @click="router.push(`/requests/${req.id}`)">
                <td><a class="req-ref" href="#" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td>{{ req.bank_name ?? '—' }}</td>
                <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
                <td><StatusBadge :status="req.status" :role="UserRole.CBY_ADMIN" /></td>
                <td class="progress-cell">
                  <div class="progress-bar">
                    <div class="progress-bar__fill" style="width:40%" />
                  </div>
                  <span class="progress-pct">40%</span>
                </td>
                <td><button class="btn-action" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- أنشط البنوك -->
        <section class="section-card" aria-labelledby="banks-heading">
          <h2 id="banks-heading" class="section-title">تصنيف الامتثال</h2>

          <!-- compliance alerts compact -->
          <div class="compliance-group">
            <h3 class="compliance-subtitle">فاتورة مكررة خارجياً</h3>
            <div v-if="!stats.compliance_alerts.duplicate_suppliers.length" class="compliance-ok">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1b5e20" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
              لا توجد تنبيهات
            </div>
            <ul v-else class="compliance-list">
              <li v-for="item in stats.compliance_alerts.duplicate_suppliers" :key="item.supplier_name" class="compliance-item">
                <span class="compliance-name">{{ item.supplier_name }}</span>
                <span class="badge badge--amber">{{ item.count }} طلب</span>
              </li>
            </ul>
          </div>

          <div class="compliance-group">
            <h3 class="compliance-subtitle">طلبات بمبالغ مرتفعة</h3>
            <div v-if="!stats.compliance_alerts.high_amount_requests.length" class="compliance-ok">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1b5e20" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
              لا توجد تنبيهات
            </div>
            <ul v-else class="compliance-list">
              <li
                v-for="req in stats.compliance_alerts.high_amount_requests"
                :key="req.id"
                class="compliance-item compliance-item--link"
                @click="router.push(`/requests/${req.id}`)"
              >
                <span class="compliance-ref">{{ req.reference_number }}</span>
                <span class="compliance-name">{{ req.bank_name }}</span>
                <span class="badge badge--red">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: req.currency, maximumFractionDigits: 0 }).format(req.amount) }}</span>
              </li>
            </ul>
          </div>

          <!-- Most active banks bar list -->
          <div v-if="stats.most_active_banks.length" class="compliance-group">
            <h3 class="compliance-subtitle">أنشط البنوك</h3>
            <ul class="banks-list">
              <li v-for="(bank, index) in stats.most_active_banks" :key="bank.bank_id" class="banks-list__item">
                <span class="banks-list__rank">{{ index + 1 }}</span>
                <span class="banks-list__name">{{ bank.bank_name }}</span>
                <div class="banks-list__bar-wrap">
                  <div class="banks-list__bar" :style="{ width: `${Math.round(bank.request_count / (stats.most_active_banks[0]?.request_count || 1) * 100)}%` }" />
                </div>
                <span class="banks-list__count">{{ bank.request_count }}</span>
              </li>
            </ul>
          </div>

        </section>
      </div>

    </template>
  </div>
</template>

<style scoped>
.cby-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; }
.kpi-card--amber { border-inline-start: 3px solid #f57f17; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--gray  { background: #f5f5f5; color: #6c757d; }
.kpi-icon--blue  { background: #e3f2fd; color: #0066cc; }
.kpi-icon--amber { background: #fff8e1; color: #f57f17; }
.kpi-icon--green { background: #e8f5e9; color: #1b5e20; }

.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-card--amber .kpi-card__value { color: #f57f17; }
.kpi-card__label { font-size: 13px; color: #6c757d; }

/* Skeleton */
.kpi-skeleton { gap: 12px; animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.skel { background: #f5f5f5; border-radius: 6px; }
.skel--label { height: 14px; width: 60%; }
.skel--value { height: 32px; width: 40%; }

/* Error */
.error-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px; color: #c62828; display: flex; align-items: center; gap: 16px; }
.btn-retry { border: 1px solid #cccccc; background: #ffffff; border-radius: 8px; padding: 6px 14px; cursor: pointer; color: #1c222b; }

/* Section heading */
.section-heading { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #1c222b; margin: 0 0 12px; }

/* Quick actions */
.quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
@media (max-width: 900px) { .quick-actions { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .quick-actions { grid-template-columns: 1fr; } }
.qa-card { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; padding: 16px 20px; background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; cursor: pointer; text-align: right; transition: border-color 0.15s; }
.qa-card:hover { border-color: #0066cc; }
.qa-card--primary { background: #0066cc; border-color: #0066cc; }
.qa-card--primary:hover { background: #0052a3; }
.qa-card--primary .qa-card__label { color: #ffffff; }
.qa-card--primary .qa-card__sub { color: rgba(255,255,255,0.75); }
.qa-card--primary .qa-card__icon { color: #ffffff; }
.qa-card__icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #0066cc; margin-bottom: 4px; }
.qa-card__label { font-size: 14px; font-weight: 600; color: #1c222b; }
.qa-card__sub { font-size: 12px; color: #6c757d; }

/* Charts row */
.charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
@media (max-width: 900px) { .charts-row { grid-template-columns: 1fr; } }

.chart-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; }
.section-title { font-size: 15px; font-weight: 600; color: #1c222b; margin: 0; }
.chart-subtitle { font-size: 12px; color: #6c757d; margin: 4px 0 12px; }

.chart-wrap { display: flex; flex-direction: column; gap: 6px; }
.sparkline { width: 100%; height: 100px; }
.chart-labels { display: flex; justify-content: space-between; padding: 0 12px; }
.chart-label { font-size: 10px; color: #6c757d; }
.chart-legend { display: flex; gap: 16px; margin-top: 6px; }
.legend-item { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #6c757d; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; }
.legend-dot--blue { background: #0066cc; }
.legend-dot--green { background: #1b5e20; }

.donut-wrap { display: flex; align-items: center; gap: 16px; }
.donut-svg { width: 100px; height: 100px; flex-shrink: 0; }
.donut-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.donut-legend__item { display: flex; align-items: center; gap: 6px; font-size: 11px; }
.donut-legend__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.donut-legend__label { color: #1c222b; flex: 1; }
.donut-legend__pct { color: #6c757d; }

/* Two-column */
.two-col { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
@media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

/* Section card */
.section-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.section-card__header { display: flex; align-items: center; justify-content: space-between; }
.viewall-link { font-size: 13px; color: #0066cc; text-decoration: none; }
.viewall-link:hover { text-decoration: underline; }

.empty-state { padding: 24px; text-align: center; color: #6c757d; font-size: 14px; }
.empty-state p { margin: 0; }

/* Request table */
.req-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.req-table th { padding: 8px 10px; text-align: right; font-weight: 500; color: #6c757d; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
.req-table__row { cursor: pointer; }
.req-table__row:hover td { background: #f9fafb; }
.req-table__row + .req-table__row td { border-top: 1px solid #f0f0f0; }
.req-table td { padding: 8px 10px; color: #1c222b; text-align: right; vertical-align: middle; }
.req-ref { font-family: monospace; color: #0066cc; text-decoration: none; }
.req-ref:hover { text-decoration: underline; }
.mono { direction: ltr; font-variant-numeric: tabular-nums; text-align: left; font-size: 11px; }

.progress-cell { display: flex; align-items: center; gap: 4px; min-width: 70px; }
.progress-bar { flex: 1; height: 5px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: #0066cc; border-radius: 999px; }
.progress-pct { font-size: 10px; color: #6c757d; white-space: nowrap; }
.btn-action { padding: 4px 10px; background: #ffffff; border: 1px solid #cccccc; border-radius: 6px; font-size: 11px; color: #1c222b; cursor: pointer; }
.btn-action:hover { border-color: #0066cc; color: #0066cc; }

/* Compliance */
.compliance-group { display: flex; flex-direction: column; gap: 6px; padding-top: 12px; border-top: 1px solid #f0f0f0; }
.compliance-group:first-child { padding-top: 0; border-top: none; }
.compliance-subtitle { font-size: 12px; font-weight: 600; color: #6c757d; margin: 0; }
.compliance-ok { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #1b5e20; }
.compliance-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.compliance-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: #f9fafb; border-radius: 6px; }
.compliance-item--link { cursor: pointer; }
.compliance-item--link:hover { background: #f0f0f5; }
.compliance-ref { font-family: monospace; font-size: 11px; color: #0066cc; }
.compliance-name { font-size: 12px; color: #1c222b; flex: 1; }
.badge { font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 20px; }
.badge--amber { background: #fff8e1; color: #f57f17; }
.badge--red { background: #fde8e8; color: #c62828; }

/* Banks bar list */
.banks-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.banks-list__item { display: flex; align-items: center; gap: 8px; }
.banks-list__rank { width: 20px; height: 20px; background: #0066cc; color: #ffffff; border-radius: 50%; font-size: 11px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.banks-list__name { font-size: 12px; color: #1c222b; width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.banks-list__bar-wrap { flex: 1; height: 6px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.banks-list__bar { height: 100%; background: #0066cc; border-radius: 999px; transition: width 0.3s; }
.banks-list__count { font-size: 11px; color: #6c757d; white-space: nowrap; }
</style>
