<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import WizardStepper from './WizardStepper.vue'
import WizardStep1 from './WizardStep1.vue'
import WizardStep2 from './WizardStep2.vue'
import WizardStep3 from './WizardStep3.vue'
import WizardStep4 from './WizardStep4.vue'
import { useRequestWizard } from '../../composables/useRequestWizard'
import { useMerchants } from '../../composables/useMerchants'
import { useAuthStore } from '../../stores/auth.store'

const router = useRouter()
const authStore = useAuthStore()
const { fetchMerchants } = useMerchants()

const STEP_LABELS = [
  'بيانات الطلب',
  'بيانات المورد والشحنة',
  'الوثائق المطلوبة',
  'المراجعة والإرسال',
]

const toast = ref<{ message: string; type: 'success' | 'error' | 'info' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(message: string, type: 'success' | 'error' | 'info' = 'info'): void {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => { toast.value = null }, 3500)
}

const wizard = useRequestWizard()

// Merchant name for Step 4 summary
const merchantName = ref('')

async function resolveDataEntryMerchant(): Promise<void> {
  if (!wizard.isDataEntry.value) return
  try {
    const merchants = await fetchMerchants()
    const first = merchants[0]
    if (!first) return

    wizard.step1.value.merchant_id = first.id
    merchantName.value = first.name
  }
  catch { /* ignore — user can retry */ }
}

onMounted(resolveDataEntryMerchant)

// Keep merchantName in sync with BANK_ADMIN selection
async function refreshMerchantName(id: number | null): Promise<void> {
  if (!id) { merchantName.value = ''; return }
  try {
    const merchants = await fetchMerchants()
    merchantName.value = merchants.find(m => m.id === id)?.name ?? ''
  }
  catch { merchantName.value = '' }
}

// Watch merchant_id changes for BANK_ADMIN
const step1DataProxy = computed({
  get: () => wizard.step1.value,
  set: (val) => {
    wizard.step1.value = val
    if (!wizard.isDataEntry.value && val.merchant_id !== wizard.step1.value.merchant_id) {
      refreshMerchantName(val.merchant_id)
    }
  },
})

