<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Eye } from 'lucide-vue-next'
import { UserRole } from '../types/enums'
import type { Merchant } from '../types/models'
import { useMerchants } from '../composables/useMerchants'
import { useBanks } from '../composables/useBanks'
import { useAuthStore } from '../stores/auth.store'
import MerchantCard from '../components/merchants/MerchantCard.vue'
import MerchantModal from '../components/merchants/MerchantModal.vue'
import SuspendConfirmDialog from '../components/merchants/SuspendConfirmDialog.vue'
import Dialog from '../components/ui/dialog/Dialog.vue'
import DialogContent from '../components/ui/dialog/DialogContent.vue'
import DialogHeader from '../components/ui/dialog/DialogHeader.vue'
import DialogTitle from '../components/ui/dialog/DialogTitle.vue'
import DialogOverlay from '../components/ui/dialog/DialogOverlay.vue'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

const { fetchMerchants, createMerchant, updateMerchant, suspendMerchant } = useMerchants()
const authStore = useAuthStore()
const { fetchBanks } = useBanks()

const merchants = ref<Merchant[]>([])
const bankOptions = ref<Array<{ id: number; name: string }>>([])
const loading = ref(false)
const loadError = ref<string | null>(null)
const bankLoadError = ref<string | null>(null)

const showModal = ref(false)
const editingMerchant = ref<Merchant | null>(null)
const saving = ref(false)
const saveError = ref<string | null>(null)

const suspendTarget = ref<Merchant | null>(null)
const suspending = ref(false)

const viewingMerchant = ref<Merchant | null>(null)

const searchQuery = ref('')
const statusFilter = ref<'' | 'active' | 'suspended'>('')
const bankFilter = ref<string>('')

const isBankAdmin = computed(() => authStore.currentRole === UserRole.BANK_ADMIN)

const pageSubtitle = computed(() =>
  isBankAdmin.value
    ? 'تسجيل ومتابعة التجار والمستوردين المرتبطين بالبنك'
    : 'عرض جميع التجار المسجّلين على المنصّة مع البنوك التابعة لها',
)

const filteredMerchants = computed(() => {
  let list = merchants.value
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(m =>
      m.name.toLowerCase().includes(q)
      || (m.commercial_register ?? '').toLowerCase().includes(q)
      || (m.tax_number ?? '').toLowerCase().includes(q),
    )
  }
  if (statusFilter.value === 'active') list = list.filter(m => m.is_active)
  if (statusFilter.value === 'suspended') list = list.filter(m => !m.is_active)
  if (!isBankAdmin.value && bankFilter.value) {
    list = list.filter(m => String(m.bank_id) === bankFilter.value)
  }
  return list
})

const totalCount = computed(() => merchants.value.length)
const activeCount = computed(() => merchants.value.filter(m => m.is_active).length)
const suspendedCount = computed(() => merchants.value.filter(m => !m.is_active).length)

const isFiltered = computed(() =>
  searchQuery.value.trim() !== '' || statusFilter.value !== '' || bankFilter.value !== '',
)

function businessTypeLabel(type: string | null | undefined): string {
  const MAP: Record<string, string> = {
    import: 'استيراد',
    export: 'تصدير',
    retail: 'تجارة تجزئة',
    wholesale: 'تجارة جملة',
    manufacturing: 'تصنيع',
    services: 'خدمات',
  }
  return type ? (MAP[type] ?? type) : '—'
}

function metaVal(val: string | null | undefined): string {
  return val ?? '—'
}

async function loadMerchants() {
  loading.value = true
  loadError.value = null
  try {
    merchants.value = await fetchMerchants()
  }
  catch {
    loadError.value = 'تعذّر تحميل التجار. حاول مجدداً.'
  }
  finally {
    loading.value = false
  }
}

async function loadBanksForCbyAdmin() {
  if (!authStore.isCbyAdmin) {
    bankOptions.value = []
    return
  }
  bankLoadError.value = null
  try {
    const banks = await fetchBanks()
    bankOptions.value = banks
      .filter(bank => bank.is_active)
      .map(bank => ({ id: bank.id, name: bank.name_ar || bank.name_en }))
  }
  catch {
    bankLoadError.value = 'تعذّر تحميل قائمة البنوك.'
  }
}

