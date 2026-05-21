<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { AuditLog } from '../types/models'
import { useAudit } from '../composables/useAudit'
import type { AuditStats, DuplicateGroup, RiskIndicator } from '../composables/useAudit'
import { ROLE_LABELS } from '../constants/workflow'
import Badge from '../components/ui/Badge.vue'
import Icon from '../components/ui/Icon.vue'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR],
})

const { fetchAuditLogs, fetchAuditStats, fetchDuplicates, fetchRiskIndicators } = useAudit()

// ─── Tab state ────────────────────────────────────────────────────────────────
const activeTab = ref<'logs' | 'dup' | 'risk'>('logs')
const tabLoaded = reactive({ logs: true, dup: false, risk: false })

// ─── Tab 1: Activity log ──────────────────────────────────────────────────────
const logs = ref<AuditLog[]>([])
const logsLoading = ref(false)
const logsError = ref<string | null>(null)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const searchQuery = ref('')

const filters = reactive({
  action: '',
  from_date: '',
  to_date: '',
})

// ─── KPI stats ────────────────────────────────────────────────────────────────
const stats = ref<AuditStats>({ today_count: 0, duplicate_invoice_count: 0 })
const statsLoading = ref(false)

// ─── Tab 2: Duplicates ────────────────────────────────────────────────────────
const duplicates = ref<DuplicateGroup[]>([])
const dupLoading = ref(false)
const dupError = ref<string | null>(null)
const expandedGroups = ref<Set<string>>(new Set())

// ─── Tab 3: Risk indicators ───────────────────────────────────────────────────
const riskIndicators = ref<RiskIndicator[]>([])
const riskLoading = ref(false)
const riskError = ref<string | null>(null)

// ─── Computed ─────────────────────────────────────────────────────────────────
const openAlertsCount = computed(() => riskIndicators.value.length)
const potentialFraudCount = computed(() =>
  riskIndicators.value.filter(r => r.level === 'عالية').length,
)
const kpiLoading = computed(() => statsLoading.value || riskLoading.value)
const dupTotal = computed(() => duplicates.value.length)

function toggleGroup(invoiceNumber: string) {
  if (expandedGroups.value.has(invoiceNumber)) {
    expandedGroups.value.delete(invoiceNumber)
  }
  else {
    expandedGroups.value.add(invoiceNumber)
  }
}

const filteredLogs = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return logs.value
  return logs.value.filter(log =>
    actorName(log).toLowerCase().includes(q)
    || log.action.toLowerCase().includes(q)
    || String(log.entity_id ?? '').toLowerCase().includes(q),
  )
})

// ─── Labels & helpers ─────────────────────────────────────────────────────────
const ACTION_LABELS: Record<string, string> = {
  LOGIN: 'تسجيل دخول',
  LOGOUT: 'تسجيل خروج',
  LOGIN_FAILED: 'محاولة دخول فاشلة',
  REQUEST_CREATED: 'إنشاء طلب',
  REQUEST_UPDATED: 'تحديث طلب',
  REQUEST_DELETED: 'حذف طلب',
  STATUS_TRANSITION: 'انتقال الحالة',
  VOTE_CAST: 'تسجيل تصويت',
  DOCUMENT_UPLOADED: 'رفع مستند',
  DOCUMENT_DOWNLOADED: 'تحميل مستند',
  SWIFT_UPLOADED: 'رفع SWIFT',
  CUSTOMS_ISSUED: 'إصدار البيان الجمركي',
  USER_CREATED: 'إنشاء مستخدم',
  USER_UPDATED: 'تحديث مستخدم',
  USER_DEACTIVATED: 'إيقاف مستخدم',
  BANK_UPDATED: 'تحديث بنك',
  PASSWORD_CHANGED: 'تغيير كلمة المرور',
  SETTINGS_UPDATED: 'تحديث الإعدادات',
  AUTHORIZATION_FAILURE: 'فشل التخويل',
  REPORT_EXPORTED: 'تصدير تقرير',
}

const ACTION_OPTIONS = Object.entries(ACTION_LABELS)

