// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/executive page level
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
  <div class="exec-dashboard" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 3" :key="n" class="kpi-card kpi-skeleton" aria-hidden="true">
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

      <!-- KPI grid — 3 cards for EXECUTIVE_MEMBER, 4 for COMMITTEE_DIRECTOR -->
      <div class="kpi-grid" :class="{ 'kpi-grid--4': isDirector }">
        <!-- قرارات رفض -->
        <div class="kpi-card kpi-card--red">
          <div class="kpi-card__icon kpi-icon--red" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.decisions_rejected }}</span>
          <span class="kpi-card__label">قرارات رفض</span>
        </div>

        <!-- قرارات اعتماد -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ isDirector ? stats.finalized_decisions : stats.decisions_approved }}</span>
          <span class="kpi-card__label">{{ isDirector ? 'قرارات اعتماد' : 'قرارات اعتماد' }}</span>
        </div>

        <!-- طابور التصويت -->
        <div class="kpi-card kpi-card--indigo" :class="{ 'kpi-card--indigo-highlight': stats.active_voting_sessions > 0 }">
          <div class="kpi-card__icon kpi-icon--indigo" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.active_voting_sessions }}</span>
          <span class="kpi-card__label">طابور التصويت</span>
        </div>

        <!-- D6: Director override count (amber) — COMMITTEE_DIRECTOR only -->
        <div v-if="isDirector" class="kpi-card kpi-card--amber">
          <div class="kpi-card__icon kpi-icon--amber" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ (stats as any).director_override_count ?? 0 }}</span>
          <span class="kpi-card__label">تجاوزات المدير</span>
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
                <path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
              </svg>
            </div>
            <span class="qa-card__label">طابور التصويت</span>
            <span class="qa-card__sub">{{ stats.active_voting_sessions }} طلب بانتظار التصويت</span>
          </button>

          <button class="qa-card" @click="router.push('/reports')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="qa-card__label">التقارير</span>
            <span class="qa-card__sub">تقارير التصويت والقرارات</span>
          </button>
        </div>
      </section>

      <!-- Voting queue — "طلبات بانتظار تصويتك" -->
      <section aria-labelledby="voting-queue-heading">
        <div class="section-header">
          <h2 id="voting-queue-heading" class="section-title">طلبات بانتظار تصويتك</h2>
          <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="(stats.voting_queue?.length ?? 0) === 0" class="empty-queue" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد طلبات في طابور التصويت حالياً</p>
        </div>

        <table v-else class="req-table" role="table" aria-label="طلبات بانتظار تصويتك">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">المورد</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">التصويت</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="req in (stats.voting_queue ?? [])"
              :key="req.id"
              class="req-table__row"
              :class="{ 'req-table__row--voting-open': isVotingOpen(req.status) }"
            >
              <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td>{{ req.supplier_name }}</td>
              <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.EXECUTIVE_MEMBER" /></td>
              <td>
                <span v-if="isVotingOpen(req.status)" class="voting-chip">باب التصويت مفتوح</span>
                <span v-else class="closed-chip">انتظار فتح التصويت</span>
              </td>
              <td><button class="btn-action" :aria-label="`عرض الطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">عرض</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Director-only: customs declaration queue -->
      <section v-if="isDirector" aria-labelledby="customs-heading">
        <div class="section-header">
          <h2 id="customs-heading" class="section-title">بيانات جمركية بانتظار الإصدار</h2>
        </div>
        <div v-if="customsDeclarationPending.length === 0" class="empty-queue" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد بيانات جمركية بانتظار الإصدار حالياً</p>
        </div>
        <table v-else class="req-table" role="table" aria-label="طلبات بانتظار إصدار البيان الجمركي">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">البنك</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in customsDeclarationPending" :key="req.id" class="req-table__row">
              <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td>{{ req.bank_name ?? '—' }}</td>
              <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.COMMITTEE_DIRECTOR" /></td>
              <td><button class="btn-action btn-action--primary" :aria-label="`إصدار البيان الجمركي للطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">إصدار</button></td>
            </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
.exec-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.kpi-grid--4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 1100px) { .kpi-grid--4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; }
.kpi-card--indigo-highlight { border-inline-start: 3px solid #5856d6; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--red    { background: #fde8e8; color: #c62828; }
.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--indigo { background: #ede7f6; color: #5856d6; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }

.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-card--red .kpi-card__value    { color: #c62828; }
.kpi-card--green .kpi-card__value  { color: #1b5e20; }
.kpi-card--indigo .kpi-card__value { color: #5856d6; }
.kpi-card--amber .kpi-card__value  { color: #f57f17; }
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
/* D5: voting CTA uses voting indigo, not primary blue */
.qa-card--primary { background: #5856d6; border-color: #5856d6; }
.qa-card--primary:hover { background: #4b4ac0; }
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
.req-table__row--voting-open td { background: #f3f2ff; }
.req-table__row--voting-open:hover td { background: #e9e8ff; }
.req-table td { padding: 10px 14px; color: #1c222b; text-align: right; vertical-align: middle; }
.req-ref { font-family: monospace; color: #0066cc; text-decoration: none; }
.req-ref:hover { text-decoration: underline; }
.mono { direction: ltr; font-variant-numeric: tabular-nums; text-align: left; }

.voting-chip {
  display: inline-flex; align-items: center; padding: 3px 10px;
  background: #5856d6; color: #ffffff; border-radius: 999px; font-size: 11px; font-weight: 500;
  animation: badge-pulse 2s ease-in-out infinite;
}
.closed-chip {
  display: inline-flex; align-items: center; padding: 3px 10px;
  background: #f0f0f0; color: #6c757d; border-radius: 999px; font-size: 11px;
}
@keyframes badge-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

.btn-action { padding: 5px 14px; background: #ffffff; border: 1px solid #cccccc; border-radius: 8px; font-size: 12px; color: #1c222b; cursor: pointer; }
.btn-action:hover { border-color: #0066cc; color: #0066cc; }
.btn-action--primary { background: #0066cc; color: #ffffff; border-color: #0066cc; }
.btn-action--primary:hover { background: #0052a3; }

@media (max-width: 600px) {
  .req-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .req-table th, .req-table td { white-space: nowrap; }
}
</style>
