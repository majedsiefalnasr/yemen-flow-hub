<script setup lang="ts">
import { ref } from 'vue'
import { useRequestWizard } from '../../composables/useRequestWizard'
import type { WizardStep3Data, WizardUploadState, WizardDocumentKey } from '../../composables/useRequestWizard'
import { Button } from '../ui/button'
import { Card, CardContent } from '../ui/card'
import { Badge } from '../ui/badge'
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
} from '../ui/field'
import { AlertTriangle, CheckCircle2, FileDown, FileText, Upload, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { useRequests } from '../../composables/useRequests'

const props = defineProps<{
  modelValue: WizardStep3Data
  errors: Partial<Record<WizardDocumentKey, string>>
  uploadState: WizardUploadState
  loading?: boolean
  requestId?: number | null
  templateReady?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WizardStep3Data]
  'file-reset': [key: WizardDocumentKey]
}>()

interface DocumentZone {
  key: WizardDocumentKey
  title: string
  description: string
  required: boolean
}

const ZONES: DocumentZone[] = [
  { key: 'confirmation_request', title: 'طلب وثيقة التأكيد (مختوم)', description: 'حمّل النموذج أدناه، اطبعه، اختمه بختم البنك ثم ارفعه هنا', required: true },
  { key: 'proforma_invoice', title: 'الفاتورة الأولية (Proforma Invoice)', description: 'الفاتورة الأولية من المورد الخارجي', required: true },
  { key: 'commercial_register', title: 'السجل التجاري', description: 'نسخة سارية من السجل التجاري للمستورد', required: true },
  { key: 'tax_card', title: 'البطاقة الضريبية', description: 'نسخة سارية من البطاقة الضريبية للمستورد', required: true },
  { key: 'extra_docs', title: 'مستندات إضافية', description: 'أي مستندات داعمة أخرى (اختياري)', required: false },
]

const ALLOWED_EXTENSIONS = ['.pdf']

const dragOver = ref<WizardDocumentKey | null>(null)
const fileErrors = ref<Partial<Record<WizardDocumentKey, string>>>({})
const { downloadConfirmationRequestTemplate } = useRequests()
const { validateUploadFile } = useRequestWizard()

function formatBytes(bytes: number): string {
  const mb = bytes / (1024 * 1024)
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`
}

function handleFileSelect(key: WizardDocumentKey, file: File | null): void {
  if (props.loading) return
  if (!file) return
  const err = validateUploadFile(file)
  emit('file-reset', key)
  if (err) {
    fileErrors.value = { ...fileErrors.value, [key]: err }
    return
  }
  fileErrors.value = { ...fileErrors.value, [key]: undefined }
  emit('update:modelValue', { ...props.modelValue, [key]: file })
}

function onInputChange(key: WizardDocumentKey, event: Event): void {
  const input = event.target as HTMLInputElement
  handleFileSelect(key, input.files?.[0] ?? null)
  input.value = ''
}

function onDrop(key: WizardDocumentKey, event: DragEvent): void {
  dragOver.value = null
  event.preventDefault()
  if (props.loading) return
  handleFileSelect(key, event.dataTransfer?.files?.[0] ?? null)
}

function onDragOver(key: WizardDocumentKey, event: DragEvent): void {
  event.preventDefault()
  if (props.loading) return
  dragOver.value = key
}

function onDragLeave(): void {
  dragOver.value = null
}

function removeFile(key: WizardDocumentKey): void {
  if (props.loading) return
  fileErrors.value = { ...fileErrors.value, [key]: undefined }
  emit('file-reset', key)
  emit('update:modelValue', { ...props.modelValue, [key]: null })
}

function getZoneFile(key: WizardDocumentKey): File | null {
  return props.modelValue[key] ?? null
}

function getFileError(key: WizardDocumentKey): string | null {
  return fileErrors.value[key]
    ?? props.errors[key]
    ?? (props.uploadState[key] === 'error' ? 'تعذّر رفع الملف، يرجى إعادة المحاولة.' : null)
}

async function downloadTemplate(): Promise<void> {
  if (!props.requestId) return
  try {
    const blob = await downloadConfirmationRequestTemplate(props.requestId)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `confirmation-request-${props.requestId}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  catch {
    toast.error('تعذر تحميل النموذج. أعد المحاولة.')
  }
}

function triggerInput(key: WizardDocumentKey): void {
  if (props.loading) return
  document.getElementById(`file-input-${key}`)?.click()
}
</script>

