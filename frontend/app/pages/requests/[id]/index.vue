<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
import { VoteType } from '../../../types/enums'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '../../../components/ui/alert-dialog'
import { Button } from '../../../components/ui/button'
import { Skeleton } from '../../../components/ui/skeleton'
import { useRequests } from '../../../composables/useRequests'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import { useVotingStore } from '../../../stores/voting.store'
import { useClaimLifecycle } from '../../../composables/useClaimLifecycle'
import { canDownloadCustoms } from '../../../composables/useDocumentPermissions'
import { STATUS_LABELS, ROLE_LABELS } from '../../../constants/workflow'
import StatusBadge from '../../../components/shared/StatusBadge.vue'
import LockedBanner from '../../../components/banners/LockedBanner.vue'
import CorrectionBanner from '../../../components/banners/CorrectionBanner.vue'
import ActiveReviewBanner from '../../../components/banners/ActiveReviewBanner.vue'
import ClaimedByOthersBanner from '../../../components/banners/ClaimedByOthersBanner.vue'
import SegregationBlockedBanner from '../../../components/banners/SegregationBlockedBanner.vue'
import UnclaimedBanner from '../../../components/banners/UnclaimedBanner.vue'
import VotingPendingBanner from '../../../components/banners/VotingPendingBanner.vue'
import VotedConfirmationBanner from '../../../components/banners/VotedConfirmationBanner.vue'
import ActionsPanel from '../../../components/requests/ActionsPanel.vue'
import DocumentChecklist from '../../../components/requests/DocumentChecklist.vue'
import VotingPanel from '../../../components/voting/VotingPanel.vue'
import WorkflowTimeline from '../../../components/workflow/WorkflowTimeline.vue'
import AuditTimeline from '../../../components/workflow/AuditTimeline.vue'
import WorkflowProgress from '../../../components/workflow/WorkflowProgress.vue'

