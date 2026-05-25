<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useReportsStore } from '../../stores/reports.store'
import { UserRole } from '../../types/enums'
import LineChart from '../../components/charts/LineChart.vue'
import PieChart from '../../components/charts/PieChart.vue'
import CurrencyBarChart from '../../components/charts/CurrencyBarChart.vue'
import SubmissionHeatmap from '../../components/charts/SubmissionHeatmap.vue'

const REPORTING_ROLES = [
  UserRole.CBY_ADMIN,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
]

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: REPORTING_ROLES,
})

const auth = useAuthStore()
const store = useReportsStore()

const role = computed(() => auth.user?.role)

const isCbyUser = computed(() =>
  [UserRole.CBY_ADMIN, UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR, UserRole.SUPPORT_COMMITTEE].includes(role.value as UserRole),
)
const isBankUser = computed(() =>
  [UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN].includes(role.value as UserRole),
)

// Filters
const fromDate = ref('')
const toDate = ref('')

// Preset management
const presetName = ref('')
const showPresetForm = ref(false)

onMounted(async () => {
  store.loadPresetsFromStorage()
  await loadReports()
})

async function loadReports() {
  store.applyFilters({ fromDate: fromDate.value || undefined, toDate: toDate.value || undefined })
  if (isCbyUser.value) {
    await store.loadWorkflowReport()
  }
  if (isBankUser.value || role.value === UserRole.CBY_ADMIN) {
    await store.loadBankReport()
  }
}

async function applyFilters() {
  await loadReports()
}

async function clearFilters() {
  fromDate.value = ''
  toDate.value = ''
  await loadReports()
}

function loadPreset(preset: typeof store.presets[0]) {
  fromDate.value = preset.filter.fromDate ?? ''
  toDate.value = preset.filter.toDate ?? ''
}

function handleSavePreset() {
  if (!presetName.value.trim()) return
  store.savePreset(presetName.value.trim())
  presetName.value = ''
  showPresetForm.value = false
}

async function handleExportWorkflow(format: 'excel' | 'pdf') {
  await store.exportWorkflow(format)
}

async function handleExportBank(format: 'excel' | 'pdf') {
  await store.exportBank(format)
}

function retry() {
  store.error = null
  loadReports()
}

// ─── KPI strip ────────────────────────────────────────────────────────────────

const kpiData = computed(() => {
  const wr = store.workflowReport
  const br = store.bankReport

  if (wr) {
    const counts = wr.counts_by_status
    const total = Object.values(counts).reduce((s, v) => s + v, 0)
    const approvalRate = total > 0 ? Math.round(((wr.throughput.completed + wr.throughput.approved) / total) * 100) : 0
    return {
      totalRequests: total,
      totalFinancingValue: wr.total_financing_value ?? 0,
      avgProcessingHours: null as number | null,
      approvalRate,
      duplicateInvoiceCount: wr.duplicate_invoice_count ?? 0,
    }
  }

  if (br) {
    return {
      totalRequests: br.total_requests,
      totalFinancingValue: null as number | null,
      avgProcessingHours: br.avg_processing_hours,
      approvalRate: br.approval_rate,
      duplicateInvoiceCount: null as number | null,
    }
  }

  return null
})

