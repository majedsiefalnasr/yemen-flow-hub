<script setup lang="ts">
import { ref, computed } from 'vue'
import { AlertCircle } from 'lucide-vue-next'
import type { RequestDocument, CustomsDeclarationSummary } from '../../types/models'
import { UserRole, RequestStatus } from '../../types/enums'
import {
  canDownloadDocument,
  canDownloadCustoms,
  canUploadDocument,
  isDocumentModificationLocked,
} from '../../composables/useDocumentPermissions'
import { Button } from '../ui/button'
import { Badge } from '../ui/badge'
import { Alert, AlertDescription } from '../ui/alert'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '../ui/tooltip'

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
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_PENDING]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
  [RequestStatus.SUPPORT_APPROVED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
  [RequestStatus.SUPPORT_REJECTED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.WAITING_FOR_SWIFT]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
  [RequestStatus.SWIFT_UPLOADED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
    { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
  ],
}

// All voting+ states share the same doc set
const VOTING_AND_BEYOND_DOCS: DocRequirement[] = [
  { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
  { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  { type: 'FX_REQUEST', label: 'مستند طلب المصارفة الخارجية', required: true },
]

const VOTING_AND_BEYOND = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
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

const missingRequiredCount = computed(
  () =>
    checklist.value.filter(
      r => r.kind === 'staged' && r.requirement.required && !r.doc,
    ).length,
)

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
  <div class="flex flex-col gap-0" >
    <!-- Loading state -->
    <div v-if="loading" class="flex flex-col gap-3 py-2" aria-busy="true" aria-label="جارٍ تحميل المستندات">
      <div class="w-full h-4 bg-gradient-to-r from-muted via-border to-muted rounded animate-pulse" />
      <div class="w-full h-4 bg-gradient-to-r from-muted via-border to-muted rounded animate-pulse" />
      <div class="w-3/5 h-4 bg-gradient-to-r from-muted via-border to-muted rounded animate-pulse" />
    </div>

    <!-- Error state -->
    <Alert v-else-if="error" class="border-s-4 border-s-[var(--severity-red)] bg-[var(--severity-red)]/10 border-0">
      <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
      <AlertDescription class="text-[var(--severity-red)] text-sm">{{ error }}</AlertDescription>
    </Alert>

    <!-- Empty state -->
    <p v-else-if="!hasContent" class="text-sm text-muted-foreground mt-2">لا توجد مستندات بعد.</p>

    <!-- Checklist summary badge -->
    <template v-else>
      <div class="flex items-center justify-between gap-2 mb-2.5">
        <span class="text-xs text-muted-foreground">قائمة المستندات</span>
        <div v-if="missingRequiredCount > 0" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[var(--severity-red)]/10 text-[var(--severity-red)] text-xs font-semibold">
          <AlertCircle class="w-3 h-3" aria-hidden="true" />
          ينقص {{ missingRequiredCount }} مستند مطلوب
        </div>
        <div v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[var(--severity-green)]/10 text-[var(--severity-green)] text-xs font-semibold">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          مكتمل
        </div>
      </div>

      <!-- Checklist rows -->
      <ul class="flex flex-col gap-1.5 list-none m-0 p-0" aria-label="قائمة المستندات">
        <template v-for="(row, idx) in checklist" :key="idx">

          <!-- Staged requirement row -->
          <li
            v-if="row.kind === 'staged'"
            class="flex items-start gap-2.5 p-3 rounded-lg border"
            :class="{
              'border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5': !!row.doc,
              'border-destructive bg-[var(--severity-red)]/10': !row.doc && row.requirement.required,
              'border-border bg-muted': !row.doc && !row.requirement.required,
            }"
          >
            <!-- Left: status icon box -->
            <div
              class="flex-shrink-0 w-7 h-7 rounded-md flex items-center justify-center text-sm"
              :class="row.doc ? 'bg-[var(--severity-green)]/10 text-[var(--severity-green)]' : 'bg-muted text-muted-foreground'"
            >
              <svg v-if="row.doc" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
              </svg>
            </div>

            <!-- Center: labels -->
            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-foreground">{{ row.requirement.label }}</span>
              </div>
              <template v-if="row.doc">
                <span class="text-xs font-medium text-foreground break-all">{{ row.doc.original_filename }}</span>
                <span class="text-xs text-muted-foreground">
                  {{ formatFileSize(row.doc.size_bytes) }}
                  · {{ formatDate(row.doc.uploaded_at) }}
                  <template v-if="row.doc.uploaded_by_name"> · {{ row.doc.uploaded_by_name }}</template>
                </span>
                <span v-if="downloadErrors[row.doc.id]" class="text-xs text-[var(--severity-red)]" role="alert">
                  {{ downloadErrors[row.doc.id] }}
                </span>
              </template>
              <span v-else class="text-xs text-muted-foreground">{{ row.requirement.required ? 'لم يُرفع بعد' : 'لم يُرفع' }}</span>
            </div>

            <!-- Right: badge + download -->
            <div class="flex flex-col items-end gap-1.5 flex-shrink-0 pt-0.5">
              <Badge :variant="row.requirement.required ? 'destructive' : 'secondary'" class="text-xs">
                {{ row.requirement.required ? 'مطلوب' : 'اختياري' }}
              </Badge>
              <Button
                v-if="row.doc && canDownloadDocument(userRole, row.doc.type)"
                variant="outline"
                size="sm"
                :disabled="downloadingIds.has(row.doc.id)"
                class="h-7 text-xs px-3 whitespace-nowrap"
                :aria-label="`تحميل ${row.doc.original_filename}`"
                @click="emit('download', row.doc.id, row.doc.original_filename)"
              >
                {{ downloadingIds.has(row.doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
              </Button>
            </div>
          </li>

          <!-- Extra uploaded docs -->
          <li v-else-if="row.kind === 'extra'" class="flex items-start gap-2.5 p-3 rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5">
            <div class="flex-shrink-0 w-7 h-7 rounded-md flex items-center justify-center bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-foreground">
                  {{
                    row.doc.type === 'SWIFT'
                      ? 'مستند SWIFT'
                      : row.doc.type === 'FX_REQUEST'
                        ? 'مستند طلب المصارفة الخارجية'
                        : 'مستند طلب'
                  }}
                </span>
              </div>
              <span class="text-xs font-medium text-foreground break-all">{{ row.doc.original_filename }}</span>
              <span class="text-xs text-muted-foreground">
                {{ formatFileSize(row.doc.size_bytes) }}
                · {{ formatDate(row.doc.uploaded_at) }}
                <template v-if="row.doc.uploaded_by_name"> · {{ row.doc.uploaded_by_name }}</template>
              </span>
              <span v-if="downloadErrors[row.doc.id]" class="text-xs text-[var(--severity-red)]" role="alert">
                {{ downloadErrors[row.doc.id] }}
              </span>
            </div>
            <div class="flex flex-col items-end gap-1.5 flex-shrink-0 pt-0.5">
              <Badge v-if="row.doc.type === 'SWIFT'" class="bg-cyan-500 text-white text-xs">SWIFT</Badge>
              <Badge v-else-if="row.doc.type === 'FX_REQUEST'" class="bg-violet-600 text-white text-xs">FX</Badge>
              <Button
                v-if="canDownloadDocument(userRole, row.doc.type)"
                variant="outline"
                size="sm"
                :disabled="downloadingIds.has(row.doc.id)"
                class="h-7 text-xs px-3 whitespace-nowrap"
                :aria-label="`تحميل ${row.doc.original_filename}`"
                @click="emit('download', row.doc.id, row.doc.original_filename)"
              >
                {{ downloadingIds.has(row.doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
              </Button>
              <!-- Lock indicator for DATA_ENTRY on downstream docs (SWIFT / FX) -->
              <TooltipProvider v-else-if="userRole === UserRole.DATA_ENTRY && (row.doc.type === 'SWIFT' || row.doc.type === 'FX_REQUEST')">
                <Tooltip>
                  <TooltipTrigger as-child>
                    <span
                      class="inline-flex h-7 items-center gap-1 rounded-md border border-dashed border-[var(--color-border)] px-2 text-xs text-muted-foreground cursor-default select-none"
                      aria-label="هذا المستند محجوب عن دورك"
                    >
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                      </svg>
                      محجوب
                    </span>
                  </TooltipTrigger>
                  <TooltipContent side="top" class="max-w-[220px] text-center text-xs">
                    هذا المستند يُعالَج من قِبل فريق CBY ولا يتاح للتنزيل في هذه المرحلة
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            </div>
          </li>

          <!-- Customs declaration row -->
          <li v-else-if="row.kind === 'customs'" class="flex items-start gap-2.5 p-3 rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5">
            <div class="flex-shrink-0 w-7 h-7 rounded-md flex items-center justify-center bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-foreground">بيان جمركي</span>
              </div>
              <span class="text-xs font-medium text-foreground">{{ row.customs.declaration_number }}</span>
              <span class="text-xs text-muted-foreground">{{ formatDate(row.customs.issued_at) }}</span>
              <span v-if="customsDownloadError" class="text-xs text-[var(--severity-red)]" role="alert">
                {{ customsDownloadError }}
              </span>
            </div>
            <div class="flex flex-col items-end gap-1.5 flex-shrink-0 pt-0.5">
              <Button
                v-if="canDownloadCustoms(userRole)"
                variant="outline"
                size="sm"
                :disabled="customsDownloading"
                class="h-7 text-xs px-3 whitespace-nowrap"
                aria-label="تحميل البيان الجمركي"
                @click="emit('download-customs', row.customs.id, row.customs.declaration_number)"
              >
                {{ customsDownloading ? 'جارٍ التحميل…' : 'تحميل' }}
              </Button>
            </div>
          </li>

        </template>
      </ul>
    </template>

    <!-- Upload section (DATA_ENTRY only) -->
    <div v-if="userRole === UserRole.DATA_ENTRY" class="flex flex-col items-start gap-2 pt-4">
      <template v-if="showUploadButton">
        <input
          ref="fileInputRef"
          type="file"
          accept="application/pdf"
          class="sr-only"
          aria-label="اختر ملف PDF للرفع"
          @change="handleFileChange"
        />
        <Button
          :disabled="uploadingDocument || loading"
          class="h-9 px-4 rounded-lg"
          @click="triggerFileInput"
        >
          {{ uploadingDocument ? 'جارٍ الرفع…' : 'رفع مستند' }}
        </Button>
        <Alert v-if="fileTypeError" class="border-s-4 border-s-[var(--severity-red)] bg-[var(--severity-red)]/10 border-0 w-full">
          <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
          <AlertDescription class="text-[var(--severity-red)] text-sm">{{ fileTypeError }}</AlertDescription>
        </Alert>
        <Alert v-else-if="uploadError" class="border-s-4 border-s-[var(--severity-red)] bg-[var(--severity-red)]/10 border-0 w-full">
          <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
          <AlertDescription class="text-[var(--severity-red)] text-sm">{{ uploadError }}</AlertDescription>
        </Alert>
      </template>

      <p v-else-if="showLockedNote" class="text-xs text-muted-foreground flex items-center gap-1" role="note">
        🔒 مقفل — لا يمكن تعديل المستندات
      </p>
    </div>
  </div>
</template>