definePageMeta({
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const requestsStore = useRequestsStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const votingStore = useVotingStore()

type TabKey = 'overview' | 'documents' | 'parties' | 'activity_log' | 'fx_confirmation'
const activeTab = ref<TabKey>('overview')

const VOTING_STAGE_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const EXECUTIVE_ROLES = new Set([UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR])

const request = computed(() => requestsStore.currentRequest)
const userRole = computed(() => auth.user?.role ?? UserRole.DATA_ENTRY)
const canDownloadCustomsDeclaration = computed(() => canDownloadCustoms(userRole.value))

// BANK_REVIEWER may not review requests they personally created (segregation of duties)
const isSegregationBlocked = computed(() =>
  userRole.value === UserRole.BANK_REVIEWER
  && !!request.value
  && !!auth.user
  && request.value.created_by === auth.user.id,
)
const DRAFT_EDITOR_ROLES = new Set([UserRole.DATA_ENTRY, UserRole.BANK_ADMIN])

// VotingPanel is shown inline above tabs for executive/director roles in voting stages
const showVotingPanelInline = computed(() =>
  !!request.value
  && EXECUTIVE_ROLES.has(userRole.value)
  && VOTING_STAGE_STATUSES.has(request.value.status),
)

const showDirectorFxTab = computed(() =>
  userRole.value === UserRole.COMMITTEE_DIRECTOR
  && request.value?.status === RequestStatus.EXECUTIVE_APPROVED,
)

const tabs = computed((): Array<{ key: TabKey; label: string }> => {
  const base: Array<{ key: TabKey; label: string }> = [
    { key: 'overview', label: 'المعلومات' },
    { key: 'documents', label: 'الوثائق' },
    { key: 'parties', label: 'الأطراف' },
    { key: 'activity_log', label: 'سجل الأحداث' },
  ]
  if (showDirectorFxTab.value) {
    base.push({ key: 'fx_confirmation', label: 'تأكيد المصارفة' })
  }
  return base
})

const isEditable = computed(() => {
  const s = request.value?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL || s === RequestStatus.BANK_RETURNED || s === RequestStatus.SUPPORT_RETURNED
})

const ACTIONABLE_REVIEWER_STATUSES = new Set([
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
])

// Terminal states — completely immutable, no role can act
const TERMINAL_STATUSES = new Set([
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
  RequestStatus.COMPLETED,
  RequestStatus.BANK_REJECTED,
])

// Readonly states — intermediate, no action for the viewing user on this page
const READONLY_STATUSES = new Set([
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
  RequestStatus.BANK_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_APPROVED,
])

// Pending states — actively in review by another role
const PENDING_STATUSES = new Set([
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

type LockedBannerVariant = 'locked' | 'readonly' | 'pending' | 'bank_rejected'

const lockedBannerVariant = computed((): LockedBannerVariant | null => {
  if (!request.value) return null
  const s = request.value.status
  if (s === RequestStatus.BANK_REJECTED) return 'bank_rejected'
  if (TERMINAL_STATUSES.has(s)) return 'locked'
  if (userRole.value === UserRole.BANK_REVIEWER && ACTIONABLE_REVIEWER_STATUSES.has(s)) return null
  // Executive roles viewing voting stages have full access — no banner
  if (EXECUTIVE_ROLES.has(userRole.value) && VOTING_STAGE_STATUSES.has(s)) return null
  if (READONLY_STATUSES.has(s)) return 'readonly'
  if (PENDING_STATUSES.has(s)) return 'pending'
  return null
})

const isLocked = computed(() => lockedBannerVariant.value !== null)
const isCommitteeDirector = computed(() => userRole.value === UserRole.COMMITTEE_DIRECTOR)
const isSwiftOfficer = computed(() => userRole.value === UserRole.SWIFT_OFFICER)
const isReturnedForCorrection = computed(() =>
  request.value?.status === RequestStatus.DRAFT_REJECTED_INTERNAL,
)
const isBankReturned = computed(() => request.value?.status === RequestStatus.BANK_RETURNED)
const isSupportReturned = computed(() => request.value?.status === RequestStatus.SUPPORT_RETURNED)

const showDirectorVotingActiveBanner = computed(() =>
  isCommitteeDirector.value
  && request.value?.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  && request.value?.ready_to_close !== true
  && request.value?.is_tie !== true,
)

const showDirectorReadyToCloseBanner = computed(() =>
  isCommitteeDirector.value
  && request.value?.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  && request.value?.ready_to_close === true,
)

const showDirectorTieBreakBanner = computed(() =>
  isCommitteeDirector.value
  && request.value?.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  && request.value?.is_tie === true,
)

const showDirectorReadyToFinalizeBanner = computed(() =>
  isCommitteeDirector.value && request.value?.status === RequestStatus.EXECUTIVE_VOTING_CLOSED,
)

const showDirectorFxReadyBanner = computed(() =>
  isCommitteeDirector.value && request.value?.status === RequestStatus.EXECUTIVE_APPROVED,
)

const SWIFT_READY_STATUSES = new Set([
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
])
const SWIFT_COMPLETED_STATUSES = new Set([
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
])

const showSwiftPreApprovalLockedBanner = computed(() =>
  isSwiftOfficer.value
  && !!request.value
  && !SWIFT_READY_STATUSES.has(request.value.status)
  && !SWIFT_COMPLETED_STATUSES.has(request.value.status)
  && !TERMINAL_STATUSES.has(request.value.status),
)

const showSwiftReadyBanner = computed(() =>
  isSwiftOfficer.value && request.value?.status === RequestStatus.WAITING_FOR_SWIFT,
)

const showSwiftAwaitingEnableBanner = computed(() =>
  isSwiftOfficer.value && request.value?.status === RequestStatus.EXECUTIVE_APPROVED,
)

const showSwiftCompletedBanner = computed(() =>
  isSwiftOfficer.value
  && !!request.value
  && SWIFT_COMPLETED_STATUSES.has(request.value.status),
)

const showSwiftUploadShortcut = computed(() =>
  isSwiftOfficer.value && request.value?.status === RequestStatus.WAITING_FOR_SWIFT,
)

const showSwiftFxLockedRow = computed(() =>
  isSwiftOfficer.value
  && !!request.value
  && (SWIFT_COMPLETED_STATUSES.has(request.value.status) || request.value.status === RequestStatus.EXECUTIVE_APPROVED),
)

/** Chip shown to bank reviewer when a SUBMITTED request was previously support-returned */
const supportReturnHint = computed(() => {
  if (userRole.value !== UserRole.BANK_REVIEWER) return null
  if (request.value?.status !== RequestStatus.SUBMITTED) return null
  const latestSubmitIndex = requestsStore.history.findLastIndex(entry => entry.to_status === RequestStatus.SUBMITTED)
  if (latestSubmitIndex <= 0) return null
  const previousEntry = requestsStore.history[latestSubmitIndex - 1]
  if (!previousEntry || previousEntry.to_status !== RequestStatus.SUPPORT_RETURNED) return null
  return { comment: previousEntry.notes }
})

const canEdit = computed(
  () => DRAFT_EDITOR_ROLES.has(userRole.value) && isEditable.value,
)

const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

// ─── Duplicate warnings (AC7) ─────────────────────────────────────────────────
const FULL_DUPLICATE_ROLES = new Set([UserRole.CBY_ADMIN, UserRole.SUPPORT_COMMITTEE])
const BANK_DUPLICATE_ROLES = new Set([UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN])

const duplicateWarnings = computed(() => request.value?.duplicate_warnings ?? [])
const duplicateWidgetExpanded = ref(true)
const duplicateBankNames = computed(() =>
  Array.from(
    new Set(
      duplicateWarnings.value
        .map(warning => warning.bank_name)
        .filter((name): name is string => Boolean(name)),
    ),
  ),
)

const showDuplicateWidget = computed(() =>
  duplicateWarnings.value.length > 0
  && (FULL_DUPLICATE_ROLES.has(userRole.value) || BANK_DUPLICATE_ROLES.has(userRole.value)),
)

const duplicateWidgetFull = computed(() => FULL_DUPLICATE_ROLES.has(userRole.value))

// Maps current status → the role currently responsible (for BANK_ADMIN read-only panel)
const STATUS_RESPONSIBLE_ROLE: Partial<Record<RequestStatus, UserRole>> = {
  [RequestStatus.DRAFT]: UserRole.DATA_ENTRY,
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: UserRole.DATA_ENTRY,
  [RequestStatus.SUBMITTED]: UserRole.BANK_REVIEWER,
  [RequestStatus.BANK_REVIEW]: UserRole.BANK_REVIEWER,
  [RequestStatus.BANK_RETURNED]: UserRole.DATA_ENTRY,
  [RequestStatus.BANK_APPROVED]: UserRole.SUPPORT_COMMITTEE,
  [RequestStatus.SUPPORT_REVIEW_PENDING]: UserRole.SUPPORT_COMMITTEE,
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: UserRole.SUPPORT_COMMITTEE,
  [RequestStatus.SUPPORT_APPROVED]: UserRole.SWIFT_OFFICER,
  [RequestStatus.SUPPORT_REJECTED]: UserRole.DATA_ENTRY,
  [RequestStatus.SUPPORT_RETURNED]: UserRole.DATA_ENTRY,
  [RequestStatus.WAITING_FOR_SWIFT]: UserRole.SWIFT_OFFICER,
  [RequestStatus.SWIFT_UPLOADED]: UserRole.EXECUTIVE_MEMBER,
  [RequestStatus.WAITING_FOR_VOTING_OPEN]: UserRole.COMMITTEE_DIRECTOR,
  [RequestStatus.EXECUTIVE_VOTING_OPEN]: UserRole.EXECUTIVE_MEMBER,
  [RequestStatus.EXECUTIVE_VOTING_CLOSED]: UserRole.COMMITTEE_DIRECTOR,
  [RequestStatus.EXECUTIVE_APPROVED]: UserRole.COMMITTEE_DIRECTOR,
  [RequestStatus.EXECUTIVE_REJECTED]: UserRole.DATA_ENTRY,
  // FX_CONFIRMATION_PENDING and CUSTOMS_DECLARATION_ISSUED both sit in the
  // Director's external-FX completion phase (Story 3.6 / Epic 11). Verify
  // against docs/01-workflow-and-business-rules.md when ownership changes.
  [RequestStatus.FX_CONFIRMATION_PENDING]: UserRole.COMMITTEE_DIRECTOR,
  [RequestStatus.CUSTOMS_DECLARATION_ISSUED]: UserRole.COMMITTEE_DIRECTOR,
  [RequestStatus.COMPLETED]: UserRole.CBY_ADMIN,
  [RequestStatus.BANK_REJECTED]: UserRole.DATA_ENTRY,
}

const bankAdminInfoText = computed(() => {
  if (!request.value) return ''
  const stage = STATUS_LABELS[request.value.status] ?? request.value.status
  const responsibleRole = STATUS_RESPONSIBLE_ROLE[request.value.status]
  const roleLabel = responsibleRole ? ROLE_LABELS[responsibleRole] : '—'
  return `الطلب حالياً في مرحلة ${stage} — المسؤول: ${roleLabel}`
})

// BANK_ADMIN sees a read-only informational panel instead of action buttons
// Exception: own-DRAFT requests get "تعديل" + "حذف" (handled via canEdit/hasActions)
const showBankAdminInfoPanel = computed(() => {
  if (!request.value || userRole.value !== UserRole.BANK_ADMIN) return false
  const s = request.value.status
  // Own-draft exception: DATA_ENTRY/BANK_ADMIN edit path handles this
  if ((s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL) && request.value.created_by === auth.user?.id) return false
  return true
})

// Mirror ActionsPanel's showAnyActions to conditionally show the rail actions card
const hasActions = computed(() => {
  if (!request.value) return false
  const s = request.value.status
  const role = userRole.value
  const bankReviewerAction
    = role === UserRole.BANK_REVIEWER
    && !isSegregationBlocked.value
    && (s === RequestStatus.SUBMITTED || s === RequestStatus.BANK_REVIEW)
  const dataEntryAction
    = DRAFT_EDITOR_ROLES.has(role)
    && (s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL || s === RequestStatus.BANK_RETURNED || s === RequestStatus.SUPPORT_RETURNED)
  const supportAction
    = role === UserRole.SUPPORT_COMMITTEE
    && s === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
    && request.value.is_claimed_by_me
  const directorVotingAction
    = role === UserRole.COMMITTEE_DIRECTOR
    && DIRECTOR_VOTING_STATUSES.has(s)
  const directorCustomsAction
    = role === UserRole.COMMITTEE_DIRECTOR
    && s === RequestStatus.EXECUTIVE_APPROVED
  return bankReviewerAction || dataEntryAction || supportAction || directorVotingAction || directorCustomsAction
})

const showSwiftActionCard = computed(() =>
  isSwiftOfficer.value
  && !!request.value
  && (
    request.value.status === RequestStatus.EXECUTIVE_APPROVED
    || request.value.status === RequestStatus.WAITING_FOR_SWIFT
    || SWIFT_COMPLETED_STATUSES.has(request.value.status)
  ),
)

// Downloading state per document id
const downloadingIds = ref<Set<number>>(new Set())
const downloadErrors = ref<Record<number, string>>({})
const customsDownloadError = ref('')
const checklistCustomsDownloadError = ref('')
const fxTemplateChecksum = ref<string | null>(null)
const fxSignedFile = ref<File | null>(null)
const fxSignedChecksum = ref<string | null>(null)
const fxFlowError = ref('')
const fxFlowSuccess = ref(false)
const fxGeneratingTemplate = ref(false)
const fxCompleting = ref(false)

// Claim lifecycle for SUPPORT_COMMITTEE
const {
  claimRequest,
  releaseRequest,
  verifyClaimAlive,
  startHeartbeat,
  stopHeartbeat,
  claimError,
  sessionExpired,
} = useClaimLifecycle()
const isActiveReviewer = ref(false)
// Local guard against double-clicks on "مطالبة" / "إفراج" before the previous
// call resolves; prevents duplicate POST/DELETE and the resulting orphan claim.
const claimMutating = ref(false)

const isSupportCommittee = computed(() => userRole.value === UserRole.SUPPORT_COMMITTEE)
const isExecutiveMember = computed(() => userRole.value === UserRole.EXECUTIVE_MEMBER)
const votingDetailLoadedForCurrentRequest = computed(() =>
  votingStore.votingDetail?.request?.id === id,
)

// VotingPendingBanner: voting open, EXECUTIVE_MEMBER, not yet voted
const showVotingPendingBanner = computed(() =>
  isExecutiveMember.value
  && request.value?.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  && votingDetailLoadedForCurrentRequest.value
  && !votingStore.votingDetail?.my_vote,
)

// VotedConfirmationBanner: EXECUTIVE_MEMBER has already cast vote on this session
const showVotedConfirmationBanner = computed(() =>
  isExecutiveMember.value
  && request.value?.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  && votingDetailLoadedForCurrentRequest.value
  && !!votingStore.votingDetail?.my_vote,
)

const showActiveReviewBanner = computed(
  () => isSupportCommittee.value && isActiveReviewer.value,
)

const showClaimedByOthersBanner = computed(() => {
  if (!isSupportCommittee.value || isActiveReviewer.value) return false
  const req = request.value
  return !!req && req.is_claimed && !req.is_claimed_by_me
})

const showUnclaimedBanner = computed(() => {
  if (!isSupportCommittee.value || isActiveReviewer.value) return false
  const req = request.value
  return !!req && req.status === RequestStatus.SUPPORT_REVIEW_PENDING && !req.is_claimed
})

// Destruction guard: set to false in onBeforeUnmount so any in-flight async
// continuations after unmount do not mutate component state or start timers.
let isMounted = false

async function handleSessionExpired() {
  isActiveReviewer.value = false
  stopHeartbeat(id)
  await navigateTo('/login')
}

async function handleClaimLost() {
  isActiveReviewer.value = false
  stopHeartbeat(id)
  if (isMounted) {
    await requestsStore.loadRequest(id)
  }
}

async function handleManualClaim() {
  if (claimMutating.value) return
  claimMutating.value = true
  try {
    const claimed = await claimRequest(id)
    if (!isMounted) return
    if (!claimed) {
      await requestsStore.loadRequest(id)
      return
    }

    isActiveReviewer.value = true
    startHeartbeat(id, handleSessionExpired, handleClaimLost)
    await requestsStore.loadRequest(id)
    if (!isMounted) return
    syncActiveReviewState()
  }
  finally {
    claimMutating.value = false
  }
}

async function handleReleaseClaim() {
  if (claimMutating.value) return
  claimMutating.value = true
  try {
    // Only mutate local state once the server confirms release. If the request
    // fails (network, 5xx) the user retains the claim until TTL and the UI
    // still reflects that — no orphan ghost state.
    const released = await releaseRequest(id)
    if (!isMounted) return
    if (!released) {
      await requestsStore.loadRequest(id)
      return
    }
    isActiveReviewer.value = false
    stopHeartbeat(id)
    await requestsStore.loadRequest(id)
  }
  finally {
    claimMutating.value = false
  }
}

function syncActiveReviewState() {
  const req = requestsStore.currentRequest
  if (
    isActiveReviewer.value
    && (!req || req.status !== RequestStatus.SUPPORT_REVIEW_IN_PROGRESS || !req.is_claimed_by_me)
  ) {
    isActiveReviewer.value = false
    stopHeartbeat(id)
  }
}

onMounted(async () => {
  isMounted = true

  if (Number.isNaN(id)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(id)

  if (!isMounted) return

  if (requestsStore.error || !requestsStore.currentRequest) {
    await router.replace('/requests')
    return
  }

  // Auto-claim lifecycle for SUPPORT_COMMITTEE
  if (isSupportCommittee.value && requestsStore.currentRequest) {
    const req = requestsStore.currentRequest

    if (req.is_claimed && req.is_claimed_by_me) {
      const alive = await verifyClaimAlive(id)

      if (!isMounted) return

      if (alive) {
        isActiveReviewer.value = true
        startHeartbeat(id, handleSessionExpired, handleClaimLost)
      }
      else {
        await requestsStore.loadRequest(id)
        if (!isMounted) return

        if (sessionExpired.value) {
          await handleSessionExpired()
          return
        }
      }
    }
  }

  if (!isMounted) return

  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id)
  }

  // Pre-load voting detail for executive/director in voting stages
  if (
    EXECUTIVE_ROLES.has(userRole.value)
    && requestsStore.currentRequest
    && VOTING_STAGE_STATUSES.has(requestsStore.currentRequest.status)
  ) {
    await votingStore.loadVotingDetail(id)
  }

  // Pre-load history for bank reviewers on SUBMITTED requests to detect resubmit-after-support-return
  if (
    userRole.value === UserRole.BANK_REVIEWER
    && requestsStore.currentRequest?.status === RequestStatus.SUBMITTED
    && !requestsStore.historyLoaded
    && !requestsStore.loadingHistory
  ) {
    await requestsStore.loadHistory(id)
  }
})

onBeforeUnmount(() => {
  isMounted = false
  stopHeartbeat(id)
  if (isActiveReviewer.value) {
    releaseRequest(id)
  }
})

async function onTabChange(key: TabKey) {
  activeTab.value = key
  if (key === 'documents' && !requestsStore.documentsLoaded && !requestsStore.loadingDocuments) {
    await requestsStore.loadDocuments(id)
  }
  if ((key === 'parties' || key === 'activity_log') && !requestsStore.historyLoaded && !requestsStore.loadingHistory) {
    await requestsStore.loadHistory(id)
  }
}

async function onActionCompleted() {
  await requestsStore.loadRequest(id)
  customsDownloadError.value = ''
  syncActiveReviewState()
  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id)
  }
  if (activeTab.value === 'parties' || activeTab.value === 'activity_log') {
    await requestsStore.loadHistory(id)
  }
  if (showVotingPanelInline.value || votingStore.votingDetail) {
    await votingStore.loadVotingDetail(id)
  }
}

async function openSupportReturnHistory() {
  if (activeTab.value !== 'parties') {
    await onTabChange('parties')
  }

  await nextTick()
  document.getElementById('audit-trail')?.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  })
}

