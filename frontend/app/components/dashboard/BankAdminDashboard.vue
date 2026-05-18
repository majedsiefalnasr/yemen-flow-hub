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

// ── SVG sparkline helpers ──────────────────────────────────────────────────

const CHART_W = 480
const CHART_H = 80
const CHART_PAD = 8

function buildSparklinePoints(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map(e => e.count)
  const max = Math.max(...counts, 1)
  const step = (CHART_W - CHART_PAD * 2) / Math.max(entries.length - 1, 1)
  return entries
    .map((e, i) => {
      const x = CHART_PAD + i * step
      const y = CHART_PAD + (1 - e.count / max) * (CHART_H - CHART_PAD * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

function buildSparklineArea(entries: BankAdminMonthlyEntry[]): string {
  if (!entries.length) return ''
  const counts = entries.map(e => e.count)
  const max = Math.max(...counts, 1)
  const step = (CHART_W - CHART_PAD * 2) / Math.max(entries.length - 1, 1)
  const pts = entries.map((e, i) => {
    const x = CHART_PAD + i * step
    const y = CHART_PAD + (1 - e.count / max) * (CHART_H - CHART_PAD * 2)
    return `${x.toFixed(1)},${y.toFixed(1)}`
  })
  const bottom = CHART_H - CHART_PAD
  const firstX = CHART_PAD
  const lastX = (CHART_PAD + (entries.length - 1) * step).toFixed(1)
  return `${firstX},${bottom} ${pts.join(' ')} ${lastX},${bottom}`
}

function monthLabel(ym: string): string {
  const [y, m] = ym.split('-')
  return new Intl.DateTimeFormat('ar-YE', { month: 'short', year: '2-digit' }).format(new Date(Number(y), Number(m) - 1, 1))
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="ba-dashboard" dir="rtl">

    <!-- ── Skeleton loaders ── -->
    <div v-if="store.loading">
      <div class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
        <div v-for="n in 5" :key="n" class="kpi-card kpi-card--skeleton" aria-hidden="true">
          <div class="skeleton skeleton--label" />
          <div class="skeleton skeleton--value" />
        </div>
      </div>
      <div class="chart-card chart-card--skeleton">
        <div class="skeleton skeleton--title" />
        <div class="skeleton skeleton--chart" />
      </div>
      <div class="section-card">
        <div class="skeleton skeleton--title" />
        <div v-for="r in 5" :key="r" class="skeleton skeleton--row" />
      </div>
    </div>

    <!-- ── Error state ── -->
    <div v-else-if="store.error" class="error-card" role="alert">
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <!-- ── Main content ── -->
    <template v-else-if="stats">

      <!-- KPI cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <span class="kpi-label">إجمالي الطلبات</span>
          <span class="kpi-value">{{ stats.total }}</span>
        </div>
        <div class="kpi-card kpi-card--amber">
          <span class="kpi-label">طلبات قيد الانتظار</span>
          <span class="kpi-value">{{ stats.pending }}</span>
        </div>
        <div class="kpi-card kpi-card--green">
          <span class="kpi-label">طلبات معتمدة</span>
          <span class="kpi-value">{{ stats.approved }}</span>
        </div>
        <div class="kpi-card kpi-card--red">
          <span class="kpi-label">طلبات مرفوضة</span>
          <span class="kpi-value">{{ stats.rejected }}</span>
        </div>
        <div class="kpi-card kpi-card--blue">
          <span class="kpi-label">إجمالي التمويل</span>
          <span class="kpi-value kpi-value--sm">{{ formatAmount(stats.total_financed_amount) }}</span>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="quick-actions">
        <button class="btn-primary" @click="router.push('/requests/new')">
          + تقديم طلب جديد
        </button>
      </div>

      <!-- Monthly chart -->
      <div v-if="stats.monthly_requests.length" class="chart-card">
        <h2 class="section-title">حركة طلبات البنك الشهرية</h2>
        <div class="chart-wrap">
          <svg
            :viewBox="`0 0 ${480} ${80}`"
            class="sparkline"
            aria-label="مخطط الطلبات الشهرية"
            role="img"
            preserveAspectRatio="none"
          >
            <polygon
              :points="buildSparklineArea(stats.monthly_requests)"
              class="sparkline-area"
            />
            <polyline
              :points="buildSparklinePoints(stats.monthly_requests)"
              class="sparkline-line"
              fill="none"
              stroke-width="2"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
          </svg>
          <div class="chart-labels">
            <span
              v-for="entry in stats.monthly_requests"
              :key="entry.month"
              class="chart-label"
            >{{ monthLabel(entry.month) }}</span>
          </div>
        </div>
      </div>

      <!-- Recent requests table -->
      <div class="section-card">
        <h2 class="section-title">أحدث طلبات البنك</h2>

        <!-- Empty state -->
        <div
          v-if="stats.recent_requests.length === 0"
          class="empty-state"
          role="status"
        >
          <span class="empty-icon" aria-hidden="true">📋</span>
          <p class="empty-title">لا توجد طلبات بعد</p>
          <p class="empty-body">لم يتم تقديم أي طلبات من هذا البنك حتى الآن.</p>
          <button class="btn-primary" @click="router.push('/requests/new')">تقديم أول طلب</button>
        </div>

        <table v-else class="req-table" aria-label="أحدث طلبات البنك">
          <thead>
            <tr>
              <th>رقم المرجع</th>
              <th>التاجر / المورد</th>
              <th>المبلغ</th>
              <th>الحالة</th>
              <th>التاريخ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in stats.recent_requests" :key="req.id">
              <td>
                <a class="req-ref" href="#" @click.prevent="router.push(`/requests/${req.id}`)">
                  {{ req.reference_number }}
                </a>
              </td>
              <td>{{ req.supplier_name }}</td>
              <td class="mono">{{ formatAmount(req.amount) }} {{ req.currency }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.BANK_ADMIN" /></td>
              <td class="date-cell">{{ formatDate(req.updated_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>

  </div>
</template>

<style scoped>
.ba-dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ── KPI grid ── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kpi-label {
  font-size: 13px;
  color: #6c757d;
}

.kpi-value {
  font-size: 28px;
  font-weight: 500;
  color: #1c222b;
}

.kpi-value--sm {
  font-size: 20px;
}

.kpi-card--green .kpi-value { color: #1b5e20; }
.kpi-card--amber .kpi-value { color: #f57f17; }
.kpi-card--red .kpi-value   { color: #c62828; }
.kpi-card--blue .kpi-value  { color: #0066cc; }

/* ── Quick actions ── */
.quick-actions {
  display: flex;
  gap: 12px;
}

.btn-primary {
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary:hover {
  background: #0052a3;
}

/* ── Chart ── */
.chart-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
}

.section-title {
  font-size: 16px;
  font-weight: 500;
  margin: 0 0 16px;
  color: #1c222b;
}

.chart-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sparkline {
  width: 100%;
  height: 80px;
}

.sparkline-line {
  stroke: #0066cc;
}

.sparkline-area {
  fill: #0066cc;
  opacity: 0.08;
}

.chart-labels {
  display: flex;
  justify-content: space-between;
  padding: 0 8px;
}

.chart-label {
  font-size: 11px;
  color: #6c757d;
}

/* ── Recent requests table ── */
.section-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
}

.req-table {
  width: 100%;
  border-collapse: collapse;
}

.req-table th,
.req-table td {
  padding: 12px;
  border-bottom: 1px solid #f5f5f7;
  text-align: right;
  font-size: 14px;
}

.req-table th {
  color: #6c757d;
  font-weight: 500;
  font-size: 13px;
}

.req-ref {
  color: #0066cc;
  text-decoration: none;
}

.req-ref:hover {
  text-decoration: underline;
}

.mono {
  direction: ltr;
  font-variant-numeric: tabular-nums;
  text-align: left;
}

.date-cell {
  color: #6c757d;
  font-size: 13px;
}

/* ── Empty state ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px 24px;
  text-align: center;
}

.empty-icon {
  font-size: 32px;
}

.empty-title {
  font-size: 16px;
  font-weight: 500;
  color: #1c222b;
  margin: 0;
}

.empty-body {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

/* ── Skeleton loaders ── */
.kpi-card--skeleton {
  gap: 12px;
}

.skeleton {
  background: linear-gradient(90deg, #f5f5f7 25%, #ebebeb 50%, #f5f5f7 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton--label { height: 14px; width: 60%; }
.skeleton--value { height: 32px; width: 40%; }
.skeleton--title { height: 18px; width: 200px; margin-bottom: 16px; }
.skeleton--chart { height: 80px; width: 100%; }
.skeleton--row   { height: 44px; width: 100%; margin-bottom: 8px; }

.chart-card--skeleton {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
}

/* ── Error ── */
.error-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px;
  color: #c62828;
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-retry {
  border: 1px solid #cccccc;
  background: #ffffff;
  border-radius: 8px;
  padding: 6px 14px;
  cursor: pointer;
  color: #1c222b;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
