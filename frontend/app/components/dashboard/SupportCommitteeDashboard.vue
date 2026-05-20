<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { SupportCommitteeDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed<SupportCommitteeDashboardStats | null>(() => {
  const raw = store.stats as Partial<SupportCommitteeDashboardStats> | null
  if (!raw) return null
  return {
    waiting_for_claim: raw.waiting_for_claim ?? 0,
    active_by_me: raw.active_by_me ?? 0,
    claimed_by_others: raw.claimed_by_others ?? 0,
    recently_approved: raw.recently_approved ?? 0,
    support_queue: Array.isArray(raw.support_queue) ? raw.support_queue : [],
  }
})
const queue = computed(() => stats.value?.support_queue ?? [])
const currentUserId = computed(() => auth.user?.id ?? null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function claimOwnerLabel(req: SupportCommitteeDashboardStats['support_queue'][number]): string {
  if (!req.claimed_by) return 'غير مطالب به'
  if (req.is_claimed_by_me || (currentUserId.value != null && req.claimed_by.id === currentUserId.value)) {
    return `${req.claimed_by.name} (أنت)`
  }
  return req.claimed_by.name
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="sc-dashboard" dir="rtl">

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
        <!-- اعتُمِدت مؤخراً -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.recently_approved }}</span>
          <span class="kpi-card__label">اعتُمِدت مؤخراً</span>
        </div>

        <!-- محجوزة لأعضاء آخرين -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--gray" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.claimed_by_others }}</span>
          <span class="kpi-card__label">محجوزة لأعضاء آخرين</span>
        </div>

        <!-- أعمل عليها الآن -->
        <div class="kpi-card" :class="{ 'kpi-card--indigo': stats.active_by_me > 0 }">
          <div class="kpi-card__icon" :class="stats.active_by_me > 0 ? 'kpi-icon--indigo' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--indigo': stats.active_by_me > 0 }">{{ stats.active_by_me }}</span>
          <span class="kpi-card__label">أعمل عليها الآن</span>
        </div>

        <!-- بانتظار المطالبة -->
        <div class="kpi-card" :class="{ 'kpi-card--amber': stats.waiting_for_claim > 0 }">
          <div class="kpi-card__icon" :class="stats.waiting_for_claim > 0 ? 'kpi-icon--amber' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--amber': stats.waiting_for_claim > 0 }">{{ stats.waiting_for_claim }}</span>
          <span class="kpi-card__label">بانتظار المطالبة</span>
        </div>
      </div>

      <!-- Quick actions (2 cards) -->
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
            <span class="qa-card__label">طابور المساندة</span>
            <span class="qa-card__sub">{{ stats.waiting_for_claim }} طلب جاهز للمراجعة</span>
          </button>

          <button class="qa-card" @click="router.push('/notifications')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="qa-card__label">الإشعارات</span>
            <span class="qa-card__sub">آخر تحديثات الطابور والقرارات</span>
          </button>
        </div>
      </section>

      <!-- Support queue — "طابور عملي" -->
      <section aria-labelledby="queue-heading">
        <div class="section-header">
          <h2 id="queue-heading" class="section-title">طابور عملي</h2>
          <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="queue.length === 0" class="empty-queue" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد طلبات في طابور لجنة الدعم حالياً</p>
        </div>

        <table v-else class="req-table" role="table" aria-label="طابور عملي">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">المورد</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">الحجز</th>
              <th scope="col">التقدم</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr
               v-for="req in queue"
               :key="req.id"
               class="req-table__row"
               :class="{
                 'req-table__row--mine': req.is_claimed_by_me,
                 'req-table__row--claimed': !!req.claimed_by && !req.is_claimed_by_me,
               }"
             >
               <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
               <td>{{ req.supplier_name }}</td>
               <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
               <td><StatusBadge :status="req.status" :role="UserRole.SUPPORT_COMMITTEE" /></td>
               <td>
                 <span
                   class="claim-owner"
                   :class="{
                     'claim-owner--mine': req.is_claimed_by_me,
                     'claim-owner--claimed': !!req.claimed_by && !req.is_claimed_by_me,
                   }"
                 >
                   {{ claimOwnerLabel(req) }}
                 </span>
               </td>
               <td class="progress-cell">
                 <div class="progress-bar">
                   <div
                     class="progress-bar__fill"
                     :class="req.is_claimed_by_me ? 'fill--indigo' : ''"
                     :style="{ width: `${getRequestProgress(req.status)}%` }"
                   />
                 </div>
                 <span class="progress-pct">{{ getRequestProgress(req.status) }}%</span>
               </td>
               <td><button class="btn-action" :aria-label="`عرض الطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">عرض</button></td>
             </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
.sc-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; }
.kpi-card--amber  { border-inline-start: 3px solid #f57f17; }
.kpi-card--indigo { border-inline-start: 3px solid #5856d6; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--indigo { background: #ede7f6; color: #5856d6; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }
.kpi-icon--gray   { background: #f5f5f5; color: #6c757d; }

.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-value--amber  { color: #f57f17; }
.kpi-value--indigo { color: #5856d6; }
.kpi-card--green .kpi-card__value  { color: #1b5e20; }
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
.qa-card--primary { background: #0066cc; border-color: #0066cc; }
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
.req-table__row--mine td { background: #f0f5ff; }
.req-table__row--mine:hover td { background: #e8eeff; }
.req-table__row--claimed td { background: #faf7ff; }
.req-table__row--claimed:hover td { background: #f5eeff; }
.req-table td { padding: 10px 14px; color: #1c222b; text-align: right; vertical-align: middle; }
.req-ref { font-family: monospace; color: #0066cc; text-decoration: none; }
.req-ref:hover { text-decoration: underline; }
.mono { direction: ltr; font-variant-numeric: tabular-nums; text-align: left; }

.claim-owner {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  background: #f5f5f5;
  color: #6c757d;
  font-size: 12px;
  white-space: nowrap;
}

.claim-owner--mine {
  background: #ede7f6;
  color: #5856d6;
}

.claim-owner--claimed {
  background: #fff8e1;
  color: #f57f17;
}

.progress-cell { display: flex; align-items: center; gap: 6px; min-width: 90px; }
.progress-bar { flex: 1; height: 6px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: #0066cc; border-radius: 999px; }
.fill--indigo { background: #5856d6; }
.progress-pct { font-size: 11px; color: #6c757d; white-space: nowrap; }

.btn-action { padding: 5px 14px; background: #ffffff; border: 1px solid #cccccc; border-radius: 8px; font-size: 12px; color: #1c222b; cursor: pointer; }
.btn-action:hover { border-color: #0066cc; color: #0066cc; }
</style>