function formatFinancing(v: number): string {
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`
  if (v >= 1_000) return `${(v / 1_000).toFixed(1)}K`
  return v.toLocaleString('ar-EG')
}

// ─── Chart data sources (CBY from workflowReport, bank from bankReport) ──────

const chartMonthlyTrend = computed(() =>
  store.workflowReport?.monthly_trend ?? store.bankReport?.monthly_trend ?? [],
)
const chartCategoryDist = computed(() =>
  store.workflowReport?.category_distribution ?? store.bankReport?.category_distribution ?? [],
)
const chartAmountByCurrency = computed(() =>
  store.workflowReport?.amount_by_currency ?? store.bankReport?.amount_by_currency ?? [],
)
const chartHeatmap = computed(() =>
  store.workflowReport?.submission_heatmap ?? store.bankReport?.submission_heatmap ?? [],
)

const hasChartData = computed(() => !!(store.workflowReport || store.bankReport))

// ─── Line chart ──────────────────────────────────────────────────────────────

const lineChartSeries = computed(() => {
  const trend = chartMonthlyTrend.value
  if (!trend.length) return []
  return [
    { label: 'طلبات', values: trend.map((m) => m.total), color: 'var(--color-primary)' },
    { label: 'مُعتمد', values: trend.map((m) => m.approved), color: 'var(--color-success)' },
    { label: 'مرفوض', values: trend.map((m) => m.rejected), color: 'var(--color-destructive)' },
  ]
})

const lineChartLabels = computed(() =>
  chartMonthlyTrend.value.map((m) => m.month.slice(5)), // show MM only
)

// ─── Pie chart ────────────────────────────────────────────────────────────────

const PIE_COLORS = ['var(--color-primary)', 'var(--color-voting)', 'var(--color-info)', 'var(--color-warning)', 'var(--color-destructive)']

const pieChartData = computed(() => {
  return chartCategoryDist.value.map((c, i) => ({
    label: c.category,
    value: c.count,
    color: PIE_COLORS[i % PIE_COLORS.length] ?? 'var(--color-primary)',
  }))
})

// ─── Currency bar chart ────────────────────────────────────────────────────

const currencyBarData = computed(() =>
  chartAmountByCurrency.value.map((c) => ({ currency: c.currency, amount: c.amount })),
)

// ─── Heatmap ─────────────────────────────────────────────────────────────────

const heatmapData = computed(() => chartHeatmap.value)

// ─── Bank bar chart (existing) ────────────────────────────────────────────────

const bankChartData = computed(() => {
  if (!store.workflowReport) return []
  return store.workflowReport.counts_by_bank
    .filter((b) => b.total > 0)
    .slice(0, 8)
})

const maxBankTotal = computed(() => Math.max(...bankChartData.value.map((b) => b.total), 1))

// Status breakdown rows (existing)
const statusRows = computed(() => {
  const counts = store.workflowReport?.counts_by_status ?? {}
  return Object.entries(counts).map(([status, count]) => ({ status, count }))
})
</script>

<template>
  <div class="reports-page" dir="rtl">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-text">
        <nav class="breadcrumbs" aria-label="مسار التنقل">
          <NuxtLink to="/dashboard" class="breadcrumb-link">الرئيسية</NuxtLink>
          <span class="breadcrumb-sep">←</span>
          <span class="breadcrumb-current">التقارير</span>
        </nav>
        <h1 class="page-title">التقارير والتحليلات المتقدمة</h1>
        <p class="page-subtitle">مؤشرات الأداء، التحليل الإحصائي، والتقارير القابلة للتصدير</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline btn-icon" :disabled="store.loading" aria-label="تحديد الفترة الزمنية">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
          الفترة
        </button>
        <button
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('pdf') : handleExportBank('pdf')"
        >
          تصدير PDF
        </button>
        <button
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="isCbyUser ? handleExportWorkflow('excel') : handleExportBank('excel')"
        >
          تصدير Excel
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="store.error" class="error-banner" role="alert">
      <span>{{ store.error }}</span>
      <button class="btn btn-sm" @click="retry">إعادة المحاولة</button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-group">
          <label class="filter-label" for="from-date">من تاريخ</label>
          <input
            id="from-date"
            v-model="fromDate"
            type="date"
            class="filter-input"
          />
        </div>
        <div class="filter-group">
          <label class="filter-label" for="to-date">إلى تاريخ</label>
          <input
            id="to-date"
            v-model="toDate"
            type="date"
            class="filter-input"
          />
        </div>
        <div class="filter-actions">
          <button class="btn btn-primary" :disabled="store.loading" @click="applyFilters">
            تطبيق
          </button>
          <button class="btn btn-outline" :disabled="store.loading" @click="clearFilters">
            مسح
          </button>
        </div>
      </div>

      <!-- Presets -->
      <div class="presets-row">
        <div v-if="store.presets.length" class="presets-list">
          <span class="presets-label">الفلاتر المحفوظة:</span>
          <button
            v-for="preset in store.presets"
            :key="preset.id"
            class="preset-chip"
            @click="loadPreset(preset)"
          >
            {{ preset.name }}
            <span
              class="preset-delete"
              role="button"
              aria-label="حذف الفلتر"
              @click.stop="store.deletePreset(preset.id)"
            >×</span>
          </button>
        </div>
        <div class="save-preset">
          <button
            v-if="!showPresetForm"
            class="btn btn-ghost"
            @click="showPresetForm = true"
          >
            حفظ الفلتر الحالي
          </button>
          <div v-else class="preset-form">
            <input
              v-model="presetName"
              type="text"
              class="filter-input preset-name-input"
              placeholder="اسم الفلتر"
              maxlength="50"
              @keydown.enter="handleSavePreset"
            />
            <button class="btn btn-primary btn-sm" @click="handleSavePreset">حفظ</button>
            <button class="btn btn-ghost btn-sm" @click="showPresetForm = false">إلغاء</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="store.loading" class="skeleton-grid">
      <div class="skeleton-kpi-row">
        <div v-for="i in 5" :key="i" class="skeleton-kpi" />
      </div>
      <div class="skeleton-charts-row">
        <div class="skeleton-chart skeleton-chart-lg" />
        <div class="skeleton-chart skeleton-chart-sm" />
      </div>
      <div class="skeleton-charts-row">
        <div class="skeleton-chart skeleton-chart-lg" />
        <div class="skeleton-chart skeleton-chart-sm" />
      </div>
      <div class="skeleton-chart skeleton-chart-full" />
    </div>

    <template v-else>
      <!-- 5-KPI Strip -->
      <div v-if="kpiData" class="kpi-strip" data-testid="kpi-cards">
        <div class="kpi-card">
          <div class="kpi-icon kpi-icon-blue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <div class="kpi-content">
            <div class="kpi-label">إجمالي الطلبات</div>
            <div class="kpi-value">{{ kpiData.totalRequests.toLocaleString('ar-EG') }}</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-icon-green">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <div class="kpi-content">
            <div class="kpi-label">إجمالي قيمة التمويل</div>
            <div class="kpi-value">
              {{ kpiData.totalFinancingValue != null ? formatFinancing(kpiData.totalFinancingValue) : '—' }}
            </div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-icon-indigo">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="kpi-content">
            <div class="kpi-label">متوسط وقت المعالجة</div>
            <div class="kpi-value">
              {{ kpiData.avgProcessingHours != null ? `${kpiData.avgProcessingHours} ساعة` : '—' }}
            </div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-icon-cyan">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div class="kpi-content">
            <div class="kpi-label">معدل الاعتماد</div>
            <div class="kpi-value">{{ kpiData.approvalRate }}%</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-icon-orange">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="kpi-content">
            <div class="kpi-label">الفواتير المكررة</div>
            <div class="kpi-value">
              {{ kpiData.duplicateInvoiceCount != null ? kpiData.duplicateInvoiceCount : '—' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Charts: Row 1 — Line + Pie -->
      <div v-if="hasChartData" class="charts-row">
        <div class="section-card chart-lg" data-testid="line-chart">
          <h2 class="section-title">تطور أحجام الطلبات</h2>
          <p class="section-subtitle">أحجام الطلبات الشهرية خلال آخر 12 شهرًا</p>
          <LineChart
            v-if="lineChartSeries.length"
            :labels="lineChartLabels"
            :series="lineChartSeries"
          />
          <div v-else class="chart-empty-msg">لا توجد بيانات للفترة المحددة</div>
        </div>
        <div class="section-card chart-sm" data-testid="pie-chart">
          <h2 class="section-title">التوزيع حسب الفئة</h2>
          <PieChart
            v-if="pieChartData.length"
            :data="pieChartData"
          />
          <div v-else class="chart-empty-msg">لا توجد بيانات للفترة المحددة</div>
        </div>
      </div>

      <!-- Charts: Row 2 — Currency Bar + Bank Volume -->
      <div v-if="hasChartData" class="charts-row">
        <div class="section-card chart-lg">
          <h2 class="section-title">قيمة التمويل بالعملة</h2>
          <CurrencyBarChart
            v-if="currencyBarData.length"
            :data="currencyBarData"
          />
          <div v-else class="chart-empty-msg">لا توجد بيانات للفترة المحددة</div>
        </div>
        <div v-if="bankChartData.length" class="section-card chart-sm">
          <h2 class="section-title">حجم الطلبات حسب البنك</h2>
          <div class="bar-chart" role="list" aria-label="مخطط طلبات البنوك">
            <div
              v-for="bank in bankChartData"
              :key="bank.bank_id"
              class="bar-row"
              role="listitem"
            >
              <span class="bar-label">{{ bank.bank_name }}</span>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: `${Math.round((bank.total / maxBankTotal) * 100)}%` }" />
              </div>
              <span class="bar-value">{{ bank.total }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Heatmap (full width) -->
      <div v-if="hasChartData" class="section-card" data-testid="heatmap">
        <h2 class="section-title">خريطة حرارية: كثافة التقديم خلال الأسبوع</h2>
        <p class="section-subtitle">أنماط تقديم الطلبات حسب اليوم والوقت</p>
        <SubmissionHeatmap :data="heatmapData" />
      </div>

      <!-- Workflow Report: Status Breakdown Table -->
      <div v-if="store.workflowReport && statusRows.length" class="section-card">
        <h2 class="section-title">توزيع الطلبات حسب الحالة</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>الحالة</th>
              <th>العدد</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in statusRows" :key="row.status">
              <td>{{ row.status }}</td>
              <td>{{ row.count }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bank Report: Per-bank cross-bank breakdown (CBY Admin) -->
      <div v-if="store.bankReport?.per_bank" class="section-card">
        <h2 class="section-title">إحصاءات البنوك</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>البنك</th>
              <th>إجمالي الطلبات</th>
              <th>المعتمدة</th>
              <th>المرفوضة</th>
              <th>المعلقة</th>
              <th>نسبة الاعتماد</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in store.bankReport.per_bank" :key="b.bank_id">
              <td>{{ b.bank_name }}</td>
              <td>{{ b.total_requests }}</td>
              <td>{{ b.approved_count }}</td>
              <td>{{ b.rejected_count }}</td>
              <td>{{ b.pending_count }}</td>
              <td>{{ b.approval_rate }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bank Report: Own-bank summary (bank users) -->
      <div v-else-if="store.bankReport && isBankUser" class="section-card">
        <h2 class="section-title">إحصاءات بنكك</h2>
        <div class="kpi-strip">
          <div class="kpi-card">
            <div class="kpi-content">
              <div class="kpi-label">إجمالي الطلبات</div>
              <div class="kpi-value">{{ store.bankReport.total_requests }}</div>
            </div>
          </div>
          <div class="kpi-card">
            <div class="kpi-content">
              <div class="kpi-label">الطلبات المعتمدة</div>
              <div class="kpi-value" style="color: var(--success)">{{ store.bankReport.approved_count }}</div>
            </div>
          </div>
          <div class="kpi-card">
            <div class="kpi-content">
              <div class="kpi-label">الطلبات المرفوضة</div>
              <div class="kpi-value" style="color: var(--destructive)">{{ store.bankReport.rejected_count }}</div>
            </div>
          </div>
          <div class="kpi-card">
            <div class="kpi-content">
              <div class="kpi-label">متوسط وقت المعالجة</div>
              <div class="kpi-value">{{ store.bankReport.avg_processing_hours }} ساعة</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!store.workflowReport && !store.bankReport && !store.error" class="empty-state">
        <p>لا توجد بيانات متاحة للفترة المحددة.</p>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reports-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  direction: rtl;
}

/* ─── Header ────────────────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted-foreground);
  margin-bottom: 6px;
}

.breadcrumb-link {
  color: var(--color-primary);
  text-decoration: none;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-sep {
  color: var(--border);
}

.breadcrumb-current {
  color: var(--foreground);
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: var(--foreground);
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: var(--muted-foreground);
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  align-items: center;
}

/* ─── Buttons ───────────────────────────────────────────────── */
.btn {
  padding: 8px 16px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: var(--primary); color: var(--primary-foreground); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--foreground); }
.btn-ghost { background: transparent; color: var(--primary); }
.btn-sm { padding: 4px 10px; font-size: 13px; }
.btn-icon { padding: 8px 12px; }

/* ─── Error ─────────────────────────────────────────────────── */
.error-banner {
  background: color-mix(in srgb, var(--destructive) 8%, var(--background));
  border: 1px solid color-mix(in srgb, var(--destructive) 40%, transparent);
  border-radius: 12px;
  padding: 12px 16px;
  color: var(--destructive);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

/* ─── Filter Card ────────────────────────────────────────────── */
.filter-card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.filter-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-label {
  font-size: 13px;
  color: var(--muted-foreground);
}

.filter-input {
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 14px;
  color: var(--foreground);
  background: var(--background);
  min-width: 160px;
}

.filter-actions {
  display: flex;
  gap: 8px;
}

/* ─── Presets ───────────────────────────────────────────────── */
.presets-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.presets-label {
  font-size: 13px;
  color: var(--muted-foreground);
}

.presets-list {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.preset-chip {
  background: var(--muted);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.preset-delete {
  color: var(--destructive);
  font-weight: bold;
  line-height: 1;
  cursor: pointer;
  padding: 0 2px;
}

.save-preset {
  margin-right: auto;
}

.preset-form {
  display: flex;
  align-items: center;
  gap: 8px;
}

.preset-name-input {
  min-width: 160px;
}

/* ─── Skeleton ──────────────────────────────────────────────── */
.skeleton-grid {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.skeleton-kpi-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
}

.skeleton-charts-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}

.skeleton-kpi,
.skeleton-chart {
  background: linear-gradient(90deg, var(--muted) 25%, var(--border) 50%, var(--muted) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 12px;
}

.skeleton-kpi { height: 100px; }
.skeleton-chart-lg { height: 240px; }
.skeleton-chart-sm { height: 240px; }
.skeleton-chart-full { height: 200px; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ─── KPI Strip ─────────────────────────────────────────────── */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (min-width: 1024px) {
  .kpi-strip {
    grid-template-columns: repeat(5, 1fr);
  }
}

.kpi-card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.kpi-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-blue { background: color-mix(in srgb, var(--color-primary) 10%, transparent); color: var(--color-primary); }
.kpi-icon-green { background: color-mix(in srgb, var(--color-success) 10%, transparent); color: var(--color-success); }
.kpi-icon-indigo { background: color-mix(in srgb, var(--color-voting) 10%, transparent); color: var(--color-voting); }
.kpi-icon-cyan { background: color-mix(in srgb, var(--color-info) 10%, transparent); color: var(--color-info); }
.kpi-icon-orange { background: color-mix(in srgb, var(--color-warning) 10%, transparent); color: var(--color-warning); }

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.kpi-label {
  font-size: 12px;
  color: var(--muted-foreground);
}

.kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--foreground);
  line-height: 1.2;
}

/* ─── Charts Layout ─────────────────────────────────────────── */
.charts-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}

@media (max-width: 900px) {
  .charts-row {
    grid-template-columns: 1fr;
  }
}

/* ─── Section Card ──────────────────────────────────────────── */
.section-card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px 24px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--foreground);
  margin: 0 0 4px;
}

.section-subtitle {
  font-size: 13px;
  color: var(--muted-foreground);
  margin: 0 0 16px;
}

.chart-empty-msg {
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted-foreground);
  font-size: 14px;
}

/* ─── Bank bar chart (existing pattern) ─────────────────────── */
.bar-chart {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.bar-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bar-label {
  width: 120px;
  font-size: 13px;
  color: var(--foreground);
  text-align: right;
  flex-shrink: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bar-track {
  flex: 1;
  height: 10px;
  background: var(--muted);
  border-radius: 5px;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  background: var(--color-primary);
  border-radius: 5px;
  transition: width 0.3s ease;
  min-width: 2px;
}

.bar-value {
  width: 36px;
  font-size: 13px;
  color: var(--muted-foreground);
  text-align: left;
  flex-shrink: 0;
}

/* ─── Table ─────────────────────────────────────────────────── */
.report-table {
  width: 100%;
  border-collapse: collapse;
  text-align: right;
}

.report-table th,
.report-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
}

.report-table th {
  background: var(--muted);
  font-weight: 600;
  color: var(--foreground);
}

.report-table td {
  color: var(--foreground);
}

.report-table tr:last-child td {
  border-bottom: none;
}

/* ─── Empty State ───────────────────────────────────────────── */
.empty-state {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 48px;
  text-align: center;
  color: var(--muted-foreground);
}
</style>
