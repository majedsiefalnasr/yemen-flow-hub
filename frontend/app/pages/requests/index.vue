<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useRequestsStore } from '../../stores/requests.store'
import { useBanks } from '../../composables/useBanks'
import { useUsers } from '../../composables/useUsers'
import { UserRole, RequestStatus } from '../../types/enums'
import type { Bank, ImportRequest, User } from '../../types/models'
import {
  ROLE_BUCKETS,
  CBY_BANK_FILTER_ROLES,
  CURRENCY_OPTIONS,
} from '../../constants/workflow'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import RequestProgress from '../../components/requests/RequestProgress.vue'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const requestsStore = useRequestsStore()
const { fetchBanks } = useBanks()
const { fetchUsers } = useUsers()

// ── Filter state ──────────────────────────────────────────────────────────────

const search = ref('')
const selectedBankId = ref<number | ''>('')
const selectedCurrency = ref<string | ''>('')
const selectedFromDate = ref('')
const selectedToDate = ref('')
const selectedAmountMin = ref<number | ''>('')
const selectedAmountMax = ref<number | ''>('')
const selectedReviewerId = ref<number | ''>('')
const selectedBucket = ref<string>('all')
const showAdvancedFilters = ref(false)
const banks = ref<Bank[]>([])
const loadingBanks = ref(false)
const reviewers = ref<User[]>([])
const loadingReviewers = ref(false)

// ── Role-derived flags ────────────────────────────────────────────────────────

const role = computed(() => auth.user?.role)

/** Bank-scoped roles (DATA_ENTRY, BANK_REVIEWER, BANK_ADMIN, SWIFT_OFFICER) never see bank filter */
const isBankScoped = computed(() =>
  !!role.value && !CBY_BANK_FILTER_ROLES.includes(role.value),
)

const showBankFilter = computed(() =>
  !!role.value && CBY_BANK_FILTER_ROLES.includes(role.value),
)

/** Reviewer filter is active only for CBY/global roles (bank actors see it disabled) */
const reviewerFilterEnabled = computed(() =>
  !!role.value && CBY_BANK_FILTER_ROLES.includes(role.value),
)

const canCreateRequest = computed(() => role.value === UserRole.DATA_ENTRY)
const hasAdvancedFilters = computed(() =>
  selectedFromDate.value !== ''
  || selectedToDate.value !== ''
  || selectedAmountMin.value !== ''
  || selectedAmountMax.value !== ''
  || selectedReviewerId.value !== '',
)

// ── Stage tabs ────────────────────────────────────────────────────────────────

const roleBuckets = computed(() => {
  if (!role.value) return []
  return ROLE_BUCKETS[role.value] ?? []
})

const activeBucket = computed(() =>
  roleBuckets.value.find(bucket => bucket.key === selectedBucket.value),
)

const statusTotals = computed(() => requestsStore.meta?.status_totals ?? {})

function countStatuses(statuses: RequestStatus[]): number {
  return statuses.reduce(
    (total, status) => total + (statusTotals.value[status] ?? 0),
    0,
  )
}

/** Buckets that actually have matching requests in the active filtered dataset */
const visibleBuckets = computed(() => {
  return roleBuckets.value.filter(bucket =>
    countForBucket(bucket.key) > 0 || bucket.key === selectedBucket.value,
  )
})

function countForBucket(key: string): number {
  if (key === 'all') {
    const totalFromBuckets = Object.values(statusTotals.value).reduce<number>(
      (total, count) => total + (count ?? 0),
      0,
    )

    return totalFromBuckets > 0
      ? totalFromBuckets
      : (requestsStore.meta?.total ?? requestsStore.requests.length)
  }
  const bucket = roleBuckets.value.find(b => b.key === key)
  if (!bucket) return 0
  return countStatuses(bucket.statuses)
}

const displayedRequests = computed(() => {
  return requestsStore.requests ?? []
})

// ── Special badge helpers ─────────────────────────────────────────────────────

function isVotingOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
}

function canSeeVotingBadge(): boolean {
  return role.value === UserRole.EXECUTIVE_MEMBER || role.value === UserRole.COMMITTEE_DIRECTOR
}

function claimBadgeLabel(request: Pick<ImportRequest, 'is_claimed' | 'is_claimed_by_me' | 'claimed_by' | 'can_be_claimed'>): string {
  if (request.is_claimed_by_me) return 'محجوز لك'
  if (request.is_claimed && request.claimed_by?.name) return `محجوز: ${request.claimed_by.name}`
  if (request.can_be_claimed) return 'متاح للمراجعة'
  return 'محجوز'
}

