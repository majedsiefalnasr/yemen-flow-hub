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
const merchantResolutionError = ref<string | null>(null)

async function resolveDataEntryMerchant(): Promise<void> {
  if (!wizard.isDataEntry.value) return
  try {
    const merchants = await fetchMerchants({
      bank_id: authStore.user?.bank_id ?? undefined,
      is_active: true,
    })

    if (wizard.step1.value.merchant_id) {
      const currentMerchant = merchants.find(merchant => merchant.id === wizard.step1.value.merchant_id)
      if (currentMerchant) {
        merchantName.value = currentMerchant.name
        merchantResolutionError.value = null
        return
      }
    }

    if (merchants.length === 1) {
      wizard.step1.value.merchant_id = merchants[0]!.id
      merchantName.value = merchants[0]!.name
      merchantResolutionError.value = null
      return
    }

    wizard.step1.value.merchant_id = null
    merchantName.value = ''
    merchantResolutionError.value = merchants.length === 0
      ? 'لا يوجد تاجر نشط مرتبط بحساب إدخال البيانات هذا.'
      : 'تعذّر تحديد التاجر تلقائياً لهذا الحساب. يرجى التواصل مع مسؤول البنك.'
  }
  catch {
    wizard.step1.value.merchant_id = null
    merchantName.value = ''
    merchantResolutionError.value = 'تعذّر تحميل بيانات التاجر المرتبط بالحساب. حاول مرة أخرى لاحقاً.'
  }
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
  <div class="flex flex-col gap-6" dir="rtl">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-1.5 text-xs text-muted-foreground font-family-arabic" aria-label="مسار التنقل">
      <NuxtLink to="/" class="text-muted-foreground hover:text-primary-blue hover:underline transition-colors">الرئيسية</NuxtLink>
      <span class="text-border">/</span>
      <NuxtLink to="/requests" class="text-muted-foreground hover:text-primary-blue hover:underline transition-colors">الطلبات</NuxtLink>
      <span class="text-border">/</span>
      <span class="text-primary-text font-medium">طلب جديد</span>
    </nav>

    <!-- Page title -->
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold text-primary-text font-cairo">تقديم طلب تمويل واردات جديد</h1>
      <p class="text-sm text-muted-foreground font-family-arabic">املأ البيانات بدقة وأرفق المستندات المطلوبة</p>
    </div>

    <!-- Toast -->
    <div
      v-if="toast"
      :class="{
        'bg-success/10 text-success border border-green-200': toast.type === 'success',
        'bg-destructive/10 text-destructive border border-destructive': toast.type === 'error',
        'bg-primary/10 text-primary border border-border': toast.type === 'info',
      }"
      class="px-4 py-3 rounded-md text-sm font-medium font-family-arabic"
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
    <div class="bg-white border border-border rounded-2xl p-6 shadow-sm">
      <WizardStep1
        v-if="wizard.currentStep.value === 1"
        v-model="wizard.step1.value"
        :errors="wizard.step1Errors.value"
        :is-data-entry="wizard.isDataEntry.value"
        :data-entry-merchant-name="merchantName"
        :data-entry-merchant-error="merchantResolutionError"
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
        @file-reset="wizard.resetUploadState"
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

      <!-- Bottom navigation bar -->
      <div class="h-px bg-border my-6" aria-hidden="true" />
      <div class="flex items-center gap-3" role="toolbar" aria-label="تنقل خطوات الطلب">
        <!-- Previous -->
        <button
          v-if="!isFirstStep"
          type="button"
          class="h-10 px-5 border border-border rounded-2xl bg-transparent text-primary-text hover:border-primary-blue hover:text-primary-blue disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-sm whitespace-nowrap"
          :disabled="wizard.saving.value || wizard.submitting.value"
          @click="wizard.prevStep"
        >
          → السابق
        </button>
        <span v-else class="flex-shrink-0 w-20" />

        <!-- Save draft -->
        <button
          type="button"
          class="h-10 px-5 border border-border rounded-2xl bg-transparent text-primary-text hover:border-primary-blue hover:text-primary-blue disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-sm whitespace-nowrap ml-auto"
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
          class="h-10 px-6 bg-primary-blue text-white rounded-2xl border-none font-medium text-sm whitespace-nowrap hover:opacity-90 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed transition-all"
          :disabled="wizard.saving.value"
          @click="handleNext"
        >
          التالي ←
        </button>
        <button
          v-else
          type="button"
          class="h-10 px-6 bg-primary-blue text-white rounded-2xl border-none font-medium text-sm whitespace-nowrap hover:opacity-90 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed transition-all"
          :disabled="isSubmitDisabled"
          @click="handleSubmit"
        >
          <span v-if="wizard.submitting.value">جارٍ الإرسال...</span>
          <span v-else>إرسال للمراجعة ←</span>
        </button>
      </div>
    </div>
  </div>
</template>

