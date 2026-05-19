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

// ── Stage → required/optional document types ──────────────────────────────────

type DocRequirement = { type: string; label: string; required: boolean }

const STAGE_DOCS: Record<string, DocRequirement[]> = {
  [RequestStatus.DRAFT]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.SUBMITTED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.BANK_REVIEW]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.BANK_APPROVED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_PENDING]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_APPROVED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REJECTED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.WAITING_FOR_SWIFT]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SWIFT_UPLOADED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
}

// All voting+ states share the same doc set
const VOTING_AND_BEYOND_DOCS: DocRequirement[] = [
  { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
  { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  { type: 'SWIFT', label: 'مستند SWIFT', required: true },
]

const VOTING_AND_BEYOND = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])

function getStageDocs(status: RequestStatus): DocRequirement[] {
  if (VOTING_AND_BEYOND.has(status)) return VOTING_AND_BEYOND_DOCS
  return STAGE_DOCS[status] ?? []
}

function uploadedAtMs(doc: RequestDocument): number {
  const ts = Date.parse(doc.uploaded_at ?? '')
  return Number.isNaN(ts) ? 0 : ts
}

// ── Checklist row type ────────────────────────────────────────────────────────

type ChecklistRow =
  | { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
  | { kind: 'extra'; doc: RequestDocument }
  | { kind: 'customs'; customs: CustomsDeclarationSummary }

const checklist = computed((): ChecklistRow[] => {
  const stageDocs = getStageDocs(props.requestStatus)
  const rows: ChecklistRow[] = []

  // Build a lookup: type → first matching uploaded doc
  const uploadedByType = new Map<string, RequestDocument>()
  const extraDocs: RequestDocument[] = []

  for (const doc of props.documents) {
    const t = doc.type ?? 'REQUEST_DOC'
    const isStagedType = stageDocs.some(r => r.type === t)
    if (!isStagedType) {
      extraDocs.push(doc)
      continue
    }

    const existing = uploadedByType.get(t)
    if (!existing) {
      uploadedByType.set(t, doc)
      continue
    }

    if (uploadedAtMs(doc) >= uploadedAtMs(existing)) {
      extraDocs.push(existing)
      uploadedByType.set(t, doc)
    }
    else {
      extraDocs.push(doc)
    }
  }

  // Staged requirement rows
  for (const req of stageDocs) {
    rows.push({ kind: 'staged', requirement: req, doc: uploadedByType.get(req.type) ?? null })
  }

  // Extra uploaded docs not matching any requirement
  for (const doc of extraDocs) {
    rows.push({ kind: 'extra', doc })
  }

  // Customs declaration row
  if (props.customsDeclaration) {
    rows.push({ kind: 'customs', customs: props.customsDeclaration })
  }

  return rows
})

const hasContent = computed(() => checklist.value.length > 0)

// ── Upload state ──────────────────────────────────────────────────────────────

const fileInputRef = ref<HTMLInputElement | null>(null)
const fileTypeError = ref<string | null>(null)

const showUploadButton = computed(() => canUploadDocument(props.userRole, props.requestStatus))

const showLockedNote = computed(
  () => props.userRole === UserRole.DATA_ENTRY && isDocumentModificationLocked(props.requestStatus),
)

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (file.type !== 'application/pdf') {
    fileTypeError.value = 'يُقبل ملف PDF فقط. الرجاء اختيار ملف بصيغة PDF.'
    input.value = ''
    return
  }

  fileTypeError.value = null
  emit('upload', file)
  input.value = ''
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
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
    <p v-else-if="!hasContent" class="docs-empty">لا توجد مستندات بعد.</p>

    <!-- Checklist -->
    <ul v-else class="docs-list" aria-label="قائمة المستندات">

      <!-- Staged requirement rows -->
      <template v-for="(row, idx) in checklist" :key="idx">

        <!-- Staged requirement with optional upload -->
        <li v-if="row.kind === 'staged'" class="doc-item">
          <div class="doc-info">
            <div class="doc-header">
              <span class="doc-type-label">{{ row.requirement.label }}</span>
              <span
                class="doc-badge"
                :class="row.requirement.required ? 'doc-badge--required' : 'doc-badge--optional'"
              >
                {{ row.requirement.required ? 'مطلوب' : 'اختياري' }}
              </span>
            </div>

            <template v-if="row.doc">
              <!-- Uploaded -->
              <span class="doc-name">{{ row.doc.original_filename }}</span>
              <span class="doc-meta">
                {{ formatFileSize(row.doc.size_bytes) }}
                · {{ formatDate(row.doc.uploaded_at) }}
                <template v-if="row.doc.uploaded_by_name"> · {{ row.doc.uploaded_by_name }}</template>
              </span>
              <span v-if="downloadErrors[row.doc.id]" class="doc-download-error" role="alert">
                {{ downloadErrors[row.doc.id] }}
              </span>
            </template>
            <template v-else>
              <!-- Not uploaded yet -->
              <span class="doc-not-uploaded">—</span>
            </template>
          </div>

          <div class="doc-item__actions">
            <!-- Upload status badge -->
            <span
              class="upload-status-badge"
              :class="
                row.doc
                  ? 'upload-status-badge--uploaded'
                  : row.requirement.required
                    ? 'upload-status-badge--required'
                    : 'upload-status-badge--optional'
              "
            >
              {{ row.doc ? 'مرفوع' : row.requirement.required ? 'مطلوب' : 'غير مطلوب' }}
            </span>

            <!-- Download button for uploaded doc -->
            <button
              v-if="row.doc && canDownloadDocument(userRole, row.doc.type)"
              class="doc-download-btn"
              :disabled="downloadingIds.has(row.doc.id)"
              :aria-label="`تحميل ${row.doc.original_filename}`"
              @click="emit('download', row.doc.id, row.doc.original_filename)"
            >
              {{ downloadingIds.has(row.doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
            </button>
          </div>
        </li>

        <!-- Extra uploaded docs not in stage requirements -->
        <li v-else-if="row.kind === 'extra'" class="doc-item">
          <div class="doc-info">
            <div class="doc-header">
              <span class="doc-type-label">{{ row.doc.type === 'SWIFT' ? 'مستند SWIFT' : 'مستند طلب' }}</span>
              <span v-if="row.doc.type === 'SWIFT'" class="doc-badge doc-badge--swift" aria-label="مستند SWIFT">SWIFT</span>
            </div>
            <span class="doc-name">{{ row.doc.original_filename }}</span>
            <span class="doc-meta">
              {{ formatFileSize(row.doc.size_bytes) }}
              · {{ formatDate(row.doc.uploaded_at) }}
              <template v-if="row.doc.uploaded_by_name"> · {{ row.doc.uploaded_by_name }}</template>
            </span>
            <span v-if="downloadErrors[row.doc.id]" class="doc-download-error" role="alert">
              {{ downloadErrors[row.doc.id] }}
            </span>
          </div>

          <div class="doc-item__actions">
            <span class="upload-status-badge upload-status-badge--uploaded">مرفوع</span>
            <button
              v-if="canDownloadDocument(userRole, row.doc.type)"
              class="doc-download-btn"
              :disabled="downloadingIds.has(row.doc.id)"
              :aria-label="`تحميل ${row.doc.original_filename}`"
              @click="emit('download', row.doc.id, row.doc.original_filename)"
            >
              {{ downloadingIds.has(row.doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
            </button>
          </div>
        </li>

        <!-- Customs declaration row -->
        <li v-else-if="row.kind === 'customs'" class="doc-item doc-item--customs">
          <div class="doc-info">
            <div class="doc-header">
              <span class="doc-type-label">بيان جمركي</span>
            </div>
            <span class="doc-name">{{ row.customs.declaration_number }}</span>
            <span class="doc-meta">{{ formatDate(row.customs.issued_at) }}</span>
            <span v-if="customsDownloadError" class="doc-download-error" role="alert">
              {{ customsDownloadError }}
            </span>
          </div>

          <div class="doc-item__actions">
            <span class="upload-status-badge upload-status-badge--uploaded">مرفوع</span>
            <button
              v-if="canDownloadCustoms(userRole)"
              class="doc-download-btn"
              :disabled="customsDownloading"
              aria-label="تحميل البيان الجمركي"
              @click="emit('download-customs', row.customs.id, row.customs.declaration_number)"
            >
              {{ customsDownloading ? 'جارٍ التحميل…' : 'تحميل' }}
            </button>
          </div>
        </li>

      </template>
    </ul>

    <!-- Upload section (DATA_ENTRY only) -->
    <div v-if="userRole === UserRole.DATA_ENTRY" class="doc-upload-section">
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
          :disabled="uploadingDocument || loading"
          @click="triggerFileInput"
        >
          {{ uploadingDocument ? 'جارٍ الرفع…' : 'رفع مستند' }}
        </button>
        <p v-if="fileTypeError" class="docs-error docs-error--upload" role="alert">
          {{ fileTypeError }}
        </p>
        <p v-else-if="uploadError" class="docs-error docs-error--upload" role="alert">
          {{ uploadError }}
        </p>
      </template>

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

.doc-item:last-child { border-bottom: none; }

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

.doc-badge--required { background: #fff0f0; color: #c62828; }
.doc-badge--optional { background: #f5f5f7; color: #6e6e73; }
.doc-badge--swift { background: #32ade6; color: #ffffff; }

.doc-name {
  font-size: 14px;
  font-weight: 500;
  color: #1d1d1f;
  word-break: break-all;
}

.doc-not-uploaded {
  font-size: 14px;
  color: #8e8e93;
}

.doc-meta {
  font-size: 12px;
  color: #8e8e93;
}

.doc-download-error {
  font-size: 12px;
  color: #ff3b30;
}

/* Actions column */
.doc-item__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

/* Upload status badge */
.upload-status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.upload-status-badge--uploaded { background: #e8f5e9; color: #1b5e20; }
.upload-status-badge--required { background: #fff0f0; color: #c62828; }
.upload-status-badge--optional { background: #f5f5f7; color: #6e6e73; }

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

.doc-download-btn:hover:not(:disabled) { background: #f0f7ff; }
.doc-download-btn:disabled { opacity: 0.55; cursor: not-allowed; }

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

.doc-upload-btn:hover:not(:disabled) { background: #0071e3; color: #ffffff; }
.doc-upload-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.docs-error--upload { margin: 0; }

.docs-locked-note {
  font-size: 13px;
  color: #8e8e93;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>
