import { ref, computed } from 'vue'
import { useRuntimeConfig } from '#app'
import { useRequests } from './useRequests'
import { useAuthStore } from '../stores/auth.store'
import { step1Schema, step2Schema, CUSTOMS_BY_PORT } from '../schemas/wizard.schema'
import { Currency } from '../types/enums'
import type { ImportRequest } from '../types/models'

export interface WizardStep1Data {
  goods_type: string
  amount: number | null
  currency: string
  payment_terms: string
  due_date: string
  merchant_id: number | null
  notes: string
}

export interface WizardStep2Data {
  supplier_name: string
  invoice_number: string
  origin_country: string
  invoice_date: string
  arrival_port: string
  shipping_port: string
  customs_office: string
  bl_number: string
}

export interface WizardStep3Data {
  confirmation_request: File | null
  proforma_invoice: File | null
  commercial_register: File | null
  tax_card: File | null
  extra_docs: File | null
}

export interface WizardUploadState {
  confirmation_request: 'idle' | 'uploading' | 'done' | 'error'
  proforma_invoice: 'idle' | 'uploading' | 'done' | 'error'
  commercial_register: 'idle' | 'uploading' | 'done' | 'error'
  tax_card: 'idle' | 'uploading' | 'done' | 'error'
  extra_docs: 'idle' | 'uploading' | 'done' | 'error'
}

export type WizardDocumentKey = keyof WizardUploadState

const DOCUMENT_LABELS: Record<WizardDocumentKey, string> = {
  confirmation_request: 'طلب وثيقة التأكيد (مختوم)',
  proforma_invoice: 'الفاتورة الأولية',
  commercial_register: 'السجل التجاري',
  tax_card: 'البطاقة الضريبية',
  extra_docs: 'مستندات إضافية',
}

const UPLOAD_ERROR_MESSAGE = 'تعذّر رفع الملف، يرجى إعادة المحاولة.'

function getXsrfToken(): string | null {
  if (!process.client) return null
  const raw = document.cookie
    .split(';')
    .map(cookie => cookie.trim())
    .find(cookie => cookie.startsWith('XSRF-TOKEN='))
    ?.split('=')
    .slice(1)
    .join('=')

  return raw ? decodeURIComponent(raw) : null
}

