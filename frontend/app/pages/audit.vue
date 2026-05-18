<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { AuditLog } from '../types/models'
import { useAudit } from '../composables/useAudit'
import { ROLE_LABELS } from '../constants/workflow'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchAuditLogs } = useAudit()

const logs = ref<AuditLog[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

const filters = reactive({
  action: '',
  from_date: '',
  to_date: '',
})

const ACTION_LABELS: Record<string, string> = {
  submit: 'تقديم الطلب',
  bank_approve: 'موافقة البنك',
  bank_reject: 'رفض البنك',
  support_claim: 'حجز المراجعة',
  support_approve: 'موافقة لجنة الدعم',
  support_reject: 'رفض لجنة الدعم',
  swift_upload: 'رفع SWIFT',
  start_voting: 'فتح جلسة التصويت',
  close_voting: 'إغلاق جلسة التصويت',
  cast_vote: 'تسجيل تصويت',
  finalize_approved: 'اعتماد نهائي — موافقة',
  finalize_rejected: 'اعتماد نهائي — رفض',
  issue_customs: 'إصدار البيان الجمركي',
  complete: 'إتمام الطلب',
  login: 'تسجيل دخول',
  logout: 'تسجيل خروج',
  login_failed: 'محاولة دخول فاشلة',
  document_upload: 'رفع مستند',
  document_download: 'تحميل مستند',
  authorization_failure: 'فشل التخويل',
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

function actionColor(action: string): string {
  if (action.includes('reject') || action.includes('failed') || action.includes('failure')) return '#ff3b30'
  if (action.includes('approve') || action.includes('approved') || action === 'complete') return '#34c759'
  if (action === 'issue_customs') return '#34c759'
  if (action.includes('vote') || action.includes('voting')) return '#5856d6'
  return '#0071e3'
}

async function loadLogs(page = 1) {
  loading.value = true
  error.value = null
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
    error.value = 'تعذّر تحميل سجلات التدقيق.'
  }
  finally {
    loading.value = false
  }
}

function applyFilters() {
  loadLogs(1)
}

function resetFilters() {
  filters.action = ''
  filters.from_date = ''
  filters.to_date = ''
  loadLogs(1)
}

onMounted(() => loadLogs(1))
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">التدقيق والامتثال</h1>
        <p class="page-subtitle">سجل أحداث المنصة — {{ total.toLocaleString('ar') }} حدث</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
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

    <div v-if="loading" class="state-card">
      <div class="spinner" />
      <span>جارٍ التحميل…</span>
    </div>

    <div v-else-if="error" class="state-card state-error">
      {{ error }}
      <button class="btn-retry" @click="loadLogs(currentPage)">إعادة المحاولة</button>
    </div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>التاريخ والوقت</th>
            <th>المستخدم</th>
            <th>الدور</th>
            <th>الإجراء</th>
            <th>الكيان</th>
            <th>الحالة السابقة ← الجديدة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="logs.length === 0">
            <td colspan="6" class="empty-row">لا توجد سجلات تطابق الفلاتر المحددة.</td>
          </tr>
          <tr v-for="log in logs" :key="log.id">
            <td class="date-cell">{{ formatDate(log.created_at) }}</td>
            <td class="actor-cell">{{ actorName(log) }}</td>
            <td>
              <span v-if="actorRole(log)" class="badge badge-role">{{ actorRole(log) }}</span>
            </td>
            <td>
              <span class="action-badge" :style="{ color: actionColor(log.action), borderColor: actionColor(log.action) }">
                {{ actionLabel(log.action) }}
              </span>
            </td>
            <td class="entity-cell">
              <span v-if="log.entity_type">{{ log.entity_type }}{{ log.entity_id ? ` #${log.entity_id}` : '' }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="status-cell">
              <span v-if="log.from_status || log.to_status" class="status-flow">
                <span class="status-from">{{ log.from_status ?? '—' }}</span>
                <span class="status-arrow">←</span>
                <span class="status-to">{{ log.to_status ?? '—' }}</span>
              </span>
              <span v-else class="text-muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="pagination">
        <button
          class="page-btn"
          :disabled="currentPage <= 1"
          @click="loadLogs(currentPage - 1)"
        >
          السابق
        </button>
        <span class="page-info">{{ currentPage }} / {{ lastPage }}</span>
        <button
          class="page-btn"
          :disabled="currentPage >= lastPage"
          @click="loadLogs(currentPage + 1)"
        >
          التالي
        </button>
      </div>
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

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.filters-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  direction: rtl;
}

.filter-input:focus {
  border-color: #0071e3;
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
  color: var(--color-text-secondary);
  font-size: 14px;
}

.btn-apply {
  height: 40px;
  padding: 0 18px;
  background: #0071e3;
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
  color: var(--color-text-secondary);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
}

.state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 48px 24px;
  color: var(--color-text-secondary);
  text-align: center;
}

.state-error {
  color: #ff3b30;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: #0071e3;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-retry {
  padding: 8px 20px;
  background: transparent;
  color: #0071e3;
  border: 1px solid #0071e3;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
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
  color: var(--color-text-secondary);
  font-weight: 500;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.data-table td {
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-primary);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.date-cell {
  white-space: nowrap;
  color: var(--color-text-secondary);
  font-size: 12px;
}

.actor-cell {
  font-weight: 500;
}

.entity-cell {
  font-size: 12px;
  color: var(--color-text-secondary);
}

.status-cell {
  font-size: 12px;
}

.text-muted {
  color: var(--color-text-secondary);
}

.badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}

.badge-role {
  background: #f0f0f3;
  color: #6e6e73;
}

.action-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  border: 1px solid currentColor;
  background: transparent;
}

.status-flow {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
}

.status-from {
  color: var(--color-text-secondary);
}

.status-arrow {
  color: #8e8e93;
}

.status-to {
  color: var(--color-text-primary);
  font-weight: 500;
}

.empty-row {
  text-align: center !important;
  color: var(--color-text-secondary);
  padding: 32px !important;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid var(--color-border);
}

.page-btn {
  height: 36px;
  padding: 0 16px;
  background: transparent;
  color: #0071e3;
  border: 1px solid #0071e3;
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
  color: var(--color-text-secondary);
}
</style>