function claimBadgeClass(request: Pick<ImportRequest, 'is_claimed' | 'is_claimed_by_me' | 'can_be_claimed'>): string {
  if (request.is_claimed_by_me) return 'badge--claim-mine'
  if (request.is_claimed) return 'badge--claim-other'
  return 'badge--claim-available'
}

function showClaimBadge(request: Pick<ImportRequest, 'is_claimed' | 'can_be_claimed'>): boolean {
  return role.value === UserRole.SUPPORT_COMMITTEE
    && (request.is_claimed || request.can_be_claimed)
}

// ── URL persistence ───────────────────────────────────────────────────────────

function hydrateFromUrl() {
  const q = route.query
  if (q.search) search.value = String(q.search)
  if (q.bank_id) selectedBankId.value = Number(q.bank_id)
  if (q.currency) selectedCurrency.value = String(q.currency)
  if (q.bucket) selectedBucket.value = String(q.bucket)
  if (q.created_from ?? q.from_date) selectedFromDate.value = String(q.created_from ?? q.from_date)
  if (q.created_to ?? q.to_date) selectedToDate.value = String(q.created_to ?? q.to_date)
  if (q.amount_min !== undefined && q.amount_min !== '') selectedAmountMin.value = Number(q.amount_min)
  if (q.amount_max !== undefined && q.amount_max !== '') selectedAmountMax.value = Number(q.amount_max)
  if (q.assigned_reviewer_id ?? q.reviewer_id) selectedReviewerId.value = Number(q.assigned_reviewer_id ?? q.reviewer_id)
  if (hasAdvancedFilters.value) showAdvancedFilters.value = true
}

function pushUrl() {
  const query: Record<string, string> = {}
  if (search.value) query.search = search.value
  if (selectedBankId.value !== '') query.bank_id = String(selectedBankId.value)
  if (selectedCurrency.value) query.currency = selectedCurrency.value
  if (selectedBucket.value && selectedBucket.value !== 'all') query.bucket = selectedBucket.value
  if (selectedFromDate.value) query.created_from = selectedFromDate.value
  if (selectedToDate.value) query.created_to = selectedToDate.value
  if (selectedAmountMin.value !== '') query.amount_min = String(selectedAmountMin.value)
  if (selectedAmountMax.value !== '') query.amount_max = String(selectedAmountMax.value)
  if (selectedReviewerId.value !== '') query.assigned_reviewer_id = String(selectedReviewerId.value)
  void router.replace({ query })
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadPage(page = 1) {
  pushUrl()
  await requestsStore.loadRequests({
    search: search.value || undefined,
    status: activeBucket.value?.statuses,
    bank_id: selectedBankId.value || undefined,
    currency: selectedCurrency.value || undefined,
    created_from: selectedFromDate.value || undefined,
    created_to: selectedToDate.value || undefined,
    amount_min: selectedAmountMin.value !== '' ? selectedAmountMin.value : undefined,
    amount_max: selectedAmountMax.value !== '' ? selectedAmountMax.value : undefined,
    assigned_reviewer_id: selectedReviewerId.value || undefined,
    page,
  })
}

async function loadBankOptions() {
  if (!showBankFilter.value || loadingBanks.value || banks.value.length > 0) return

  loadingBanks.value = true

  try {
    banks.value = await fetchBanks()
  }
  catch (err) {
    if (import.meta.dev) {
      console.error('[requests.page] loadBankOptions failed:', err)
    }
  }
  finally {
    loadingBanks.value = false
  }
}

async function loadReviewerOptions() {
  if (!reviewerFilterEnabled.value || loadingReviewers.value || reviewers.value.length > 0) return

  loadingReviewers.value = true

  try {
    reviewers.value = await fetchUsers({
      role: UserRole.BANK_REVIEWER,
      is_active: true,
      per_page: 100,
    })
  }
  catch (err) {
    if (import.meta.dev) {
      console.error('[requests.page] loadReviewerOptions failed:', err)
    }
  }
  finally {
    loadingReviewers.value = false
  }
}

function clearAdvancedFilters() {
  selectedFromDate.value = ''
  selectedToDate.value = ''
  selectedAmountMin.value = ''
  selectedAmountMax.value = ''
  selectedReviewerId.value = ''
}

onMounted(() => {
  hydrateFromUrl()
  void loadPage()
})

const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

watch(search, () => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => loadPage(), 350)
})

watch(selectedBucket, () => {
  void loadPage()
})

watch([selectedBankId, selectedCurrency, selectedFromDate, selectedToDate, selectedAmountMin, selectedAmountMax, selectedReviewerId], () => {
  void loadPage()
})

watch(showBankFilter, enabled => {
  if (enabled) {
    void loadBankOptions()
  }
}, { immediate: true })

