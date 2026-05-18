<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { UserRole } from '../types/enums'
import type { Merchant } from '../types/models'
import { useMerchants } from '../composables/useMerchants'
import { useAuthStore } from '../stores/auth.store'
import MerchantCard from '../components/merchants/MerchantCard.vue'
import MerchantModal from '../components/merchants/MerchantModal.vue'
import SuspendConfirmDialog from '../components/merchants/SuspendConfirmDialog.vue'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

const { fetchMerchants, createMerchant, updateMerchant, suspendMerchant } = useMerchants()
const authStore = useAuthStore()

const merchants = ref<Merchant[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const showModal = ref(false)
const editingMerchant = ref<Merchant | null>(null)
const saving = ref(false)
const saveError = ref<string | null>(null)

const suspendTarget = ref<Merchant | null>(null)
const suspending = ref(false)

const isBankAdmin = computed(() => authStore.currentRole === UserRole.BANK_ADMIN)

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

async function handleSave(data: {
  name: string
  commercial_register: string
  tax_number: string
  address: string | null
  business_type: string | null
}) {
  saving.value = true
  saveError.value = null
  try {
    if (editingMerchant.value) {
      const updated = await updateMerchant(editingMerchant.value.id, {
        name: data.name,
        commercial_register: data.commercial_register || null,
        tax_number: data.tax_number || null,
        address: data.address,
        is_active: editingMerchant.value.is_active,
      })
      const idx = merchants.value.findIndex(m => m.id === updated.id)
      if (idx !== -1) merchants.value[idx] = updated
    }
    else {
      const created = await createMerchant({
        name: data.name,
        commercial_register: data.commercial_register || null,
        tax_number: data.tax_number || null,
        address: data.address,
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

onMounted(loadMerchants)
</script>

<template>
  <div class="merchants-page" dir="rtl">
    <!-- Page header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">إدارة التجار</h1>
        <p class="page-subtitle">تجار وموردو البنك المسجّلون في المنظومة</p>
      </div>
      <button v-if="isBankAdmin || authStore.isCbyAdmin" class="btn-primary" @click="openCreate">
        + إضافة تاجر جديد
      </button>
    </div>

    <!-- Load error -->
    <div v-if="loadError" class="error-banner" role="alert">
      {{ loadError }}
      <button class="retry-btn" @click="loadMerchants">إعادة المحاولة</button>
    </div>

    <!-- Skeleton loaders while loading -->
    <div v-else-if="loading" class="card-grid" aria-busy="true" aria-label="جارٍ تحميل التجار">
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
          <div class="skel-bar skel-action" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="merchants.length === 0" class="empty-state" role="status">
      <div class="empty-icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" />
          <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
          <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 0-2 2h-2" />
          <path d="M10 6h4" />
          <path d="M10 10h4" />
          <path d="M10 14h4" />
          <path d="M10 18h4" />
        </svg>
      </div>
      <h2 role="heading" aria-level="2" class="empty-heading">لا يوجد تجار مسجلون</h2>
      <p class="empty-subtext">ابدأ بتسجيل أول تاجر أو مستورد مرتبط بهذا البنك في النظام.</p>
      <button v-if="isBankAdmin || authStore.isCbyAdmin" class="btn-primary" aria-label="تسجيل تاجر جديد" @click="openCreate">
        تسجيل تاجر جديد
      </button>
    </div>

    <!-- Merchant card grid -->
    <div v-else class="card-grid">
      <MerchantCard
        v-for="merchant in merchants"
        :key="merchant.id"
        :merchant="merchant"
        @edit="openEdit"
        @toggle-status="requestToggleStatus"
      />
    </div>

    <!-- Add/Edit modal -->
    <MerchantModal
      v-if="showModal"
      :merchant="editingMerchant"
      :saving="saving"
      :server-error="saveError"
      @save="handleSave"
      @close="closeModal"
    />

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
  gap: 24px;
  max-width: 1600px;
}

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

/* Card grid — 3 cols desktop, 2 tablet, 1 mobile */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
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
  width: 40px;
  height: 40px;
  border-radius: 50%;
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

.skel-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.skel-meta-row { height: 12px; width: 100%; }

.skel-actions {
  display: flex;
  gap: 8px;
}

.skel-action { height: 36px; flex: 1; border-radius: 10px; }

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
</style>
