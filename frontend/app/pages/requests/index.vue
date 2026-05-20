<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useRequestsStore } from '../../stores/requests.store'
import { UserRole, RequestStatus } from '../../types/enums'
import {
  ROLE_BUCKETS,
  CBY_BANK_FILTER_ROLES,
  CURRENCY_OPTIONS,
} from '../../constants/workflow'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import RequestProgress from '../../components/requests/RequestProgress.vue'

const auth = useAuthStore()
const requestsStore = useRequestsStore()

// ── Filter state ──────────────────────────────────────────────────────────────

const search = ref('')
const selectedBankId = ref<number | ''>('')
const selectedCurrency = ref<string | ''>('')
const selectedBucket = ref<string>('all')

// ── Role-derived flags ────────────────────────────────────────────────────────

const role = computed(() => auth.user?.role)

/** Bank-scoped roles (DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN, SWIFT_OFFICER) never see bank filter */
const isBankScoped = computed(() =>
  !!role.value && !CBY_BANK_FILTER_ROLES.includes(role.value),
)

const showBankFilter = computed(() =>
  !!role.value && CBY_BANK_FILTER_ROLES.includes(role.value),
)

const canCreateRequest = computed(() => role.value === UserRole.DATA_ENTRY)

// ── Stage tabs ────────────────────────────────────────────────────────────────

const roleBuckets = computed(() => {
  if (!role.value) return []
  return ROLE_BUCKETS[role.value] ?? []
})

/** Buckets that actually have matching requests in the current loaded page */
const visibleBuckets = computed(() => {
  const requests = requestsStore.requests ?? []
  return roleBuckets.value.filter(b =>
    requests.some(r => b.statuses.includes(r.status)),
  )
})

function countForBucket(key: string): number {
  const requests = requestsStore.requests ?? []
  if (key === 'all') return requests.length
  const bucket = roleBuckets.value.find(b => b.key === key)
  if (!bucket) return 0
  return requests.filter(r => bucket.statuses.includes(r.status)).length
}

/** Requests filtered by the active bucket tab (client-side of loaded page) */
const displayedRequests = computed(() => {
  const requests = requestsStore.requests ?? []
  if (selectedBucket.value === 'all') return requests
  const bucket = roleBuckets.value.find(b => b.key === selectedBucket.value)
  if (!bucket) return requests
  return requests.filter(r => bucket.statuses.includes(r.status))
})

// ── Special badge helpers ─────────────────────────────────────────────────────

function isVotingOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
}

function canSeeVotingBadge(): boolean {
  return role.value === UserRole.EXECUTIVE_MEMBER || role.value === UserRole.COMMITTEE_DIRECTOR
}

function claimBadgeLabel(request: { is_claimed_by_me: boolean; claimed_by: { name: string } | null }): string {
  if (request.is_claimed_by_me) return 'محجوز لك'
  if (request.claimed_by?.name) return `محجوز: ${request.claimed_by.name}`
  return 'محجوز'
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadPage(page = 1) {
  await requestsStore.loadRequests({
    search: search.value || undefined,
    bank_id: selectedBankId.value || undefined,
    currency: selectedCurrency.value || undefined,
    page,
  })
  // Reset to 'all' tab when data reloads so counts stay consistent
  if (selectedBucket.value !== 'all') {
    const stillHasMatch = countForBucket(selectedBucket.value) > 0
    if (!stillHasMatch) selectedBucket.value = 'all'
  }
}

onMounted(() => loadPage())

const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

watch(search, () => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => loadPage(), 350)
})

watch([selectedBankId, selectedCurrency], () => loadPage())

onUnmounted(() => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
})

// ── Formatting ────────────────────────────────────────────────────────────────

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar-YE')} ${currency}`
}
</script>