function actionLabel(action: string): string {
  return ACTION_LABELS[action] ?? action
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function actorName(log: AuditLog): string {
  if (log.user?.name) return log.user.name
  if (log.user_id) return `#${log.user_id}`
  return 'النظام'
}

function actorRole(log: AuditLog): string {
  const role = log.user?.role ?? log.user_role
  if (!role) return ''
  return ROLE_LABELS[role as keyof typeof ROLE_LABELS] ?? role
}

function formatRef(entityReference?: string | null): string {
  return entityReference ?? '—'
}

function parseDevice(ua: string | null | undefined): string {
  if (!ua) return '—'
  const browser = (ua.includes('Edg/') || ua.includes('Edge')) ? 'Edge'
    : ua.includes('Chrome') ? 'Chrome'
    : ua.includes('Firefox') ? 'Firefox'
    : ua.includes('Safari') ? 'Safari'
    : 'Unknown'
  const os = ua.includes('Android') ? 'Android'
    : (ua.includes('iOS') || ua.includes('iPhone')) ? 'iOS'
    : ua.includes('Windows') ? 'Win'
    : ua.includes('Mac') ? 'Mac'
    : ua.includes('Linux') ? 'Linux'
    : 'Unknown'
  return `${browser} / ${os}`
}

function riskIconColor(level: RiskIndicator['level']): string {
  if (level === 'عالية') return '#c62828'
  if (level === 'متوسطة') return '#f57f17'
  return '#32ade6'
}

// ─── Data loading ─────────────────────────────────────────────────────────────
async function loadLogs(page = 1) {
  logsLoading.value = true
  logsError.value = null
  currentPage.value = page
  try {
    const result = await fetchAuditLogs({
      action: filters.action || undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      page,
    })
    logs.value = result.data
    lastPage.value = result.meta.last_page
    total.value = result.meta.total
  }
  catch {
    logsError.value = 'تعذّر تحميل سجلات التدقيق.'
  }
  finally {
    logsLoading.value = false
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    stats.value = await fetchAuditStats()
  }
  catch {
    // Stats failure is non-fatal; KPIs show 0
  }
  finally {
    statsLoading.value = false
  }
}

async function loadDuplicates() {
  dupLoading.value = true
  dupError.value = null
  try {
    duplicates.value = await fetchDuplicates()
  }
  catch {
    dupError.value = 'تعذّر تحميل بيانات الفواتير المكررة.'
  }
  finally {
    dupLoading.value = false
  }
}

async function loadRiskIndicators() {
  riskLoading.value = true
  riskError.value = null
  try {
    riskIndicators.value = await fetchRiskIndicators()
  }
  catch {
    riskError.value = 'تعذّر تحميل مؤشرات المخاطر.'
  }
  finally {
    riskLoading.value = false
  }
}

function applyFilters() { loadLogs(1) }

function resetFilters() {
  filters.action = ''
  filters.from_date = ''
  filters.to_date = ''
  loadLogs(1)
}

function onTabChange(tab: 'logs' | 'dup' | 'risk') {
  activeTab.value = tab
  if (tab === 'dup' && !tabLoaded.dup) {
    void loadDuplicates()
    tabLoaded.dup = true
  }
  if (tab === 'risk' && !tabLoaded.risk) {
    loadRiskIndicators()
    tabLoaded.risk = true
  }
}

onMounted(() => {
  void Promise.allSettled([
    loadLogs(1),
    loadStats(),
    loadRiskIndicators(),
  ]).then(() => {
    if (riskIndicators.value.length > 0) {
      tabLoaded.risk = true
    }
  })
})
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page-header">
      <div>
        <nav class="breadcrumbs" aria-label="مسار التنقل">
          <span class="breadcrumb-item">الرئيسية</span>
          <span class="breadcrumb-sep">←</span>
          <span class="breadcrumb-item breadcrumb-current">التدقيق والامتثال</span>
        </nav>
        <h1 class="page-title">التدقيق والامتثال</h1>
        <p class="page-subtitle">سجل النشاط، كشف الفواتير المكررة، وتنبيهات المخاطر الأمنية</p>
      </div>
    </div>

    <!-- KPI Strip -->
    <div class="kpi-grid" data-testid="kpi-strip">
      <div class="kpi-card-wrap">
        <div class="kpi-icon-wrap" style="background: #eff6ff; color: #0066cc;">
          <Icon name="activity" :size="20" />
        </div>
        <div>
          <div class="kpi-label">نشاطات اليوم</div>
          <div class="kpi-value">{{ kpiLoading ? '…' : stats.today_count }}</div>
        </div>
      </div>

      <div class="kpi-card-wrap">
        <div class="kpi-icon-wrap" style="background: #fff8ee; color: #f57f17;">
          <Icon name="alert-triangle" :size="20" />
        </div>
        <div>
          <div class="kpi-label">تنبيهات مفتوحة</div>
          <div class="kpi-value">{{ kpiLoading ? '…' : openAlertsCount }}</div>
        </div>
      </div>

      <div class="kpi-card-wrap">
        <div class="kpi-icon-wrap" style="background: #fff0f0; color: #c62828;">
          <Icon name="file-warning" :size="20" />
        </div>
        <div>
          <div class="kpi-label">فواتير مكررة</div>
          <div class="kpi-value">{{ kpiLoading ? '…' : stats.duplicate_invoice_count }}</div>
        </div>
      </div>

      <div class="kpi-card-wrap">
        <div class="kpi-icon-wrap" style="background: #fff0f0; color: #c62828;">
          <Icon name="shield-check" :size="20" />
        </div>
        <div>
          <div class="kpi-label">حالات احتيال محتملة</div>
          <div class="kpi-value">{{ kpiLoading ? '…' : potentialFraudCount }}</div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <nav class="tab-nav" role="tablist" aria-label="تبويبات التدقيق">
      <button
        v-for="tab in [
          { key: 'logs', label: 'سجل النشاط' },
          { key: 'dup',  label: 'الفواتير المكررة' },
          { key: 'risk', label: 'مؤشرات المخاطر' },
        ]"
        :key="tab.key"
        role="tab"
        :aria-selected="activeTab === tab.key"
        :class="['tab-btn', activeTab === tab.key && 'tab-btn--active']"
        @click="onTabChange(tab.key as 'logs' | 'dup' | 'risk')"
      >
        {{ tab.label }}
      </button>
    </nav>

    <!-- ── Tab 1: سجل النشاط ──────────────────────────────────────────────── -->
    <div v-if="activeTab === 'logs'">
      <!-- Filters -->
      <div class="filters-bar">
        <input
          v-model="searchQuery"
          type="text"
          class="filter-input"
          placeholder="بحث عن مستخدم، إجراء، مرجع…"
          style="min-width: 220px;"
        >
        <select v-model="filters.action" class="filter-input">
          <option value="">كل الإجراءات</option>
          <option v-for="[value, label] in ACTION_OPTIONS" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <div class="date-range">
          <input v-model="filters.from_date" type="date" class="filter-input filter-date" placeholder="من تاريخ">
          <span class="date-sep">—</span>
          <input v-model="filters.to_date" type="date" class="filter-input filter-date" placeholder="إلى تاريخ">
        </div>
        <button class="btn-apply" @click="applyFilters">تطبيق</button>
        <button class="btn-reset" @click="resetFilters">إعادة تعيين</button>
      </div>

      <div v-if="logsLoading" class="state-card">
        <div class="skeleton-table">
          <div v-for="i in 6" :key="i" class="skeleton-row" />
        </div>
      </div>

      <div v-else-if="logsError" class="state-card state-error">
        {{ logsError }}
        <button class="btn-retry" @click="loadLogs(currentPage)">إعادة المحاولة</button>
      </div>

      <div v-else class="card">
        <table class="data-table" data-testid="audit-table">
          <thead>
            <tr>
              <th>المستخدم</th>
              <th>الإجراء</th>
              <th>الطلب</th>
              <th>الجهاز</th>
              <th>IP</th>
              <th>التوقيت</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredLogs.length === 0">
              <td colspan="6" class="empty-row">لا توجد سجلات تطابق البحث.</td>
            </tr>
            <tr v-for="log in filteredLogs" :key="log.id">
              <td class="actor-cell">
                <div class="font-medium text-sm">{{ actorName(log) }}</div>
                <div v-if="actorRole(log)" class="text-xs" style="color: #6c757d;">{{ actorRole(log) }}</div>
              </td>
              <td>
                <Badge variant="secondary">{{ actionLabel(log.action) }}</Badge>
              </td>
              <td>
                <NuxtLink
                  v-if="log.entity_type === 'ImportRequest' && log.entity_id"
                  :to="`/requests/${log.entity_id}`"
                  class="font-mono text-xs"
                  style="color: #0066cc;"
                >
                  {{ formatRef(log.entity_reference) }}
                </NuxtLink>
                <span v-else style="color: #6c757d;">—</span>
              </td>
              <td class="text-xs" style="color: #6c757d;">{{ parseDevice(log.user_agent) }}</td>
              <td class="text-xs" style="color: #6c757d;">{{ log.ip_address ?? '—' }}</td>
              <td class="text-xs whitespace-nowrap" style="color: #6c757d;">{{ formatDate(log.created_at) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="pagination">
          <button class="page-btn" :disabled="currentPage <= 1" @click="loadLogs(currentPage - 1)">السابق</button>
          <span class="page-info">{{ currentPage }} / {{ lastPage }}</span>
          <button class="page-btn" :disabled="currentPage >= lastPage" @click="loadLogs(currentPage + 1)">التالي</button>
        </div>
      </div>
    </div>

    <!-- ── Tab 2: الفواتير المكررة ────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'dup'">
      <div v-if="dupLoading" class="state-card">
        <div class="skeleton-table">
          <div v-for="i in 4" :key="i" class="skeleton-row" />
        </div>
      </div>

      <div v-else-if="dupError" class="state-card state-error">
        {{ dupError }}
        <button class="btn-retry" @click="() => loadDuplicates()">إعادة المحاولة</button>
      </div>

      <template v-else>
        <div
          v-if="dupTotal > 0"
          class="dup-banner"
          data-testid="dup-banner"
        >
          <Icon name="alert-triangle" :size="18" style="color: #c62828; margin-top: 2px; flex-shrink: 0;" />
          <p class="dup-banner-text">
            تم اكتشاف {{ dupTotal }} مجموعات لفواتير مكررة بحاجة لمراجعة عاجلة
          </p>
        </div>

        <div v-if="dupTotal === 0" class="state-card">
          <p style="color: #6c757d; font-size: 14px;">لا توجد فواتير مكررة حالياً.</p>
        </div>

        <div v-else class="dup-list" data-testid="dup-groups">
          <div
            v-for="group in duplicates"
            :key="group.invoice_number"
            class="dup-card"
          >
            <button
              type="button"
              class="dup-card-header"
              :aria-expanded="expandedGroups.has(group.invoice_number)"
              @click="toggleGroup(group.invoice_number)"
            >
              <div class="dup-card-header-info">
                <span class="dup-ref">فاتورة: {{ group.invoice_number }}</span>
                <span class="dup-detail">{{ group.banks.join('، ') }}</span>
                <Badge variant="destructive" style="font-size: 11px;">{{ group.requests.length }} طلبات</Badge>
              </div>
              <Icon
                :name="expandedGroups.has(group.invoice_number) ? 'chevron-up' : 'chevron-down'"
                :size="16"
                style="flex-shrink: 0; color: #6c757d;"
              />
            </button>

            <div v-if="expandedGroups.has(group.invoice_number)" class="dup-rows">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>الرقم المرجعي</th>
                    <th>البنك</th>
                    <th>المبلغ</th>
                    <th>العملة</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="req in group.requests" :key="req.id">
                    <td>
                      <NuxtLink :to="`/requests/${req.id}`" style="color: #0066cc;" class="font-mono text-xs">
                        {{ req.reference_number }}
                      </NuxtLink>
                    </td>
                    <td class="text-sm">{{ req.bank_name ?? '—' }}</td>
                    <td class="text-sm font-mono">{{ req.amount.toLocaleString('ar') }}</td>
                    <td class="text-sm">{{ req.currency }}</td>
                    <td><Badge variant="secondary" style="font-size: 11px;">{{ req.status }}</Badge></td>
                    <td class="text-xs" style="color: #6c757d;">{{ formatDate(req.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Tab 3: مؤشرات المخاطر ────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'risk'">
      <div v-if="riskLoading" class="state-card">
        <div class="skeleton-table">
          <div v-for="i in 4" :key="i" class="skeleton-row" />
        </div>
      </div>

      <div v-else-if="riskError" class="state-card state-error">
        {{ riskError }}
        <button class="btn-retry" @click="() => loadRiskIndicators()">إعادة المحاولة</button>
      </div>

      <template v-else>
        <h2 class="risk-section-title">مؤشرات المخاطر النشطة</h2>
        <div class="risk-list" data-testid="risk-list">
          <div
            v-for="(indicator, idx) in riskIndicators"
            :key="idx"
            class="risk-row"
          >
            <Icon
              name="shield-check"
              :size="18"
              :style="{ color: riskIconColor(indicator.level), marginTop: '2px', flexShrink: '0' }"
            />
            <div class="risk-body">
              <p class="risk-title">{{ indicator.title }}</p>
              <p class="risk-desc">{{ indicator.body }}</p>
            </div>
            <span
              class="risk-badge"
              :class="{
                'risk-badge--high': indicator.level === 'عالية',
                'risk-badge--med':  indicator.level === 'متوسطة',
                'risk-badge--low':  indicator.level === 'منخفضة',
              }"
            >
              {{ indicator.level }}
            </span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}

.breadcrumb-item {
  font-size: 13px;
  color: #6c757d;
}

.breadcrumb-sep {
  font-size: 12px;
  color: #6c757d;
}

.breadcrumb-current {
  color: #1c222b;
  font-weight: 500;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary, #1c222b);
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary, #6c757d);
  margin: 0;
}

/* KPI strip */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (min-width: 1024px) {
  .kpi-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.kpi-card-wrap {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.kpi-label {
  font-size: 12px;
  color: #6c757d;
}

.kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: #1c222b;
  line-height: 1.2;
}

/* Tabs */
.tab-nav {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #cccccc;
}

.tab-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  color: #6c757d;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}

.tab-btn:hover {
  color: #1c222b;
}

.tab-btn--active {
  color: #0066cc;
  border-bottom-color: #0066cc;
}

/* Filters */
.filters-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.filter-input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 10px;
  font-size: 14px;
  color: var(--color-text-primary, #1c222b);
  background: var(--color-surface, #ffffff);
  outline: none;
  direction: rtl;
}

.filter-input:focus {
  border-color: #0066cc;
}

.filter-date {
  width: 150px;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-sep {
  color: var(--color-text-secondary, #6c757d);
  font-size: 14px;
}

.btn-apply {
  height: 40px;
  padding: 0 18px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
}

.btn-reset {
  height: 40px;
  padding: 0 18px;
  background: transparent;
  color: var(--color-text-secondary, #6c757d);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
}

/* State cards */
.state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  padding: 48px 24px;
  color: var(--color-text-secondary, #6c757d);
  text-align: center;
}

.state-error {
  color: #c62828;
}

.btn-retry {
  padding: 8px 20px;
  background: transparent;
  color: #0066cc;
  border: 1px solid #0066cc;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

/* Skeleton */
.skeleton-table {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.skeleton-row {
  height: 40px;
  background: #f0f0f3;
  border-radius: 6px;
  animation: pulse 1.2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Table */
.card {
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  direction: rtl;
}

.data-table th,
.data-table td {
  padding: 12px 14px;
  text-align: right;
  font-size: 13px;
}

.data-table th {
  background: #f5f5f7;
  color: var(--color-text-secondary, #6c757d);
  font-weight: 500;
  border-bottom: 1px solid var(--color-border, #cccccc);
  white-space: nowrap;
}

.data-table td {
  border-bottom: 1px solid var(--color-border, #cccccc);
  color: var(--color-text-primary, #1c222b);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.actor-cell {
  min-width: 120px;
}

.action-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  background: #f0f0f3;
  color: #6e6e73;
}

.empty-row {
  text-align: center !important;
  color: var(--color-text-secondary, #6c757d);
  padding: 32px !important;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid var(--color-border, #cccccc);
}

.page-btn {
  height: 36px;
  padding: 0 16px;
  background: transparent;
  color: #0066cc;
  border: 1px solid #0066cc;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 13px;
  color: var(--color-text-secondary, #6c757d);
}

/* Dup tab */
.dup-banner {
  background: #fff5f5;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 16px;
}

.dup-banner-text {
  font-size: 14px;
  font-weight: 500;
  color: #c62828;
  margin: 0;
}

.dup-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.dup-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  overflow: hidden;
}

.dup-card-header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: right;
}

.dup-card-header:hover {
  background: #f9fafb;
}

.dup-card-header-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.dup-ref {
  font-family: monospace;
  font-weight: 700;
  font-size: 14px;
  color: #1c222b;
}

.dup-detail {
  font-size: 12px;
  color: #6c757d;
}

.dup-rows {
  border-top: 1px solid #eeeeee;
  overflow-x: auto;
}

/* Risk tab */
.risk-section-title {
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 12px;
}

.risk-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.risk-row {
  border: 1px solid #cccccc;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: #ffffff;
}

.risk-body {
  flex: 1;
  min-width: 0;
}

.risk-title {
  font-size: 14px;
  font-weight: 500;
  color: #1c222b;
  margin: 0;
}

.risk-desc {
  font-size: 12px;
  color: #6c757d;
  margin: 2px 0 0;
}

.risk-badge {
  font-size: 11px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
}

.risk-badge--high {
  background: #fee2e2;
  color: #c62828;
}

.risk-badge--med {
  background: #ffedd5;
  color: #f57f17;
}

.risk-badge--low {
  background: #e0f2fe;
  color: #32ade6;
}
</style>
