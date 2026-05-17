<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import type { CbyAdminDashboardStats } from '../../composables/useDashboard'

const router = useRouter()
const store = useDashboardStore()

const stats = computed(() => store.stats as CbyAdminDashboardStats | null)

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

    <template v-else-if="stats">
      <!-- KPI grid -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <span class="kpi-label">إجمالي الطلبات</span>
          <span class="kpi-value">{{ stats.total }}</span>
        </div>
        <div class="kpi-card kpi-card--green">
          <span class="kpi-label">موافق عليها</span>
          <span class="kpi-value">{{ stats.approved }}</span>
        </div>
        <div class="kpi-card kpi-card--indigo">
          <span class="kpi-label">قيد المعالجة</span>
          <span class="kpi-value">{{ stats.in_process }}</span>
        </div>
        <div class="kpi-card kpi-card--red">
          <span class="kpi-label">مرفوضة</span>
          <span class="kpi-value">{{ stats.rejected }}</span>
        </div>
      </div>

      <!-- Compliance alerts -->
      <section class="section-card" aria-labelledby="compliance-heading">
        <h2 id="compliance-heading" class="section-title">
          <span class="section-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ff9f0a" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </span>
          تنبيهات الامتثال
        </h2>

        <!-- Duplicate suppliers -->
        <div class="alert-group">
          <h3 class="alert-group__title">موردون مكررون في طلبات نشطة</h3>
          <div v-if="stats.compliance_alerts.duplicate_suppliers.length === 0" class="alert-empty">
            لا توجد تنبيهات
          </div>
          <ul v-else class="alert-list">
            <li
              v-for="item in stats.compliance_alerts.duplicate_suppliers"
              :key="item.supplier_name"
              class="alert-list__item"
            >
              <span class="alert-list__name">{{ item.supplier_name }}</span>
              <span class="alert-list__badge alert-list__badge--amber">{{ item.count }} طلب</span>
            </li>
          </ul>
        </div>

        <!-- High amount requests -->
        <div class="alert-group">
          <h3 class="alert-group__title">طلبات بمبالغ مرتفعة (أكثر من مليون دولار)</h3>
          <div v-if="stats.compliance_alerts.high_amount_requests.length === 0" class="alert-empty">
            لا توجد تنبيهات
          </div>
          <ul v-else class="alert-list">
            <li
              v-for="req in stats.compliance_alerts.high_amount_requests"
              :key="req.id"
              class="alert-list__item alert-list__item--link"
              @click="router.push(`/requests/${req.id}`)"
            >
              <span class="alert-list__ref">{{ req.reference_number }}</span>
              <span class="alert-list__name">{{ req.bank_name }}</span>
              <span class="alert-list__badge alert-list__badge--red">
                {{ new Intl.NumberFormat('en-US', { style: 'currency', currency: req.currency, maximumFractionDigits: 0 }).format(req.amount) }}
              </span>
            </li>
          </ul>
        </div>

        <!-- Stale pending requests -->
        <div class="alert-group">
          <h3 class="alert-group__title">طلبات راكدة (أكثر من 14 يوماً دون تحديث)</h3>
          <div v-if="stats.compliance_alerts.stale_pending_requests.length === 0" class="alert-empty">
            لا توجد تنبيهات
          </div>
          <ul v-else class="alert-list">
            <li
              v-for="req in stats.compliance_alerts.stale_pending_requests"
              :key="req.id"
              class="alert-list__item alert-list__item--link"
              @click="router.push(`/requests/${req.id}`)"
            >
              <span class="alert-list__ref">{{ req.reference_number }}</span>
              <span class="alert-list__name">{{ req.bank_name }}</span>

            </li>
          </ul>
        </div>
      </section>

      <!-- Most active banks -->
      <section class="section-card" aria-labelledby="banks-heading">
        <h2 id="banks-heading" class="section-title">أكثر البنوك نشاطاً</h2>
        <div v-if="stats.most_active_banks.length === 0" class="alert-empty">
          لا توجد بيانات
        </div>
        <ul v-else class="banks-list">
          <li
            v-for="(bank, index) in stats.most_active_banks"
            :key="bank.bank_id"
            class="banks-list__item"
          >
            <span class="banks-list__rank">{{ index + 1 }}</span>
            <span class="banks-list__name">{{ bank.bank_name }}</span>
            <span class="banks-list__count">{{ bank.request_count }} طلب</span>
          </li>
        </ul>
      </section>

      <!-- Quick links -->
      <section aria-labelledby="quicklinks-heading">
        <h2 id="quicklinks-heading" class="section-title">روابط سريعة</h2>
        <div class="quick-links">
          <button class="quick-link-card" @click="navigateTo('/audit')">
            <span class="quick-link-card__icon" aria-hidden="true">📋</span>
            <span class="quick-link-card__label">سجل المراجعة</span>
          </button>
          <button class="quick-link-card" @click="navigateTo('/users')">
            <span class="quick-link-card__icon" aria-hidden="true">👥</span>
            <span class="quick-link-card__label">إدارة المستخدمين</span>
          </button>
          <button class="quick-link-card" @click="navigateTo('/banks')">
            <span class="quick-link-card__icon" aria-hidden="true">🏦</span>
            <span class="quick-link-card__label">إدارة البنوك</span>
          </button>
        </div>
      </section>
    </template>

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
.kpi-card--indigo .kpi-value { color: #5856d6; }
.kpi-card--red .kpi-value   { color: #ff3b30; }

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

/* Section card */
.section-card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0;
}

.section-icon { display: flex; align-items: center; }

/* Alert groups */
.alert-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid #f5f5f7;
  padding-top: 16px;
}

.alert-group:first-of-type {
  border-top: none;
  padding-top: 0;
}

.alert-group__title {
  font-size: 13px;
  font-weight: 500;
  color: #6e6e73;
  margin: 0;
}

.alert-empty {
  font-size: 13px;
  color: #34c759;
  padding: 8px 0;
}

.alert-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.alert-list__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: #f5f5f7;
  border-radius: 8px;
}

.alert-list__item--link {
  cursor: pointer;
}

.alert-list__item--link:hover { background: #ececf0; }

.alert-list__ref {
  font-family: monospace;
  font-size: 13px;
  color: #0071e3;
}

.alert-list__name {
  font-size: 13px;
  color: #1d1d1f;
  flex: 1;
}

.alert-list__badge {
  font-size: 12px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
}

.alert-list__badge--amber { background: #fff3cd; color: #b76e00; }
.alert-list__badge--red   { background: #fde8e8; color: #c0392b; }
.alert-list__badge--gray  { background: #f0f0f5; color: #6e6e73; }

/* Most active banks */
.banks-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.banks-list__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #f5f5f7;
  border-radius: 8px;
}

.banks-list__rank {
  width: 24px;
  height: 24px;
  background: #0071e3;
  color: #ffffff;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.banks-list__name {
  font-size: 14px;
  color: #1d1d1f;
  flex: 1;
}

.banks-list__count {
  font-size: 13px;
  color: #6e6e73;
}

/* Quick links */
.quick-links {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.quick-link-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 20px 24px;
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  cursor: pointer;
  min-width: 120px;
  transition: border-color 0.15s;
}

.quick-link-card:hover {
  border-color: #0071e3;
}

.quick-link-card__icon { font-size: 24px; }

.quick-link-card__label {
  font-size: 13px;
  color: #1d1d1f;
  font-weight: 500;
}
</style>
