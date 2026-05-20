<script setup lang="ts">
import { ref } from 'vue'
import type { WizardStep3Data, WizardUploadState, WizardDocumentKey } from '../../composables/useRequestWizard'

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
  <div class="step-content" dir="rtl">
    <h2 class="section-title">رفع الوثائق المطلوبة</h2>

    <div class="upload-grid">
      <div
        v-for="zone in ZONES"
        :key="zone.key"
        class="upload-zone"
        :class="{
          'upload-zone--drag': dragOver === zone.key,
          'upload-zone--done': getZoneFile(zone.key) && !getFileError(zone.key),
          'upload-zone--error': !!getFileError(zone.key),
        }"
        @dragover="onDragOver(zone.key, $event)"
        @dragleave="onDragLeave"
        @drop="onDrop(zone.key, $event)"
      >
        <!-- Required / optional badge -->
        <span class="zone-badge" :class="zone.required ? 'zone-badge--required' : 'zone-badge--optional'">
          {{ zone.required ? 'إلزامي' : 'اختياري' }}
        </span>

        <!-- Uploaded state -->
        <template v-if="getZoneFile(zone.key) && !getFileError(zone.key)">
          <div class="zone-uploaded">
            <span class="zone-check">✓</span>
            <span class="zone-title zone-title--done">{{ zone.title }}</span>
          </div>
          <div class="file-chip">
            <span class="file-chip__icon">📎</span>
            <span class="file-chip__name">{{ getZoneFile(zone.key)!.name }}</span>
            <span class="file-chip__size">{{ formatBytes(getZoneFile(zone.key)!.size) }}</span>
            <button
              type="button"
              class="file-chip__remove"
              :aria-label="`إزالة ${zone.title}`"
              :disabled="loading"
              @click="removeFile(zone.key)"
            >
              ✗
            </button>
          </div>
        </template>

        <!-- Idle / error state -->
        <template v-else>
          <span class="zone-upload-icon" aria-hidden="true">⬆</span>
          <p class="zone-title">{{ zone.title }}</p>
          <p v-if="getZoneFile(zone.key)" class="zone-selected-file">{{ getZoneFile(zone.key)!.name }}</p>
          <p class="zone-hint">
            {{ zone.required ? 'إلزامي' : 'اختياري' }} — PDF (حد أقصى {{ MAX_SIZE_MB }}MB)
          </p>
          <label class="zone-browse-btn">
            اضغط للرفع
            <input
              type="file"
              class="visually-hidden"
              :accept="ALLOWED_EXTENSIONS.join(',')"
              :disabled="loading"
              @change="onInputChange(zone.key, $event)"
            />
          </label>
        </template>

        <!-- File error -->
        <p v-if="getFileError(zone.key)" class="zone-file-error" role="alert">
          ⚠ {{ getFileError(zone.key) }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.step-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-title {
  font-family: 'Tajawal', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

.upload-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.upload-zone {
  position: relative;
  min-height: 140px;
  padding: 20px 16px;
  border: 2px dashed #cccccc;
  border-radius: 12px;
  background: #fafafa;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
  transition: border-color 150ms, background 150ms;
}

.upload-zone--drag {
  border-color: #0066cc;
  background: #e3f2fd;
}

.upload-zone--done {
  border: 2px solid #1b5e20;
  background: #f1f8f4;
}

.upload-zone--error {
  border: 2px solid #c62828;
  background: #ffebee;
}

/* Badge */
.zone-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  border-radius: 9999px;
  padding: 2px 8px;
}

.zone-badge--required {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

.zone-badge--optional {
  background: #e3f2fd;
  color: #0d47a1;
  border: 1px solid #bbdefb;
}

.zone-upload-icon {
  font-size: 24px;
  color: #6c757d;
}

.upload-zone--drag .zone-upload-icon {
  color: #0066cc;
}

.zone-drop-text {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  line-height: 1.6;
}

.upload-zone--drag .zone-drop-text {
  color: #0066cc;
}

.zone-or {
  font-size: 12px;
}

.zone-browse-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 36px;
  padding: 0 16px;
  border: 1px solid #cccccc;
  border-radius: 16px;
  background: transparent;
  color: #1c222b;
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  cursor: pointer;
  transition: border-color 150ms;
  box-sizing: border-box;
}

.zone-browse-btn:hover {
  border-color: #0066cc;
  color: #0066cc;
}

.zone-title {
  font-family: 'Tajawal', sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.zone-title--done {
  color: #1b5e20;
}

.zone-hint {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  color: #6c757d;
  margin: 0;
}

.zone-selected-file {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  font-weight: 500;
  color: #1c222b;
  margin: 0;
  word-break: break-word;
}

/* Uploaded state */
.zone-uploaded {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
}

.zone-check {
  color: #1b5e20;
  font-size: 18px;
  font-weight: 700;
}

/* File chip */
.file-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #e8f5e9;
  border-radius: 8px;
  padding: 4px 10px;
  max-width: 100%;
}

.file-chip__icon { font-size: 14px; }

.file-chip__name {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  color: #1b5e20;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 120px;
}

.file-chip__size {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 12px;
  color: #6c757d;
  white-space: nowrap;
}

.file-chip__remove {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #c62828;
  font-size: 14px;
  padding: 0 2px;
  line-height: 1;
}

.zone-file-error {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  color: #c62828;
  margin: 4px 0 0;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

@media (max-width: 600px) {
  .upload-grid {
    grid-template-columns: 1fr;
  }
}
</style>
