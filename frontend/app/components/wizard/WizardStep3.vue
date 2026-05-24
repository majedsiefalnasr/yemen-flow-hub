<script setup lang="ts">
import { ref } from 'vue'
import type { WizardStep3Data, WizardUploadState, WizardDocumentKey } from '../../composables/useRequestWizard'
import { Button } from '../ui/button'
import { AlertTriangle, CheckCircle2, Upload, X } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: WizardStep3Data
  errors: Partial<Record<WizardDocumentKey, string>>
  uploadState: WizardUploadState
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WizardStep3Data]
  'file-reset': [key: WizardDocumentKey]
}>()

interface DocumentZone {
  key: WizardDocumentKey
  title: string
  required: boolean
}

const ZONES: DocumentZone[] = [
  { key: 'proforma_invoice', title: 'الفاتورة الأولية (Proforma Invoice)', required: true },
  { key: 'commercial_register', title: 'السجل التجاري', required: true },
  { key: 'tax_card', title: 'البطاقة الضريبية', required: true },
  { key: 'extra_docs', title: 'مستندات إضافية', required: false },
]

const MAX_SIZE_MB = 10
const ALLOWED_TYPES = ['application/pdf']
const ALLOWED_EXTENSIONS = ['.pdf']

const dragOver = ref<WizardDocumentKey | null>(null)
const fileErrors = ref<Partial<Record<WizardDocumentKey, string>>>({})

function formatBytes(bytes: number): string {
  const mb = bytes / (1024 * 1024)
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`
}

function validateFile(file: File): string | null {
  const normalizedName = file.name.toLowerCase()
  const hasAllowedExtension = ALLOWED_EXTENSIONS.some(extension => normalizedName.endsWith(extension))
  if (!ALLOWED_TYPES.includes(file.type) && !hasAllowedExtension) {
    return 'يجب أن يكون الملف بصيغة PDF فقط'
  }
  if (file.size > MAX_SIZE_MB * 1024 * 1024) {
    return `حجم الملف يتجاوز الحد الأقصى (${MAX_SIZE_MB}MB)`
  }
  return null
}

function handleFileSelect(key: WizardDocumentKey, file: File | null): void {
  if (!file) return
  const err = validateFile(file)
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
  const file = input.files?.[0] ?? null
  handleFileSelect(key, file)
  input.value = ''
}

function onDrop(key: WizardDocumentKey, event: DragEvent): void {
  dragOver.value = null
  event.preventDefault()
  const file = event.dataTransfer?.files?.[0] ?? null
  handleFileSelect(key, file)
}

function onDragOver(key: WizardDocumentKey, event: DragEvent): void {
  event.preventDefault()
  dragOver.value = key
}

function onDragLeave(): void {
  dragOver.value = null
}

function removeFile(key: WizardDocumentKey): void {
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
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">
    <h2 class="text-2xl font-bold">رفع الوثائق المطلوبة</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <div
        v-for="zone in ZONES"
        :key="zone.key"
        class="relative min-h-36 p-5 border-2 border-dashed rounded-lg transition-colors"
        :class="{
          'border-primary bg-primary/10': dragOver === zone.key,
          'border-green-600 bg-success/10': getZoneFile(zone.key) && !getFileError(zone.key),
          'border-destructive bg-destructive/10': getFileError(zone.key),
          'border-border bg-muted': !dragOver && !getZoneFile(zone.key),
        }"
        @dragover="onDragOver(zone.key, $event)"
        @dragleave="onDragLeave"
        @drop="onDrop(zone.key, $event)"
      >
        <!-- Badge -->
        <span
          class="absolute top-2 start-2 text-xs font-normal rounded-full px-2 py-1"
          :class="zone.required ? 'bg-destructive/10 text-destructive border border-destructive' : 'bg-primary/10 text-primary border border-border'"
        >
          {{ zone.required ? 'إلزامي' : 'اختياري' }}
        </span>

        <!-- Uploaded state -->
        <template v-if="getZoneFile(zone.key) && !getFileError(zone.key)">
          <div class="flex flex-col items-center justify-center h-full gap-2">
            <div class="flex items-center gap-2">
              <CheckCircle2 class="h-5 w-5 text-success" />
              <p class="font-semibold text-success">{{ zone.title }}</p>
            </div>
            <div class="flex items-center gap-2 bg-success/10 rounded px-3 py-1">
              <span class="text-sm text-success">{{ getZoneFile(zone.key)!.name }}</span>
              <span class="text-xs text-muted-foreground">{{ formatBytes(getZoneFile(zone.key)!.size) }}</span>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-5 w-5 p-0 text-destructive hover:text-destructive"
                :aria-label="`إزالة ${zone.title}`"
                :disabled="loading"
                @click="removeFile(zone.key)"
              >
                <X class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </template>

        <!-- Idle / error state -->
        <template v-else>
          <div class="flex flex-col items-center justify-center h-full gap-3">
            <Upload class="h-6 w-6 text-muted-foreground" :class="{ 'text-primary': dragOver === zone.key }" />
            <p class="font-semibold text-foreground">{{ zone.title }}</p>
            <p v-if="getZoneFile(zone.key)" class="text-sm text-foreground break-words">{{ getZoneFile(zone.key)!.name }}</p>
            <p class="text-xs text-muted-foreground">{{ zone.required ? 'إلزامي' : 'اختياري' }} — PDF ({{ MAX_SIZE_MB }}MB)</p>
            <label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                as-child
              >
                <span>اضغط للرفع</span>
              </Button>
              <input
                type="file"
                class="sr-only"
                :accept="ALLOWED_EXTENSIONS.join(',')"
                :disabled="loading"
                @change="onInputChange(zone.key, $event)"
              />
            </label>
          </div>
        </template>

        <!-- File error -->
        <p v-if="getFileError(zone.key)" class="absolute bottom-2 start-2 end-2 text-xs text-destructive flex items-center gap-1" role="alert">
          <AlertTriangle class="h-3 w-3" />
          {{ getFileError(zone.key) }}
        </p>
      </div>
    </div>
  </div>
</template>