<template>
  <div class="requests-page" dir="rtl">
    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-text">
        <nav class="breadcrumbs" aria-label="مسار التنقل">
          <NuxtLink to="/dashboard" class="breadcrumb-link">الرئيسية</NuxtLink>
          <span class="breadcrumb-sep">›</span>
          <span class="breadcrumb-current">الطلبات</span>
        </nav>
        <h1 class="page-title">طلبات تمويل الواردات</h1>
        <p class="page-subtitle">
          {{ isBankScoped ? 'طلبات جهتك فقط' : 'جميع الطلبات المقدمة عبر المنصة مع حالاتها ومراحل المعالجة' }}
        </p>
      </div>
      <div class="page-header-actions">
        <NuxtLink
          v-if="canCreateRequest"
          to="/requests/new"
          class="btn-primary"
          aria-label="تقديم طلب جديد"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          طلب جديد
        </NuxtLink>
      </div>
    </div>

    <!-- Stage tabs -->
    <div v-if="roleBuckets.length > 0" class="stage-tabs-card">
      <div class="stage-tabs">
        <button
          :class="['stage-tab', selectedBucket === 'all' && 'stage-tab--active']"
          @click="selectedBucket = 'all'"
        >
          الكل
          <span class="tab-count">{{ countForBucket('all') }}</span>
        </button>
        <button
          v-for="bucket in visibleBuckets"
          :key="bucket.key"
          :class="['stage-tab', selectedBucket === bucket.key && 'stage-tab--active']"
          @click="selectedBucket = bucket.key"
        >
          {{ bucket.label }}
          <span class="tab-count">{{ countForBucket(bucket.key) }}</span>
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-card">
      <div class="filter-row">
        <!-- Search -->
        <div class="filter-search">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="search"
            type="text"
            class="filter-input filter-input--search"
            placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
            dir="rtl"
          />
        </div>

        <!-- Bank filter (CBY/global roles only) -->
        <select
          v-if="showBankFilter"
          v-model="selectedBankId"
          class="filter-select filter-select--bank"
          dir="rtl"
        >
          <option value="">جميع البنوك</option>
        </select>

        <!-- Currency filter -->
        <select
          v-model="selectedCurrency"
          class="filter-select filter-select--currency"
          dir="rtl"
        >
          <option value="">جميع العملات</option>
          <option v-for="cur in CURRENCY_OPTIONS" :key="cur" :value="cur">{{ cur }}</option>
        </select>
      </div>
    </div>

    <!-- Loading: 5 skeleton rows -->
    <div v-if="requestsStore.loadingList" class="table-card">
      <div class="table-wrapper">
        <table class="requests-table">
          <colgroup>
            <col class="col-reference" />
            <col class="col-importer" />
            <col class="col-type" />
            <col class="col-amount" />
            <col class="col-status" />
            <col class="col-progress" />
            <col class="col-action" />
          </colgroup>
          <thead>
            <tr class="table-head-row">
              <th class="th">المرجع</th>
              <th class="th">المستورد / البنك</th>
              <th class="th">النوع</th>
              <th class="th">المبلغ</th>
              <th class="th">الحالة</th>
              <th class="th">التقدم</th>
              <th class="th th--sticky-action">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in 5" :key="i" class="skeleton-row">
              <td class="td"><div class="skel skel--ref" /></td>
              <td class="td"><div class="skel skel--importer" /><div class="skel skel--bank" /></td>
              <td class="td"><div class="skel skel--type" /></td>
              <td class="td"><div class="skel skel--amount" /></td>
              <td class="td"><div class="skel skel--badge" /></td>
              <td class="td"><div class="skel skel--progress" /></td>
              <td class="td td--sticky-action"><div class="skel skel--action" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="requestsStore.error" class="state-card state-card--error">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#c62828" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p class="state-text">{{ requestsStore.error }}</p>
      <button class="btn-retry" @click="loadPage()">إعادة المحاولة</button>
    </div>

    <!-- Empty state -->
    <div v-else-if="displayedRequests.length === 0" class="state-card">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="1.5" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <p class="state-text">لا توجد طلبات في هذا القسم.</p>
    </div>

    <!-- Table -->
    <div v-else class="table-card">
      <div class="table-wrapper">
        <table class="requests-table">
          <colgroup>
            <col class="col-reference" />
            <col class="col-importer" />
            <col class="col-type" />
            <col class="col-amount" />
            <col class="col-status" />
            <col class="col-progress" />
            <col class="col-action" />
          </colgroup>
          <thead>
            <tr class="table-head-row">
              <th class="th">المرجع</th>
              <th class="th">المستورد / البنك</th>
              <th class="th">النوع</th>
              <th class="th">المبلغ</th>
              <th class="th">الحالة</th>
              <th class="th">التقدم</th>
              <th class="th th--sticky-action">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="request in displayedRequests"
              :key="request.id"
              class="table-row"
            >
              <!-- Reference + invoice + special badges -->
              <td class="td">
                <div class="ref-cell">
                  <NuxtLink :to="`/requests/${request.id}`" class="ref-link">
                    {{ request.reference_number }}
                  </NuxtLink>
                  <!-- Voting open badge (executive roles only) -->
                  <span
                    v-if="isVotingOpen(request.status) && canSeeVotingBadge()"
                    class="badge badge--voting"
                    role="status"
                  >
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    باب التصويت مفتوح
                  </span>
                  <!-- Support claim badge (support committee only) -->
                  <span
                    v-if="role === UserRole.SUPPORT_COMMITTEE && request.is_claimed"
                    :class="['badge', request.is_claimed_by_me ? 'badge--claim-mine' : 'badge--claim-other']"
                    role="status"
                  >
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    {{ claimBadgeLabel(request) }}
                  </span>
                </div>
                <div v-if="request.invoice_number" class="invoice-number">
                  {{ request.invoice_number }}
                </div>
              </td>

              <!-- Importer name + bank -->
              <td class="td">
                <div class="importer-name">{{ request.merchant?.name ?? request.supplier_name }}</div>
                <div class="bank-name">{{ request.bank_name ?? '—' }}</div>
              </td>

              <!-- Goods type -->
              <td class="td td--type">{{ request.goods_type ?? '—' }}</td>

              <!-- Amount + currency -->
              <td class="td td--amount">
                {{ request.amount.toLocaleString('ar-YE') }}
                <span class="currency-label">{{ request.currency }}</span>
              </td>

              <!-- Status badge -->
              <td class="td">
                <StatusBadge
                  v-if="auth.user"
                  :status="request.status"
                  :role="auth.user.role"
                />
              </td>

              <!-- Progress -->
              <td class="td">
                <RequestProgress :status="request.status" />
              </td>

              <!-- Sticky action -->
              <td class="td td--sticky-action">
                <NuxtLink :to="`/requests/${request.id}`" class="btn-view">
                  عرض
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination footer -->
      <div v-if="requestsStore.meta" class="pagination-footer">
        <span class="pagination-info">
          عرض {{ displayedRequests.length }} من {{ requestsStore.meta.total }} طلب
        </span>
        <div class="pagination-controls">
          <button
            class="pagination-btn"
            :disabled="!requestsStore.hasPrevPage"
            @click="requestsStore.prevPage()"
          >
            السابق
          </button>

          <!-- Numbered pages — show up to 5 around current -->
          <template v-for="p in requestsStore.meta.last_page" :key="p">
            <button
              v-if="Math.abs(p - requestsStore.currentPage) <= 2"
              :class="['pagination-page', p === requestsStore.currentPage && 'pagination-page--active']"
              @click="loadPage(p)"
            >
              {{ p }}
            </button>
          </template>

          <button
            class="pagination-btn"
            :disabled="!requestsStore.hasNextPage"
            @click="requestsStore.nextPage()"
          >
            التالي
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────────── */

