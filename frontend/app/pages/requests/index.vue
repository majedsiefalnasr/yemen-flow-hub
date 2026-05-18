<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useRequestsStore } from '../../stores/requests.store'
import { UserRole, RequestStatus } from '../../types/enums'
import { STATUS_LABELS, OPERATIONAL_FILTER_ROLES, ROLE_FILTER_STATUSES } from '../../constants/workflow'
import StatusBadge from '../../components/ui/StatusBadge.vue'

const auth = useAuthStore()
const requestsStore = useRequestsStore()

const search = ref('')
const selectedStatus = ref<RequestStatus | ''>('')

const showsOperationalFilters = computed(() =>
  !!auth.user && OPERATIONAL_FILTER_ROLES.includes(auth.user.role),
)

const canCreateRequest = computed(() => auth.user?.role === UserRole.DATA_ENTRY)

const statusOptions = computed<Array<{ value: RequestStatus | '', label: string }>>(() => {
  const role = auth.user?.role
  const filterStatuses = role ? ROLE_FILTER_STATUSES[role] : undefined

  const statuses = filterStatuses
    ? filterStatuses.map(s => ({ value: s, label: STATUS_LABELS[s] }))
    : Object.entries(STATUS_LABELS).map(([value, label]) => ({
        value: value as RequestStatus,
        label,
      }))

  return [{ value: '' as RequestStatus | '', label: 'جميع الحالات' }, ...statuses]
})

async function loadPage(page = 1) {
  await requestsStore.loadRequests({
    search: search.value || undefined,
    status: selectedStatus.value || undefined,
    page,
  })
}

onMounted(() => loadPage())

const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

watch(search, () => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => loadPage(), 350)
})

watch(selectedStatus, () => loadPage())

onUnmounted(() => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
})

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar-YE')} ${currency}`
}
</script>

<template>
  <div class="requests-page">
    <div class="requests-header">
      <h1 class="requests-title">طلبات التمويل</h1>
      <NuxtLink
        v-if="canCreateRequest"
        to="/requests/new"
        class="new-request-btn"
        aria-label="تقديم طلب جديد"
      >
        + طلب جديد
      </NuxtLink>
    </div>

    <!-- Filters (operational roles only) -->
    <div v-if="showsOperationalFilters" class="requests-filters">
      <div class="filter-search">
        <label class="filter-label" for="search-input">بحث</label>
        <input
          id="search-input"
          v-model="search"
          type="text"
          class="filter-input"
          placeholder="رقم المرجع أو اسم المورد..."
          dir="rtl"
        />
      </div>

      <div class="filter-status">
        <label class="filter-label" for="status-select">الحالة</label>
        <select
          id="status-select"
          v-model="selectedStatus"
          class="filter-select"
          dir="rtl"
        >
          <option
            v-for="option in statusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="requestsStore.loadingList" class="state-card">
      <span class="state-text">جاري التحميل...</span>
    </div>

    <!-- Error state -->
    <div v-else-if="requestsStore.error" class="state-card state-card--error">
      <span class="state-text">{{ requestsStore.error }}</span>
      <button class="retry-btn" @click="loadPage()">إعادة المحاولة</button>
    </div>

    <!-- Empty state -->
    <div v-else-if="(requestsStore.requests?.length ?? 0) === 0" class="state-card">
      <span class="state-text">لا توجد طلبات.</span>
    </div>

    <!-- Table -->
    <div v-else class="requests-table-wrapper">
      <table class="requests-table" dir="rtl">
        <thead>
          <tr>
            <th scope="col" class="col-reference">رقم المرجع</th>
            <th scope="col" class="col-supplier">المورد</th>
            <th scope="col" class="col-amount">المبلغ</th>
            <th scope="col" class="col-status">الحالة</th>
            <th scope="col" class="col-actions" aria-label="الإجراءات" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="request in (requestsStore.requests ?? [])"
            :key="request.id"
            class="table-row"
          >
            <td class="col-reference">
              <NuxtLink
                :to="`/requests/${request.id}`"
                class="reference-link"
              >
                {{ request.reference_number }}
              </NuxtLink>
            </td>
            <td class="col-supplier">{{ request.supplier_name }}</td>
            <td class="col-amount">{{ formatAmount(request.amount, request.currency) }}</td>
            <td class="col-status">
              <StatusBadge
                v-if="auth.user"
                :status="request.status"
                :role="auth.user.role"
              />
            </td>
            <td class="col-actions">
              <NuxtLink
                :to="`/requests/${request.id}`"
                class="view-btn"
              >
                عرض
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="requestsStore.meta && requestsStore.meta.last_page > 1"
      class="pagination"
    >
      <button
        class="pagination-btn"
        :disabled="!requestsStore.hasPrevPage"
        @click="requestsStore.prevPage()"
      >
        السابق
      </button>

      <span class="pagination-info">
        {{ requestsStore.currentPage }} / {{ requestsStore.meta.last_page }}
      </span>

      <button
        class="pagination-btn"
        :disabled="!requestsStore.hasNextPage"
        @click="requestsStore.nextPage()"
      >
        التالي
      </button>
    </div>
  </div>
