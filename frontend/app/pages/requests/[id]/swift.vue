<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RequestStatus, UserRole } from '../../../types/enums'
import { useRequestsStore } from '../../../stores/requests.store'
import { useRequests } from '../../../composables/useRequests'
import { STATUS_LABELS } from '../../../constants/workflow'
import StatusBadge from '../../../components/ui/StatusBadge.vue'
import type { RequestDocument } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.SWIFT_OFFICER],
})

const route = useRoute()
const router = useRouter()
const requestsStore = useRequestsStore()
const { uploadSwift } = useRequests()
const auth = useAuthStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const request = computed(() => requestsStore.currentRequest)

const swiftDoc = computed<RequestDocument | undefined>(() =>
  requestsStore.documents.find(d => d.type === 'SWIFT'),
)

const isUploaded = computed(() => !!swiftDoc.value)
const isReadyForUpload = computed(() =>
  request.value?.status === RequestStatus.WAITING_FOR_SWIFT,
)

// Upload state
const selectedFile = ref<File | null>(null)
const fileError = ref<string | null>(null)
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const isDragOver = ref(false)

function validateFile(file: File): boolean {
  if (file.type !== 'application/pdf') {
    fileError.value = 'يُقبل ملف PDF فقط. الرجاء اختيار ملف بصيغة PDF.'
    selectedFile.value = null
    return false
  }
  fileError.value = null
  return true
}

function onFileChange(event: Event) {
  uploadError.value = null
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  if (!file) { selectedFile.value = null; return }
  if (validateFile(file)) selectedFile.value = file
}

function onDragOver(event: DragEvent) {
  event.preventDefault()
  isDragOver.value = true
}

function onDragLeave() {
  isDragOver.value = false
}

function onDrop(event: DragEvent) {
  event.preventDefault()
  isDragOver.value = false
  uploadError.value = null
  const file = event.dataTransfer?.files?.[0] ?? null
  if (!file) return
  if (validateFile(file)) selectedFile.value = file
}

async function submitUpload() {
  if (!selectedFile.value) return

  uploading.value = true
  uploadError.value = null

  try {
    await uploadSwift(id, selectedFile.value)
    await requestsStore.fetchRequest(id)
    await requestsStore.loadDocuments(id)
    router.push(`/requests/${id}`)
  }
  catch (err: unknown) {
    const apiErr = err as { data?: { message?: string } }
    uploadError.value = apiErr?.data?.message ?? 'حدث خطأ أثناء الرفع. يرجى المحاولة مرة أخرى.'
  }
  finally {
    uploading.value = false
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ar-YE', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(async () => {
  await requestsStore.fetchRequest(id)
  await requestsStore.loadDocuments(id)
})
</script>

<template>
  <div class="swift-page" dir="rtl">

    <!-- Back link -->
    <button class="btn-back" @click="router.back()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
      العودة
    </button>

    <div v-if="!request" class="loading-state" role="status" aria-label="جارٍ تحميل">
      <div class="loading-spinner" />
    </div>

    <template v-else>
      <!-- Request summary (read-only) -->
      <div class="summary-card">
        <div class="summary-header">
          <h1 class="summary-ref">{{ request.reference_number }}</h1>
          <StatusBadge :status="request.status" :role="auth.user?.role" />
        </div>
        <dl class="summary-grid">
          <div class="summary-item">
            <dt class="summary-label">البنك</dt>
            <dd class="summary-value">{{ request.bank_name ?? '—' }}</dd>
          </div>
          <div class="summary-item">
            <dt class="summary-label">المبلغ</dt>
            <dd class="summary-value summary-value--mono">{{ formatAmount(request.amount, request.currency) }}</dd>
          </div>
          <div class="summary-item">
            <dt class="summary-label">المورد</dt>
            <dd class="summary-value">{{ request.supplier_name }}</dd>
          </div>
          <div class="summary-item">
            <dt class="summary-label">ميناء الدخول</dt>
            <dd class="summary-value">{{ request.port_of_entry }}</dd>
          </div>
        </dl>
      </div>

      <!-- Uploaded state: show doc metadata + immutability warning -->
      <div v-if="isUploaded && swiftDoc" class="uploaded-state">
        <div class="immutability-banner" role="alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          وثيقة SWIFT لا يمكن استبدالها أو حذفها بعد الرفع
        </div>

        <div class="doc-card">
          <div class="doc-icon" aria-hidden="true">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#32ade6" stroke-width="1.5">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
          <div class="doc-meta">
            <p class="doc-filename">{{ swiftDoc.original_filename }}</p>
            <p class="doc-details">
              {{ formatFileSize(swiftDoc.size_bytes) }} ·
              تم الرفع بواسطة {{ swiftDoc.uploaded_by_name ?? '—' }} ·
              {{ formatDate(swiftDoc.uploaded_at) }}
            </p>
          </div>
          <a
            :href="swiftDoc.download_url"
            class="btn-download"
            target="_blank"
            rel="noopener noreferrer"
          >تحميل</a>
        </div>
      </div>

      <!-- Upload state: drop zone for WAITING_FOR_SWIFT -->
      <div v-else-if="isReadyForUpload" class="upload-state">
        <h2 class="section-title">رفع وثيقة SWIFT</h2>
        <p class="section-hint">يُقبل ملف PDF فقط (الحد الأقصى: 10 ميجابايت)</p>

        <!-- File input area with full drag-and-drop support -->
        <label
          class="drop-zone"
          :class="{
            'drop-zone--has-file': !!selectedFile,
            'drop-zone--error': !!fileError,
            'drop-zone--drag-over': isDragOver,
          }"
          @dragover="onDragOver"
          @dragleave="onDragLeave"
          @drop="onDrop"
        >
          <input
            type="file"
            accept="application/pdf"
            class="drop-zone__input"
            aria-label="اختر ملف PDF للرفع"
            @change="onFileChange"
          >
          <span v-if="!selectedFile" class="drop-zone__prompt">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#32ade6" stroke-width="1.5" aria-hidden="true">
              <polyline points="16 16 12 12 8 16" /><line x1="12" y1="12" x2="12" y2="21" />
              <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
            </svg>
            <span>انقر لاختيار ملف PDF أو اسحب الملف هنا</span>
          </span>
          <span v-else class="drop-zone__selected">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" aria-hidden="true">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
          </span>
        </label>

        <!-- Validation error -->
        <p v-if="fileError" class="field-error" role="alert">{{ fileError }}</p>

        <!-- Upload error -->
        <div v-if="uploadError" class="upload-error" role="alert">{{ uploadError }}</div>

        <!-- Submit button with progress indicator -->
        <button
          class="btn-submit"
          :disabled="!selectedFile || uploading"
          @click="submitUpload"
        >
          <span v-if="uploading" class="btn-spinner" aria-hidden="true" />
          <span>{{ uploading ? 'جارٍ الرفع...' : 'رفع الوثيقة' }}</span>
        </button>
      </div>

      <!-- Wrong status state: show Arabic label, not raw enum -->
      <div v-else class="wrong-status-card" role="status">
        <p>هذا الطلب لا يقبل رفع SWIFT في حالته الحالية ({{ STATUS_LABELS[request.status as RequestStatus] ?? request.status }}).</p>
        <button class="btn-back-link" @click="router.push(`/requests/${id}`)">العودة إلى تفاصيل الطلب</button>
      </div>
    </template>

  </div>
