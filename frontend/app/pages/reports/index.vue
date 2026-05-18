<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useReportsStore } from '../../stores/reports.store'
import { UserRole } from '../../types/enums'

const REPORTING_ROLES = [
  UserRole.CBY_ADMIN,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.DATA_ENTRY,
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
  [UserRole.CBY_ADMIN, UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR].includes(role.value as UserRole),
)
const isBankUser = computed(() =>
  [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN].includes(role.value as UserRole),
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

// KPI computations from workflow report
const kpis = computed(() => {
  const wr = store.workflowReport
  const br = store.bankReport
  if (!wr && !br) return []

  if (wr) {
    const counts = wr.counts_by_status
    const total = Object.values(counts).reduce((s, v) => s + v, 0)
    const completed = (wr.throughput.completed ?? 0) + (wr.throughput.approved ?? 0)
    const rejected = wr.throughput.rejected ?? 0
    const approvalRate = total > 0 ? Math.round((completed / total) * 100) : 0
    const pending = (counts['SUBMITTED'] ?? 0) + (counts['BANK_REVIEW'] ?? 0) + (counts['SUPPORT_REVIEW_IN_PROGRESS'] ?? 0)

    return [
      { label: 'إجمالي الطلبات', value: total.toLocaleString('ar-EG'), sub: `${pending} معلّق` },
      { label: 'نسبة الاعتماد', value: `${approvalRate}%`, sub: `${completed} مكتمل` },
      { label: 'نسبة الرفض', value: `${total > 0 ? Math.round((rejected / total) * 100) : 0}%`, sub: `${rejected} مرفوض` },
      { label: 'الطلبات المعلقة', value: pending.toLocaleString('ar-EG'), sub: 'في قيد المعالجة' },
    ]
  }

  if (br) {
    return [
      { label: 'إجمالي طلبات البنك', value: br.total_requests.toLocaleString('ar-EG'), sub: `${br.pending_count} معلّق` },
      { label: 'نسبة الاعتماد', value: `${br.approval_rate}%`, sub: `${br.approved_count} مكتمل` },
      { label: 'نسبة الرفض', value: `${br.rejection_rate}%`, sub: `${br.rejected_count} مرفوض` },
      { label: 'متوسط وقت المعالجة', value: `${br.avg_processing_hours} ساعة`, sub: 'من التقديم للقرار' },
    ]
  }

  return []
})

// Status breakdown rows
const statusRows = computed(() => {
  const counts = store.workflowReport?.counts_by_status ?? {}
  return Object.entries(counts).map(([status, count]) => ({ status, count }))
})

// Chart: simple bar data for counts_by_bank
const bankChartData = computed(() => {
  if (!store.workflowReport) return []
  return store.workflowReport.counts_by_bank
    .filter((b) => b.total > 0)
    .slice(0, 8)
})

const maxBankTotal = computed(() =>
  Math.max(...bankChartData.value.map((b) => b.total), 1),
)

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
</script>

<template>
  <div class="reports-page" dir="rtl">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-text">
        <h1 class="page-title">التقارير التشغيلية</h1>
        <p class="page-subtitle">مؤشرات الأداء والبيانات الإدارية</p>
      </div>
      <div class="header-actions">
        <button
          v-if="isCbyUser"
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="handleExportWorkflow('excel')"
        >
          تصدير Excel
        </button>
        <button
          v-if="isCbyUser"
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="handleExportWorkflow('pdf')"
        >
          تصدير PDF
        </button>
        <button
          v-if="isBankUser"
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="handleExportBank('excel')"
        >
          تصدير Excel
        </button>
        <button
          v-if="isBankUser"
          class="btn btn-outline"
          :disabled="store.exportLoading"
          @click="handleExportBank('pdf')"
        >
          تصدير PDF
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
      <div v-for="i in 4" :key="i" class="skeleton-kpi" />
      <div class="skeleton-table" />
    </div>

    <template v-else>
      <!-- KPI Strip -->
      <div v-if="kpis.length" class="kpi-strip">
        <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card">
          <div class="kpi-label">{{ kpi.label }}</div>
          <div class="kpi-value">{{ kpi.value }}</div>
          <div class="kpi-sub">{{ kpi.sub }}</div>
        </div>
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

      <!-- Bank Volume Chart (CBY: counts_by_bank) -->
      <div v-if="store.workflowReport && bankChartData.length" class="section-card">
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
              <div
                class="bar-fill"
                :style="{ width: `${Math.round((bank.total / maxBankTotal) * 100)}%` }"
              />
            </div>
            <span class="bar-value">{{ bank.total }}</span>
          </div>
        </div>
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
            <div class="kpi-label">إجمالي الطلبات</div>
            <div class="kpi-value">{{ store.bankReport.total_requests }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">الطلبات المعتمدة</div>
            <div class="kpi-value" style="color:#34c759">{{ store.bankReport.approved_count }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">الطلبات المرفوضة</div>
            <div class="kpi-value" style="color:#ff3b30">{{ store.bankReport.rejected_count }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">متوسط وقت المعالجة</div>
            <div class="kpi-value">{{ store.bankReport.avg_processing_hours }} ساعة</div>
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

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0;
}

.page-subtitle {
  font-size: 15px;
  color: #6e6e73;
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

/* Buttons */
.btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #0071e3; color: #fff; }
.btn-outline { background: transparent; border: 1px solid #d2d2d7; color: #1d1d1f; }
.btn-ghost { background: transparent; color: #0071e3; }
.btn-sm { padding: 4px 10px; font-size: 13px; }

/* Error */
.error-banner {
  background: #fff5f5;
  border: 1px solid #ff3b30;
  border-radius: 8px;
  padding: 12px 16px;
  color: #ff3b30;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

/* Filter Card */
.filter-card {
  background: #fff;
  border: 1px solid #d2d2d7;
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
  color: #6e6e73;
}

.filter-input {
  padding: 8px 10px;
  border: 1px solid #d2d2d7;
  border-radius: 8px;
  font-size: 14px;
  color: #1d1d1f;
  background: #fff;
  min-width: 160px;
}

.filter-actions {
  display: flex;
  gap: 8px;
}

/* Presets */
.presets-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.presets-label {
  font-size: 13px;
  color: #6e6e73;
}

.presets-list {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.preset-chip {
  background: #f5f5f7;
  border: 1px solid #d2d2d7;
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.preset-delete {
  color: #ff3b30;
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

/* Skeleton */
.skeleton-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-kpi,
.skeleton-table {
  background: linear-gradient(90deg, #f5f5f7 25%, #e8e8ed 50%, #f5f5f7 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 12px;
}

.skeleton-kpi { height: 88px; }
.skeleton-table { height: 200px; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* KPI Strip */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 16px;
}

.kpi-label {
  font-size: 12px;
  color: #6e6e73;
  margin-bottom: 4px;
}

.kpi-value {
  font-size: 24px;
  font-weight: 600;
  color: #1d1d1f;
}

.kpi-sub {
  font-size: 12px;
  color: #8e8e93;
  margin-top: 2px;
}

/* Section Card */
.section-card {
  background: #fff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px 24px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1d1d1f;
  margin: 0 0 16px;
}

/* Table */
.report-table {
  width: 100%;
  border-collapse: collapse;
  text-align: right;
}

.report-table th,
.report-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #d2d2d7;
  font-size: 14px;
}

.report-table th {
  background: #f5f5f7;
  font-weight: 600;
  color: #1d1d1f;
}

.report-table td {
  color: #1d1d1f;
}

.report-table tr:last-child td {
  border-bottom: none;
}

/* Bar Chart */
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
  width: 140px;
  font-size: 13px;
  color: #1d1d1f;
  text-align: right;
  flex-shrink: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bar-track {
  flex: 1;
  height: 10px;
  background: #f5f5f7;
  border-radius: 5px;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  background: #0071e3;
  border-radius: 5px;
  transition: width 0.3s ease;
  min-width: 2px;
}

.bar-value {
  width: 40px;
  font-size: 13px;
  color: #6e6e73;
  text-align: left;
  flex-shrink: 0;
}

/* Empty State */
.empty-state {
  background: #fff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 48px;
  text-align: center;
  color: #6e6e73;
}
</style>
