<script setup lang="ts">
import { ref, computed } from 'vue'
import { AlertCircle, Eye } from 'lucide-vue-next'
import { Skeleton } from '../ui/skeleton'
import { ButtonGroup } from '../ui/button-group'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '../ui/tooltip'

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
  view: [docId: number, title: string]
  download: [docId: number, filename: string]
  'view-customs': [customsId: number, title: string]
  'download-customs': [customsId: number, filename: string]
  upload: [file: File]
}>()

// ── Required document slots ───────────────────────────────────────────────────
// These are the documents the bank's data-entry user attaches in the creation
// wizard. Each maps to a persisted `document_sub_type` on the request document.
// SWIFT / FX_REQUEST are added later by CBY and matched by `type`, not sub-type.

type DocRequirement = {
  // matched against document_sub_type (bank wizard docs) or type (CBY docs)
  match: string
  matchBy: 'sub_type' | 'type'
  label: string
  required: boolean
}

const BANK_WIZARD_DOCS: DocRequirement[] = [
  {
    match: 'confirmation_request',
    matchBy: 'sub_type',
    label: 'طلب وثيقة التأكيد',
    required: true,
  },
  {
    match: 'proforma_invoice',
    matchBy: 'sub_type',
    label: 'الفاتورة الأولية (Proforma Invoice)',
    required: true,
  },
  { match: 'commercial_register', matchBy: 'sub_type', label: 'السجل التجاري', required: true },
  { match: 'tax_card', matchBy: 'sub_type', label: 'البطاقة الضريبية', required: true },
]

const CBY_DOCS: DocRequirement[] = [
  { match: 'SWIFT', matchBy: 'type', label: 'مستند SWIFT', required: true },
  { match: 'FX_REQUEST', matchBy: 'type', label: 'مستند طلب المصارفة الخارجية', required: true },
]

