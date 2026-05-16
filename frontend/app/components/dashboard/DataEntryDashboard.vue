<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { DataEntryDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'
import { getBusinessStatus } from '../../constants/workflow'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as DataEntryDashboardStats | null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="dashboard" dir="rtl">

    <!-- KPI grid skeleton -->
    <div v-if="store.loading" class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="kpi-card kpi-card--skeleton" aria-hidden="true">
        <div class="skeleton skeleton--label" />
        <div class="skeleton skeleton--value" />
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="store.error" class="error-card" role="alert">
      <span class="error-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      </span>
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <!-- KPI grid -->
    <div v-else-if="stats" class="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">المسودات</span>
        <span class="kpi-value">{{ stats.draft }}</span>
      </div>
      <div class="kpi-card kpi-card--amber" :class="{ 'kpi-card--highlight': stats.returned > 0 }">
        <span class="kpi-label">تحتاج تعديل</span>
        <span class="kpi-value">{{ stats.returned }}</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">قيد معالجة CBY</span>
        <span class="kpi-value">{{ stats.under_cby_processing }}</span>
      </div>
      <div class="kpi-card kpi-card--green">
        <span class="kpi-label">مكتملة</span>
        <span class="kpi-value">{{ stats.completed }}</span>
      </div>
    </div>

    <!-- Returned alert card -->
    <div
      v-if="stats && stats.returned_requests.length > 0"
      class="returned-alert"
      role="alert"
      aria-label="طلبات تحتاج تعديل"
    >
      <div class="returned-alert__header">
        <span class="returned-alert__icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
        </span>
        <span class="returned-alert__title">طلبات مُعادة للتعديل</span>
      </div>
      <ul class="returned-alert__list">
        <li v-for="req in stats.returned_requests" :key="req.id" class="returned-alert__item">
          <a
            :href="`/requests/${req.id}`"
            class="returned-alert__link"
            @click.prevent="router.push(`/requests/${req.id}`)"
          >
            <span class="returned-alert__ref">{{ req.reference_number }}</span>
            <span class="returned-alert__supplier">{{ req.supplier_name }}</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- Quick actions -->
    <div v-if="stats" class="quick-actions">
      <h2 class="section-title">إجراءات سريعة</h2>
      <div class="quick-actions__buttons">
        <button class="btn-primary" @click="router.push('/requests/new')">تقديم طلب جديد</button>
        <button class="btn-secondary" @click="router.push('/requests')">عرض جميع الطلبات</button>
      </div>
    </div>

    <!-- Recent requests table -->
    <div v-if="stats && stats.recent_requests.length > 0" class="recent-requests">
      <h2 class="section-title">آخر الطلبات</h2>
      <table class="req-table" role="table" aria-label="آخر الطلبات">
        <thead>
          <tr class="req-table__header-row">
            <th class="req-table__th" scope="col">المرجع</th>
            <th class="req-table__th" scope="col">المورد</th>
            <th class="req-table__th" scope="col">المبلغ</th>
            <th class="req-table__th" scope="col">الحالة</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="req in stats.recent_requests"
            :key="req.id"
            class="req-table__row"
            @click="router.push(`/requests/${req.id}`)"
          >
            <td class="req-table__td">
              <a
                :href="`/requests/${req.id}`"
                class="req-ref"
                @click.prevent="router.push(`/requests/${req.id}`)"
              >{{ req.reference_number }}</a>
            </td>
            <td class="req-table__td">{{ req.supplier_name }}</td>
            <td class="req-table__td req-table__td--mono">{{ formatAmount(req.amount, req.currency) }}</td>
            <td class="req-table__td">
              <StatusBadge :status="req.status" :role="UserRole.DATA_ENTRY" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* KPI grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 600px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kpi-card--highlight {
  border-right: 4px solid #ff9f0a;
}

.kpi-label {
  font-size: 13px;
  color: #6e6e73;
}

.kpi-value {
  font-size: 28px;
  font-weight: 500;
  color: #1d1d1f;
}

.kpi-card--green .kpi-value { color: #34c759; }
.kpi-card--amber .kpi-value { color: #ff9f0a; }

/* Skeleton */
.kpi-card--skeleton {
  gap: 12px;
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.skeleton {
  background: #f5f5f7;
  border-radius: 6px;
}

.skeleton--label { height: 14px; width: 60%; }
.skeleton--value { height: 32px; width: 40%; }

/* Error */
.error-card {
  background: #fff0f0;
  border: 1px solid #ff3b3033;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: #ff3b30;
}

.error-icon { display: flex; }

.btn-retry {
  margin-right: auto;
  padding: 6px 16px;
  background: #ffffff;
  border: 1px solid #ff3b30;
  border-radius: 8px;
  color: #ff3b30;
  font-size: 13px;
  cursor: pointer;
}

.btn-retry:hover { background: #fff0f0; }

/* Returned alert */
.returned-alert {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-right: 4px solid #ff9f0a;
  border-radius: 12px;
  padding: 16px 20px;
}

.returned-alert__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  color: #ff9f0a;
}

.returned-alert__title {
  font-size: 14px;
  font-weight: 500;
  color: #1d1d1f;
}

.returned-alert__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.returned-alert__item {
  border-top: 1px solid #f5f5f7;
  padding-top: 8px;
}

.returned-alert__item:first-child {
  border-top: none;
  padding-top: 0;
}

.returned-alert__link {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: inherit;
}

.returned-alert__link:hover .returned-alert__ref { text-decoration: underline; }

.returned-alert__ref {
  font-family: monospace;
  font-size: 13px;
  color: #0071e3;
}

.returned-alert__supplier {
  font-size: 13px;
  color: #6e6e73;
}

/* Quick actions */
.section-title {
  font-size: 16px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0 0 12px;
}

.quick-actions__buttons {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.btn-primary {
  padding: 10px 20px;
  background: #0071e3;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  min-height: 44px;
}

.btn-primary:hover { background: #0077ed; }

.btn-secondary {
  padding: 10px 20px;
  background: #ffffff;
  color: #0071e3;
  border: 1px solid #0071e3;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  min-height: 44px;
}

.btn-secondary:hover { background: #f0f7ff; }

/* Recent requests table */
.req-table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  overflow: hidden;
}

.req-table__header-row {
  background: #f5f5f7;
}

.req-table__th {
  padding: 12px 16px;
  text-align: right;
  font-size: 12px;
  font-weight: 500;
  color: #6e6e73;
  border-bottom: 1px solid #d2d2d7;
}

.req-table__row {
  height: 44px;
  cursor: pointer;
}

.req-table__row:hover { background: #f5f5f7; }
.req-table__row + .req-table__row { border-top: 1px solid #d2d2d7; }

.req-table__td {
  padding: 10px 16px;
  font-size: 14px;
  color: #1d1d1f;
  text-align: right;
}

.req-table__td--mono { font-family: monospace; font-size: 13px; }

.req-ref {
  font-family: monospace;
  font-size: 13px;
  color: #0071e3;
  text-decoration: none;
}

.req-ref:hover { text-decoration: underline; }
</style>