async function downloadCustomsDeclaration() {
  const declaration = request.value?.customs_declaration
  if (!declaration) return

  customsDownloadError.value = ''
  try {
    await requestsStore.downloadCustomsDeclaration(
      declaration.id,
      `customs-declaration-${declaration.declaration_number}.pdf`,
    )
  }
  catch {
    customsDownloadError.value = 'تعذّر تحميل البيان الجمركي.'
  }
}

async function handleDownloadCustoms(customsId: number, declarationNumber: string) {
  checklistCustomsDownloadError.value = ''
  try {
    await requestsStore.downloadCustomsDeclaration(
      customsId,
      `customs-declaration-${declarationNumber}.pdf`,
    )
  }
  catch {
    checklistCustomsDownloadError.value = 'تعذّر تحميل البيان الجمركي.'
  }
}

async function fileSha256(file: File): Promise<string> {
  const buffer = await file.arrayBuffer()
  const digest = await crypto.subtle.digest('SHA-256', buffer)
  return Array.from(new Uint8Array(digest))
    .map(byte => byte.toString(16).padStart(2, '0'))
    .join('')
}

function triggerFxSignedUpload() {
  const input = document.getElementById('fx-signed-upload') as HTMLInputElement | null
  input?.click()
}

async function handleFxSignedFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  if (file.type !== 'application/pdf') {
    fxFlowError.value = 'يرجى رفع ملف PDF فقط.'
    target.value = ''
    return
  }

  fxFlowError.value = ''
  fxSignedFile.value = file
  fxSignedChecksum.value = await fileSha256(file)
}