watch(reviewerFilterEnabled, enabled => {
  if (enabled) {
    void loadReviewerOptions()
  }
}, { immediate: true })

onUnmounted(() => {
  if (searchTimeout.value !== null) clearTimeout(searchTimeout.value)
})

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
          :disabled="loadingBanks"
        >
          <option value="">جميع البنوك</option>
          <option v-for="bank in banks" :key="bank.id" :value="bank.id">
            {{ bank.name_ar || bank.name_en }}
          </option>
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

        <button
          type="button"
          :class="['filter-advanced-toggle', (showAdvancedFilters || hasAdvancedFilters) && 'filter-advanced-toggle--active']"
          @click="showAdvancedFilters = !showAdvancedFilters"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          فلاتر متقدمة
        </button>
      </div>

      <div v-if="showAdvancedFilters || hasAdvancedFilters" class="advanced-filters">
        <!-- Date range -->
        <label class="advanced-filter-group">
          <span class="filter-label">من</span>
          <input v-model="selectedFromDate" type="date" class="filter-input" dir="rtl" aria-label="من تاريخ" />
        </label>
        <label class="advanced-filter-group">
          <span class="filter-label">إلى</span>
          <input v-model="selectedToDate" type="date" class="filter-input" dir="rtl" aria-label="إلى تاريخ" />
        </label>

        <!-- Amount range -->
        <label class="advanced-filter-group">
          <span class="filter-label">أقل مبلغ</span>
          <input
            v-model.number="selectedAmountMin"
            type="number"
            min="0"
            step="any"
            class="filter-input filter-input--amount"
            dir="ltr"
            placeholder="0"
            aria-label="أقل مبلغ"
          />
        </label>
        <label class="advanced-filter-group">
          <span class="filter-label">أعلى مبلغ</span>
          <input
            v-model.number="selectedAmountMax"
            type="number"
            min="0"
            step="any"
            class="filter-input filter-input--amount"
            dir="ltr"
            placeholder="∞"
            aria-label="أعلى مبلغ"
          />
        </label>

        <!-- Reviewer select -->
        <label class="advanced-filter-group">
          <span class="filter-label">المراجع</span>
          <span
            v-if="!reviewerFilterEnabled"
            class="filter-reviewer-locked"
            :title="'متاح لمسؤول النظام'"
          >
            <select
              v-model="selectedReviewerId"
              class="filter-select filter-select--reviewer"
              dir="rtl"
              disabled
              aria-label="المراجع المكلف"
            >
              <option value="">متاح لمسؤول النظام</option>
            </select>
          </span>
          <select
            v-else
            v-model="selectedReviewerId"
            class="filter-select filter-select--reviewer"
            dir="rtl"
            :disabled="loadingReviewers"
            aria-label="المراجع المكلف"
          >
            <option value="">جميع المراجعين</option>
            <option v-for="reviewer in reviewers" :key="reviewer.id" :value="reviewer.id">
              {{ reviewer.name }}
            </option>
          </select>
        </label>

        <button
          v-if="hasAdvancedFilters"
          type="button"
          class="btn-clear-filters"
          @click="clearAdvancedFilters"
        >
          مسح الفلاتر
        </button>
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
                    v-if="showClaimBadge(request)"
                    :class="['badge', claimBadgeClass(request)]"
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
                <RequestProgress v-if="role" :status="request.status" :role="role" />
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

.filter-advanced-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  padding: 0 14px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  background: var(--color-surface, #fff);
  color: var(--color-text-primary, #1c222b);
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
  transition: border-color 100ms, background 100ms, color 100ms;
}

.filter-advanced-toggle:hover,
.filter-advanced-toggle--active {
  border-color: #0066cc;
  color: #0066cc;
  background: rgba(0,102,204,.05);
}

.advanced-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 12px;
  align-items: flex-end;
}

.advanced-filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}

.filter-label {
  font-size: 12px;
  color: var(--color-text-secondary, #6c757d);
}

.btn-clear-filters {
  height: 40px;
  padding: 0 14px;
  border: 1px solid var(--color-border, #cccccc);
  border-radius: 12px;
  background: transparent;
  color: var(--color-text-secondary, #6c757d);
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
}

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

.badge--claim-available {
  background: rgba(52,199,89,.12);
  color: #1b5e20;
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
  .filter-select--currency,
  .filter-advanced-toggle,
  .advanced-filter-group,
  .btn-clear-filters { width: 100%; }
  .advanced-filters { flex-direction: column; }
  .pagination-footer { flex-direction: column; gap: 8px; align-items: flex-start; }
  .pagination-controls { flex-wrap: wrap; }
  /* Hide numbered pages on mobile to avoid overflow */
  .pagination-page { display: none; }
}
</style>
