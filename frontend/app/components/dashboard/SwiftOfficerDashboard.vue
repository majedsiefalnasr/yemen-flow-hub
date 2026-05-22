// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/swift-officer page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { SwiftOfficerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<SwiftOfficerDashboardStats | null>(() => {
  const raw = store.stats as Partial<SwiftOfficerDashboardStats> | null
  if (!raw) return null
  return {
    pending_swift_upload: raw.pending_swift_upload ?? 0,
    uploaded: raw.uploaded ?? 0,
    final_approved: raw.final_approved ?? 0,
    final_rejected: raw.final_rejected ?? 0,
    swift_queue: Array.isArray(raw.swift_queue) ? raw.swift_queue : [],
  }
})
const queue = computed(() => stats.value?.swift_queue ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="swift-dashboard" dir="rtl">

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
        <!-- مرفوض نهائياً -->
        <div class="kpi-card kpi-card--red">
          <div class="kpi-card__icon kpi-icon--red" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.final_rejected }}</span>
          <span class="kpi-card__label">مرفوض نهائياً</span>
        </div>

        <!-- مُعتمد نهائياً -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.final_approved }}</span>
          <span class="kpi-card__label">مُعتمد نهائياً</span>
        </div>

        <!-- تم رفع السويفت -->
        <div class="kpi-card kpi-card--cyan">
          <div class="kpi-card__icon kpi-icon--cyan" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="16 16 12 12 8 16" /><line x1="12" y1="12" x2="12" y2="21" />
              <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.uploaded }}</span>
          <span class="kpi-card__label">تم رفع السويفت</span>
        </div>

        <!-- بانتظار رفع السويفت -->
        <div class="kpi-card" :class="{ 'kpi-card--amber': stats.pending_swift_upload > 0 }">
          <div class="kpi-card__icon" :class="stats.pending_swift_upload > 0 ? 'kpi-icon--amber' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--amber': stats.pending_swift_upload > 0 }">{{ stats.pending_swift_upload }}</span>
          <span class="kpi-card__label">بانتظار رفع السويفت</span>
        </div>
      </div>

      <!-- Quick action (single card — Lovable screenshot) -->
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
                <polyline points="16 16 12 12 8 16" /><line x1="12" y1="12" x2="12" y2="21" />
                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
              </svg>
            </div>
            <span class="qa-card__label">طابور رفع السويفت</span>
            <span class="qa-card__sub">{{ stats.pending_swift_upload }} طلب بانتظار الرفع MT103</span>
          </button>
        </div>
      </section>

      <!-- SWIFT queue table -->
      <section aria-labelledby="swift-queue-heading">
        <div class="section-header">
          <h2 id="swift-queue-heading" class="section-title">طابور رفع السويفت</h2>
          <a class="viewall-link" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="queue.length === 0" class="empty-queue" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد طلبات بانتظار رفع SWIFT حالياً</p>
        </div>

        <table v-else class="req-table" role="table" aria-label="طابور رفع السويفت">
          <thead>
            <tr>
              <th scope="col">المرجع</th>
              <th scope="col">البنك</th>
              <th scope="col">المبلغ</th>
              <th scope="col">الحالة</th>
              <th scope="col">التقدم</th>
              <th scope="col">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in queue" :key="req.id" class="req-table__row">
              <td><a class="req-ref" :href="`/requests/${req.id}/swift`" @click.prevent="router.push(`/requests/${req.id}/swift`)">{{ req.reference_number }}</a></td>
              <td>{{ req.bank_name ?? '—' }}</td>
              <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
              <td><StatusBadge :status="req.status" :role="UserRole.SWIFT_OFFICER" /></td>
              <td class="progress-cell">
                <div class="progress-bar">
                  <div class="progress-bar__fill" :style="{ width: `${getRequestProgress(req.status)}%` }" />
                </div>
                <span class="progress-pct">{{ getRequestProgress(req.status) }}%</span>
              </td>
              <td>
                <button class="btn-upload" :aria-label="`رفع SWIFT للطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}/swift`)">
                  رفع SWIFT
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>

<style scoped>
.swift-dashboard { display: flex; flex-direction: column; gap: 24px; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card { background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 6px; }
.kpi-card--amber { border-inline-start: 3px solid #f57f17; }
.kpi-card__icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
.kpi-icon--red    { background: #fde8e8; color: #c62828; }
.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--cyan   { background: #e0f7fa; color: #32ade6; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }
.kpi-icon--gray   { background: #f5f5f5; color: #6c757d; }

.kpi-card__value { font-size: 28px; font-weight: 600; color: #1c222b; line-height: 1; }
.kpi-value--amber  { color: #f57f17; }
.kpi-card--red .kpi-card__value    { color: #c62828; }
.kpi-card--green .kpi-card__value  { color: #1b5e20; }
.kpi-card--cyan .kpi-card__value   { color: #32ade6; }
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
.quick-actions { display: grid; grid-template-columns: repeat(1, 1fr); max-width: 360px; }
.qa-card { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; padding: 16px 20px; background: #ffffff; border: 1px solid #cccccc; border-radius: 12px; cursor: pointer; text-align: right; transition: border-color 0.15s; }
.qa-card:hover { border-color: #0066cc; }
.qa-card--primary { background: #0066cc; border-color: #0066cc; }
.qa-card--primary:hover { background: #0052a3; border-color: #0052a3; }
.qa-card--primary .qa-card__label { color: #ffffff; }
.qa-card--primary .qa-card__sub { color: rgba(255,255,255,0.75); }
.qa-card--primary .qa-card__icon { color: #ffffff; }
.qa-card__icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #32ade6; margin-bottom: 4px; }
.qa-card__label { font-size: 14px; font-weight: 600; color: #1c222b; }
.qa-card__sub { font-size: 12px; color: #6c757d; }

/* Section header */
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-title { font-size: 15px; font-weight: 600; color: #1c222b; margin: 0; }
.viewall-link { font-size: 13px; color: #0066cc; text-decoration: none; }
.viewall-link:hover { text-decoration: underline; }

/* Queue */
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

.progress-cell { display: flex; align-items: center; gap: 6px; min-width: 90px; }
.progress-bar { flex: 1; height: 6px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: #32ade6; border-radius: 999px; }
.progress-pct { font-size: 11px; color: #6c757d; white-space: nowrap; }

.btn-upload { padding: 6px 14px; background: #32ade6; border: none; border-radius: 8px; color: #ffffff; font-size: 13px; cursor: pointer; }
.btn-upload:hover { background: #28a0d8; }

@media (max-width: 600px) {
  .req-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .req-table th, .req-table td { white-space: nowrap; }
  .quick-actions { max-width: 100%; }
}
</style>