// Statuses at/after BANK_APPROVED also surface the CBY-side documents.
const CBY_STAGE_STATUSES = new Set([
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
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
  // Bank wizard docs are always shown; CBY docs appear once the request leaves the bank.
  return CBY_STAGE_STATUSES.has(status) ? [...BANK_WIZARD_DOCS, ...CBY_DOCS] : [...BANK_WIZARD_DOCS]
}

function uploadedAtMs(doc: RequestDocument): number {
  const ts = Date.parse(doc.uploaded_at ?? '')
  return Number.isNaN(ts) ? 0 : ts
}

/** Does this document fill the given requirement slot? */
function docMatchesRequirement(doc: RequestDocument, req: DocRequirement): boolean {
  if (req.matchBy === 'type') return doc.type === req.match

  // confirmation_request: match by explicit sub_type OR legacy CONFIRMATION_REQUEST type
  if (req.match === 'confirmation_request') {
    return doc.document_sub_type === 'confirmation_request' || doc.type === 'CONFIRMATION_REQUEST'
  }

  // Exact sub_type match (new documents with sub_type persisted)
  if (doc.document_sub_type) return doc.document_sub_type === req.match

  // Fallback for legacy REQUEST_DOC documents uploaded before sub_type was introduced:
  // any REQUEST_DOC without a sub_type can fill any wizard sub_type slot.
  // buildChecklist's usedDocIds ensures each document fills at most one slot,
  // so docs are assigned to slots greedily in upload order.
  return doc.type === 'REQUEST_DOC'
}

// ── Checklist row type ────────────────────────────────────────────────────────

type ChecklistRow =
  | { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
  | { kind: 'extra'; doc: RequestDocument }
  | { kind: 'customs'; customs: CustomsDeclarationSummary }

const checklist = computed((): ChecklistRow[] => {
  const stageDocs = getStageDocs(props.requestStatus)
  const rows: ChecklistRow[] = []

  // For each requirement slot, pick the newest matching uploaded document.
  // Any document that does not fill a slot becomes an "extra" row.
  const usedDocIds = new Set<number>()
  const slotDoc = new Map<string, RequestDocument>()

  for (const req of stageDocs) {
    let best: RequestDocument | null = null
    for (const doc of props.documents) {
      if (usedDocIds.has(doc.id)) continue
      if (!docMatchesRequirement(doc, req)) continue
      if (!best || uploadedAtMs(doc) >= uploadedAtMs(best)) best = doc
    }
    if (best) {
      slotDoc.set(req.match, best)
      usedDocIds.add(best.id)
    }
  }

  // Staged requirement rows
  for (const req of stageDocs) {
    rows.push({ kind: 'staged', requirement: req, doc: slotDoc.get(req.match) ?? null })
  }

  // Extra uploaded docs not matching any requirement slot
  for (const doc of props.documents) {
    if (!usedDocIds.has(doc.id)) {
      rows.push({ kind: 'extra', doc })
    }
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
    checklist.value.filter((r) => r.kind === 'staged' && r.requirement.required && !r.doc).length,
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
  <div class="flex flex-col gap-0">
    <!-- Loading state -->
    <div
      v-if="loading"
      class="flex flex-col gap-3 py-2"
      aria-busy="true"
      aria-label="جارٍ تحميل المستندات"
    >
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-3/5 rounded-lg" />
    </div>

    <!-- Error state -->
    <Alert
      v-else-if="error"
      class="border-0 border-[var(--severity-red)] bg-[var(--severity-red)]/10"
    >
      <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
      <AlertDescription class="text-sm text-[var(--severity-red)]">{{ error }}</AlertDescription>
    </Alert>

    <!-- Empty state -->
    <p v-else-if="!hasContent" class="text-muted-foreground mt-2 text-sm">لا توجد مستندات بعد.</p>

    <!-- Checklist summary badge -->
    <template v-else>
      <div class="mb-2.5 flex items-center justify-between gap-2">
        <span class="text-muted-foreground text-xs">قائمة المستندات</span>
        <div
          v-if="missingRequiredCount > 0"
          class="inline-flex items-center gap-1 rounded-full bg-[var(--severity-red)]/10 px-2.5 py-0.5 text-xs font-semibold text-[var(--severity-red)]"
        >
          <AlertCircle class="h-3 w-3" aria-hidden="true" />
          ينقص {{ missingRequiredCount }} مستند مطلوب
        </div>
        <div
          v-else
          class="inline-flex items-center gap-1 rounded-full bg-[var(--severity-green)]/10 px-2.5 py-0.5 text-xs font-semibold text-[var(--severity-green)]"
        >
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            aria-hidden="true"
          >
            <polyline points="20 6 9 17 4 12" />
          </svg>
          مكتمل
        </div>
      </div>

      <!-- Checklist rows -->
      <ul class="m-0 flex list-none flex-col gap-1.5 p-0" aria-label="قائمة المستندات">
        <template v-for="(row, idx) in checklist" :key="idx">
          <!-- Staged requirement row -->
          <li
            v-if="row.kind === 'staged'"
            class="border-border flex items-start gap-2.5 rounded-lg border p-3"
          >
            <!-- Left: status icon box -->
            <div
              class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md text-sm"
              :class="
                row.doc
                  ? 'bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
                  : 'bg-muted text-muted-foreground'
              "
            >
              <svg
                v-if="row.doc"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                aria-hidden="true"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <svg
                v-else
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
            </div>

            <!-- Center: labels -->
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <div class="flex items-center gap-1.5">
                <span class="text-foreground text-xs font-semibold">{{
                  row.requirement.label
                }}</span>
              </div>
              <template v-if="row.doc">
                <span class="text-foreground text-xs font-medium break-all">{{
                  row.doc.original_filename
                }}</span>
                <span class="text-muted-foreground text-xs">
                  {{ formatFileSize(row.doc.size_bytes) }}
                  · {{ formatDate(row.doc.uploaded_at) }}
                  <template v-if="row.doc.uploaded_by_name">
                    · {{ row.doc.uploaded_by_name }}</template
                  >
                </span>
                <span
                  v-if="downloadErrors[row.doc.id]"
                  class="text-xs text-[var(--severity-red)]"
                  role="alert"
                >
                  {{ downloadErrors[row.doc.id] }}
                </span>
              </template>
              <span v-else class="text-muted-foreground text-xs">{{
                row.requirement.required ? 'لم يُرفع بعد' : 'لم يُرفع'
              }}</span>
            </div>

            <!-- Right: badge + download -->
            <div class="flex flex-shrink-0 flex-col items-end gap-1.5 pt-0.5">
              <Badge
                :variant="row.requirement.required ? 'destructive' : 'secondary'"
                class="text-xs"
              >
                {{ row.requirement.required ? 'مطلوب' : 'اختياري' }}
              </Badge>
              <ButtonGroup v-if="row.doc && canDownloadDocument(userRole, row.doc.type)">
                <Button
                  variant="outline"
                  size="sm"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  :aria-label="`عرض ${row.requirement.label}`"
                  @click="emit('view', row.doc.id, row.requirement.label)"
                >
                  <Eye class="me-1 h-3.5 w-3.5" aria-hidden="true" />
                  عرض
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="downloadingIds.has(row.doc.id)"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  :aria-label="`تنزيل ${row.doc.original_filename}`"
                  @click="emit('download', row.doc.id, row.doc.original_filename)"
                >
                  {{ downloadingIds.has(row.doc.id) ? 'جارٍ التنزيل…' : 'تنزيل' }}
                </Button>
              </ButtonGroup>
            </div>
          </li>

          <!-- Extra uploaded docs -->
          <li
            v-else-if="row.kind === 'extra'"
            class="border-border flex items-start gap-2.5 rounded-lg border p-3"
          >
            <div
              class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
            >
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                aria-hidden="true"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <div class="flex items-center gap-1.5">
                <span class="text-foreground text-xs font-semibold">
                  {{
                    row.doc.title ||
                    (row.doc.type === 'SWIFT'
                      ? 'مستند SWIFT'
                      : row.doc.type === 'FX_REQUEST'
                        ? 'مستند طلب المصارفة الخارجية'
                        : 'مستند إضافي')
                  }}
                </span>
              </div>
              <span class="text-foreground text-xs font-medium break-all">{{
                row.doc.original_filename
              }}</span>
              <span class="text-muted-foreground text-xs">
                {{ formatFileSize(row.doc.size_bytes) }}
                · {{ formatDate(row.doc.uploaded_at) }}
                <template v-if="row.doc.uploaded_by_name">
                  · {{ row.doc.uploaded_by_name }}</template
                >
              </span>
              <span
                v-if="downloadErrors[row.doc.id]"
                class="text-xs text-[var(--severity-red)]"
                role="alert"
              >
                {{ downloadErrors[row.doc.id] }}
              </span>
            </div>
            <div class="flex flex-shrink-0 flex-col items-end gap-1.5 pt-0.5">
              <Badge
                v-if="row.doc.type === 'SWIFT'"
                class="border border-[var(--info)]/30 bg-[var(--info)]/15 text-xs text-[var(--info)]"
                >SWIFT</Badge
              >
              <Badge
                v-else-if="row.doc.type === 'FX_REQUEST'"
                class="border border-[var(--voting)]/30 bg-[var(--voting)]/10 text-xs text-[var(--voting)]"
                >FX</Badge
              >
              <ButtonGroup v-if="canDownloadDocument(userRole, row.doc.type)">
                <Button
                  variant="outline"
                  size="sm"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  :aria-label="`عرض ${row.doc.title || row.doc.original_filename}`"
                  @click="emit('view', row.doc.id, row.doc.title || row.doc.original_filename)"
                >
                  <Eye class="me-1 h-3.5 w-3.5" aria-hidden="true" />
                  عرض
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="downloadingIds.has(row.doc.id)"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  :aria-label="`تنزيل ${row.doc.original_filename}`"
                  @click="emit('download', row.doc.id, row.doc.original_filename)"
                >
                  {{ downloadingIds.has(row.doc.id) ? 'جارٍ التنزيل…' : 'تنزيل' }}
                </Button>
              </ButtonGroup>
              <!-- Lock indicator for DATA_ENTRY on downstream docs (SWIFT / FX) -->
              <template
                v-else-if="
                  userRole === UserRole.DATA_ENTRY &&
                  (row.doc.type === 'SWIFT' || row.doc.type === 'FX_REQUEST')
                "
              >
                <Tooltip>
                  <TooltipTrigger as-child>
                    <span
                      class="text-muted-foreground inline-flex h-7 cursor-default items-center gap-1 rounded-md border border-dashed border-[var(--color-border)] px-2 text-xs select-none"
                      aria-label="هذا المستند محجوب عن دورك"
                    >
                      <svg
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        aria-hidden="true"
                      >
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                      </svg>
                      محجوب
                    </span>
                  </TooltipTrigger>
                  <TooltipContent side="top" class="max-w-[220px] text-center text-xs">
                    هذا المستند يُعالَج من قِبل فريق CBY ولا يتاح للتنزيل في هذه المرحلة
                  </TooltipContent>
                </Tooltip>
              </template>
            </div>
          </li>

          <!-- Customs declaration row -->
          <li
            v-else-if="row.kind === 'customs'"
            class="border-border flex items-start gap-2.5 rounded-lg border p-3"
          >
            <div
              class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
            >
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                aria-hidden="true"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <div class="flex items-center gap-1.5">
                <span class="text-foreground text-xs font-semibold">بيان جمركي</span>
              </div>
              <span class="text-foreground text-xs font-medium">{{
                row.customs.declaration_number
              }}</span>
              <span class="text-muted-foreground text-xs">{{
                formatDate(row.customs.issued_at)
              }}</span>
              <span
                v-if="customsDownloadError"
                class="text-xs text-[var(--severity-red)]"
                role="alert"
              >
                {{ customsDownloadError }}
              </span>
            </div>
            <div class="flex flex-shrink-0 flex-col items-end gap-1.5 pt-0.5">
              <ButtonGroup v-if="canDownloadCustoms(userRole)">
                <Button
                  variant="outline"
                  size="sm"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  aria-label="عرض البيان الجمركي"
                  @click="emit('view-customs', row.customs.id, 'البيان الجمركي')"
                >
                  <Eye class="me-1 h-3.5 w-3.5" aria-hidden="true" />
                  عرض
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="customsDownloading"
                  class="h-7 px-3 text-xs whitespace-nowrap"
                  aria-label="تنزيل البيان الجمركي"
                  @click="emit('download-customs', row.customs.id, row.customs.declaration_number)"
                >
                  {{ customsDownloading ? 'جارٍ التنزيل…' : 'تنزيل' }}
                </Button>
              </ButtonGroup>
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
          class="h-9 rounded-lg px-4"
          @click="triggerFileInput"
        >
          {{ uploadingDocument ? 'جارٍ الرفع…' : 'رفع مستند' }}
        </Button>
        <Alert
          v-if="fileTypeError"
          class="w-full border-0 border-[var(--severity-red)] bg-[var(--severity-red)]/10"
        >
          <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
          <AlertDescription class="text-sm text-[var(--severity-red)]">{{
            fileTypeError
          }}</AlertDescription>
        </Alert>
        <Alert
          v-else-if="uploadError"
          class="w-full border-0 border-[var(--severity-red)] bg-[var(--severity-red)]/10"
        >
          <AlertCircle class="h-4 w-4 text-[var(--severity-red)]" aria-hidden="true" />
          <AlertDescription class="text-sm text-[var(--severity-red)]">{{
            uploadError
          }}</AlertDescription>
        </Alert>
      </template>

      <p
        v-else-if="showLockedNote"
        class="text-muted-foreground flex items-center gap-1 text-xs"
        role="note"
      >
        🔒 المستندات مقفلة، ولا يمكن تعديلها الآن
      </p>
    </div>
  </div>
</template>
