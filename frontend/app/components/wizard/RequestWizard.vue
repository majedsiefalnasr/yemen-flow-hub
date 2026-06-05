<script setup lang="ts">
import { computed, ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { AlertTriangle, Save, ChevronLeft, ChevronRight, Loader2 } from 'lucide-vue-next'
import WizardStepper from './WizardStepper.vue'
import WizardStep1 from './WizardStep1.vue'
import WizardStep2 from './WizardStep2.vue'
import WizardStep3 from './WizardStep3.vue'
import WizardStep4 from './WizardStep4.vue'
import { useRequestWizard } from '../../composables/useRequestWizard'
import { useMerchants } from '../../composables/useMerchants'
import { useAuthStore } from '../../stores/auth.store'
import type { DuplicateWarning, Merchant } from '../../types/models'
import { Alert, AlertDescription } from '../ui/alert'
import { Button } from '../ui/button'

const router = useRouter()
const authStore = useAuthStore()
const { fetchMerchants } = useMerchants()

const emit = defineEmits<{
  dirty: []
  clean: []
  submitted: []
}>()

const STEP_LABELS = [
  'بيانات الطلب',
  'بيانات المورد والشحنة',
  'الوثائق المطلوبة',
  'المراجعة والإرسال',
]

const duplicateWarnings = ref<DuplicateWarning[]>([])

const wizard = useRequestWizard()

// Merchant name for Step 4 summary
const merchantName = ref('')
const dataEntryMerchants = ref<Merchant[]>([])
const merchantResolutionError = ref<string | null>(null)

async function resolveDataEntryMerchant(): Promise<void> {
  if (!wizard.isDataEntry.value) return
  try {
    const merchants = await fetchMerchants({
      bank_id: authStore.user?.bank_id ?? undefined,
      is_active: true,
    })
    dataEntryMerchants.value = merchants

    if (wizard.step1.value.merchant_id) {
      const currentMerchant = merchants.find(
        (merchant) => merchant.id === wizard.step1.value.merchant_id,
      )
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
    merchantResolutionError.value =
      merchants.length === 0
        ? 'لا يوجد مستورد نشط مرتبط بحساب إدخال البيانات هذا.'
        : 'اختر المستورد المرتبط بهذا الطلب من قائمة مستوردي البنك.'
  } catch {
    dataEntryMerchants.value = []
    wizard.step1.value.merchant_id = null
    merchantName.value = ''
    merchantResolutionError.value =
      'تعذّر تحميل بيانات المستورد المرتبط بالحساب. أعد المحاولة بعد قليل.'
  }
}

onMounted(resolveDataEntryMerchant)

// Keep merchantName in sync with BANK_ADMIN selection
async function refreshMerchantName(id: number | null): Promise<void> {
  if (!id) {
    merchantName.value = ''
    return
  }
  try {
    const merchants =
      wizard.isDataEntry.value && dataEntryMerchants.value.length > 0
        ? dataEntryMerchants.value
        : await fetchMerchants()
    merchantName.value = merchants.find((m) => m.id === id)?.name ?? ''
  } catch {
    merchantName.value = ''
  }
}

async function handleNext(): Promise<void> {
  emit('dirty')
  const ok = wizard.nextStep()
  if (!ok) {
    // Let Vue flush the error state into the DOM before scrolling
    await new Promise((r) => setTimeout(r, 60))
    const firstInvalid = document.querySelector<HTMLElement>(
      '[aria-invalid="true"], .border-destructive',
    )
    firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    firstInvalid?.focus({ preventScroll: true })
    return
  }

  scrollToStepper()

  if (wizard.currentStep.value === 3) {
    await wizard.ensureDraftSavedForStep3()
  }
}

async function handleSaveDraft(): Promise<void> {
  emit('clean')
  const result = await wizard.saveDraft()
  if (result) {
    duplicateWarnings.value = result.duplicate_warnings ?? []
    toast.success('تم حفظ الطلب كمسودة.')
  } else if (wizard.saveError.value) {
    emit('dirty')
    toast.error(wizard.saveError.value)
  }
}

async function handleSubmit(): Promise<void> {
  const result = await wizard.submitRequest()
  if (result) {
    emit('submitted')
    toast.success('تم إرسال الطلب للمراجعة البنكية.')
    await new Promise((r) => setTimeout(r, 800))
    await router.push(`/requests/${result.id}`)
  } else if (wizard.submitError.value) {
    toast.error(wizard.submitError.value)
  }
}

const isLastStep = computed(() => wizard.currentStep.value === wizard.totalSteps)
const isFirstStep = computed(() => wizard.currentStep.value === 1)
const isSubmitDisabled = computed(() => !wizard.acknowledged.value || wizard.submitting.value)

const stepperRef = ref<HTMLElement | null>(null)

function scrollToStepper(): void {
  nextTick(() => {
    stepperRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <!-- Breadcrumb -->
    <nav
      class="font-section text-muted-foreground flex items-center gap-1.5 text-xs leading-5"
      aria-label="مسار التنقل"
    >
      <NuxtLink
        to="/"
        class="text-muted-foreground hover:text-primary transition-colors hover:underline"
        >الرئيسية</NuxtLink
      >
      <span class="text-border">/</span>
      <NuxtLink
        to="/requests"
        class="text-muted-foreground hover:text-primary transition-colors hover:underline"
        >الطلبات</NuxtLink
      >
      <span class="text-border">/</span>
      <span class="text-foreground font-medium">طلب جديد</span>
    </nav>

    <!-- Page title -->
    <div class="flex max-w-[65ch] flex-col gap-1.5">
      <h1 class="font-heading text-foreground text-2xl leading-tight font-semibold">
        تقديم طلب تمويل واردات جديد
      </h1>
      <p class="text-muted-foreground text-sm leading-6">
        أدخل بيانات الطلب وأرفق ملفات PDF المطلوبة قبل الإرسال للمراجعة البنكية.
      </p>
    </div>

    <!-- Stepper -->
    <div ref="stepperRef">
      <WizardStepper
        :steps="STEP_LABELS"
        :current-step="wizard.currentStep.value"
        :step-statuses="wizard.stepStatuses.value"
        @step-click="
          (s) => {
            wizard.goToStep(s)
            scrollToStepper()
          }
        "
      />
    </div>

    <!-- Step content card -->
    <div class="bg-card border-border rounded-2xl border p-6 shadow-sm" data-field-nav>
      <WizardStep1
        v-if="wizard.currentStep.value === 1"
        v-model="wizard.step1.value"
        :errors="wizard.step1Errors.value"
        :is-data-entry="wizard.isDataEntry.value"
        :data-entry-merchant-name="merchantName"
        :data-entry-merchants="dataEntryMerchants"
        :data-entry-merchant-error="merchantResolutionError"
        :loading="wizard.saving.value"
        @clear-error="wizard.clearStep1Error"
        @update:model-value="
          (v) => {
            wizard.step1.value = v
            if (v.merchant_id) refreshMerchantName(v.merchant_id)
          }
        "
      />
      <WizardStep2
        v-else-if="wizard.currentStep.value === 2"
        v-model="wizard.step2.value"
        :errors="wizard.step2Errors.value"
        :auto-fill-chip="wizard.autoFillChip.value"
        :loading="wizard.saving.value"
        @clear-error="wizard.clearStep2Error"
        @arrival-port-change="wizard.onArrivalPortChange"
        @update:model-value="
          (v) => {
            wizard.step2.value = v
          }
        "
      />
      <WizardStep3
        v-else-if="wizard.currentStep.value === 3"
        v-model="wizard.step3.value"
        :errors="wizard.step3Errors.value"
        :upload-state="wizard.uploadState.value"
        :loading="wizard.saving.value"
        :request-id="wizard.savedRequestId.value"
        :template-ready="wizard.autoSavedForStep3.value"
        @file-reset="wizard.resetUploadState"
        @update:model-value="
          (v) => {
            wizard.step3.value = v
          }
        "
      />
      <template v-else-if="wizard.currentStep.value === 4">
        <!-- Duplicate invoice soft warning — non-blocking, backend is authority -->
        <Alert
          v-if="duplicateWarnings.length > 0"
          class="mb-4 border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 text-[var(--severity-amber)]"
        >
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription class="text-foreground">
            تنبيه: رقم الفاتورة
            <span class="font-mono font-semibold">{{ wizard.step2.value.invoice_number }}</span>
            مستخدم في
            {{
              duplicateWarnings.length === 1
                ? 'طلب سابق'
                : `${duplicateWarnings.length} طلبات سابقة`
            }}
            المراجع:
            {{
              duplicateWarnings
                .map((w) => w.reference_number)
                .filter(Boolean)
                .join('، ') || 'مرجع غير متاح'
            }}. راجع الرقم قبل الإرسال، ويمكنك المتابعة إذا كانت الفاتورة صحيحة.
          </AlertDescription>
        </Alert>
        <WizardStep4
          :step1="wizard.step1.value"
          :step2="wizard.step2.value"
          :step3="wizard.step3.value"
          :merchant-name="merchantName"
          :acknowledged="wizard.acknowledged.value"
          @update:acknowledged="
            (v) => {
              wizard.acknowledged.value = v
            }
          "
        />
      </template>

      <!-- Bottom navigation bar -->
      <div class="bg-border my-6 h-px" aria-hidden="true" />
      <div class="flex items-center gap-3" role="toolbar" aria-label="تنقل خطوات الطلب">
        <!-- Previous — ChevronRight points right = backward in RTL -->
        <Button
          v-if="!isFirstStep"
          variant="outline"
          :disabled="wizard.saving.value || wizard.submitting.value"
          @click="
            () => {
              wizard.prevStep()
              scrollToStepper()
            }
          "
        >
          <ChevronRight class="h-4 w-4" />
          السابق
        </Button>
        <span v-else class="w-20 flex-shrink-0" />

        <!-- Save draft -->
        <Button
          variant="outline"
          class="ms-auto"
          :disabled="wizard.saving.value || wizard.submitting.value"
          @click="handleSaveDraft"
        >
          <Save class="me-1 h-4 w-4" />
          <span v-if="wizard.saving.value">جارٍ حفظ المسودة...</span>
          <span v-else>حفظ كمسودة</span>
        </Button>

        <!-- Next — ChevronLeft points left = forward in RTL -->
        <Button v-if="!isLastStep" :disabled="wizard.saving.value" @click="handleNext">
          التالي
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <Button v-else :disabled="isSubmitDisabled" @click="handleSubmit">
          <Loader2 v-if="wizard.submitting.value" class="me-1 h-4 w-4 animate-spin" />
          <span v-if="wizard.submitting.value">جارٍ إرسال الطلب...</span>
          <span v-else>إرسال للمراجعة البنكية</span>
          <ChevronLeft v-if="!wizard.submitting.value" class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
