<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { BankAdminDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../ui/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()
const stats = computed(() => store.stats as BankAdminDashboardStats | null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="dashboard" dir="rtl">
    <div v-if="store.loading" class="kpi-grid" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 5" :key="n" class="kpi-card kpi-card--skeleton" aria-hidden="true">
        <div class="skeleton skeleton--label" />
        <div class="skeleton skeleton--value" />
      </div>
    </div>

    <div v-else-if="store.error" class="error-card" role="alert">
      <span>{{ store.error }}</span>
      <button class="btn-retry" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <div v-else-if="stats" class="kpi-grid">
      <div class="kpi-card kpi-card--amber">
        <span class="kpi-label">بانتظار مراجعة البنك</span>
        <span class="kpi-value">{{ stats.pending_bank_review }}</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">لدى CBY</span>
        <span class="kpi-value">{{ stats.at_cby }}</span>
      </div>
      <div class="kpi-card kpi-card--green">
        <span class="kpi-label">معتمد / مكتمل</span>
        <span class="kpi-value">{{ stats.completed }}</span>
      </div>
      <div class="kpi-card kpi-card--red">
        <span class="kpi-label">مرفوض</span>
        <span class="kpi-value">{{ stats.rejected }}</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">مستخدمون نشطون</span>
        <span class="kpi-value">{{ stats.active_users }}</span>
      </div>
    </div>

    <div v-if="stats" class="recent">
      <h2 class="section-title">أحدث طلبات البنك</h2>
      <div v-if="stats.recent_requests.length === 0" class="empty-queue" role="status">
        لا توجد طلبات حديثة.
      </div>
      <table v-else class="req-table" aria-label="أحدث طلبات البنك">
        <thead>
          <tr>
            <th>المرجع</th>
            <th>المورد</th>
            <th>المبلغ</th>
            <th>الحالة</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in stats.recent_requests" :key="req.id">
            <td>
              <a href="#" class="req-ref" @click.prevent="router.push(`/requests/${req.id}`)">
                {{ req.reference_number }}
              </a>
            </td>
            <td>{{ req.supplier_name }}</td>
            <td class="mono">{{ formatAmount(req.amount, req.currency) }}</td>
            <td><StatusBadge :status="req.status" :role="UserRole.BANK_ADMIN" /></td>
            <td><button class="btn-view" @click="router.push(`/requests/${req.id}`)">عرض</button></td>
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

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
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
.kpi-card--amber .kpi-value { color: #ff9f0a; }
.kpi-card--red .kpi-value { color: #ff3b30; }

.recent {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px;
}

.section-title {
  font-size: 16px;
  font-weight: 500;
  margin: 0 0 12px;
  color: #1d1d1f;
}

.req-table {
  width: 100%;
  border-collapse: collapse;
}

.req-table th,
.req-table td {
  padding: 12px;
  border-bottom: 1px solid #f5f5f7;
  text-align: right;
  font-size: 14px;
}

.req-ref {
  color: #0071e3;
  text-decoration: none;
}

.mono {
  direction: ltr;
  font-variant-numeric: tabular-nums;
}

.btn-view,
.btn-retry {
  border: 1px solid #d2d2d7;
  background: #ffffff;
  border-radius: 8px;
  padding: 6px 14px;
  cursor: pointer;
}

.error-card,
.empty-queue {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px;
  color: #6e6e73;
}

.skeleton {
  background: #f5f5f7;
  border-radius: 6px;
}

.skeleton--label { height: 14px; width: 60%; }
.skeleton--value { height: 32px; width: 40%; }

@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
