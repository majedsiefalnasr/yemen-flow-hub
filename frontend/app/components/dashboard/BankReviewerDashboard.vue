<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as BankReviewerDashboardStats | null)

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
      <div class="kpi-card kpi-card--amber" :class="{ 'kpi-card--highlight': stats.pending_review > 0 }">
        <span class="kpi-label">بانتظار المراجعة</span>
        <span class="kpi-value">{{ stats.pending_review }}</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">لدى CBY</span>
        <span class="kpi-value">{{ stats.at_cby }}</span>
      </div>
      <div class="kpi-card kpi-card--red" :class="{ 'kpi-card--highlight-red': stats.returned_by_support > 0 }">
        <span class="kpi-label">مُعاد من لجنة الدعم</span>
        <span class="kpi-value">{{ stats.returned_by_support }}</span>
      </div>
      <div class="kpi-card kpi-card--green">
        <span class="kpi-label">معتمد / مكتمل</span>
        <span class="kpi-value">{{ stats.approved_completed }}</span>
      </div>
    </div>

    <!-- Review queue table -->
    <div v-if="stats" class="review-queue">
      <h2 class="section-title">طابور المراجعة</h2>

      <div v-if="stats.review_queue.length === 0" class="empty-queue" role="status">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <p>لا توجد طلبات في طابور المراجعة حالياً</p>
      </div>

      <table v-else class="req-table" role="table" aria-label="طابور المراجعة">
        <thead>
          <tr class="req-table__header-row">
            <th class="req-table__th" scope="col">المرجع</th>
            <th class="req-table__th" scope="col">المورد</th>
            <th class="req-table__th" scope="col">المبلغ</th>
            <th class="req-table__th" scope="col">الحالة</th>
            <th class="req-table__th" scope="col">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="req in stats.review_queue"
            :key="req.id"
            class="req-table__row"
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
              <StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" />
            </td>
            <td class="req-table__td">
              <button
                class="btn-view"
                :aria-label="`عرض الطلب ${req.reference_number}`"
                @click="router.push(`/requests/${req.id}`)"
              >عرض</button>
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

.kpi-card--highlight-red {
  border-right: 4px solid #ff3b30;
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
.kpi-card--red .kpi-value { color: #ff3b30; }

/* Skeleton */
.kpi-card--skeleton {
  gap: 12px;
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.skeleton { background: #f5f5f7; border-radius: 6px; }
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

/* Review queue */
.section-title {
  font-size: 16px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0 0 12px;
}

.empty-queue {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #8e8e93;
  font-size: 14px;
}

.empty-queue p { margin: 0; }

.req-table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  overflow: hidden;
}

.req-table__header-row { background: #f5f5f7; }

.req-table__th {
  padding: 12px 16px;
  text-align: right;
  font-size: 12px;
  font-weight: 500;
  color: #6e6e73;
  border-bottom: 1px solid #d2d2d7;
}

.req-table__row { height: 44px; }
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

.btn-view {
  padding: 6px 14px;
  background: #ffffff;
  border: 1px solid #0071e3;
  border-radius: 8px;
  color: #0071e3;
  font-size: 13px;
  cursor: pointer;
  min-height: 32px;
}

.btn-view:hover { background: #f0f7ff; }
</style>