</template>

<style scoped>
.swift-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 800px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: #0071e3;
  font-size: 14px;
  cursor: pointer;
  padding: 0;
}

.btn-back:hover { text-decoration: underline; }

/* Loading */
.loading-state {
  display: flex;
  justify-content: center;
  padding: 40px;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #d2d2d7;
  border-top-color: #0071e3;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Summary card */
.summary-card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.summary-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.summary-ref {
  font-family: monospace;
  font-size: 18px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin: 0;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-label {
  font-size: 12px;
  color: #6e6e73;
}

.summary-value {
  font-size: 14px;
  color: #1d1d1f;
  font-weight: 500;
}

.summary-value--mono { font-family: monospace; }

/* Immutability warning */
.immutability-banner {
  background: #fff8e6;
  border: 1px solid #ff9f0a55;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #7a4500;
  font-size: 14px;
}

/* Uploaded doc card */
.doc-card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.doc-icon { flex-shrink: 0; }

.doc-meta {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.doc-filename {
  font-size: 15px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0;
}

.doc-details {
  font-size: 13px;
  color: #6e6e73;
  margin: 0;
}

.btn-download {
  padding: 8px 18px;
  background: #0071e3;
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-size: 14px;
  text-decoration: none;
  min-height: 36px;
  display: inline-flex;
  align-items: center;
}

.btn-download:hover { background: #005bbf; }

/* Upload state */
.section-title {
  font-size: 16px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0;
}

.section-hint {
  font-size: 13px;
  color: #6e6e73;
  margin: 0;
}

.upload-state {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.drop-zone {
  border: 2px dashed #d2d2d7;
  border-radius: 12px;
  padding: 40px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: border-color 0.15s, background-color 0.15s;
  position: relative;
}

.drop-zone:hover { border-color: #32ade6; }
.drop-zone--has-file { border-color: #34c759; background: #f0fff4; }
.drop-zone--error { border-color: #ff3b30; }
.drop-zone--drag-over { border-color: #32ade6; background: #f0faff; }

.drop-zone__input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}

.drop-zone__prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6e6e73;
  text-align: center;
}

.drop-zone__selected {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #34c759;
  font-weight: 500;
}

.field-error {
  font-size: 13px;
  color: #ff3b30;
  margin: 0;
}

.upload-error {
  background: #fff0f0;
  border: 1px solid #ff3b3033;
  border-radius: 8px;
  padding: 12px 16px;
  color: #ff3b30;
  font-size: 14px;
}

.btn-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 24px;
  background: #32ade6;
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  min-height: 44px;
  align-self: flex-start;
}

.btn-submit:hover:not(:disabled) { background: #28a0d8; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* Wrong status */
.wrong-status-card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 24px;
  color: #6e6e73;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-back-link {
  background: none;
  border: none;
  color: #0071e3;
  font-size: 14px;
  cursor: pointer;
  padding: 0;
  text-align: right;
}

.btn-back-link:hover { text-decoration: underline; }

.uploaded-state {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