async function handleNext(): Promise<void> {
  const ok = wizard.nextStep()
  if (!ok) {
    // Scroll to first error
    await new Promise(r => setTimeout(r, 50))
    document.querySelector('[role="alert"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}

async function handleSaveDraft(): Promise<void> {
  const result = await wizard.saveDraft()
  if (result) {
    showToast('تم الحفظ كمسودة ✓', 'success')
  }
  else if (wizard.saveError.value) {
    showToast(wizard.saveError.value, 'error')
  }
}

async function handleSubmit(): Promise<void> {
  const result = await wizard.submitRequest()
  if (result) {
    showToast('تم إرسال الطلب بنجاح!', 'success')
    await new Promise(r => setTimeout(r, 800))
    await router.push(`/requests/${result.id}`)
  }
  else if (wizard.submitError.value) {
    showToast(wizard.submitError.value, 'error')
  }
}

const isLastStep = computed(() => wizard.currentStep.value === wizard.totalSteps)
const isFirstStep = computed(() => wizard.currentStep.value === 1)
const isSubmitDisabled = computed(() => !wizard.acknowledged.value || wizard.submitting.value)
</script>

<template>
  <div class="wizard-page" dir="rtl">
    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="مسار التنقل">
      <NuxtLink to="/" class="breadcrumb-link">الرئيسية</NuxtLink>
      <span class="breadcrumb-sep">/</span>
      <NuxtLink to="/requests" class="breadcrumb-link">الطلبات</NuxtLink>
      <span class="breadcrumb-sep">/</span>
      <span class="breadcrumb-current">طلب جديد</span>
    </nav>

    <!-- Page title -->
    <div class="page-header">
      <h1 class="page-title">تقديم طلب تمويل واردات جديد</h1>
      <p class="page-subtitle">أملأ البيانات بدقة وأرفق المستندات المطلوبة</p>
    </div>

    <!-- Toast -->
    <div
      v-if="toast"
      class="toast"
      :class="`toast--${toast.type}`"
      role="status"
      aria-live="polite"
    >
      {{ toast.message }}
    </div>

    <!-- Stepper -->
    <WizardStepper
      :steps="STEP_LABELS"
      :current-step="wizard.currentStep.value"
      :step-statuses="wizard.stepStatuses.value"
      @step-click="wizard.goToStep"
    />

    <!-- Step content card -->
    <div class="step-card">
      <WizardStep1
        v-if="wizard.currentStep.value === 1"
        v-model="wizard.step1.value"
        :errors="wizard.step1Errors.value"
        :is-data-entry="wizard.isDataEntry.value"
        :loading="wizard.saving.value"
        @update:model-value="(v) => { wizard.step1.value = v; if (v.merchant_id) refreshMerchantName(v.merchant_id) }"
      />
      <WizardStep2
        v-else-if="wizard.currentStep.value === 2"
        v-model="wizard.step2.value"
        :errors="wizard.step2Errors.value"
        :auto-fill-chip="wizard.autoFillChip.value"
        :loading="wizard.saving.value"
        @arrival-port-change="wizard.onArrivalPortChange"
        @update:model-value="(v) => { wizard.step2.value = v }"
      />
      <WizardStep3
        v-else-if="wizard.currentStep.value === 3"
        v-model="wizard.step3.value"
        :errors="wizard.step3Errors.value"
        :upload-state="wizard.uploadState.value"
        :loading="wizard.saving.value"
        @update:model-value="(v) => { wizard.step3.value = v }"
      />
      <WizardStep4
        v-else-if="wizard.currentStep.value === 4"
        :step1="wizard.step1.value"
        :step2="wizard.step2.value"
        :step3="wizard.step3.value"
        :merchant-name="merchantName"
        :acknowledged="wizard.acknowledged.value"
        @update:acknowledged="(v) => { wizard.acknowledged.value = v }"
      />
    </div>

    <!-- Bottom navigation bar -->
    <div class="bottom-nav" role="toolbar" aria-label="تنقل خطوات الطلب">
      <!-- Previous -->
      <button
        v-if="!isFirstStep"
        type="button"
        class="btn-ghost btn-prev"
        :disabled="wizard.saving.value || wizard.submitting.value"
        @click="wizard.prevStep"
      >
        → السابق
      </button>
      <span v-else class="btn-placeholder" />

      <!-- Save draft -->
      <button
        type="button"
        class="btn-ghost btn-draft"
        :disabled="wizard.saving.value || wizard.submitting.value"
        @click="handleSaveDraft"
      >
        <span v-if="wizard.saving.value">جارٍ الحفظ...</span>
        <span v-else>💾 حفظ كمسودة</span>
      </button>

      <!-- Next / Submit -->
      <button
        v-if="!isLastStep"
        type="button"
        class="btn-primary btn-next"
        :disabled="wizard.saving.value"
        @click="handleNext"
      >
        التالي ←
      </button>
      <button
        v-else
        type="button"
        class="btn-primary btn-submit"
        :disabled="isSubmitDisabled"
        @click="handleSubmit"
      >
        <span v-if="wizard.submitting.value">جارٍ الإرسال...</span>
        <span v-else>إرسال للمراجعة ←</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.wizard-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding-bottom: 100px; /* room for sticky bottom nav */
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  color: #6c757d;
}

.breadcrumb-link {
  color: #6c757d;
  text-decoration: none;
}

.breadcrumb-link:hover {
  color: #0066cc;
  text-decoration: underline;
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
  flex-direction: column;
  gap: 4px;
}

.page-title {
  font-family: 'Cairo', sans-serif;
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.page-subtitle {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

/* Toast */
.toast {
  padding: 12px 16px;
  border-radius: 12px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  font-weight: 500;
}

.toast--success { background: #f1f8f4; color: #1b5e20; border: 1px solid #a5d6a7; }
.toast--error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.toast--info { background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }

/* Step card */
.step-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

/* Bottom nav */
.bottom-nav {
  position: sticky;
  bottom: 0;
  background: #ffffff;
  border-top: 1px solid #cccccc;
  padding: 16px 24px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 -24px;
}

.btn-placeholder { flex: 0 0 80px; }

/* Ghost button */
.btn-ghost {
  height: 40px;
  padding: 0 20px;
  background: transparent;
  border: 1px solid #cccccc;
  border-radius: 16px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #1c222b;
  cursor: pointer;
  white-space: nowrap;
  transition: border-color 150ms;
}

.btn-ghost:hover:not(:disabled) {
  border-color: #0066cc;
  color: #0066cc;
}

.btn-ghost:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-prev { margin-left: auto; }
.btn-draft { margin-right: auto; }

/* Primary button */
.btn-primary {
  height: 40px;
  padding: 0 24px;
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 150ms;
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.9;
}

.btn-primary:disabled {
  background: #e9ecef;
  color: #6c757d;
  cursor: not-allowed;
}
</style>
