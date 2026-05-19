<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankReviewerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<BankReviewerDashboardStats | null>(() => {
  const raw = store.stats as Partial<BankReviewerDashboardStats> | null
  if (!raw || !Array.isArray(raw.review_queue)) return null
  return {
    pending_review: raw.pending_review ?? 0,
    at_cby: raw.at_cby ?? 0,
    returned_by_support: raw.returned_by_support ?? 0,
    approved_completed: raw.approved_completed ?? 0,
    review_queue: raw.review_queue,
  }
})
const queue = computed(() => stats.value?.review_queue ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

/** Map status to a deterministic progress percentage for the progress bar (visual only) */
function progressForStatus(status: string): number {
  const map: Record<string, number> = {
    SUBMITTED: 25,
    BANK_REVIEW: 25,
    BANK_APPROVED: 50,
    SUPPORT_REVIEW_PENDING: 60,
    SUPPORT_REVIEW_IN_PROGRESS: 70,
  }
  return map[status] ?? 25
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="br-dashboard" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="kpi-card kpi-skeleton" aria-hidden="true">
        <div class="skel skel--label" />
        <div class="skel skel--value" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="error-card" role="alert">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="kpi-grid">
        <!-- قُعَّد / مكتمل -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.approved_completed }}</span>
          <span class="kpi-card__label">قُعَّد / مكتمل</span>
        </div>

        <!-- قيد البنك المركزي -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--blue" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.at_cby }}</span>
          <span class="kpi-card__label">قيد البنك المركزي</span>
        </div>

        <!-- قيد للتعديل -->
        <div class="kpi-card" :class="{ 'kpi-card--amber': stats.returned_by_support > 0 }">
          <div class="kpi-card__icon" :class="stats.returned_by_support > 0 ? 'kpi-icon--amber' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 .49-3.15" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--amber': stats.returned_by_support > 0 }">{{ stats.returned_by_support }}</span>
          <span class="kpi-card__label">قيد للتعديل</span>
        </div>

        <!-- بانتظار المراجعة -->
        <div class="kpi-card" :class="{ 'kpi-card--amber': stats.pending_review > 0 }">
          <div class="kpi-card__icon" :class="stats.pending_review > 0 ? 'kpi-icon--amber' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--amber': stats.pending_review > 0 }">{{ stats.pending_review }}</span>
          <span class="kpi-card__label">بانتظار المراجعة</span>
        </div>
      </div>

      <!-- Quick actions -->
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
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <span class="qa-card__label">طابور المراجعة</span>
            <span class="qa-card__sub">{{ stats.pending_review }} طلب بانتظار المراجعة</span>
          </button>

          <button class="qa-card" @click="router.push('/requests')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
              </svg>
            </div>
            <span class="qa-card__label">كل الطلبات</span>
            <span class="qa-card__sub">عرض سائر الطلبات كاملاً</span>
          </button>
        </div>
      </section>

      <!-- Review queue table -->
      <section aria-labelledby="queue-heading">
        <div class="section-header">
          <h2 id="queue-heading" class="section-title">طابور المراجعة الحالي</h2>
          <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="queue.length === 0" class="empty-queue" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد طلبات في طابور المراجعة حالياً</p>
        </div>

        <table v-else class="req-table" role="table" aria-label="طابور المراجعة الحالي">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">المورد</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">التقدم</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in queue" :key="req.id" class="req-table__row">
              <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td>{{ req.supplier_name }}</td>
              <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.BANK_REVIEWER" /></td>
              <td class="progress-cell">
                <div class="progress-bar" :aria-label="`التقدم ${progressForStatus(req.status)}%`">
                  <div class="progress-bar__fill" :style="{ width: `${progressForStatus(req.status)}%` }" />
                </div>
                <span class="progress-pct">{{ progressForStatus(req.status) }}%</span>
              </td>
              <td>
                <button class="btn-action" :aria-label="`عرض الطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">عرض</button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
.br-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.kpi-card--amber { border-inline-start: 3px solid #f57f17; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--blue   { background: #e3f2fd; color: #0066cc; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }
.kpi-icon--gray   { background: #f5f5f5; color: #6c757d; }

.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-value--amber { color: #f57f17; }
.kpi-card--green .kpi-card__value { color: #1b5e20; }
.kpi-card__label { font-size: 13px; color: #6c757d; }

/* Skeleton */
.kpi-skeleton { gap: 12px; animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.skel { background: #f5f5f5; border-radius: 6px; }
.skel--label { height: 14px; width: 60%; }
.skel--value { height: 32px; width: 40%; }

/* Error */
.error-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px; color: #c62828; display: flex; align-items: center; gap: 12px; }
.btn-retry { margin-inline-start: auto; padding: 6px 16px; background: #ffffff; border: 1px solid #c62828; border-radius: 8px; color: #c62828; font-size: 13px; cursor: pointer; }
.btn-retry:hover { background: #fde8e8; }

/* Section heading */
.section-heading { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #1c222b; margin: 0 0 12px; }

/* Quick actions */
.quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (max-width: 600px) { .quick-actions { grid-template-columns: 1fr; } }

.qa-card { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; padding: 16px 20px; background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; cursor: pointer; text-align: right; transition: border-color 0.15s; }
.qa-card:hover { border-color: #0066cc; }
.qa-card--primary { background: #0066cc; border-color: #0066cc; color: #ffffff; }
.qa-card--primary:hover { background: #0052a3; border-color: #0052a3; }
.qa-card--primary .qa-card__label { color: #ffffff; }
.qa-card--primary .qa-card__sub { color: rgba(255,255,255,0.75); }
.qa-card--primary .qa-card__icon { color: #ffffff; }
.qa-card__icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #0066cc; margin-bottom: 4px; }
.qa-card__label { font-size: 14px; font-weight: 600; color: #1c222b; }
.qa-card__sub { font-size: 12px; color: #6c757d; }

/* Section header */
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-title { font-size: 15px; font-weight: 600; color: #1c222b; margin: 0; }
.viewall-link { font-size: 13px; color: #0066cc; text-decoration: none; }
.viewall-link:hover { text-decoration: underline; }

/* Queue table */
.empty-queue { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 40px; display: flex; flex-direction: column; align-items: center; gap: 12px; color: #6c757d; font-size: 14px; }
.empty-queue p { margin: 0; }

.req-table { width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; overflow: hidden; font-size: 13px; }
.req-table thead tr { background: #f9fafb; }
.req-table th { padding: 10px 14px; text-align: right; font-weight: 500; color: #6c757d; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
.req-table__row:hover td { background: #f9fafb; }
.req-table__row + .req-table__row td { border-top: 1px solid #f0f0f0; }
.req-table td { padding: 10px 14px; color: #1c222b; text-align: right; vertical-align: middle; }

.req-ref { font-family: monospace; color: #0066cc; text-decoration: none; }
.req-ref:hover { text-decoration: underline; }
.mono { direction: ltr; font-variant-numeric: tabular-nums; text-align: left; }

/* Progress bar */
.progress-cell { display: flex; align-items: center; gap: 6px; min-width: 100px; }
.progress-bar { flex: 1; height: 6px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: #0066cc; border-radius: 999px; transition: width 0.3s; }
.progress-pct { font-size: 11px; color: #6c757d; white-space: nowrap; }

.btn-action { padding: 5px 14px; background: #ffffff; border: 1px solid #cccccc; border-radius: 8px; font-size: 12px; color: #1c222b; cursor: pointer; }
.btn-action:hover { border-color: #0066cc; color: #0066cc; }
</style>