async function handleDownloadFxTemplate() {
  fxFlowError.value = ''
  fxGeneratingTemplate.value = true

  try {
    const declaration = await generateCustomsDeclaration(id)
    const blob = await downloadCustomsBlob(declaration.id)

    const bytes = await blob.arrayBuffer()
    const digest = await crypto.subtle.digest('SHA-256', bytes)
    fxTemplateChecksum.value = Array.from(new Uint8Array(digest))
      .map(byte => byte.toString(16).padStart(2, '0'))
      .join('')

    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `fx-confirmation-${declaration.declaration_number}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  catch (error: unknown) {
    const message = error instanceof Error ? error.message : ''
    fxFlowError.value = message || 'تعذّر تحميل نموذج تأكيد المصارفة.'
  }
  finally {
    fxGeneratingTemplate.value = false
  }
}

async function handleCompleteFxConfirmation() {
  if (!fxSignedFile.value) {
    fxFlowError.value = 'يجب رفع النموذج الموقّع قبل الإتمام.'
    return
  }

  fxFlowError.value = ''
  fxCompleting.value = true

  try {
    await requestsStore.issueCustomsDeclaration(id)
    fxFlowSuccess.value = true
    await onActionCompleted()
  }
  catch (error: unknown) {
    const message = error instanceof Error ? error.message : ''
    fxFlowError.value = message || 'تعذّر إتمام تأكيد المصارفة.'
  }
  finally {
    fxCompleting.value = false
  }
}

async function handleUploadDocument(file: File) {
  try {
    await requestsStore.uploadDocument(id, file)
  }
  catch {
    // uploadError is set on the store by the action
  }
}

async function downloadDocument(docId: number, filename: string) {
  if (downloadingIds.value.has(docId)) return

  downloadingIds.value = new Set([...downloadingIds.value, docId])
  delete downloadErrors.value[docId]

  try {
    const response = await $fetch<Blob>(`/api/documents/${docId}/download`, {
      responseType: 'blob',
      credentials: 'include',
    })

    const url = URL.createObjectURL(response)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  catch {
    downloadErrors.value = { ...downloadErrors.value, [docId]: 'تعذّر تحميل الملف.' }
  }
  finally {
    const next = new Set(downloadingIds.value)
    next.delete(docId)
    downloadingIds.value = next
  }
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar-YE')} ${currency}`
}

function scrollToActionPanel() {
  document.querySelector('.rail-card--actions')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function actorLabel(
  user: { id: number; name: string } | null | undefined,
  id?: number | null,
): string {
  if (user?.name) return user.name
  if (id === null || id === undefined) return '—'
  return `#${id}`
}

// Watch voting panel inline to pre-load voting detail when it becomes visible
watch(showVotingPanelInline, async (visible) => {
  if (visible && !votingStore.votingDetail && !votingStore.loadingDetail) {
    await votingStore.loadVotingDetail(id)
  }
})

// ─── Clone & resubmit ─────────────────────────────────────────────────────────
const CLONEABLE_STATUSES = new Set([
  RequestStatus.BANK_REJECTED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const showCloneButton = computed(() => {
  if (!request.value) return false
  return (
    CLONEABLE_STATUSES.has(request.value.status)
    && DRAFT_EDITOR_ROLES.has(userRole.value)
  )
})

const showCloneDialog = ref(false)
const cloneLoading = ref(false)
const cloneError = ref('')
const {
  cloneRequest,
  generateCustomsDeclaration,
  downloadCustomsDeclaration: downloadCustomsBlob,
} = useRequests()

function openCloneDialog() {
  cloneError.value = ''
  showCloneDialog.value = true
}

function handleCloneDialogOpenChange(nextOpen: boolean) {
  if (cloneLoading.value && !nextOpen) return
  if (!nextOpen) {
    cloneError.value = ''
  }
  showCloneDialog.value = nextOpen
}

async function handleCloneConfirm() {
  cloneLoading.value = true
  cloneError.value = ''
  try {
    const newId = await cloneRequest(id)
    showCloneDialog.value = false
    await navigateTo(`/requests/${newId}/edit`)
  }
  catch {
    cloneError.value = 'تعذّر إنشاء النسخة. يرجى المحاولة مرة أخرى.'
  }
  finally {
    cloneLoading.value = false
  }
}
</script>

<template>
  <div class="detail-page" dir="rtl">
    <!-- Loading skeleton -->
    <div v-if="requestsStore.loadingRequest" class="mx-auto w-full max-w-7xl px-4 space-y-3" aria-busy="true" aria-label="جارٍ التحميل">
      <Skeleton class="h-8 w-64" />
      <Skeleton class="h-5 w-full" />
      <Skeleton class="h-5 w-2/3" />
    </div>

    <template v-else-if="request">
      <div class="mx-auto w-full max-w-7xl px-4">
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs" aria-label="مسار التنقل">
          <NuxtLink to="/dashboard" class="breadcrumb-link">الرئيسية</NuxtLink>
          <span class="breadcrumb-sep" aria-hidden="true">/</span>
          <NuxtLink to="/requests" class="breadcrumb-link">الطلبات</NuxtLink>
          <span class="breadcrumb-sep" aria-hidden="true">/</span>
          <span class="breadcrumb-current">{{ request.reference_number }}</span>
        </nav>

      <!-- Page header -->
      <div class="page-header">
        <div class="page-header__main">
          <h1 class="page-title">{{ request.reference_number }}</h1>
          <p class="page-subtitle">
            <span>{{ request.merchant?.name ?? '—' }}</span>
            <template v-if="request.goods_type">
              <span class="subtitle-dot" aria-hidden="true">·</span>
              <span>{{ request.goods_type }}</span>
            </template>
            <template v-if="request.bank_name">
              <span class="subtitle-dot" aria-hidden="true">·</span>
              <span>{{ request.bank_name }}</span>
            </template>
          </p>
        </div>
        <div class="page-header__actions">
          <!-- AC5: Print button navigates to /requests/{id}/print -->
          <NuxtLink
            :to="`/requests/${id}/print`"
            class="print-btn"
            data-testid="print-request-btn"
            aria-label="طباعة الطلب"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <polyline points="6 9 6 2 18 2 18 9" />
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
              <rect x="6" y="14" width="12" height="8" />
            </svg>
            طباعة
          </NuxtLink>
          <button
            v-if="showCloneButton"
            class="clone-btn"
            :disabled="cloneLoading"
            data-testid="clone-request-btn"
            @click="openCloneDialog"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
            نسخ وإعادة إرسال
          </button>
          <button
            v-if="request.customs_declaration && canDownloadCustomsDeclaration"
            class="download-btn"
            :disabled="requestsStore.downloadingCustoms"
            @click="downloadCustomsDeclaration"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            {{ requestsStore.downloadingCustoms ? 'جارٍ التحميل…' : 'تحميل البيان' }}
          </button>
          <StatusBadge :status="request.status" :role="userRole" />
        </div>
      </div>

      <!-- Two-column layout -->
      <div class="detail-layout">
        <!-- Primary content (2/3) -->
        <div class="detail-main">
          <!-- Banners -->
          <div
            v-if="showDirectorVotingActiveBanner || showDirectorReadyToCloseBanner || showDirectorTieBreakBanner || showDirectorReadyToFinalizeBanner || showDirectorFxReadyBanner || showSwiftPreApprovalLockedBanner || showSwiftAwaitingEnableBanner || showSwiftReadyBanner || showSwiftCompletedBanner || isSegregationBlocked || claimError || showActiveReviewBanner || showClaimedByOthersBanner || showUnclaimedBanner || showVotingPendingBanner || showVotedConfirmationBanner || isLocked || isReturnedForCorrection || isBankReturned || isSupportReturned"
            class="banner-area"
          >
            <div
              v-if="showDirectorVotingActiveBanner"
              class="rounded-lg border border-[#5856d6]/30 bg-[#5856d6]/10 px-4 py-3 text-[#5856d6]"
            >
              جلسة التصويت نشطة — {{ request.votes_cast ?? 0 }} / {{ request.total_voters ?? 0 }} صوتوا.
            </div>
            <div
              v-else-if="showDirectorReadyToCloseBanner"
              class="rounded-lg border border-[#5856d6]/30 bg-[#5856d6]/10 px-4 py-3 text-[#5856d6] flex items-center justify-between gap-3"
            >
              <span>جميع الأعضاء صوتوا — يمكن إغلاق الجلسة الآن.</span>
              <button class="text-xs font-semibold underline" @click="scrollToActionPanel">
                إغلاق الجلسة
              </button>
            </div>
            <div
              v-else-if="showDirectorTieBreakBanner"
              class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 flex items-center justify-between gap-3"
            >
              <span>تعادل في التصويت — يتطلب حسم المدير.</span>
              <button class="text-xs font-semibold underline" @click="scrollToActionPanel">
                حسم التعادل
              </button>
            </div>
            <div
              v-else-if="showDirectorReadyToFinalizeBanner"
              class="rounded-lg border border-[#5856d6]/30 bg-[#5856d6]/10 px-4 py-3 text-[#5856d6] flex items-center justify-between gap-3"
            >
              <span>الجلسة مغلقة — جاهز للإصدار النهائي.</span>
              <button class="text-xs font-semibold underline" @click="scrollToActionPanel">
                إصدار القرار النهائي
              </button>
            </div>
            <div
              v-else-if="showDirectorFxReadyBanner"
              class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 flex items-center justify-between gap-3"
            >
              <span>جاهز لإتمام تأكيد المصارفة الخارجية.</span>
              <button class="text-xs font-semibold underline" @click="onTabChange('fx_confirmation')">
                ابدأ التأكيد
              </button>
            </div>
            <div
              v-else-if="showSwiftPreApprovalLockedBanner"
              class="rounded-lg border border-[var(--locked)]/40 bg-[var(--locked)]/10 px-4 py-3 text-[#3f3f46]"
            >
              هذا الطلب لم يصل بعد مرحلة السويفت. لا يمكن رفع الوثائق حتى يكتمل اعتماد اللجنة التنفيذية.
            </div>
            <div
              v-else-if="showSwiftReadyBanner"
              class="rounded-lg border border-[#32ade6]/40 bg-[#32ade6]/10 px-4 py-3 text-[#0b6f94] flex items-center justify-between gap-3"
            >
              <span>الطلب جاهز لرفع وثائق السويفت.</span>
              <NuxtLink :to="`/requests/${id}/swift`" class="text-xs font-semibold underline">
                ابدأ الرفع
              </NuxtLink>
            </div>
            <div
              v-else-if="showSwiftAwaitingEnableBanner"
              class="rounded-lg border border-[var(--locked)]/40 bg-[var(--locked)]/10 px-4 py-3 text-[#3f3f46]"
            >
              في انتظار الإتاحة — سيتم تفعيل رفع وثائق السويفت بعد الانتقال لمرحلة الانتظار.
            </div>
            <div
              v-else-if="showSwiftCompletedBanner"
              class="rounded-lg border border-[var(--locked)]/40 bg-[var(--locked)]/10 px-4 py-3 text-[#3f3f46]"
            >
              تم تسليم السويفت — انتقلت المسؤولية إلى مدير اللجنة التنفيذية لإتمام تأكيد المصارفة الخارجية.
            </div>
            <SegregationBlockedBanner v-if="isSegregationBlocked" />
            <div v-else-if="claimError" class="claim-error-banner" role="alert" aria-live="assertive">
              <span class="claim-error-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </span>
              <span>{{ claimError }}</span>
            </div>
            <ActiveReviewBanner
              v-else-if="showActiveReviewBanner"
              :claimed-until="request.claimed_until"
              :heartbeat-active="showActiveReviewBanner"
              @release="handleReleaseClaim"
            />
            <ClaimedByOthersBanner v-else-if="showClaimedByOthersBanner" :claimer-name="request.claimed_by?.name ?? ''" />
            <UnclaimedBanner v-else-if="showUnclaimedBanner" @claim="handleManualClaim" />
            <VotingPendingBanner
              v-else-if="showVotingPendingBanner"
              :votes-cast="votingStore.votingDetail?.tally?.total_cast"
              :total-voters="votingStore.votingDetail?.total_members"
            />
            <VotedConfirmationBanner
              v-else-if="showVotedConfirmationBanner"
              :vote="votingStore.votingDetail!.my_vote!.vote === VoteType.APPROVE ? 'approve' : 'reject'"
              :voted-at="votingStore.votingDetail?.my_vote?.voted_at"
            />
            <LockedBanner
              v-else-if="isLocked && lockedBannerVariant"
              :variant="lockedBannerVariant"
              :comment="lockedBannerVariant === 'bank_rejected' ? (request?.bank_reject_comment ?? undefined) : undefined"
            />
            <CorrectionBanner
              v-else-if="isBankReturned"
              variant="bank_returned"
              :reviewer-comment="request.bank_return_comment"
            />
            <CorrectionBanner
              v-else-if="isSupportReturned"
              variant="support_returned"
              :support-comment="request.support_return_comment"
            />
            <CorrectionBanner v-else-if="isReturnedForCorrection" />
          </div>

          <!-- Duplicate invoice warning widget (AC7) -->
          <div v-if="showDuplicateWidget" class="dup-widget" data-testid="dup-widget">
            <div class="dup-widget-header">
              <div class="dup-widget-heading">
                <span class="dup-badge" data-testid="dup-badge">مكرر</span>
                <span class="dup-widget-title">فواتير مكررة ({{ duplicateWarnings.length }})</span>
              </div>
              <button
                type="button"
                class="dup-widget-toggle"
                :aria-expanded="duplicateWidgetExpanded"
                data-testid="dup-widget-toggle"
                @click="duplicateWidgetExpanded = !duplicateWidgetExpanded"
              >
                {{ duplicateWidgetExpanded ? 'إخفاء التفاصيل' : 'إظهار التفاصيل' }}
              </button>
            </div>
            <div v-if="duplicateWidgetExpanded" class="dup-widget-body" data-testid="dup-widget-body">
              <!-- Full view: CBY_ADMIN and SUPPORT_COMMITTEE -->
              <template v-if="duplicateWidgetFull">
                <table class="data-table dup-widget-table">
                  <thead>
                    <tr>
                      <th>الرقم المرجعي</th>
                      <th>البنك</th>
                      <th>المبلغ</th>
                      <th>العملة</th>
                      <th>التاريخ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="warn in duplicateWarnings" :key="warn.id">
                      <td>
                        <NuxtLink v-if="warn.id" :to="`/requests/${warn.id}`" style="color: var(--primary);" class="font-mono text-xs">
                          {{ warn.reference_number ?? '—' }}
                        </NuxtLink>
                        <span v-else class="font-mono text-xs">{{ warn.reference_number ?? '—' }}</span>
                      </td>
                      <td class="text-sm">{{ warn.bank_name ?? '—' }}</td>
                      <td class="text-sm font-mono">{{ warn.amount?.toLocaleString('ar') ?? '—' }}</td>
                      <td class="text-sm">{{ warn.currency ?? '—' }}</td>
                      <td class="text-xs text-muted-foreground">
                        {{ warn.created_at ? new Date(warn.created_at).toLocaleDateString('ar-YE') : '—' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </template>
              <!-- Restricted view: BANK_REVIEWER / BANK_ADMIN — count + bank names only -->
              <template v-else>
                <p class="dup-widget-summary" data-testid="dup-bank-summary">
                  مكرر مع: {{ duplicateBankNames.join('، ') }}
                </p>
              </template>
            </div>
          </div>

          <!-- Bank reviewer chip: re-submitted after support return (AC10) -->
          <div v-if="supportReturnHint" class="support-return-hint" role="note" dir="rtl">
            <span class="support-return-hint__icon" aria-hidden="true">🔄</span>
            <span class="support-return-hint__text">إعادة بعد عودة من المساندة</span>
            <span v-if="supportReturnHint.comment" class="support-return-hint__comment">— {{ supportReturnHint.comment }}</span>
            <button type="button" class="support-return-hint__link" @click="openSupportReturnHistory">
              عرض التعليق في السجل
            </button>
          </div>

          <!-- VotingPanel inline for executive roles in voting stages -->
          <div v-if="showVotingPanelInline" class="card card--no-padding voting-inline">
            <VotingPanel
              :request-id="id"
              :request-status="request.status"
              :user-role="userRole"
            />
          </div>

          <!-- Tab navigation -->
          <nav class="tab-nav" role="tablist" aria-label="تبويبات تفاصيل الطلب">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              role="tab"
              :aria-selected="activeTab === tab.key"
              :class="['tab-btn', { 'tab-btn--active': activeTab === tab.key }]"
              @click="onTabChange(tab.key)"
            >
              {{ tab.label }}
            </button>
          </nav>

          <!-- Tab panels -->
          <div class="tab-content">
            <!-- المعلومات tab -->
            <section v-if="activeTab === 'overview'" class="tab-panel" role="tabpanel" aria-label="المعلومات">
              <div class="card">
                <h2 class="card-title">بيانات الطلب</h2>
                <dl class="detail-grid">
                  <div class="detail-row">
                    <dt class="detail-label">نوع الواردات</dt>
                    <dd class="detail-value">{{ request.goods_type ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">المستورد</dt>
                    <dd class="detail-value">{{ request.merchant?.name ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">المبلغ</dt>
                    <dd class="detail-value">{{ formatAmount(request.amount, request.currency) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">البنك / الجهة</dt>
                    <dd class="detail-value">{{ request.bank_name ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">المورّد</dt>
                    <dd class="detail-value">{{ request.supplier_name }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">رقم الفاتورة</dt>
                    <dd class="detail-value">{{ request.invoice_number ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">ميناء الوصول</dt>
                    <dd class="detail-value">{{ request.arrival_port ?? request.port_of_entry }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">تاريخ التقديم</dt>
                    <dd class="detail-value">{{ formatDate(request.submitted_at) }}</dd>
                  </div>
                </dl>
              </div>

              <div v-if="request.customs_declaration" class="card customs-card">
                <div class="customs-card__header">
                  <h2 class="card-title customs-card__title">البيان الجمركي</h2>
                  <div class="customs-card__actions">
                    <a
                      v-if="[UserRole.COMMITTEE_DIRECTOR, UserRole.CBY_ADMIN, UserRole.BANK_REVIEWER].includes(userRole)"
                      :href="`/requests/${id}/customs-preview`"
                      class="customs-preview-link"
                    >
                      معاينة البيان
                    </a>
                    <button
                      v-if="canDownloadCustomsDeclaration"
                      class="customs-download"
                      :disabled="requestsStore.downloadingCustoms"
                      @click="downloadCustomsDeclaration"
                    >
                      {{ requestsStore.downloadingCustoms ? 'جارٍ التحميل…' : 'تحميل PDF' }}
                    </button>
                    <!-- BANK_ADMIN: locked FX PDF row — visible but not downloadable -->
                    <span
                      v-else-if="userRole === UserRole.BANK_ADMIN"
                      class="fx-pdf-locked"
                      :title="'تحميل PDF مخصص لمسؤول البنك المركزي والمديرين الموافقين فقط'"
                      aria-disabled="true"
                      data-testid="fx-pdf-locked"
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                      </svg>
                      تحميل PDF (مقيّد)
                    </span>
                  </div>
                </div>
                <dl class="detail-grid">
                  <div class="detail-row">
                    <dt class="detail-label">رقم البيان</dt>
                    <dd class="detail-value">{{ request.customs_declaration.declaration_number }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">تاريخ الإصدار</dt>
                    <dd class="detail-value">{{ formatDate(request.customs_declaration.issued_at) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">أصدره</dt>
                    <dd class="detail-value">{{ request.customs_declaration.issuer?.name ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">الحالة</dt>
                    <dd class="detail-value detail-value--approved">مكتمل</dd>
                  </div>
                </dl>
                <p v-if="customsDownloadError" class="docs-error" role="alert">
                  {{ customsDownloadError }}
                </p>
              </div>
            </section>

            <!-- الوثائق tab -->
            <section v-else-if="activeTab === 'documents'" class="tab-panel" role="tabpanel" aria-label="الوثائق">
              <div class="card">
                <div class="flex items-center justify-between gap-3 mb-3">
                  <h2 class="card-title">المستندات المرفوعة</h2>
                  <NuxtLink
                    v-if="showSwiftUploadShortcut"
                    :to="`/requests/${id}/swift`"
                    class="text-xs font-semibold text-[#32ade6] underline"
                  >
                    رفع وثائق السويفت
                  </NuxtLink>
                </div>
                <DocumentChecklist
                  :documents="requestsStore.documents"
                  :customs-declaration="request.customs_declaration ?? null"
                  :user-role="userRole"
                  :request-status="request.status"
                  :loading="requestsStore.loadingDocuments"
                  :error="requestsStore.documentsError"
                  :uploading-document="requestsStore.uploading"
                  :upload-error="requestsStore.uploadError"
                  :downloading-ids="downloadingIds"
                  :download-errors="downloadErrors"
                  :customs-downloading="requestsStore.downloadingCustoms"
                  :customs-download-error="checklistCustomsDownloadError"
                  @download="downloadDocument"
                  @download-customs="handleDownloadCustoms"
                  @upload="handleUploadDocument"
                />
                <div
                  v-if="showSwiftFxLockedRow"
                  class="mt-3 flex items-center justify-between gap-2 rounded-lg border border-[var(--locked)]/40 bg-[var(--locked)]/10 px-3 py-2 text-[#3f3f46]"
                >
                  <div class="flex items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span class="text-xs font-medium">نموذج تأكيد المصارفة الخارجية</span>
                  </div>
                  <span class="text-xs" title="مخصص لمدير اللجنة التنفيذية.">مقيّد</span>
                </div>
              </div>
            </section>

            <section
              v-else-if="activeTab === 'fx_confirmation' && showDirectorFxTab"
              class="tab-panel"
              role="tabpanel"
              aria-label="تأكيد المصارفة"
            >
              <div class="card space-y-4">
                <h2 class="card-title">تأكيد المصارفة الخارجية</h2>

                <div class="rounded-lg border border-border p-3 space-y-2">
                  <p class="text-sm font-semibold">الخطوة 1 — تحميل نموذج التأكيد</p>
                  <p class="text-xs text-muted-foreground">قم بتنزيل النموذج النظامي المولّد للطلب الحالي.</p>
                  <Button
                    :disabled="fxGeneratingTemplate"
                    class="bg-primary text-white"
                    @click="handleDownloadFxTemplate"
                  >
                    {{ fxGeneratingTemplate ? 'جارٍ التحميل…' : 'تحميل نموذج تأكيد المصارفة الخارجية' }}
                  </Button>
                  <p v-if="fxTemplateChecksum" class="text-xs text-muted-foreground break-all">
                    SHA-256: {{ fxTemplateChecksum }}
                  </p>
                </div>

                <div class="rounded-lg border border-border p-3 space-y-2 bg-muted">
                  <p class="text-sm font-semibold">الخطوة 2 — التوقيع الخارجي</p>
                  <p class="text-xs text-muted-foreground">قم بتوقيع وختم النموذج خارجياً ثم ارفعه في الخطوة التالية.</p>
                </div>

                <div class="rounded-lg border border-border p-3 space-y-3">
                  <p class="text-sm font-semibold">الخطوة 3 — رفع النموذج الموقّع والإتمام</p>
                  <input
                    id="fx-signed-upload"
                    type="file"
                    accept="application/pdf"
                    class="sr-only"
                    @change="handleFxSignedFileChange"
                  >
                  <div class="flex items-center gap-2">
                    <Button variant="outline" @click="triggerFxSignedUpload">اختيار PDF موقّع</Button>
                    <span class="text-xs text-muted-foreground" v-if="!fxSignedFile">لم يتم اختيار ملف بعد</span>
                    <span class="text-xs text-foreground" v-else>{{ fxSignedFile.name }}</span>
                  </div>
                  <p v-if="fxSignedChecksum" class="text-xs text-muted-foreground break-all">
                    SHA-256: {{ fxSignedChecksum }}
                  </p>
                  <Button
                    :disabled="!fxSignedFile || fxCompleting"
                    class="bg-green-600 text-white hover:bg-green-700"
                    @click="handleCompleteFxConfirmation"
                  >
                    {{ fxCompleting ? 'جارٍ الإتمام…' : 'إتمام تأكيد المصارفة' }}
                  </Button>
                  <p v-if="fxFlowError" class="text-xs text-red-700">{{ fxFlowError }}</p>
                  <p v-if="fxFlowSuccess" class="text-xs text-green-700">تم إتمام تأكيد المصارفة بنجاح.</p>
                </div>
              </div>
            </section>

            <!-- الأطراف tab: actors + workflow timeline + audit trail -->
            <section v-else-if="activeTab === 'parties'" class="tab-panel" role="tabpanel" aria-label="الأطراف">
              <div class="card">
                <h2 class="card-title">فريق العمل</h2>
                <dl class="detail-grid">
                  <div class="detail-row">
                    <dt class="detail-label">أنشأ الطلب</dt>
                    <dd class="detail-value">{{ actorLabel(request.created_by_user, request.created_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">قدّم الطلب</dt>
                    <dd class="detail-value">{{ actorLabel(request.submitted_by_user, request.submitted_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">المراجع الداخلي</dt>
                    <dd class="detail-value">{{ actorLabel(request.reviewed_by_user, request.reviewed_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">وافق</dt>
                    <dd class="detail-value">{{ actorLabel(request.approved_by_user, request.approved_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">مراجع لجنة الدعم</dt>
                    <dd class="detail-value">{{ actorLabel(request.support_reviewed_by_user, request.support_reviewed_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">المراجع الحالي</dt>
                    <dd class="detail-value">{{ request.claimed_by?.name ?? '—' }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">رفع SWIFT</dt>
                    <dd class="detail-value">{{ actorLabel(request.swift_uploaded_by_user, request.swift_uploaded_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">رفض الطلب</dt>
                    <dd class="detail-value">{{ actorLabel(request.rejected_by_user, request.rejected_by) }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt class="detail-label">أعاد التقديم</dt>
                    <dd class="detail-value">{{ actorLabel(request.resubmitted_by_user, request.resubmitted_by) }}</dd>
                  </div>
                  <div v-if="request.customs_declaration?.issuer" class="detail-row detail-row--customs">
                    <dt class="detail-label">مكتب الجمارك (مُصدِر البيان)</dt>
                    <dd class="detail-value">{{ request.customs_declaration.issuer.name }}</dd>
                  </div>
                </dl>
              </div>

              <div class="card">
                <h2 class="card-title">مسار سير العمل</h2>

                <div v-if="requestsStore.loadingHistory" class="space-y-3" aria-busy="true">
                  <Skeleton class="h-5 w-full" />
                  <Skeleton class="h-5 w-full" />
                  <Skeleton class="h-5 w-2/3" />
                </div>

                <p v-else-if="requestsStore.historyError" class="history-error" role="alert">
                  {{ requestsStore.historyError }}
                </p>

                <WorkflowTimeline
                  v-else
                  :current-status="request.status"
                  :history="requestsStore.history"
                />
              </div>

            </section>

            <!-- Activity log tab -->
            <section v-else-if="activeTab === 'activity_log'" class="tab-panel" role="tabpanel" aria-label="سجل الأحداث">
              <div id="audit-trail" class="card">
                <h2 class="card-title">سجل الأحداث</h2>

                <div v-if="requestsStore.loadingHistory" class="space-y-3" aria-busy="true">
                  <Skeleton class="h-5 w-full" />
                  <Skeleton class="h-5 w-full" />
                </div>

                <p v-else-if="requestsStore.historyError" class="history-error" role="alert">
                  {{ requestsStore.historyError }}
                </p>

                <AuditTimeline
                  v-else
                  :entries="requestsStore.history"
                  :user-role="userRole"
                />
              </div>
            </section>
          </div>
        </div>

        <!-- Right rail (1/3) -->
        <aside class="detail-rail">
          <!-- Workflow progress -->
          <div class="rail-card">
            <WorkflowProgress
              :current-status="request.status"
              :user-role="userRole"
            />
          </div>

          <!-- Available actions -->
          <div v-if="hasActions" class="rail-card rail-card--actions">
            <p class="rail-card__title">إجراءات متاحة لك</p>
            <ActionsPanel
              :request="request"
              :user-role="userRole"
              @action-completed="onActionCompleted"
            />
          </div>

          <div v-if="showSwiftActionCard" class="rail-card">
            <p class="rail-card__title">إجراءات السويفت</p>
            <template v-if="request.status === RequestStatus.WAITING_FOR_SWIFT">
              <NuxtLink :to="`/requests/${id}/swift`" class="text-sm font-semibold text-[#32ade6] underline">
                رفع وثائق السويفت
              </NuxtLink>
            </template>
            <template v-else-if="request.status === RequestStatus.EXECUTIVE_APPROVED">
              <p class="text-sm text-muted-foreground" title="سيتم التفعيل عند انتقال الطلب لمرحلة انتظار السويفت.">
                في انتظار الإتاحة
              </p>
            </template>
            <template v-else>
              <p class="text-sm text-muted-foreground">
                تم تسليم السويفت — لا توجد إجراءات إضافية في هذه المرحلة.
              </p>
            </template>
          </div>

          <!-- BANK_ADMIN: read-only informational status panel (no decision buttons) -->
          <div v-if="showBankAdminInfoPanel" class="rail-card">
            <p class="rail-card__title">حالة الطلب</p>
            <p class="text-sm text-muted-foreground leading-relaxed">{{ bankAdminInfoText }}</p>
          </div>

          <!-- Quick info -->
          <div class="rail-card">
            <p class="rail-card__title">معلومات سريعة</p>
            <ul class="quick-info-list">
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">المُنشئ</span>
                  <span class="quick-info-value">{{ actorLabel(request.created_by_user, request.created_by) }}</span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                    <line x1="8" y1="21" x2="16" y2="21" /><line x1="12" y1="17" x2="12" y2="21" />
                  </svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">البنك</span>
                  <span class="quick-info-value">{{ request.bank_name ?? '—' }}</span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">تاريخ التقديم</span>
                  <span class="quick-info-value">{{ formatDate(request.submitted_at) }}</span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                    <circle cx="12" cy="10" r="3" />
                  </svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">ميناء الوصول</span>
                  <span class="quick-info-value">{{ request.arrival_port ?? request.port_of_entry }}</span>
                </div>
              </li>
            </ul>
          </div>

          <!-- Back link -->
          <NuxtLink to="/requests" class="rail-back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <polyline points="15 18 9 12 15 6" />
            </svg>
            العودة إلى قائمة الطلبات
          </NuxtLink>
        </aside>
      </div>
      </div>
    </template>

    <AlertDialog :open="showCloneDialog" @update:open="handleCloneDialogOpenChange">
      <AlertDialogContent dir="rtl">
        <AlertDialogHeader>
          <AlertDialogTitle>نسخ وإعادة إرسال</AlertDialogTitle>
          <AlertDialogDescription class="clone-dialog__body">
            سيتم إنشاء طلب جديد بنفس بياناتك. متابعة؟
          </AlertDialogDescription>
        </AlertDialogHeader>
        <p v-if="cloneError" class="clone-dialog__error" role="alert">{{ cloneError }}</p>
        <AlertDialogFooter class="clone-dialog__actions">
          <AlertDialogCancel :disabled="cloneLoading" data-testid="clone-cancel-btn">
            إلغاء
          </AlertDialogCancel>
          <Button :disabled="cloneLoading" data-testid="clone-confirm-btn" @click="handleCloneConfirm">
            {{ cloneLoading ? 'جارٍ الإنشاء…' : 'متابعة' }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>

<style scoped>
.detail-page {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 100%;
  padding: 24px 0;
  direction: rtl;
}

/* Breadcrumbs */
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  font-size: 12px;
  color: var(--muted-foreground);
  padding: 0 16px;
}

.breadcrumb-link {
  color: var(--muted-foreground);
  text-decoration: none;
  transition: color 0.15s;
  font-weight: 500;
}

.breadcrumb-link:hover {
  color: var(--primary);
}

.breadcrumb-sep {
  color: var(--border);
  opacity: 0.5;
}

.breadcrumb-current {
  color: var(--foreground);
  font-weight: 600;
}

/* Page header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
  padding: 0 16px;
}

.page-header__main {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.page-header__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: var(--foreground);
  margin: 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 13px;
  color: var(--muted-foreground);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}

.subtitle-dot {
  color: var(--border);
  opacity: 0.5;
}

.print-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--background);
  color: var(--foreground);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.print-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--background);
  color: var(--foreground);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.download-btn:hover:not(:disabled) {
  border-color: var(--primary);
  color: var(--primary);
}

.download-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Two-column layout */
.detail-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  align-items: start;
  padding: 0 16px;
}

@media (min-width: 1024px) {
  .detail-layout {
    grid-template-columns: 2fr 1fr;
  }
}

.detail-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Banners */
.banner-area {
  margin-bottom: 16px;
}

.support-return-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: color-mix(in srgb, var(--primary) 8%, transparent);
  border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
  border-radius: 12px;
  color: var(--primary);
  font-size: 13px;
  margin-bottom: 16px;
  font-weight: 500;
}

.support-return-hint__icon {
  font-size: 16px;
  flex-shrink: 0;
}

.support-return-hint__comment {
  font-size: 13px;
  color: var(--primary);
  font-weight: 400;
}

.support-return-hint__link {
  margin-inline-start: auto;
  border: 0;
  background: transparent;
  color: var(--primary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

.support-return-hint__link:hover {
  color: var(--primary);
}

.dup-widget {
  border: 1px solid color-mix(in srgb, var(--warning) 33%, transparent);
  border-radius: 12px;
  overflow: hidden;
  background: color-mix(in srgb, var(--warning) 6%, var(--background));
  margin-bottom: 16px;
}

.dup-widget-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 16px;
  background: color-mix(in srgb, var(--warning) 12%, var(--background));
  border-bottom: 1px solid color-mix(in srgb, var(--warning) 20%, transparent);
}

.dup-widget-heading {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dup-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--background);
  background: var(--warning);
  border-radius: 6px;
  padding: 3px 8px;
  text-transform: uppercase;
  letter-spacing: 0.2px;
}

.dup-widget-title {
  font-size: 13px;
  font-weight: 600;
  color: color-mix(in srgb, var(--warning) 70%, var(--foreground));
  letter-spacing: -0.2px;
}

.dup-widget-toggle {
  border: none;
  background: none;
  color: color-mix(in srgb, var(--warning) 70%, var(--foreground));
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  padding: 0;
  transition: opacity 0.15s;
}

.dup-widget-toggle:hover {
  opacity: 0.8;
}

.dup-widget-body {
  padding: 12px 16px;
}

.dup-widget-table {
  margin: 0;
}

.dup-widget-summary {
  font-size: 13px;
  color: var(--muted-foreground);
  margin: 0;
  font-weight: 500;
}

.claim-error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: color-mix(in srgb, var(--destructive) 8%, var(--background));
  border: 1px solid color-mix(in srgb, var(--destructive) 20%, transparent);
  border-radius: 12px;
  font-size: 13px;
  font-weight: 500;
  color: var(--destructive);
}

.claim-error-icon {
  display: flex;
  flex-shrink: 0;
  color: var(--destructive);
}

/* Inline voting panel */
.voting-inline {
  margin-bottom: 16px;
}

/* Tabs */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  margin-top: 20px;
}

.tab-btn {
  height: 44px;
  padding: 0 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 14px;
  color: var(--muted-foreground);
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  font-weight: 500;
}

.tab-btn--active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  font-weight: 600;
}

.tab-btn:hover:not(.tab-btn--active) {
  color: var(--foreground);
}

/* Tab content */
.tab-content {
  padding-top: 16px;
}

.tab-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Cards */
.card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
}

.card--no-padding {
  padding: 0;
  overflow: hidden;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--foreground);
  margin: 0 0 16px 0;
  letter-spacing: -0.3px;
}

/* Detail grid — Lovable field order, 2 columns */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin: 0;
  gap: 16px 12px;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 0;
  border-bottom: none;
}

.detail-row--customs {
  border-inline-start: 2px solid var(--muted-foreground);
  padding-inline-start: 10px;
}

.detail-label {
  font-size: 11px;
  color: var(--muted-foreground);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  opacity: 0.7;
}

.detail-value {
  font-size: 14px;
  color: var(--foreground);
  word-break: break-word;
  font-weight: 500;
  line-height: 1.5;
}

.detail-value--approved {
  color: var(--success);
  font-weight: 600;
}

.customs-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.customs-card__title {
  margin-bottom: 0;
}

.customs-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.customs-preview-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 14px;
  border: 1px solid var(--primary);
  border-radius: 12px;
  background: transparent;
  color: var(--primary);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, opacity 0.15s;
}

.customs-preview-link:hover {
  background: color-mix(in srgb, var(--primary) 8%, transparent);
}

.customs-download {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 12px;
  background: var(--primary);
  color: var(--primary-foreground);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}

.customs-download:hover:not(:disabled) {
  opacity: 0.88;
}

.customs-download:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fx-pdf-locked {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 16px;
  border-radius: 12px;
  background: #f5f5f7;
  color: var(--locked);
  font-size: 13px;
  font-weight: 600;
  cursor: not-allowed;
  user-select: none;
}

.docs-error {
  color: var(--destructive);
  font-size: 13px;
  text-align: center;
  padding: 12px 0 0;
  font-weight: 500;
}

.history-error {
  color: var(--destructive);
  font-size: 13px;
  text-align: center;
  padding: 24px 0;
  font-weight: 500;
}

/* Right rail */
.detail-rail {
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: sticky;
  top: 24px;
}

.rail-card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
}

.rail-card--actions {
  padding-bottom: 8px;
}

.rail-card__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--foreground);
  margin: 0 0 12px 0;
  letter-spacing: -0.2px;
}

/* Quick info list */
.quick-info-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.quick-info-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.quick-info-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 10%, transparent);
  color: var(--primary);
  flex-shrink: 0;
  margin-top: 1px;
}

.quick-info-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.quick-info-label {
  font-size: 11px;
  color: var(--muted-foreground);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  opacity: 0.7;
}

.quick-info-value {
  font-size: 13px;
  color: var(--foreground);
  font-weight: 500;
  word-break: break-word;
  line-height: 1.4;
}

/* Back link in rail */
.rail-back-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 12px 16px;
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  color: var(--muted-foreground);
  font-size: 13px;
  text-decoration: none;
  transition: color 0.15s, border-color 0.15s, background 0.15s;
  font-weight: 500;
}

.rail-back-link:hover {
  color: var(--primary);
  border-color: var(--primary);
}

@media (max-width: 600px) {
  .detail-page {
    padding: 16px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .customs-card__header {
    align-items: stretch;
    flex-direction: column;
  }

  .page-header {
    flex-direction: column;
  }

  .detail-rail {
    position: static;
  }
}

@media print {
  .breadcrumbs,
  .download-btn,
  .tab-nav,
  .banner-area,
  .customs-download,
  .customs-preview-link,
  .detail-rail {
    display: none !important;
  }

  .detail-layout {
    grid-template-columns: 1fr;
  }

  .detail-page,
  .tab-content,
  .tab-panel,
  .card {
    border: 0;
    padding: 0;
    box-shadow: none;
  }
}

/* Clone button */
.clone-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid var(--primary);
  border-radius: 12px;
  background: var(--background);
  color: var(--primary);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.clone-btn:hover:not(:disabled) {
  background: var(--primary);
  color: var(--primary-foreground);
}

.clone-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.clone-dialog__body {
  font-size: 14px;
  color: var(--muted-foreground);
  line-height: 1.6;
  font-weight: 500;
}

.clone-dialog__error {
  font-size: 13px;
  color: var(--destructive);
  font-weight: 500;
}

.clone-dialog__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
</style>