function openCreate() {
  editingMerchant.value = null
  saveError.value = null
  showModal.value = true
}

function openEdit(merchant: Merchant) {
  editingMerchant.value = merchant
  saveError.value = null
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  saveError.value = null
}

function openView(merchant: Merchant) {
  viewingMerchant.value = merchant
}

function closeView() {
  viewingMerchant.value = null
}

async function handleSave(data: {
  name: string
  commercial_register: string
  tax_number: string
  phone: string | null
  address: string | null
  business_type: string | null
  is_active: boolean | undefined
  bank_id: number | null
}) {
  if (!editingMerchant.value && authStore.isCbyAdmin && !data.bank_id) {
    saveError.value = 'اختيار البنك مطلوب.'
    return
  }

  saving.value = true
  saveError.value = null
  try {
    if (editingMerchant.value) {
      const updated = await updateMerchant(editingMerchant.value.id, {
        name: data.name,
        commercial_register: data.commercial_register || null,
        tax_number: data.tax_number || null,
        phone: data.phone,
        address: data.address,
        business_type: data.business_type,
        is_active: data.is_active,
      })
      const idx = merchants.value.findIndex(m => m.id === updated.id)
      if (idx !== -1) merchants.value[idx] = updated
    }
    else {
      const created = await createMerchant({
        name: data.name,
        bank_id: authStore.isCbyAdmin ? data.bank_id : undefined,
        commercial_register: data.commercial_register || null,
        tax_number: data.tax_number || null,
        phone: data.phone,
        address: data.address,
        business_type: data.business_type,
        is_active: true,
      })
      merchants.value.unshift(created)
    }
    closeModal()
  }
  catch (err: unknown) {
    const e = err as { data?: { message?: string } }
    saveError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
  }
  finally {
    saving.value = false
  }
}

function requestToggleStatus(merchant: Merchant) {
  suspendTarget.value = merchant
}

function cancelSuspend() {
  suspendTarget.value = null
}

async function confirmToggleStatus() {
  if (!suspendTarget.value) return
  const target = suspendTarget.value
  suspending.value = true
  suspendTarget.value = null
  try {
    const updated = await suspendMerchant(target.id, !target.is_active)
    const idx = merchants.value.findIndex(m => m.id === updated.id)
    if (idx !== -1) merchants.value[idx] = updated
  }
  catch {
    loadError.value = 'تعذّر تحديث حالة التاجر.'
  }
  finally {
    suspending.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadMerchants(), loadBanksForCbyAdmin()])
})
</script>