</template>

<style scoped>
.requests-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.requests-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.requests-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.new-request-btn {
  height: 44px;
  padding: 0 20px;
  background: #0071e3;
  color: #fff;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: opacity 100ms;
}

.new-request-btn:hover {
  opacity: 0.9;
}

/* Filters */
.requests-filters {
  display: flex;
  gap: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 16px 20px;
}

.filter-search {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-status {
  width: 220px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 13px;
  color: var(--color-text-secondary);
}

.filter-input,
.filter-select {
  height: 44px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-surface);
  color: var(--color-text-primary);
  font-size: 15px;
  font-family: inherit;
  outline: none;
  transition: border-color 100ms;
}

.filter-input:focus,
.filter-select:focus {
  border-color: #0071e3;
  border-width: 1.5px;
}

/* State cards */
.state-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 48px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.state-card--error {
  border-color: #ff3b30;
}

.state-text {
  font-size: 15px;
  color: var(--color-text-secondary);
}

.retry-btn {
  height: 44px;
  padding: 0 20px;
  background: #0071e3;
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
}

/* Table */
.requests-table-wrapper {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  overflow: hidden;
}

.requests-table {
  width: 100%;
  border-collapse: collapse;
}

.requests-table thead tr {
  border-bottom: 1px solid var(--color-border);
}

.requests-table th {
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-secondary);
  text-align: right;
}

.table-row {
  height: 44px;
  border-bottom: 1px solid var(--color-border);
}

.table-row:last-child {
  border-bottom: none;
}

.requests-table td {
  padding: 8px 16px;
  font-size: 15px;
  color: var(--color-text-primary);
  vertical-align: middle;
  text-align: right;
}

.reference-link {
  font-family: 'Inter', monospace;
  color: #0071e3;
  text-decoration: none;
  font-size: 13px;
  letter-spacing: 0.02em;
}

.reference-link:hover {
  text-decoration: underline;
}

.col-reference { width: 160px; }
.col-amount { width: 140px; white-space: nowrap; }
.col-status { width: 200px; }
.col-actions { width: 80px; text-align: end; }

.view-btn {
  height: 32px;
  padding: 0 14px;
  background: transparent;
  color: #0071e3;
  border: 1px solid #0071e3;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: background 100ms;
}

.view-btn:hover {
  background: #0071e31a;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
}

.pagination-btn {
  height: 36px;
  padding: 0 16px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: var(--color-surface);
  color: var(--color-text-primary);
  font-size: 14px;
  cursor: pointer;
  transition: border-color 100ms;
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-btn:not(:disabled):hover {
  border-color: #0071e3;
}

.pagination-info {
  font-size: 14px;
  color: var(--color-text-secondary);
}

/* Responsive ≤600px */
@media (max-width: 600px) {
  .requests-filters {
    flex-direction: column;
  }

  .filter-status {
    width: 100%;
  }

  .col-supplier,
  .col-amount {
    display: none;
  }
}
</style>
