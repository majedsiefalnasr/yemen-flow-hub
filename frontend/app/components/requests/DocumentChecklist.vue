<script setup lang="ts">
import { ref, computed } from 'vue'
import type { RequestDocument, CustomsDeclarationSummary } from '../../types/models'
import { UserRole, RequestStatus } from '../../types/enums'
import {
  canDownloadDocument,
  canDownloadCustoms,
  canUploadDocument,
  isDocumentModificationLocked,
} from '../../composables/useDocumentPermissions'

const props = defineProps<{
  documents: RequestDocument[]
  customsDeclaration: CustomsDeclarationSummary | null
  userRole: UserRole
  requestStatus: RequestStatus
  loading: boolean
  error: string | null
  uploadingDocument: boolean
  uploadError: string | null
  downloadingIds: Set<number>
  downloadErrors: Record<number, string>
  customsDownloading: boolean
  customsDownloadError: string | null
}>()

const emit = defineEmits<{
  download: [docId: number, filename: string]
  'download-customs': [customsId: number, filename: string]
  upload: [file: File]
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function typeLabel(docType: string | null): string {
  if (docType === 'SWIFT') return 'مستند SWIFT'
  return 'مستند طلب'
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  emit('upload', file)
  // Reset input so the same file can be re-uploaded if needed
  input.value = ''
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

const showUploadButton = computed(() => canUploadDocument(props.userRole, props.requestStatus))

const showLockedNote = computed(
  () =>
    props.userRole === UserRole.DATA_ENTRY
    && isDocumentModificationLocked(props.requestStatus),
)

const hasContent = computed(
  () => props.documents.length > 0 || props.customsDeclaration !== null,
)
</script>

<template>
  <div class="doc-checklist" dir="rtl">
    <!-- Loading state -->
    <div v-if="loading" class="docs-loading" aria-busy="true" aria-label="جارٍ تحميل المستندات">
      <div class="skeleton skeleton--line" />
      <div class="skeleton skeleton--line" />
      <div class="skeleton skeleton--line skeleton--short" />
    </div>

    <!-- Error state -->
    <p v-else-if="error" class="docs-error" role="alert">{{ error }}</p>

    <!-- Empty state -->
    <p v-else-if="!hasContent" class="docs-empty">لا توجد مستندات مرفوعة بعد.</p>

    <!-- Document list -->
    <ul v-else class="docs-list" aria-label="قائمة المستندات">
      <!-- Request and SWIFT documents -->
      <li
        v-for="doc in documents"
        :key="doc.id"
        class="doc-item"
      >
        <div class="doc-info">
          <div class="doc-header">
            <span class="doc-type-label">{{ typeLabel(doc.type) }}</span>
            <span v-if="doc.type === 'SWIFT'" class="doc-badge doc-badge--swift" aria-label="مستند SWIFT">SWIFT</span>
          </div>
          <span class="doc-name">{{ doc.original_filename }}</span>
          <span class="doc-meta">
            {{ formatFileSize(doc.size_bytes) }}
            · {{ formatDate(doc.uploaded_at) }}
            <template v-if="doc.uploaded_by_name"> · {{ doc.uploaded_by_name }}</template>
          </span>
          <span v-if="downloadErrors[doc.id]" class="doc-download-error" role="alert">
            {{ downloadErrors[doc.id] }}
          </span>
        </div>

        <button
          v-if="canDownloadDocument(userRole, doc.type)"
          class="doc-download-btn"
          :disabled="downloadingIds.has(doc.id)"
          :aria-label="`تحميل ${doc.original_filename}`"
          @click="emit('download', doc.id, doc.original_filename)"
        >
          {{ downloadingIds.has(doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
        </button>
      </li>

      <!-- Customs declaration row -->
      <li v-if="customsDeclaration" class="doc-item doc-item--customs">
        <div class="doc-info">
          <div class="doc-header">
            <span class="doc-type-label">بيان جمركي</span>
          </div>
          <span class="doc-name">{{ customsDeclaration.declaration_number }}</span>
          <span class="doc-meta">{{ formatDate(customsDeclaration.issued_at) }}</span>
          <span v-if="customsDownloadError" class="doc-download-error" role="alert">
            {{ customsDownloadError }}
          </span>
        </div>

        <button
          v-if="canDownloadCustoms(userRole)"
          class="doc-download-btn"
          :disabled="customsDownloading"
          aria-label="تحميل البيان الجمركي"
          @click="emit('download-customs', customsDeclaration.id, customsDeclaration.declaration_number)"
        >
          {{ customsDownloading ? 'جارٍ التحميل…' : 'تحميل' }}
        </button>
      </li>
    </ul>

    <!-- Upload section (DATA_ENTRY only) -->
    <div class="doc-upload-section">
      <!-- Upload button for editable requests -->
      <template v-if="showUploadButton">
        <input
          ref="fileInputRef"
          type="file"
          accept="application/pdf"
          class="doc-file-input"
          aria-label="اختر ملف PDF للرفع"
          @change="handleFileChange"
        />
        <button
          class="doc-upload-btn"
          :disabled="uploadingDocument"
          @click="triggerFileInput"
        >
          {{ uploadingDocument ? 'جارٍ الرفع…' : 'رفع مستند' }}
        </button>
        <p v-if="uploadError" class="docs-error docs-error--upload" role="alert">
          {{ uploadError }}
        </p>
      </template>

      <!-- Locked note for DATA_ENTRY on non-editable requests -->
      <p v-else-if="showLockedNote" class="docs-locked-note" role="note">
        🔒 مقفل — لا يمكن تعديل المستندات
      </p>
    </div>
  </div>
</template>

<style scoped>
.doc-checklist {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Loading skeletons */
.docs-loading {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 8px 0;
}

.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
  height: 16px;
}

.skeleton--line { width: 100%; }
.skeleton--short { width: 60%; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* States */
.docs-error {
  color: #ff3b30;
  font-size: 14px;
  margin: 8px 0 0;
}

.docs-empty {
  color: #8e8e93;
  font-size: 14px;
  margin: 8px 0 0;
}

/* Document list */
.docs-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.doc-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.doc-item:last-child {
  border-bottom: none;
}

.doc-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.doc-header {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.doc-type-label {
  font-size: 12px;
  color: #8e8e93;
  font-weight: 500;
}

.doc-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.03em;
}

.doc-badge--swift {
  background: #32ade6;
  color: #ffffff;
}

.doc-name {
  font-size: 14px;
  font-weight: 500;
  color: #1d1d1f;
  word-break: break-all;
}

.doc-meta {
  font-size: 12px;
  color: #8e8e93;
}

.doc-download-error {
  font-size: 12px;
  color: #ff3b30;
}

/* Download button */
.doc-download-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid #d2d2d7;
  background: #ffffff;
  color: #0071e3;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
}

.doc-download-btn:hover:not(:disabled) {
  background: #f0f7ff;
}

.doc-download-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* Upload section */
.doc-upload-section {
  padding-top: 16px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.doc-file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.doc-upload-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid #0071e3;
  background: #ffffff;
  color: #0071e3;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.doc-upload-btn:hover:not(:disabled) {
  background: #0071e3;
  color: #ffffff;
}

.doc-upload-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.docs-error--upload {
  margin: 0;
}

/* Locked note */
.docs-locked-note {
  font-size: 13px;
  color: #8e8e93;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Customs row — slight visual emphasis */
.doc-item--customs .doc-type-label {
  color: #5856d6;
  font-weight: 600;
}
</style>