<template>
  <div class="merchants-page" dir="rtl">
    <!-- Breadcrumbs -->
    <nav class="breadcrumbs" aria-label="breadcrumb">
      <span class="breadcrumb-item">الرئيسية</span>
      <span class="breadcrumb-sep">/</span>
      <span class="breadcrumb-item breadcrumb-current">التجار</span>
    </nav>

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">إدارة التجار</h1>
        <p class="page-subtitle">{{ pageSubtitle }}</p>
      </div>
      <button v-if="isBankAdmin" class="btn-primary" @click="openCreate">
        + تاجر جديد
      </button>
    </div>

    <!-- Load error -->
    <div v-if="loadError" class="error-banner" role="alert">
      {{ loadError }}
      <button class="retry-btn" @click="loadMerchants">إعادة المحاولة</button>
    </div>

    <!-- Bank load error (non-blocking — still show merchants) -->
    <div v-if="bankLoadError" class="warning-banner" role="alert">
      {{ bankLoadError }}
      <button class="retry-btn-warn" @click="loadBanksForCbyAdmin">إعادة المحاولة</button>
    </div>

    <!-- Skeleton loaders while loading -->
    <template v-if="loading">
      <!-- Stat card skeletons -->
      <div class="stat-cards">
        <div v-for="n in 3" :key="n" class="stat-card skel-stat" aria-hidden="true">
          <div class="skel-bar skel-stat-val" />
          <div class="skel-bar skel-stat-lbl" />
        </div>
      </div>

      <!-- Card/table skeleton -->
      <div class="card-grid" aria-busy="true" aria-label="جارٍ تحميل التجار">
        <div v-for="n in 4" :key="n" class="skeleton-card">
          <div class="skel-header">
            <div class="skel-avatar" />
            <div class="skel-title-group">
              <div class="skel-bar skel-name" />
              <div class="skel-bar skel-badge" />
            </div>
          </div>
          <div class="skel-divider" />
          <div class="skel-meta">
            <div class="skel-bar skel-meta-row" />
            <div class="skel-bar skel-meta-row" />
            <div class="skel-bar skel-meta-row" />
          </div>
          <div class="skel-divider" />
          <div class="skel-actions">
            <div class="skel-bar skel-action" />
            <div class="skel-bar skel-action" />
          </div>
        </div>
      </div>
    </template>

    <!-- Loaded state -->
    <template v-else>
      <!-- Stat cards -->
      <div class="stat-cards">
        <div class="stat-card">
          <span class="stat-value">{{ totalCount }}</span>
          <span class="stat-label">إجمالي التجار</span>
        </div>
        <div class="stat-card stat-card-active">
          <span class="stat-value stat-value-active">{{ activeCount }}</span>
          <span class="stat-label">نشط</span>
        </div>
        <div class="stat-card stat-card-suspended">
          <span class="stat-value stat-value-suspended">{{ suspendedCount }}</span>
          <span class="stat-label">موقوف</span>
        </div>
      </div>

      <!-- Search + filter bar -->
      <div class="filter-bar">
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="بحث باسم التاجر أو رقم السجل..."
          aria-label="بحث عن تاجر"
        >
        <select v-model="statusFilter" class="filter-select" aria-label="تصفية بالحالة">
          <option value="">جميع الحالات</option>
          <option value="active">نشط</option>
          <option value="suspended">موقوف</option>
        </select>
        <select v-if="!isBankAdmin" v-model="bankFilter" class="filter-select" aria-label="تصفية بالبنك">
          <option value="">جميع البنوك</option>
          <option v-for="bank in bankOptions" :key="bank.id" :value="String(bank.id)">
            {{ bank.name }}
          </option>
        </select>
      </div>

      <!-- Empty state (no merchants at all) -->
      <div v-if="merchants.length === 0" class="empty-state" role="status">
        <div class="empty-icon" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" />
            <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
            <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 0-2 2h-2" />
            <path d="M10 6h4" /><path d="M10 10h4" /><path d="M10 14h4" /><path d="M10 18h4" />
          </svg>
        </div>
        <h2 class="empty-heading">لا يوجد تجار مسجلون</h2>
        <p class="empty-subtext">ابدأ بتسجيل أول تاجر أو مستورد مرتبط بهذا البنك في النظام.</p>
        <button v-if="isBankAdmin" class="btn-primary" aria-label="تسجيل تاجر جديد" @click="openCreate">
          تسجيل تاجر جديد
        </button>
      </div>

      <!-- Filtered-empty state -->
      <div v-else-if="filteredMerchants.length === 0" class="empty-state" role="status">
        <div class="empty-icon" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
          </svg>
        </div>
        <h2 class="empty-heading">لا توجد نتائج مطابقة.</h2>
        <p class="empty-subtext">جرّب تعديل معايير البحث أو التصفية.</p>
      </div>

      <!-- BANK_ADMIN: card grid -->
      <div v-else-if="isBankAdmin" class="card-grid">
        <MerchantCard
          v-for="merchant in filteredMerchants"
          :key="merchant.id"
          :merchant="merchant"
          @edit="openEdit"
          @toggle-status="requestToggleStatus"
        />
      </div>

      <!-- CBY_ADMIN: table -->
      <div v-else class="table-wrapper">
        <table class="merchants-table">
          <thead>
            <tr>
              <th>التاجر</th>
              <th>السجل التجاري</th>
              <th>الرقم الضريبي</th>
              <th>القطاع</th>
              <th>البنك التابع له</th>
              <th>الحالة</th>
              <th>المعاملات</th>
              <th aria-label="إجراءات" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="merchant in filteredMerchants" :key="merchant.id" class="table-row">
              <td class="cell-name">{{ merchant.name }}</td>
              <td class="cell-mono">{{ metaVal(merchant.commercial_register) }}</td>
              <td class="cell-mono">{{ metaVal(merchant.tax_number) }}</td>
              <td>{{ businessTypeLabel(merchant.business_type) }}</td>
              <td>
                <span v-if="merchant.bank_name" class="bank-badge">{{ merchant.bank_name }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span :class="['status-badge', merchant.is_active ? 'badge-active' : 'badge-suspended']">
                  {{ merchant.is_active ? 'نشط' : 'موقوف' }}
                </span>
              </td>
              <td class="cell-count">{{ merchant.transaction_count ?? 0 }}</td>
              <td>
                <button
                  class="icon-btn-view"
                  aria-label="عرض التفاصيل"
                  title="عرض"
                  @click="openView(merchant)"
                >
                  <Eye :size="16" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Add/Edit modal (BANK_ADMIN only) -->
    <MerchantModal
      v-if="showModal"
      :merchant="editingMerchant"
      :saving="saving"
      :server-error="saveError"
      :requires-bank-selection="authStore.isCbyAdmin"
      :bank-options="bankOptions"
      :default-bank-id="editingMerchant?.bank_id ?? null"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- CBY_ADMIN view-details modal -->
    <Dialog v-if="viewingMerchant" :open="true" @update:open="(open) => { if (!open) closeView() }">
      <div class="modal-layer">
        <DialogOverlay class="modal-backdrop" @click="closeView" />
        <DialogContent class="view-modal" dir="rtl" aria-label="تفاصيل التاجر">
          <DialogHeader class="view-modal-header">
            <DialogTitle class="view-modal-title">تفاصيل التاجر</DialogTitle>
            <button class="close-btn" aria-label="إغلاق" @click="closeView">✕</button>
          </DialogHeader>
          <div class="view-modal-body">
            <div class="view-grid">
              <div class="view-field">
                <span class="view-label">اسم التاجر</span>
                <span class="view-value">{{ viewingMerchant.name }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">السجل التجاري</span>
                <span class="view-value mono">{{ metaVal(viewingMerchant.commercial_register) }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">الرقم الضريبي</span>
                <span class="view-value mono">{{ metaVal(viewingMerchant.tax_number) }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">القطاع / النشاط</span>
                <span class="view-value">{{ businessTypeLabel(viewingMerchant.business_type) }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">البنك</span>
                <span class="view-value">{{ metaVal(viewingMerchant.bank_name) }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">الحالة</span>
                <span :class="['status-badge', viewingMerchant.is_active ? 'badge-active' : 'badge-suspended']">
                  {{ viewingMerchant.is_active ? 'نشط' : 'موقوف' }}
                </span>
              </div>
              <div class="view-field">
                <span class="view-label">المعاملات</span>
                <span class="view-value mono">{{ viewingMerchant.transaction_count ?? 0 }}</span>
              </div>
              <div class="view-field">
                <span class="view-label">هاتف التواصل</span>
                <span class="view-value ltr">{{ metaVal(viewingMerchant.phone) }}</span>
              </div>
              <div class="view-field view-field-full">
                <span class="view-label">العنوان</span>
                <span class="view-value">{{ metaVal(viewingMerchant.address) }}</span>
              </div>
            </div>
          </div>
        </DialogContent>
      </div>
    </Dialog>

    <!-- Suspend/Activate confirm dialog -->
    <SuspendConfirmDialog
      v-if="suspendTarget"
      :merchant="suspendTarget"
      @confirm="confirmToggleStatus"
      @cancel="cancelSuspend"
    />
  </div>
</template>

<style scoped>
.merchants-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1600px;
}

/* Breadcrumbs */
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6c757d;
}

.breadcrumb-sep {
  color: #cccccc;
}

.breadcrumb-current {
  color: #1c222b;
  font-weight: 500;
}

/* Page header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

.btn-primary {
  height: 44px;
  padding: 0 24px;
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-primary:hover {
  background: #0057b3;
}

/* Error / warning banners */
.error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 12px;
  padding: 14px 18px;
  color: #c62828;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.warning-banner {
  background: #fffde7;
  border: 1px solid #f57f17;
  border-radius: 12px;
  padding: 14px 18px;
  color: #f57f17;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.retry-btn {
  background: none;
  border: 1px solid #c62828;
  border-radius: 8px;
  color: #c62828;
  font-size: 13px;
  padding: 4px 12px;
  cursor: pointer;
  white-space: nowrap;
}

.retry-btn-warn {
  background: none;
  border: 1px solid #f57f17;
  border-radius: 8px;
  color: #f57f17;
  font-size: 13px;
  padding: 4px 12px;
  cursor: pointer;
  white-space: nowrap;
}

/* Stat cards */
.stat-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1c222b;
  font-family: 'Inter', monospace;
}

.stat-value-active { color: #1b5e20; }
.stat-value-suspended { color: #c62828; }

.stat-label {
  font-size: 13px;
  color: #6c757d;
}

/* Skeleton stat card */
.skel-stat {
  padding: 20px;
  gap: 8px;
}

.skel-stat-val { height: 28px; width: 60px; }
.skel-stat-lbl { height: 13px; width: 80px; }

/* Filter bar */
.filter-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 200px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  font-family: inherit;
}

.search-input:focus {
  border-color: #0066cc;
}

.filter-select {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  cursor: pointer;
  font-family: inherit;
  min-width: 140px;
}

.filter-select:focus {
  border-color: #0066cc;
}

/* Card grid — 3 cols desktop, 2 tablet, 1 mobile */
.card-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 1024px) {
  .card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .card-grid { grid-template-columns: 1fr; }
  .stat-cards { grid-template-columns: 1fr; }
  .filter-bar { flex-direction: column; }
}

/* Skeleton card */
.skeleton-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skel-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.skel-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #e9ecef;
  flex-shrink: 0;
  animation: shimmer 1.5s infinite;
}

.skel-title-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.skel-bar {
  background: #e9ecef;
  border-radius: 6px;
  animation: shimmer 1.5s infinite;
}

.skel-name { height: 14px; width: 70%; }
.skel-badge { height: 10px; width: 30%; }
.skel-divider { height: 1px; background: #e9ecef; }
.skel-meta { display: flex; flex-direction: column; gap: 8px; }
.skel-meta-row { height: 12px; width: 100%; }
.skel-actions { display: flex; gap: 8px; }
.skel-action { height: 32px; flex: 1; border-radius: 8px; }

@keyframes shimmer {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 64px 24px;
  text-align: center;
}

.empty-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #f5f5f7;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-heading {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.empty-subtext {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  max-width: 360px;
}

/* CBY_ADMIN table */
.table-wrapper {
  overflow-x: auto;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
}

.merchants-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.merchants-table thead th {
  padding: 14px 16px;
  text-align: right;
  font-weight: 600;
  color: #6c757d;
  background: #f9fafb;
  border-bottom: 1px solid #cccccc;
  white-space: nowrap;
}

.table-row td {
  padding: 14px 16px;
  color: #1c222b;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: middle;
}

.table-row:last-child td {
  border-bottom: none;
}

.table-row:hover {
  background: #f9fafb;
}

.cell-name {
  font-weight: 500;
}

.cell-mono {
  font-family: 'Inter', monospace;
  direction: ltr;
  text-align: right;
}

.cell-count {
  font-family: 'Inter', monospace;
  font-weight: 600;
  text-align: center;
}

.bank-badge {
  display: inline-block;
  padding: 2px 10px;
  background: #e8f0fe;
  color: #0066cc;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.text-muted {
  color: #6c757d;
}

.status-badge {
  display: inline-block;
  padding: 3px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.badge-active {
  background: #e8f5e9;
  color: #1b5e20;
}

.badge-suspended {
  background: #f5f5f7;
  color: #8e8e93;
}

.icon-btn-view {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #6c757d;
  cursor: pointer;
  transition: background 0.15s;
}

.icon-btn-view:hover {
  background: #e8f0fe;
  color: #0066cc;
}

/* View-details modal */
.modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}

.view-modal {
  position: relative;
  z-index: 1;
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 560px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.view-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.view-modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: #6c757d;
  cursor: pointer;
  line-height: 1;
  padding: 4px;
  flex-shrink: 0;
}

.view-modal-body {
  flex: 1;
}

.view-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.view-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.view-field-full {
  grid-column: 1 / -1;
}

.view-label {
  font-size: 12px;
  color: #6c757d;
  font-weight: 500;
}

.view-value {
  font-size: 14px;
  color: #1c222b;
  font-weight: 500;
}

.view-value.mono,
.view-value.ltr {
  direction: ltr;
  font-family: 'Inter', monospace;
  text-align: right;
}
</style>