.requests-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1600px;
}

/* ── Page header ─────────────────────────────────────────────────────────────── */

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-header-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--color-text-secondary, #6c757d);
}

.breadcrumb-link {
  color: #0066cc;
  text-decoration: none;
}

.breadcrumb-link:hover { text-decoration: underline; }

.breadcrumb-sep { opacity: 0.5; }

.breadcrumb-current { color: var(--color-text-secondary, #6c757d); }

.page-title {
  font-size: 26px;
  font-weight: 600;
  color: var(--color-text-primary, #1c222b);
  margin: 0;
  line-height: 1.2;
  font-family: 'Cairo', sans-serif;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary, #6c757d);
  margin: 0;
}

.page-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  padding: 0 18px;
  background: #0066cc;
  color: #fff;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity 100ms;
}

.btn-primary:hover { opacity: 0.88; }

/* ── Stage tabs ──────────────────────────────────────────────────────────────── */

.stage-tabs-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 16px;
  padding: 8px;
  overflow-x: auto;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.stage-tabs {
  display: flex;
  gap: 4px;
  min-width: max-content;
}

.stage-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 10px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-secondary, #6c757d);
  cursor: pointer;
  white-space: nowrap;
  transition: background 100ms, color 100ms;
  font-family: inherit;
}

.stage-tab:hover { background: #f5f5f7; }

.stage-tab--active {
  background: #0066cc;
  color: #fff;
}

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 9px;
  font-size: 10px;
  font-variant-numeric: tabular-nums;
  background: rgba(255,255,255,.22);
}

.stage-tab:not(.stage-tab--active) .tab-count {
  background: #f0f0f0;
  color: var(--color-text-secondary, #6c757d);
}

/* ── Filter card ─────────────────────────────────────────────────────────────── */

.filter-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.filter-search {
  position: relative;
  flex: 1;
  min-width: 200px;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-secondary, #6c757d);
  pointer-events: none;
}

.filter-input {
  width: 100%;
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1c222b);
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 100ms;
  box-sizing: border-box;
}

.filter-input--search {
  padding-right: 38px;
}

.filter-input:focus { border-color: #0066cc; }

.filter-select {
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1c222b);
  font-size: 14px;
  font-family: inherit;
  outline: none;
  cursor: pointer;
  transition: border-color 100ms;
}

.filter-select--bank { width: 200px; }
.filter-select--currency { width: 140px; }

.filter-select:focus { border-color: #0066cc; }

/* ── Table card ──────────────────────────────────────────────────────────────── */

.table-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.table-wrapper {
  overflow-x: auto;
}

.requests-table {
  width: 100%;
  min-width: 850px;
  border-collapse: collapse;
  table-layout: fixed;
}

/* Column widths matching Lovable colgroup */
.col-reference { width: 160px; }
.col-importer  { width: 200px; }
.col-type      { width: 110px; }
.col-amount    { width: 130px; }
.col-status    { width: 180px; }
.col-progress  { width: 120px; }
.col-action    { width: 100px; }

.table-head-row { background: #f8f9fa; }

.th {
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary, #6c757d);
  text-align: right;
  white-space: nowrap;
  border-bottom: 1px solid var(--color-border, #cccccc);
}

.th--sticky-action,
.td--sticky-action {
  position: sticky;
  left: 0;
  background: inherit;
  z-index: 1;
  box-shadow: 6px 0 8px -6px rgba(0,0,0,.10);
}

.table-row {
  border-bottom: 1px solid var(--color-border, #cccccc);
  transition: background 80ms;
}

.table-row:last-child { border-bottom: none; }

.table-row:hover { background: #f8f9fa; }

.td {
  padding: 10px 16px;
  font-size: 14px;
  color: var(--color-text-primary, #1c222b);
  vertical-align: top;
  text-align: right;
}

/* ── Ref cell ────────────────────────────────────────────────────────────────── */

.ref-cell {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.ref-link {
  font-family: 'Inter', monospace;
  font-size: 12px;
  font-weight: 600;
  color: #0066cc;
  text-decoration: none;
  letter-spacing: 0.02em;
}

.ref-link:hover { text-decoration: underline; }

.invoice-number {
  font-size: 11px;
  color: var(--color-text-secondary, #6c757d);
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}

/* ── Badges ──────────────────────────────────────────────────────────────────── */

.badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 1px 6px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 500;
  white-space: nowrap;
}

.badge--voting {
  background: rgba(88,86,214,.12);
  color: #5856d6;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .65; }
}

.badge--claim-mine {
  background: rgba(255,159,10,.12);
  color: #f57f17;
}

.badge--claim-other {
  background: rgba(142,142,147,.12);
  color: #8e8e93;
}

/* ── Importer / bank ─────────────────────────────────────────────────────────── */

.importer-name {
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bank-name {
  font-size: 12px;
  color: var(--color-text-secondary, #6c757d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Type + amount ───────────────────────────────────────────────────────────── */

.td--type {
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.td--amount {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.currency-label {
  font-size: 11px;
  font-weight: 400;
  color: var(--color-text-secondary, #6c757d);
  margin-right: 4px;
}

/* ── Action button ───────────────────────────────────────────────────────────── */

.btn-view {
  display: inline-flex;
  align-items: center;
  height: 30px;
  padding: 0 12px;
  border: 1px solid #0066cc;
  border-radius: 8px;
  color: #0066cc;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: background 100ms;
}

.btn-view:hover { background: rgba(0,102,204,.08); }

/* ── Skeleton rows ───────────────────────────────────────────────────────────── */

.skeleton-row { border-bottom: 1px solid var(--color-border, #cccccc); }

.skel {
  background: linear-gradient(90deg, #f0f0f0 25%, #e4e4e4 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 4px;
  height: 14px;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skel--ref    { width: 100px; }
.skel--importer { width: 140px; margin-bottom: 4px; }
.skel--bank   { width: 80px; height: 10px; }
.skel--type   { width: 70px; }
.skel--amount { width: 90px; }
.skel--badge  { width: 110px; height: 22px; border-radius: 6px; }
.skel--progress { width: 80px; height: 10px; }
.skel--action { width: 48px; height: 28px; border-radius: 8px; }

/* ── State cards ─────────────────────────────────────────────────────────────── */

.state-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 16px;
  padding: 64px 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.state-card--error { border-color: #ffcdd2; }

.state-text {
  font-size: 15px;
  color: var(--color-text-secondary, #6c757d);
}

.btn-retry {
  height: 40px;
  padding: 0 20px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 100ms;
}

.btn-retry:hover { opacity: 0.88; }

/* ── Pagination footer ───────────────────────────────────────────────────────── */

.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border, #cccccc);
  font-size: 12px;
  color: var(--color-text-secondary, #6c757d);
}

.pagination-info {
  font-variant-numeric: tabular-nums;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-btn {
  height: 30px;
  padding: 0 12px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 8px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1c222b);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: border-color 100ms;
  white-space: nowrap;
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-btn:not(:disabled):hover { border-color: #0066cc; }

.pagination-page {
  width: 30px;
  height: 30px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 8px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1c222b);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: border-color 100ms, background 100ms;
  font-variant-numeric: tabular-nums;
}

.pagination-page:hover { border-color: #0066cc; }

.pagination-page--active {
  background: #0066cc;
  border-color: #0066cc;
  color: #fff;
}

/* ── Responsive ≤600px ───────────────────────────────────────────────────────── */

@media (max-width: 600px) {
  .page-header { flex-direction: column; }
  .page-title { font-size: 20px; }
  .filter-row { flex-direction: column; }
  .filter-select--bank,
  .filter-select--currency { width: 100%; }
  .pagination-footer { flex-direction: column; gap: 8px; align-items: flex-start; }
  .pagination-controls { flex-wrap: wrap; }
  /* Hide numbered pages on mobile to avoid overflow */
  .pagination-page { display: none; }
}
</style>
