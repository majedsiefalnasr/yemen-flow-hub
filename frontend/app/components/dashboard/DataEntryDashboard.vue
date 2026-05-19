<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { DataEntryDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'
import { getBusinessStatus } from '../../constants/workflow'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as DataEntryDashboardStats | null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="de-dashboard" dir="rtl">

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

      <!-- KPI grid: مكتمل / صدر البيان | قيد المعالجة | بحاجة تعديل | مسودات -->
      <div class="kpi-grid">
        <!-- مكتمل -->
        <div class="kpi-card kpi-card--green">
          <div class="kpi-card__icon kpi-icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.completed }}</span>
          <span class="kpi-card__label">مكتمل / صدر البيان</span>
        </div>

        <!-- قيد المعالجة -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--blue" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.under_cby_processing }}</span>
          <span class="kpi-card__label">قيد المعالجة</span>
        </div>

        <!-- بحاجة تعديل -->
        <div class="kpi-card" :class="{ 'kpi-card--amber': stats.returned > 0 }">
          <div class="kpi-card__icon" :class="stats.returned > 0 ? 'kpi-icon--amber' : 'kpi-icon--gray'" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 .49-3.15" />
            </svg>
          </div>
          <span class="kpi-card__value" :class="{ 'kpi-value--amber': stats.returned > 0 }">{{ stats.returned }}</span>
          <span class="kpi-card__label">بحاجة تعديل</span>
        </div>

        <!-- مسودات أم أفكار -->
        <div class="kpi-card">
          <div class="kpi-card__icon kpi-icon--gray" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
          <span class="kpi-card__value">{{ stats.draft }}</span>
          <span class="kpi-card__label">مسودات أم أفكار</span>
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
          <!-- إنشاء طلب جديد -->
          <button class="qa-card qa-card--primary" @click="router.push('/requests/new')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />
              </svg>
            </div>
            <span class="qa-card__label">إنشاء طلب جديد</span>
            <span class="qa-card__sub">لبدء طلب تمويل جديد</span>
          </button>

          <!-- متابعة طلباتك -->
          <button class="qa-card" @click="router.push('/requests')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
            </div>
            <span class="qa-card__label">متابعة طلباتك</span>
            <span class="qa-card__sub">كل ما قدّمت رأيناه</span>
          </button>

          <!-- الإشعارات -->
          <button class="qa-card" @click="router.push('/notifications')">
            <div class="qa-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </div>
            <span class="qa-card__label">الإشعارات</span>
            <span class="qa-card__sub">آخر التحديثات على طلباتك</span>
          </button>
        </div>
      </section>

      <!-- Returned requests attention card -->
      <div
        v-if="stats.returned_requests.length > 0"
        class="returned-alert"
        role="alert"
        aria-label="طلبات تحتاج تعديل"
      >
        <div class="returned-alert__header">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f57f17" stroke-width="2" aria-hidden="true">
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <span class="returned-alert__title">
            طلبات تستلزم منك تعديلاً ({{ stats.returned_requests.length }})
          </span>
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

      <!-- Two-column: مسوداتي | آخر نشاطي -->
      <div class="two-col">

        <!-- مسوداتي (draft requests) -->
        <section class="table-card" aria-labelledby="drafts-heading">
          <div class="table-card__header">
            <h2 id="drafts-heading" class="table-card__title">مسوداتي</h2>
            <a class="table-card__viewall" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
          </div>
          <div v-if="stats.returned_requests.length === 0 && stats.draft === 0" class="empty-state" role="status">
            <p>لا توجد مسودات بعد</p>
          </div>
          <table v-else class="req-table" role="table" aria-label="مسوداتي">
            <thead>
              <tr>
                <th scope="col">المرجع</th>
                <th scope="col">التاجر</th>
                <th scope="col">المبلغ</th>
                <th scope="col">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="req in stats.returned_requests.slice(0, 5)"
                :key="req.id"
                class="req-table__row"
                @click="router.push(`/requests/${req.id}`)"
              >
                <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td>{{ req.supplier_name }}</td>
                <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
                <td><button class="btn-action" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- آخر نشاطي (recent requests) -->
        <section class="table-card" aria-labelledby="recent-heading">
          <div class="table-card__header">
            <h2 id="recent-heading" class="table-card__title">آخر نشاطي</h2>
            <a class="table-card__viewall" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
          </div>
          <div v-if="stats.recent_requests.length === 0" class="empty-state" role="status">
            <p>لا توجد طلبات بعد</p>
          </div>
          <table v-else class="req-table" role="table" aria-label="آخر نشاطي">
            <thead>
              <tr>
                <th scope="col">المرجع</th>
                <th scope="col">التاجر</th>
                <th scope="col">المبلغ</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="req in stats.recent_requests"
                :key="req.id"
                class="req-table__row"
                @click="router.push(`/requests/${req.id}`)"
              >
                <td><a class="req-ref" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                <td>{{ req.supplier_name }}</td>
                <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
                <td><StatusBadge :status="req.status" :role="UserRole.DATA_ENTRY" /></td>
                <td><button class="btn-action" @click.stop="router.push(`/requests/${req.id}`)">عرض</button></td>
              </tr>
            </tbody>
          </table>
        </section>

      </div>

    </template>
  </div>
</template>

<style scoped>
.de-dashboard {
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

.kpi-card--green  { border-color: #cccccc; }
.kpi-card--amber  { border-inline-start: 3px solid #f57f17; }

.kpi-card__icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.kpi-icon--green  { background: #e8f5e9; color: #1b5e20; }
.kpi-icon--blue   { background: #e3f2fd; color: #0066cc; }
.kpi-icon--amber  { background: #fff8e1; color: #f57f17; }
.kpi-icon--gray   { background: #f5f5f5; color: #6c757d; }

.kpi-card__value {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  line-height: 1;
}

.kpi-value--amber { color: #f57f17; }

.kpi-card--green .kpi-card__value { color: #1b5e20; }

.kpi-card__label {
  font-size: 13px;
  color: #6c757d;
}

/* Skeleton */
.kpi-skeleton { gap: 12px; animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.skel { background: #f5f5f5; border-radius: 6px; }
.skel--label { height: 14px; width: 60%; }
.skel--value { height: 32px; width: 40%; }

/* Error */
.error-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 20px;
  color: #c62828;
  display: flex;
  align-items: center;
  gap: 12px;
}
.btn-retry {
  margin-inline-start: auto;
  padding: 6px 16px;
  background: #ffffff;
  border: 1px solid #c62828;
  border-radius: 8px;
  color: #c62828;
  font-size: 13px;
  cursor: pointer;
}
.btn-retry:hover { background: #fde8e8; }

/* Section heading */
.section-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 12px;
}

/* Quick actions */
.quick-actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

@media (max-width: 700px) { .quick-actions { grid-template-columns: 1fr; } }

.qa-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 16px 20px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  cursor: pointer;
  text-align: right;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.qa-card:hover {
  border-color: #0066cc;
  box-shadow: 0 2px 8px rgba(0,102,204,0.08);
}

.qa-card--primary {
  background: #0066cc;
  border-color: #0066cc;
  color: #ffffff;
}

.qa-card--primary:hover {
  background: #0052a3;
  border-color: #0052a3;
}

.qa-card--primary .qa-card__label { color: #ffffff; }
.qa-card--primary .qa-card__sub   { color: rgba(255,255,255,0.75); }
.qa-card--primary .qa-card__icon  { color: #ffffff; }

.qa-card__icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0066cc;
  margin-bottom: 4px;
}

.qa-card__label {
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.qa-card__sub {
  font-size: 12px;
  color: #6c757d;
}

/* Returned alert */
.returned-alert {
  background: #ffffff;
  border: 1px solid #f57f17;
  border-inline-start: 4px solid #f57f17;
  border-radius: 12px;
  padding: 16px 20px;
}

.returned-alert__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.returned-alert__title {
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.returned-alert__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }

.returned-alert__item { border-top: 1px solid #f5f5f5; padding-top: 6px; }
.returned-alert__item:first-child { border-top: none; padding-top: 0; }

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
  color: #0066cc;
}

.returned-alert__supplier { font-size: 13px; color: #6c757d; }

/* Two-column layout */
.two-col {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

.table-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 16px 20px;
  overflow: hidden;
}

.table-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.table-card__title {
  font-size: 15px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.table-card__viewall {
  font-size: 13px;
  color: #0066cc;
  text-decoration: none;
}

.table-card__viewall:hover { text-decoration: underline; }

/* Empty state */
.empty-state {
  padding: 24px;
  text-align: center;
  color: #6c757d;
  font-size: 14px;
}

.empty-state p { margin: 0; }

/* Request table */
.req-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.req-table th {
  padding: 8px 10px;
  text-align: right;
  font-weight: 500;
  color: #6c757d;
  border-bottom: 1px solid #f0f0f0;
  white-space: nowrap;
}

.req-table__row { cursor: pointer; }
.req-table__row:hover td { background: #f9fafb; }
.req-table__row + .req-table__row td { border-top: 1px solid #f0f0f0; }

.req-table td {
  padding: 10px 10px;
  color: #1c222b;
  text-align: right;
  vertical-align: middle;
}

.req-ref {
  font-family: monospace;
  color: #0066cc;
  text-decoration: none;
}
.req-ref:hover { text-decoration: underline; }

.mono {
  direction: ltr;
  font-variant-numeric: tabular-nums;
  text-align: left;
}

.btn-action {
  padding: 5px 12px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 8px;
  font-size: 12px;
  color: #1c222b;
  cursor: pointer;
}
.btn-action:hover { border-color: #0066cc; color: #0066cc; }
</style>
