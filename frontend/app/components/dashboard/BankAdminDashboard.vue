<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankAdminDashboardStats, BankAdminMonthlyEntry } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

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

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="ba-dashboard" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading">
      <div class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
        <div v-for="n in 4" :key="n" class="kpi-card kpi-skeleton" aria-hidden="true">
          <div class="skel skel--label" />
          <div class="skel skel--value" />
        </div>
      </div>
      <div class="chart-skeleton">
        <div class="skel skel--title" />
        <div class="skel skel--chart" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="error-card" role="alert">
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid (4 cards — Lovable screenshot) -->
      <div class="kpi-grid">
        <!-- مُعتمد -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.approved }}</span>
          <span class="kpi-card__label">مُعتمد</span>
        </div>

        <!-- قيد البنك المركزي -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--indigo" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.pending }}</span>
          <span class="kpi-card__label">مراجعة داخلية مُعلقة</span>
        </div>

        <!-- قيد مراجعة داخلية -->
        <div class="kpi-card kpi-card--amber">
          <div class="kpi-card__icon kpi-icon--amber" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.rejected }}</span>
          <span class="kpi-card__label">مرفوض</span>
        </div>

        <!-- إجمالي طلبات البنك -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--gray" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.total }}</span>
          <span class="kpi-card__label">إجمالي طلبات البنك</span>
        </div>
      </div>

      <!-- Quick actions (4 cards — Lovable screenshot) -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="section-heading">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
          </svg>
          إجراءات سريعة
        </h2>
        <div class="quick-actions">
          <button class="qa-card qa-card--primary" @click="router.push('/requests/new')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />
              </svg>
            </div>
            <span class="qa-card__label">طلب جديد</span>
            <span class="qa-card__sub">لبدء طلب تمويل جديد</span>
          </button>

          <button class="qa-card" @click="router.push('/merchants')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <span class="qa-card__label">إدارة التجار</span>
            <span class="qa-card__sub">إدارة بيانات التجار</span>
          </button>

          <button class="qa-card" @click="router.push('/staff')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <span class="qa-card__label">مستخدمو البنك</span>
            <span class="qa-card__sub">إدارة موظفي البنك</span>
          </button>

          <button class="qa-card" @click="router.push('/reports')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="qa-card__label">التقارير</span>
            <span class="qa-card__sub">تقارير وتحليلات البنك</span>
          </button>
        </div>
      </section>

      <!-- Monthly chart -->
      <section v-if="stats.monthly_requests.length" class="chart-card" aria-labelledby="chart-heading">
        <h2 id="chart-heading" class="section-title">حركة طلبات البنك الشهرية</h2>
        <p class="chart-subtitle">تتابع ملك الشهر المُقدَّم</p>
        <div class="chart-wrap">
          <svg
            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
            class="sparkline"
            aria-label="مخطط الطلبات الشهرية"
            role="img"
            preserveAspectRatio="none"
          >
            <polygon :points="buildArea(stats.monthly_requests)" class="sparkline-area" />
            <polyline
              :points="buildLine(stats.monthly_requests)"
              class="sparkline-line"
              fill="none"
              stroke-width="2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
          </svg>
          <div class="chart-labels">
            <span v-for="entry in stats.monthly_requests" :key="entry.month" class="chart-label">
              {{ monthLabel(entry.month) }}
            </span>
          </div>
        </div>
      </section>

      <!-- Recent requests -->
      <section class="section-card" aria-labelledby="recent-heading">
        <div class="section-card__header">
          <h2 id="recent-heading" class="section-title">أحدث الطلبات</h2>
          <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="stats.recent_requests.length === 0" class="empty-state" role="status">
          <p>لا توجد طلبات بعد</p>
        </div>

        <table v-else class="req-table" aria-label="أحدث طلبات البنك">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">التاجر</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">التقدم</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in stats.recent_requests" :key="req.id" class="req-table__row">
              <td><a class="req-ref" href="#" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td>{{ req.merchant?.name ?? req.supplier_name }}</td>
              <td class="mono">{{ formatAmount(req.amount) }} {{ req.currency }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.BANK_ADMIN" /></td>
              <td class="progress-cell">
                <div class="progress-bar">
                  <div class="progress-bar__fill" :style="{ width: '25%' }" />
                </div>
                <span class="progress-pct">25%</span>
              </td>
              <td><button class="btn-action" @click="router.push(`/requests/${req.id}`)">عرض</button></td>
            </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
.ba-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; }
.kpi-card--green { }
.kpi-card--amber { border-inline-start: 3px solid #f57f17; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--indigo { background: #ede7f6; color: #5856d6; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }
.kpi-icon--gray   { background: #f5f5f5; color: #6c757d; }
.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-card--green .kpi-card__value { color: #1b5e20; }
.kpi-card--amber .kpi-card__value { color: #f57f17; }
.kpi-card__label { font-size: 13px; color: #6c757d; }

/* Skeleton */
.kpi-skeleton { gap: 12px; animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.skel { background: #f5f5f5; border-radius: 6px; }
.skel--label { height: 14px; width: 60%; }
.skel--value { height: 32px; width: 40%; }
.skel--title { height: 18px; width: 200px; margin-bottom: 16px; }
.skel--chart { height: 80px; width: 100%; }
.chart-skeleton { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; margin-top: 16px; }

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
.qa-card--primary:hover { background: #0052a3; border-color: #0052a3; }
.qa-card--primary .qa-card__label { color: #ffffff; }
.qa-card--primary .qa-card__sub { color: rgba(255,255,255,0.75); }
.qa-card--primary .qa-card__icon { color: #ffffff; }
.qa-card__icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #0066cc; margin-bottom: 4px; }
.qa-card__label { font-size: 14px; font-weight: 600; color: #1c222b; }
.qa-card__sub { font-size: 12px; color: #6c757d; }

/* Chart */
.chart-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; }
.section-title { font-size: 15px; font-weight: 600; color: #1c222b; margin: 0; }
.chart-subtitle { font-size: 12px; color: #6c757d; margin: 4px 0 12px; }
.chart-wrap { display: flex; flex-direction: column; gap: 6px; }
.sparkline { width: 100%; height: 80px; }
.sparkline-line { stroke: #0066cc; }
.sparkline-area { fill: #0066cc; opacity: 0.08; }
.chart-labels { display: flex; justify-content: space-between; padding: 0 8px; }
.chart-label { font-size: 11px; color: #6c757d; }

/* Recent requests */
.section-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; }
.section-card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.viewall-link { font-size: 13px; color: #0066cc; text-decoration: none; }
.viewall-link:hover { text-decoration: underline; }

.empty-state { padding: 40px; text-align: center; color: #6c757d; font-size: 14px; }
.empty-state p { margin: 0; }

.req-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.req-table th { padding: 10px 12px; text-align: right; font-weight: 500; color: #6c757d; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
.req-table__row:hover td { background: #f9fafb; }
.req-table__row + .req-table__row td { border-top: 1px solid #f0f0f0; }
.req-table td { padding: 10px 12px; color: #1c222b; text-align: right; vertical-align: middle; }
.req-ref { color: #0066cc; text-decoration: none; font-family: monospace; }
.req-ref:hover { text-decoration: underline; }
.mono { direction: ltr; font-variant-numeric: tabular-nums; text-align: left; }

.progress-cell { display: flex; align-items: center; gap: 6px; min-width: 90px; }
.progress-bar { flex: 1; height: 6px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: #0066cc; border-radius: 999px; }
.progress-pct { font-size: 11px; color: #6c757d; white-space: nowrap; }

.btn-action { padding: 5px 14px; background: #ffffff; border: 1px solid #cccccc; border-radius: 8px; font-size: 12px; color: #1c222b; cursor: pointer; }
.btn-action:hover { border-color: #0066cc; color: #0066cc; }
</style>