export function useRequestWizard() {
  const authStore = useAuthStore()
  const { createRequest } = useRequests()

  // ── State ──────────────────────────────────────────────────────────────────

  const currentStep = ref(1)
  const totalSteps = 4
  const saving = ref(false)
  const submitting = ref(false)
  const saveError = ref<string | null>(null)
  const submitError = ref<string | null>(null)
  const savedRequestId = ref<number | null>(null)
  const acknowledged = ref(false)
  const autoFillChip = ref(false)
  const autoSavedForStep3 = ref(false)

  // Per-step form data
  const step1 = ref<WizardStep1Data>({
    goods_type: '',
    amount: null,
    currency: Currency.USD,
    payment_terms: 'LC',
    due_date: '',
    merchant_id: null,
    notes: '',
  })

  const step2 = ref<WizardStep2Data>({
    supplier_name: '',
    invoice_number: '',
    origin_country: '',
    invoice_date: '',
    arrival_port: '',
    shipping_port: '',
    customs_office: '',
    bl_number: '',
  })

  const step3 = ref<WizardStep3Data>({
    confirmation_request: null,
    proforma_invoice: null,
    commercial_register: null,
    tax_card: null,
    extra_docs: null,
  })

  // Per-step validation errors
  const step1Errors = ref<Partial<Record<keyof WizardStep1Data, string>>>({})
  const step2Errors = ref<Partial<Record<keyof WizardStep2Data, string>>>({})
  const step3Errors = ref<Partial<Record<WizardDocumentKey, string>>>({})

  const uploadState = ref<WizardUploadState>({
    confirmation_request: 'idle',
    proforma_invoice: 'idle',
    commercial_register: 'idle',
    tax_card: 'idle',
    extra_docs: 'idle',
  })

  // ── Computed ───────────────────────────────────────────────────────────────

  const isDataEntry = computed(() => authStore.user?.role === 'DATA_ENTRY')

  const stepStatuses = computed<Array<'future' | 'active' | 'completed' | 'error'>>(() =>
    Array.from({ length: totalSteps }, (_, i) => {
      const n = i + 1
      if (n === currentStep.value) return 'active'
      if (n < currentStep.value) {
        const stepHasErrors = n === 1
          ? Object.keys(step1Errors.value).length > 0
          : n === 2
            ? Object.keys(step2Errors.value).length > 0
            : n === 3
              ? Object.keys(step3Errors.value).length > 0
              : false
        return stepHasErrors ? 'error' : 'completed'
      }
      return 'future'
    }),
  )

  const step1ErrorCount = computed(() => Object.keys(step1Errors.value).length)
  const step2ErrorCount = computed(() => Object.keys(step2Errors.value).length)
  const step3ErrorCount = computed(() => Object.keys(step3Errors.value).length)

  // ── Validation ─────────────────────────────────────────────────────────────

  function validateStep1(): boolean {
    const result = step1Schema.safeParse({
      ...step1.value,
      amount: step1.value.amount ?? undefined,
      merchant_id: step1.value.merchant_id ?? undefined,
    })
    if (result.success) {
      step1Errors.value = {}
      return true
    }
    const errs: typeof step1Errors.value = {}
    for (const issue of result.error.issues) {
      const key = issue.path[0] as keyof WizardStep1Data
      if (!errs[key]) errs[key] = issue.message
    }
    step1Errors.value = errs
    return false
  }

  function validateStep2(): boolean {
    const result = step2Schema.safeParse(step2.value)
    if (result.success) {
      step2Errors.value = {}
      return true
    }
    const errs: typeof step2Errors.value = {}
    for (const issue of result.error.issues) {
      const key = issue.path[0] as keyof WizardStep2Data
      if (!errs[key]) errs[key] = issue.message
    }
    step2Errors.value = errs
    return false
  }

  function validateStep3(): boolean {
    const errs: typeof step3Errors.value = {}
    const required: Array<WizardDocumentKey> = ['confirmation_request', 'proforma_invoice', 'commercial_register', 'tax_card']
    for (const key of required) {
      if (!step3.value[key]) {
        errs[key] = `يرجى رفع ${DOCUMENT_LABELS[key]}`
      }
    }
    step3Errors.value = errs
    return Object.keys(errs).length === 0
  }

  function resetUploadState(key: WizardDocumentKey): void {
    uploadState.value[key] = 'idle'

    if (step3Errors.value[key]) {
      const nextErrors = { ...step3Errors.value }
      delete nextErrors[key]
      step3Errors.value = nextErrors
    }
  }

  function validateCurrentStep(): boolean {
    if (currentStep.value === 1) return validateStep1()
    if (currentStep.value === 2) return validateStep2()
    if (currentStep.value === 3) return validateStep3()
    return true
  }

  // ── Navigation ─────────────────────────────────────────────────────────────

  function goToStep(step: number): void {
    if (step >= 1 && step <= totalSteps && step <= currentStep.value) {
      currentStep.value = step
    }
  }

  function canAdvance(): boolean {
    return validateCurrentStep()
  }

  function nextStep(): boolean {
    if (!validateCurrentStep()) return false
    if (currentStep.value < totalSteps) {
      currentStep.value++
    }
    return true
  }

  function prevStep(): void {
    if (currentStep.value > 1) currentStep.value--
  }

  // ── Port → Customs auto-fill ───────────────────────────────────────────────

  function onArrivalPortChange(port: string): void {
    step2.value.arrival_port = port
    const mapped = CUSTOMS_BY_PORT[port]
    if (mapped) {
      step2.value.customs_office = mapped
      autoFillChip.value = true
      setTimeout(() => { autoFillChip.value = false }, 2000)
    }
  }

  // ── Build API payload ──────────────────────────────────────────────────────

  function buildPayload() {
    return {
      merchant_id: step1.value.merchant_id as number,
      currency: step1.value.currency,
      amount: step1.value.amount as number,
      goods_type: step1.value.goods_type || null,
      payment_terms: step1.value.payment_terms || null,
      due_date: step1.value.due_date || null,
      notes: step1.value.notes || '',
      // Step 2 fields map onto existing + new backend fields
      supplier_name: step2.value.supplier_name,
      goods_description: step2.value.supplier_name, // backward-compat: reuse supplier_name for goods_description
      port_of_entry: step2.value.arrival_port || '',
      invoice_number: step2.value.invoice_number || null,
      invoice_date: step2.value.invoice_date || null,
      origin_country: step2.value.origin_country || null,
      arrival_port: step2.value.arrival_port || null,
      shipping_port: step2.value.shipping_port || null,
      customs_office: step2.value.customs_office || null,
      bl_number: step2.value.bl_number || null,
    }
  }

  // ── Save draft ─────────────────────────────────────────────────────────────

  async function saveDraft(): Promise<ImportRequest | null> {
    saving.value = true
    saveError.value = null
    try {
      const payload = buildPayload()
      let result: ImportRequest
      if (savedRequestId.value) {
        const { updateRequest } = useRequests()
        result = await updateRequest(savedRequestId.value, payload)
      }
      else {
        result = await createRequest(payload)
        savedRequestId.value = result.id
      }
      return result
    }
    catch (err: unknown) {
      saveError.value = 'تعذّر الحفظ كمسودة، يرجى المحاولة مجدداً.'
      if (import.meta.dev) console.error('[useRequestWizard] saveDraft failed:', err)
      return null
    }
    finally {
      saving.value = false
    }
  }

  async function ensureDraftSavedForStep3(): Promise<void> {
    if (savedRequestId.value !== null) {
      autoSavedForStep3.value = true
      return
    }

    const result = await saveDraft()
    if (result) {
      autoSavedForStep3.value = true
    }
  }

  // ── Upload documents ───────────────────────────────────────────────────────

  async function isFileStillReadable(file: File): Promise<boolean> {
    try {
      // Slice 1 byte — throws or returns empty ArrayBuffer if file was deleted
      const slice = file.slice(0, 1)
      const buf = await slice.arrayBuffer()
      // A deleted file returns an empty buffer even though file.size > 0
      return file.size === 0 || buf.byteLength > 0
    }
    catch {
      return false
    }
  }

  async function uploadDocuments(requestId: number): Promise<WizardDocumentKey[]> {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase as string
    const entries: Array<{ key: WizardDocumentKey; file: File }> = []
    const failedUploads: WizardDocumentKey[] = []
    const uploadErrors: Partial<Record<WizardDocumentKey, string>> = {}

    for (const key of Object.keys(step3.value) as WizardDocumentKey[]) {
      const file = step3.value[key]
      if (file) entries.push({ key, file })
    }

    step3Errors.value = {}

    await Promise.all(
      entries.map(async ({ key, file }) => {
        uploadState.value[key] = 'uploading'
        try {
          // Guard: verify the File object is still readable before uploading.
          // A file deleted from disk after selection returns an unreadable blob.
          const readable = await isFileStillReadable(file)
          if (!readable) {
            throw new Error('FILE_GONE')
          }

          const form = new FormData()
          form.append('request_id', String(requestId))
          form.append('file', file)
          if (key === 'confirmation_request') {
            form.append('confirmation_request', '1')
          }
          const xsrfToken = getXsrfToken()
          await $fetch(`/api/documents/upload`, {
            method: 'POST',
            baseURL,
            credentials: 'include',
            body: form,
            headers: {
              Accept: 'application/json',
              ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
          })
          uploadState.value[key] = 'done'
        }
        catch (err: unknown) {
          uploadState.value[key] = 'error'

          let userMessage = UPLOAD_ERROR_MESSAGE

          if (err instanceof Error && err.message === 'FILE_GONE') {
            userMessage = 'الملف لم يعد متاحاً. يرجى اختياره مجدداً.'
            // Reset the drop zone — stale File can never succeed
            step3.value = { ...step3.value, [key]: null }
          }
          else {
            // ofetch FetchError: response body is in err.data
            const data = (err as any)?.data
            const status = (err as any)?.response?.status

            if (status === 422) {
              // Two possible 422 shapes:
              // 1. Laravel validation: { errors: { file: ['The file must be...'] }, message: 'Validation failed.' }
              // 2. DocumentException: { message: 'Unsupported file type.' | 'File exceeds...', errors: {} }
              const fieldMsg: string | undefined = data?.errors?.file?.[0]
              const serverMsg: string | undefined = data?.message
              const raw = (fieldMsg ?? serverMsg ?? '').toLowerCase()

              if (raw.includes('mime') || raw.includes('pdf') || raw.includes('type') || raw.includes('unsupported')) {
                userMessage = 'نوع الملف غير مقبول. يُسمح بملفات PDF فقط.'
              }
              else if (raw.includes('max') || raw.includes('size') || raw.includes('kilobytes') || raw.includes('exceeds')) {
                userMessage = 'حجم الملف يتجاوز الحد الأقصى المسموح به (10MB).'
              }
              else if (raw.includes('authorized') || raw.includes('permission') || raw.includes('only authorized')) {
                userMessage = 'لا تملك صلاحية رفع هذا الملف.'
              }
              else if (raw.includes('editable') || raw.includes('locked')) {
                userMessage = 'لا يمكن رفع مستندات في الوضع الحالي للطلب.'
              }
              else if (fieldMsg || serverMsg) {
                // Unknown but specific server message — show it as-is
                userMessage = fieldMsg ?? serverMsg!
              }
            }
            else if (status === 403) {
              const serverMsg: string | undefined = data?.message
              const raw = (serverMsg ?? '').toLowerCase()
              if (raw.includes('editable') || raw.includes('locked')) {
                userMessage = 'لا يمكن رفع مستندات في الوضع الحالي للطلب.'
              }
              else {
                userMessage = 'لا تملك صلاحية رفع هذا الملف في الوضع الحالي للطلب.'
              }
            }
            else if (status === 409) {
              userMessage = 'تعارض في حالة الطلب. حدّث الصفحة وأعد المحاولة.'
            }
            else if (status === 401) {
              userMessage = 'انتهت جلستك. يرجى تسجيل الدخول مجدداً.'
            }
            // 5xx / network / unknown — fall through to generic UPLOAD_ERROR_MESSAGE
          }

          uploadErrors[key] = userMessage
          failedUploads.push(key)
        }
      }),
    )

    if (failedUploads.length > 0) {
      step3Errors.value = uploadErrors
    }

    return failedUploads
  }

  // ── Submit ─────────────────────────────────────────────────────────────────

  async function submitRequest(): Promise<ImportRequest | null> {
    if (!acknowledged.value) return null
    submitting.value = true
    submitError.value = null
    try {
      // Ensure request is saved first
      let reqId = savedRequestId.value
      if (!reqId) {
        const draft = await saveDraft()
        if (!draft) return null
        reqId = draft.id
      }

      // Upload documents
      const failedUploads = await uploadDocuments(reqId)
      if (failedUploads.length > 0) {
        currentStep.value = 3
        submitError.value = 'تعذّر رفع بعض الوثائق. صحّح الأخطاء الظاهرة ثم أعد المحاولة.'
        return null
      }

      // Trigger submit transition
      const { performWorkflowAction } = useRequests()
      const submitted = await performWorkflowAction(reqId, 'submit')
      return submitted
    }
    catch (err: unknown) {
      submitError.value = 'تعذّر إرسال الطلب، يرجى المحاولة مجدداً.'
      if (import.meta.dev) console.error('[useRequestWizard] submitRequest failed:', err)
      return null
    }
    finally {
      submitting.value = false
    }
  }

  return {
    // State
    currentStep,
    totalSteps,
    saving,
    submitting,
    saveError,
    submitError,
    savedRequestId,
    acknowledged,
    autoFillChip,
    autoSavedForStep3,
    // Form data
    step1,
    step2,
    step3,
    // Errors
    step1Errors,
    step2Errors,
    step3Errors,
    step1ErrorCount,
    step2ErrorCount,
    step3ErrorCount,
    uploadState,
    // Computed
    isDataEntry,
    stepStatuses,
    // Methods
    goToStep,
    nextStep,
    prevStep,
    canAdvance,
    validateStep1,
    validateStep2,
    validateStep3,
    clearStep1Error: (key: keyof WizardStep1Data) => {
      const next = { ...step1Errors.value }
      delete next[key]
      step1Errors.value = next
    },
    clearStep2Error: (key: keyof WizardStep2Data) => {
      const next = { ...step2Errors.value }
      delete next[key]
      step2Errors.value = next
    },
    resetUploadState,
    onArrivalPortChange,
    saveDraft,
    ensureDraftSavedForStep3,
    submitRequest,
    buildPayload,
    DOCUMENT_LABELS,
  }
}
