<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ExecutiveDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as ExecutiveDashboardStats | null)
const isDirector = computed(() => auth.user?.role === UserRole.COMMITTEE_DIRECTOR)
const customsDeclarationPending = computed(() => stats.value?.customs_declaration_pending ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function isVotingOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
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
    <div v-else-if="stats" class="kpi-grid" :class="{ 'kpi-grid--director': isDirector }">
      <div class="kpi-card kpi-card--amber" :class="{ 'kpi-card--highlight': stats.waiting_for_voting_open > 0 }">
        <span class="kpi-label">بانتظار فتح التصويت</span>
        <span class="kpi-value">{{ stats.waiting_for_voting_open }}</span>
      </div>
      <div class="kpi-card kpi-card--indigo" :class="{ 'kpi-card--highlight-indigo': stats.active_voting_sessions > 0 }">
        <span class="kpi-label">جلسات تصويت نشطة</span>
        <span class="kpi-value">{{ stats.active_voting_sessions }}</span>
      </div>
      <div v-if="isDirector" class="kpi-card kpi-card--green">
        <span class="kpi-label">قرارات نهائية</span>
        <span class="kpi-value">{{ stats.finalized_decisions }}</span>
      </div>
      <div v-if="!isDirector" class="kpi-card kpi-card--green">
        <span class="kpi-label">قرارات معتمدة</span>
        <span class="kpi-value">{{ stats.decisions_approved }}</span>
      </div>
      <div v-if="!isDirector" class="kpi-card kpi-card--red">
        <span class="kpi-label">قرارات مرفوضة</span>
        <span class="kpi-value">{{ stats.decisions_rejected }}</span>
      </div>
    </div>

    <!-- Voting queue table -->
    <div v-if="stats" class="voting-queue">
      <h2 class="section-title">طابور التصويت التنفيذي</h2>

      <div v-if="stats.voting_queue.length === 0" class="empty-queue" role="status">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <p>لا توجد طلبات في طابور التصويت حالياً</p>
      </div>

      <table v-else class="req-table" role="table" aria-label="طابور التصويت التنفيذي">
        <thead>
          <tr class="req-table__header-row">
            <th class="req-table__th" scope="col">المرجع</th>
            <th class="req-table__th" scope="col">البنك</th>
            <th class="req-table__th" scope="col">المبلغ</th>
            <th class="req-table__th" scope="col">الحالة</th>
            <th class="req-table__th" scope="col">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="req in stats.voting_queue"
            :key="req.id"
            class="req-table__row"
            :class="{ 'req-table__row--voting-open': isVotingOpen(req.status) }"
          >
            <td class="req-table__td">
              <a
                :href="`/requests/${req.id}`"
                class="req-ref"
                @click.prevent="router.push(`/requests/${req.id}`)"
              >{{ req.reference_number }}</a>
            </td>
            <td class="req-table__td">{{ req.bank_name ?? '—' }}</td>
            <td class="req-table__td req-table__td--mono">{{ formatAmount(req.amount, req.currency) }}</td>
            <td class="req-table__td">
              <div class="status-cell">
                <StatusBadge :status="req.status" :role="UserRole.EXECUTIVE_MEMBER" />
                <span v-if="isVotingOpen(req.status)" class="voting-open-badge" aria-label="التصويت جارٍ">
                  التصويت جارٍ
                </span>
              </div>
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

    <!-- Director customs declaration queue -->
    <div v-if="stats && isDirector" class="customs-pending">
      <h2 class="section-title">بيانات جمركية بانتظار الإصدار</h2>

      <div v-if="customsDeclarationPending.length === 0" class="empty-queue" role="status">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <p>لا توجد طلبات بانتظار إصدار البيان الجمركي حالياً</p>
      </div>

      <table v-else class="req-table" role="table" aria-label="طلبات بانتظار إصدار البيان الجمركي">
        <thead>
          <tr class="req-table__header-row">
            <th class="req-table__th" scope="col">المرجع</th>
            <th class="req-table__th" scope="col">البنك</th>
            <th class="req-table__th" scope="col">المبلغ</th>
            <th class="req-table__th" scope="col">الحالة</th>
            <th class="req-table__th req-table__th--action" scope="col">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="req in customsDeclarationPending"
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
            <td class="req-table__td">{{ req.bank_name ?? '—' }}</td>
            <td class="req-table__td req-table__td--mono">{{ formatAmount(req.amount, req.currency) }}</td>
            <td class="req-table__td">
              <StatusBadge :status="req.status" :role="UserRole.COMMITTEE_DIRECTOR" />
            </td>
            <td class="req-table__td req-table__td--action">
              <button
                class="btn-view btn-view--primary"
                :aria-label="`إصدار البيان الجمركي للطلب ${req.reference_number}`"
                @click="router.push(`/requests/${req.id}`)"
              >إصدار</button>
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

.kpi-grid--director {
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 600px) {
  .kpi-grid,
  .kpi-grid--director {
    grid-template-columns: 1fr;
  }
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

.kpi-card--highlight-indigo {
  border-right: 4px solid #5856d6;
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
.kpi-card--indigo .kpi-value { color: #5856d6; }
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

/* Voting queue */
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
.req-table__row--voting-open { background: #f3f2ff; }
.req-table__row--voting-open:hover { background: #e9e8ff; }

.req-table__td {
  padding: 10px 16px;
  font-size: 14px;
  color: #1d1d1f;
  text-align: right;
}

.req-table__th--action,
.req-table__td--action {
  text-align: left;
}

.req-table__td--mono { font-family: monospace; font-size: 13px; }

.req-ref {
  font-family: monospace;
  font-size: 13px;
  color: #0071e3;
  text-decoration: none;
}

.req-ref:hover { text-decoration: underline; }

.status-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.voting-open-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  background: #5856d6;
  color: #ffffff;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  animation: badge-pulse 2s ease-in-out infinite;
}

@keyframes badge-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.btn-view {
  min-height: 48px;
  min-width: 48px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #0071e3;
  border-radius: 8px;
  color: #0071e3;
  font-size: 14px;
  cursor: pointer;
}

.btn-view:hover { background: #f0f7ff; }

.btn-view--primary {
  background: #0071e3;
  color: #ffffff;
}

.btn-view--primary:hover {
  background: #0077ed;
}

@media (max-width: 600px) {
  .req-table {
    display: block;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .req-table__th,
  .req-table__td {
    white-space: nowrap;
  }
}
</style>