<template>
  <div class="flex flex-col gap-0">
    <!-- Template download banner -->
    <Card class="mb-6 border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 shadow-sm p-0" role="note">
      <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
        <FileDown class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)] mt-0.5" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-foreground text-sm">نموذج طلب وثيقة التأكيد: مطلوب قبل الإرسال</p>
          <p class="text-xs text-muted-foreground mt-1">
            حمّل النموذج المعبأ بالبيانات، اطبعه واختمه بختم البنك، ثم ارفعه في الحقل المخصص أدناه.
          </p>
        </div>
        <Button
          size="sm"
          variant="outline"
          class="flex-shrink-0"
          :disabled="!templateReady || !requestId"
          :aria-busy="!templateReady"
          @click="downloadTemplate"
        >
          <FileDown class="h-4 w-4 me-1" />
          <span v-if="!templateReady">جارٍ التحضير...</span>
          <span v-else>تحميل النموذج</span>
        </Button>
      </CardContent>
    </Card>

    <FieldGroup>
      <template v-for="(zone, idx) in ZONES" :key="zone.key">
        <FieldSet>
          <div class="flex items-center gap-2 mb-1">
            <FieldLegend class="mb-0">{{ zone.title }}</FieldLegend>
            <Badge
              :variant="zone.required ? 'destructive' : 'secondary'"
              class="text-xs rounded-full"
            >
              {{ zone.required ? 'إلزامي' : 'اختياري' }}
            </Badge>
          </div>
          <FieldDescription>{{ zone.description }}</FieldDescription>

          <FieldGroup>
            <Field>
              <!-- Hidden file input -->
              <input
                :id="`file-input-${zone.key}`"
                type="file"
                class="sr-only"
                :accept="ALLOWED_EXTENSIONS.join(',')"
                :disabled="loading"
                @change="onInputChange(zone.key, $event)"
              />

              <!-- Drop zone -->
              <div
                class="relative rounded-xl border-2 border-dashed transition-all duration-150 cursor-pointer select-none"
                :class="{
                  'border-primary bg-primary/5': dragOver === zone.key,
                  'border-[var(--severity-green)] bg-[var(--severity-green)]/5': getZoneFile(zone.key) && !getFileError(zone.key),
                  'border-destructive bg-destructive/5': getFileError(zone.key),
                  'border-border bg-muted/30 hover:border-primary/50 hover:bg-primary/5': !loading && !dragOver && !getZoneFile(zone.key) && !getFileError(zone.key),
                  'border-border bg-muted/30 opacity-60': loading && !dragOver && !getZoneFile(zone.key) && !getFileError(zone.key),
                }"
                role="button"
                :aria-label="`رفع ${zone.title}`"
                :aria-disabled="loading"
                tabindex="0"
                @dragover="onDragOver(zone.key, $event)"
                @dragleave="onDragLeave"
                @drop="onDrop(zone.key, $event)"
                @click="triggerInput(zone.key)"
                @keydown.enter.prevent="triggerInput(zone.key)"
                @keydown.space.prevent="triggerInput(zone.key)"
              >
                <div class="flex flex-col items-center justify-center gap-3 py-8 px-4 text-center">
                  <!-- Upload icon -->
                  <div
                    class="h-12 w-12 rounded-full flex items-center justify-center transition-colors"
                    :class="dragOver === zone.key ? 'bg-primary/15' : 'bg-muted'"
                  >
                    <Upload
                      class="h-5 w-5 transition-colors"
                      :class="dragOver === zone.key ? 'text-primary' : 'text-muted-foreground'"
                      aria-hidden="true"
                    />
                  </div>
                  <div>
                    <p class="text-sm font-medium text-foreground">
                      اسحب وأفلت الملف هنا
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      أو <span class="text-primary font-medium">اضغط للاختيار</span>، PDF فقط، حتى {{ MAX_SIZE_MB }}MB
                    </p>
                  </div>
                </div>
              </div>

              <!-- File attached row -->
              <div
                v-if="getZoneFile(zone.key) && !getFileError(zone.key)"
                class="mt-2 flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3"
              >
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-muted">
                  <FileText class="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="truncate text-sm font-medium text-foreground">{{ getZoneFile(zone.key)!.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ formatBytes(getZoneFile(zone.key)!.size) }}</p>
                </div>
                <CheckCircle2 class="h-4 w-4 flex-shrink-0 text-[var(--severity-green)]" aria-hidden="true" />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-7 w-7 flex-shrink-0 text-muted-foreground hover:text-destructive"
                  :aria-label="`إزالة ${zone.title}`"
                  :disabled="loading"
                  @click.stop="removeFile(zone.key)"
                >
                  <X class="h-4 w-4" />
                </Button>
              </div>

              <!-- Upload progress indicator -->
              <div
                v-else-if="uploadState[zone.key] === 'uploading'"
                class="mt-2 flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3"
              >
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-muted">
                  <FileText class="h-5 w-5 text-muted-foreground animate-pulse" aria-hidden="true" />
                </div>
                <p class="flex-1 text-sm text-muted-foreground">جارٍ الرفع...</p>
              </div>

              <FieldError v-if="getFileError(zone.key)" class="mt-1">
                <AlertTriangle class="inline h-3 w-3 me-1" aria-hidden="true" />
                {{ getFileError(zone.key) }}
              </FieldError>
            </Field>
          </FieldGroup>
        </FieldSet>

        <FieldSeparator v-if="idx < ZONES.length - 1" />
      </template>
    </FieldGroup>
  </div>
</template>
