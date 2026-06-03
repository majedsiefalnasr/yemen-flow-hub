<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus, VoteType } from '@/types/enums'
import {
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Copy,
  FilePen,
  Keyboard,
  Link,
  Lock,
  Printer,
  RotateCcw,
  ShieldCheck,
} from 'lucide-vue-next'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'
import { useRequests } from '@/composables/useRequests'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useVotingStore } from '@/stores/voting.store'
import { useClaimLifecycle } from '@/composables/useClaimLifecycle'
import { canDownloadCustoms } from '@/composables/useDocumentPermissions'
import { STATUS_LABELS, ROLE_LABELS } from '@/constants/workflow'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import LockedBanner from '@/components/banners/LockedBanner.vue'
import CorrectionBanner from '@/components/banners/CorrectionBanner.vue'
import ActiveReviewBanner from '@/components/banners/ActiveReviewBanner.vue'
import ClaimedByOthersBanner from '@/components/banners/ClaimedByOthersBanner.vue'
import SegregationBlockedBanner from '@/components/banners/SegregationBlockedBanner.vue'
import UnclaimedBanner from '@/components/banners/UnclaimedBanner.vue'
import VotingPendingBanner from '@/components/banners/VotingPendingBanner.vue'
import VotedConfirmationBanner from '@/components/banners/VotedConfirmationBanner.vue'
import ActionsPanel from '@/components/requests/ActionsPanel.vue'
import DocumentChecklist from '@/components/requests/DocumentChecklist.vue'
import FxConfirmationCard from '@/components/requests/FxConfirmationCard.vue'
import VotingPanel from '@/components/voting/VotingPanel.vue'
import WorkflowTimeline from '@/components/workflow/WorkflowTimeline.vue'
import AuditTimeline from '@/components/workflow/AuditTimeline.vue'
import WorkflowProgress from '@/components/workflow/WorkflowProgress.vue'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { useKeyboardShortcut } from '@/composables/useKeyboardShortcut'

definePageMeta({
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const requestsStore = useRequestsStore()

function parseRouteRequestId(): number {
  const rawId = route.params.id
  return Number(Array.isArray(rawId) ? rawId[0] : rawId)
}

const id = computed(() => parseRouteRequestId())

const votingStore = useVotingStore()

// Template ref for ActionsPanel to allow keyboard shortcut passthrough
const actionsPanelRef = ref<InstanceType<typeof ActionsPanel> | null>(null)

// Shortcut legend dialog
const showShortcutLegend = ref(false)

// Wire keyboard shortcuts for the detail page
useKeyboardShortcut({
  'ctrl+enter': {
    description: 'تنفيذ الإجراء الرئيسي',
    handler: () => actionsPanelRef.value?.triggerPrimaryAction(),
  },
  '?': {
    description: 'عرض اختصارات لوحة المفاتيح',
    handler: () => { showShortcutLegend.value = !showShortcutLegend.value },
  },
  'alt+arrowleft': {
    description: 'الطلب التالي',
    handler: () => { void navigateAdjacentRequest('next') },
  },
  'alt+arrowright': {
    description: 'الطلب السابق',
    handler: () => { void navigateAdjacentRequest('prev') },
  },
})

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

// Prev/next navigation within the loaded list page, with absolute pagination counts.
const listIds = computed(() => requestsStore.listIds)
const listPosition = computed(() => listIds.value.indexOf(id.value))
const prevRequestId = computed(() => listPosition.value > 0 ? listIds.value[listPosition.value - 1] : null)
const nextRequestId = computed(() => listPosition.value > -1 && listPosition.value < listIds.value.length - 1 ? listIds.value[listPosition.value + 1] : null)
const navigationLoading = ref(false)
const navigationPosition = computed(() => {
  if (listPosition.value < 0) return null
  const meta = requestsStore.meta
  if (!meta) return listPosition.value + 1
  return ((meta.current_page - 1) * meta.per_page) + listPosition.value + 1
})
const navigationTotal = computed(() => requestsStore.meta?.total ?? listIds.value.length)
const hasPrevRequest = computed(() => Boolean(prevRequestId.value) || requestsStore.hasPrevPage)
const hasNextRequest = computed(() => Boolean(nextRequestId.value) || requestsStore.hasNextPage)

async function navigateAdjacentRequest(direction: 'prev' | 'next') {
  if (navigationLoading.value) return

  const adjacentId = direction === 'prev' ? prevRequestId.value : nextRequestId.value
  if (adjacentId) {
    await router.push(`/requests/${adjacentId}`)
    return
  }

  const meta = requestsStore.meta
  if (!meta) return

  const targetPage = direction === 'prev' ? meta.current_page - 1 : meta.current_page + 1
  if (targetPage < 1 || targetPage > meta.last_page) return

  navigationLoading.value = true
  try {
    await requestsStore.loadRequests({
      ...requestsStore.currentFilter,
      page: targetPage,
      per_page: meta.per_page,
    })

    const targetId = direction === 'prev'
      ? requestsStore.listIds.at(-1)
      : requestsStore.listIds[0]

    if (targetId) await router.push(`/requests/${targetId}`)
  }
  finally {
    navigationLoading.value = false
  }
}

function copyCurrentLink() {
  if (!import.meta.client) return
  void navigator.clipboard?.writeText(window.location.href)
}

// BANK_REVIEWER may not review requests they personally created (segregation of duties)
const isSegregationBlocked = computed(() =>
  userRole.value === UserRole.BANK_REVIEWER
  && !!request.value
  && !!auth.user
  && request.value.created_by === auth.user.id,
)

// BANK_REVIEWER sees a dedicated follow-up banner when support has rejected the request
const showBankReviewerSupportRejectedBanner = computed(() =>
  userRole.value === UserRole.BANK_REVIEWER
  && !isSegregationBlocked.value
  && request.value?.status === RequestStatus.SUPPORT_REJECTED,
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
  && (
    request.value?.status === RequestStatus.EXECUTIVE_APPROVED
    || request.value?.status === RequestStatus.FX_CONFIRMATION_PENDING
  ),
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
  // BANK_REVIEWER handles SUPPORT_REJECTED via dedicated follow-up panel — not a locked state for them
  if (userRole.value === UserRole.BANK_REVIEWER && s === RequestStatus.SUPPORT_REJECTED) return null
  if (TERMINAL_STATUSES.has(s)) return 'locked'
  if (userRole.value === UserRole.BANK_REVIEWER && ACTIONABLE_REVIEWER_STATUSES.has(s)) return null
  // Executive roles viewing voting stages have full access — no banner
  if (EXECUTIVE_ROLES.has(userRole.value) && VOTING_STAGE_STATUSES.has(s)) return null
  // SUPPORT_COMMITTEE with an active claim on this request is in working state — not locked/pending
  if (
    userRole.value === UserRole.SUPPORT_COMMITTEE
    && s === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
    && request.value?.is_claimed_by_me
  ) return null
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

// BANK_ADMIN sees a locked placeholder row for the external FX confirmation PDF
// once the request reaches the FX/completion stage — download is denied by policy
const FX_STAGE_STATUSES = new Set([
  RequestStatus.FX_CONFIRMATION_PENDING,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])
const showBankAdminFxLockedRow = computed(() =>
  userRole.value === UserRole.BANK_ADMIN
  && !!request.value
  && FX_STAGE_STATUSES.has(request.value.status),
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
  const roleLabel = responsibleRole ? ROLE_LABELS[responsibleRole] : 'غير محدد'
  return `الطلب حاليا في مرحلة ${stage}، والمسؤول عنها: ${roleLabel}`
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
    && (
      s === RequestStatus.SUBMITTED
      || (s === RequestStatus.BANK_REVIEW && !!request.value?.is_claimed_by_me)
      || s === RequestStatus.SUPPORT_REJECTED
    )
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
  return bankReviewerAction || dataEntryAction || supportAction || directorVotingAction
})

const isDirectorCustomsPhase = computed(() =>
  userRole.value === UserRole.COMMITTEE_DIRECTOR
  && !!request.value
  && (
    request.value.status === RequestStatus.EXECUTIVE_APPROVED
    || request.value.status === RequestStatus.FX_CONFIRMATION_PENDING
  ),
)

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

// Claim lifecycle for BANK_REVIEWER (separate instance, different endpoint)
const {
  claimRequest: claimBankRequest,
  releaseRequest: releaseBankRequest,
  verifyClaimAlive: verifyBankClaimAlive,
  startHeartbeat: startBankHeartbeat,
  stopHeartbeat: stopBankHeartbeat,
  claimError: bankClaimError,
  sessionExpired: bankSessionExpired,
} = useClaimLifecycle('claim-bank-review')

const isActiveReviewer = ref(false)
const isBankActiveReviewer = ref(false)
// Local guard against double-clicks on "مطالبة" / "إفراج" before the previous
// call resolves; prevents duplicate POST/DELETE and the resulting orphan claim.
const claimMutating = ref(false)

const isCbyAdmin = computed(() => userRole.value === UserRole.CBY_ADMIN)
const isSupportCommittee = computed(() => userRole.value === UserRole.SUPPORT_COMMITTEE)
const isBankReviewer = computed(() => userRole.value === UserRole.BANK_REVIEWER)

const claimErrorRetryable = computed(() => {
  if (!claimError.value) return false
  if (sessionExpired.value) return false
  return !claimError.value.includes('محجوز')
})
const isExecutiveMember = computed(() => userRole.value === UserRole.EXECUTIVE_MEMBER)

// CBY Admin: derive blocker, SLA state, and age for intelligence panel
const cbyAgeHours = computed(() => {
  if (!request.value) return 0
  return (Date.now() - new Date(request.value.created_at).getTime()) / 3600000
})

const cbySlaState = computed(() => {
  const hrs = cbyAgeHours.value
  if (hrs > 120) return { label: 'انتهاك SLA', color: 'var(--severity-red)' }
  if (hrs > 72) return { label: 'خطر SLA', color: 'var(--severity-amber)' }
  return { label: 'ضمن SLA', color: 'var(--severity-green)' }
})

const cbyBlockerText = computed(() => {
  if (!request.value) return null
  const s = request.value.status
  const responsible = STATUS_RESPONSIBLE_ROLE[s]
  if (!responsible) return null
  const roleLabel = ROLE_LABELS[responsible] ?? responsible
  return `قيد انتظار إجراء من: ${roleLabel}`
})
const votingDetailLoadedForCurrentRequest = computed(() =>
  votingStore.votingDetail?.request?.id === id.value,
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

// BANK_REVIEWER viewing a BANK_REVIEW request they don't hold
const showBankClaimedByOthersBanner = computed(() => {
  if (!isBankReviewer.value || isBankActiveReviewer.value) return false
  const req = request.value
  return !!req && req.status === RequestStatus.BANK_REVIEW && !req.is_claimed_by_me
})

// Auto-claim replaces manual claim — this banner now shows only while the claim
// call is in-flight (brief transitional state, no action button needed).
const showUnclaimedBanner = computed(() => {
  if (!isSupportCommittee.value || isActiveReviewer.value) return false
  const req = request.value
  return !!req && req.status === RequestStatus.SUPPORT_REVIEW_PENDING && !req.is_claimed
})

// Destruction guard: set to false in onBeforeUnmount so any in-flight async
// continuations after unmount do not mutate component state or start timers.
let isMounted = false

// Poll for claim state changes when another reviewer may have just claimed this
// request. Fires every 15 s while the request is SUBMITTED or BANK_REVIEW and
// the current user is not the claimer — so colleagues see the lock without refreshing.
const CLAIM_POLL_STATUSES = new Set([RequestStatus.SUBMITTED, RequestStatus.BANK_REVIEW])
let claimPollTimer: ReturnType<typeof setInterval> | null = null

function startClaimPoll() {
  stopClaimPoll()
  claimPollTimer = setInterval(async () => {
    if (!isMounted) return
    const req = request.value
    if (!req || !CLAIM_POLL_STATUSES.has(req.status) || req.is_claimed_by_me) {
      stopClaimPoll()
      return
    }
    await requestsStore.loadRequest(req.id)
  }, 15_000)
}

function stopClaimPoll() {
  if (claimPollTimer !== null) {
    clearInterval(claimPollTimer)
    claimPollTimer = null
  }
}

async function handleSessionExpired() {
  isActiveReviewer.value = false
  const validId = Number.isNaN(id.value) ? (requestsStore.currentRequest?.id ?? null) : id.value
  if (validId !== null) stopHeartbeat(validId)
  await navigateTo('/login')
}

async function handleClaimLost() {
  isActiveReviewer.value = false
  const validId = Number.isNaN(id.value) ? (requestsStore.currentRequest?.id ?? null) : id.value
  if (validId !== null) stopHeartbeat(validId)
  if (isMounted && validId !== null) {
    await requestsStore.loadRequest(validId)
  }
}

async function handleManualClaim() {
  if (claimMutating.value) return
  claimMutating.value = true
  try {
    const claimed = await claimRequest(id.value)
    if (!isMounted) return
    if (!claimed) {
      await requestsStore.loadRequest(id.value)
      return
    }

    isActiveReviewer.value = true
    startHeartbeat(id.value, handleSessionExpired, handleClaimLost)
    await requestsStore.loadRequest(id.value)
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
    const released = await releaseRequest(id.value)
    if (!isMounted) return
    if (!released) {
      await requestsStore.loadRequest(id.value)
      return
    }
    isActiveReviewer.value = false
    stopHeartbeat(id.value)
    await requestsStore.loadRequest(id.value)
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
    stopHeartbeat(id.value)
  }
}

onMounted(async () => {
  isMounted = true

  if (Number.isNaN(id.value)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(id.value)

  if (!isMounted) return

  if (requestsStore.error || !requestsStore.currentRequest) {
    await router.replace('/requests')
    return
  }

  // Auto-claim for BANK_REVIEWER on SUBMITTED requests
  if (isBankReviewer.value && requestsStore.currentRequest) {
    const req = requestsStore.currentRequest

    if (req.is_claimed && req.is_claimed_by_me && req.status === RequestStatus.BANK_REVIEW) {
      // Resume existing bank claim — verify TTL still alive
      const alive = await verifyBankClaimAlive(id.value)
      if (!isMounted) return
      if (alive) {
        isBankActiveReviewer.value = true
        startBankHeartbeat(
          id.value,
          async () => { isBankActiveReviewer.value = false; await navigateTo('/login') },
          async () => { isBankActiveReviewer.value = false; stopBankHeartbeat(id.value); if (isMounted) await requestsStore.loadRequest(id.value) },
        )
      }
      else if (bankSessionExpired.value) {
        await navigateTo('/login')
        return
      }
    }
    else if (req.status === RequestStatus.SUBMITTED) {
      // Auto-claim: opens a SUBMITTED request — claim it atomically
      const claimed = await claimBankRequest(id.value)
      if (!isMounted) return
      if (claimed) {
        isBankActiveReviewer.value = true
        startBankHeartbeat(
          id.value,
          async () => { isBankActiveReviewer.value = false; await navigateTo('/login') },
          async () => { isBankActiveReviewer.value = false; stopBankHeartbeat(id.value); if (isMounted) await requestsStore.loadRequest(id.value) },
        )
        await requestsStore.loadRequest(id.value)
      }
      else if (bankSessionExpired.value) {
        await navigateTo('/login')
        return
      }
      else {
        // Claimed by another reviewer — refresh to show current state
        await requestsStore.loadRequest(id.value)
      }
    }
  }

  // Auto-claim lifecycle for SUPPORT_COMMITTEE
  if (isSupportCommittee.value && requestsStore.currentRequest) {
    const req = requestsStore.currentRequest

    if (req.is_claimed && req.is_claimed_by_me) {
      // Resume an existing claim — verify the Redis TTL is still alive.
      const alive = await verifyClaimAlive(id.value)

      if (!isMounted) return

      if (alive) {
        isActiveReviewer.value = true
        startHeartbeat(id.value, handleSessionExpired, handleClaimLost)
      }
      else {
        await requestsStore.loadRequest(id.value)
        if (!isMounted) return

        if (sessionExpired.value) {
          await handleSessionExpired()
          return
        }
      }
    }
    else if (req.status === RequestStatus.SUPPORT_REVIEW_PENDING && !req.is_claimed) {
      // Auto-claim: the request is unclaimed and this user opened it — claim silently.
      const claimed = await claimRequest(id.value)
      if (!isMounted) return

      if (claimed) {
        isActiveReviewer.value = true
        startHeartbeat(id.value, handleSessionExpired, handleClaimLost)
        await requestsStore.loadRequest(id.value)
        if (!isMounted) return
        syncActiveReviewState()
      }
      else if (sessionExpired.value) {
        await handleSessionExpired()
        return
      }
      else {
        // Another user claimed it between page load and now — refresh to show correct state.
        await requestsStore.loadRequest(id.value)
      }
    }
  }

  if (!isMounted) return

  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id.value)
  }

  // Pre-load voting detail for executive/director in voting stages
  if (
    EXECUTIVE_ROLES.has(userRole.value)
    && requestsStore.currentRequest
    && VOTING_STAGE_STATUSES.has(requestsStore.currentRequest.status)
  ) {
    await votingStore.loadVotingDetail(id.value)
  }

  // Pre-load history for bank reviewers on SUBMITTED requests to detect resubmit-after-support-return
  if (
    userRole.value === UserRole.BANK_REVIEWER
    && requestsStore.currentRequest?.status === RequestStatus.SUBMITTED
    && !requestsStore.historyLoaded
    && !requestsStore.loadingHistory
  ) {
    await requestsStore.loadHistory(id.value)
  }

  // Start claim-state poll for bank reviewers watching a request they don't hold
  if (isBankReviewer.value && requestsStore.currentRequest && !requestsStore.currentRequest.is_claimed_by_me) {
    const s = requestsStore.currentRequest.status
    if (CLAIM_POLL_STATUSES.has(s)) startClaimPoll()
  }
})

watch(id, async (nextId, previousId) => {
  if (!isMounted || nextId === previousId) return

  // Always clean up the previous request's claim/heartbeat before navigating,
  // regardless of where we are going (including NaN / non-request routes).
  if (Number.isFinite(previousId) && previousId > 0) {
    stopHeartbeat(previousId)
    stopBankHeartbeat(previousId)
    // Release support claim if active or if the request shows us as claimer
    if (isActiveReviewer.value || (isSupportCommittee.value && requestsStore.currentRequest?.is_claimed_by_me)) {
      releaseRequest(previousId)
      isActiveReviewer.value = false
    }
    // Release bank reviewer claim: use isBankActiveReviewer OR fall back to
    // the server-side is_claimed_by_me flag in case the local flag was never
    // set (e.g. verifyBankClaimAlive returned false due to a transient error).
    if (isBankActiveReviewer.value || (isBankReviewer.value && requestsStore.currentRequest?.is_claimed_by_me)) {
      releaseBankRequest(previousId)
      isBankActiveReviewer.value = false
    }
  }

  if (Number.isNaN(nextId)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(nextId)
  if (!isMounted) return

  if (requestsStore.error || !requestsStore.currentRequest) {
    await router.replace('/requests')
    return
  }

  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(nextId)
  }
  if (activeTab.value === 'parties' || activeTab.value === 'activity_log') {
    await requestsStore.loadHistory(nextId)
  }
  if (
    EXECUTIVE_ROLES.has(userRole.value)
    && requestsStore.currentRequest
    && VOTING_STAGE_STATUSES.has(requestsStore.currentRequest.status)
  ) {
    await votingStore.loadVotingDetail(nextId)
  }

  // Restart claim poll for the new request if applicable
  stopClaimPoll()
  if (isBankReviewer.value && requestsStore.currentRequest && !requestsStore.currentRequest.is_claimed_by_me) {
    const s = requestsStore.currentRequest.status
    if (CLAIM_POLL_STATUSES.has(s)) startClaimPoll()
  }
})

onBeforeUnmount(() => {
  isMounted = false
  // id.value may be NaN if the router already finalised navigation to a route
  // without an :id param before Suspense unmounts this component. Fall back to
  // the store's still-loaded request as the authoritative id source.
  const validId = Number.isNaN(id.value)
    ? (requestsStore.currentRequest?.id ?? null)
    : id.value
  stopClaimPoll()
  if (validId !== null) {
    stopHeartbeat(validId)
    if (isActiveReviewer.value || (isSupportCommittee.value && requestsStore.currentRequest?.is_claimed_by_me)) {
      releaseRequest(validId)
    }
    stopBankHeartbeat(validId)
    if (isBankActiveReviewer.value || (isBankReviewer.value && requestsStore.currentRequest?.is_claimed_by_me)) {
      releaseBankRequest(validId)
    }
  }
})

async function onTabChange(key: TabKey) {
  activeTab.value = key
  if (key === 'documents' && !requestsStore.documentsLoaded && !requestsStore.loadingDocuments) {
    await requestsStore.loadDocuments(id.value)
  }
  if ((key === 'parties' || key === 'activity_log') && !requestsStore.historyLoaded && !requestsStore.loadingHistory) {
    await requestsStore.loadHistory(id.value)
  }
}

async function onActionCompleted() {
  await requestsStore.loadRequest(id.value)
  customsDownloadError.value = ''
  syncActiveReviewState()
  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id.value)
  }
  if (activeTab.value === 'parties' || activeTab.value === 'activity_log') {
    await requestsStore.loadHistory(id.value)
  }
  if (showVotingPanelInline.value || votingStore.votingDetail) {
    await votingStore.loadVotingDetail(id.value)
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
    customsDownloadError.value = 'تعذر تنزيل البيان الجمركي الآن. أعد المحاولة بعد قليل.'
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
    checklistCustomsDownloadError.value = 'تعذر تنزيل البيان الجمركي الآن. أعد المحاولة بعد قليل.'
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
    const blob = await downloadFxConfirmationTemplate(id.value)

    const bytes = await blob.arrayBuffer()
    const digest = await crypto.subtle.digest('SHA-256', bytes)
    fxTemplateChecksum.value = Array.from(new Uint8Array(digest))
      .map(byte => byte.toString(16).padStart(2, '0'))
      .join('')

    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `fx-confirmation-template-${request.value?.reference_number ?? id.value}.pdf`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  catch (error: unknown) {
    const message = error instanceof Error ? error.message : ''
    fxFlowError.value = message || 'تعذر تنزيل نموذج تأكيد المصارفة الآن. أعد المحاولة بعد قليل.'
  }
  finally {
    fxGeneratingTemplate.value = false
  }
}

async function handleCompleteFxConfirmation() {
  if (request.value?.status !== RequestStatus.FX_CONFIRMATION_PENDING && !fxSignedFile.value) {
    fxFlowError.value = 'يجب رفع النموذج الموقّع قبل الإتمام.'
    return
  }

  fxFlowError.value = ''
  fxCompleting.value = true

  try {
    if (request.value?.status !== RequestStatus.FX_CONFIRMATION_PENDING && fxSignedFile.value) {
      await requestsStore.uploadSignedFxDoc(id.value, fxSignedFile.value)
    }
    await requestsStore.issueCustomsDeclaration(id.value)
    fxFlowSuccess.value = true
    await onActionCompleted()
  }
  catch (error: unknown) {
    const message = error instanceof Error ? error.message : ''
    fxFlowError.value = message || 'تعذر إتمام تأكيد المصارفة الآن. أعد المحاولة بعد قليل.'
  }
  finally {
    fxCompleting.value = false
  }
}

async function handleUploadDocument(file: File) {
  try {
    await requestsStore.uploadDocument(id.value, file)
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
    const config = useRuntimeConfig()
    const response = await $fetch<Blob>(`/api/documents/${docId}/download`, {
      baseURL: config.public.apiBase as string,
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
    downloadErrors.value = { ...downloadErrors.value, [docId]: 'تعذر تنزيل الملف الآن. أعد المحاولة بعد قليل.' }
  }
  finally {
    const next = new Set(downloadingIds.value)
    next.delete(docId)
    downloadingIds.value = next
  }
}

function formatDate(iso: string | null): string {
  if (!iso) return 'غير متاح'
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
  if (id === null || id === undefined) return 'غير متاح'
  return `#${id}`
}

// Watch voting panel inline to pre-load voting detail when it becomes visible
watch(showVotingPanelInline, async (visible) => {
  if (visible && !votingStore.votingDetail && !votingStore.loadingDetail) {
    await votingStore.loadVotingDetail(id.value)
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
  downloadCustomsDeclaration: downloadCustomsBlob,
  downloadFxConfirmationTemplate,
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
    const newId = await cloneRequest(id.value)
    showCloneDialog.value = false
    await navigateTo(`/requests/${newId}/edit`)
  }
  catch {
    cloneError.value = 'تعذر إنشاء النسخة الآن. أعد المحاولة بعد قليل.'
  }
  finally {
    cloneLoading.value = false
  }
}
</script>

<template>
  <div class="detail-page" >
    <!-- Loading skeleton -->
    <div v-if="requestsStore.loadingRequest" class="mx-auto w-full max-w-7xl px-4 space-y-3" aria-busy="true" aria-label="جارٍ التحميل">
      <Skeleton class="h-8 w-64" />
      <Skeleton class="h-5 w-full" />
      <Skeleton class="h-5 w-2/3" />
    </div>

    <template v-else-if="request">
      <div class="mx-auto w-full max-w-7xl px-4">
        <!-- Navigation row: back + prev/next -->
        <div class="mb-5 flex items-center justify-between">
          <Button type="button" variant="ghost" size="sm" class="-ms-2 gap-1 text-muted-foreground" as-child>
            <NuxtLink to="/requests">
              <ChevronRight class="size-4" />
              رجوع
            </NuxtLink>
          </Button>

          <!-- Prev / position / next — only visible when this request is in the loaded list -->
          <div v-if="listPosition > -1" class="flex items-center gap-1" role="navigation" aria-label="التنقل بين الطلبات">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="gap-1 text-muted-foreground"
              :disabled="!hasPrevRequest || navigationLoading"
              :aria-label="hasPrevRequest ? `الطلب السابق` : 'لا يوجد طلب سابق'"
              @click="navigateAdjacentRequest('prev')"
            >
              <ChevronRight class="size-4" />
              <span class="text-xs">السابق</span>
            </Button>
            <span class="select-none px-1 text-xs tabular-nums text-muted-foreground">
              {{ navigationPosition }} / {{ navigationTotal }}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="gap-1 text-muted-foreground"
              :disabled="!hasNextRequest || navigationLoading"
              :aria-label="hasNextRequest ? `الطلب التالي` : 'لا يوجد طلب تالٍ'"
              @click="navigateAdjacentRequest('next')"
            >
              <span class="text-xs">التالي</span>
              <ChevronLeft class="size-4" />
            </Button>
          </div>
        </div>

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
          <StatusBadge :status="request.status" :role="userRole" />
          <Button
            v-if="DRAFT_EDITOR_ROLES.has(userRole) && isEditable"
            variant="outline"
            size="sm"
            data-testid="header-edit-btn"
            @click="router.push(`/requests/${id}/edit`)"
          >
            <FilePen class="h-3.5 w-3.5" />
            تعديل
          </Button>
          <Button
            v-if="showCloneButton"
            variant="outline"
            size="sm"
            :disabled="cloneLoading"
            data-testid="clone-request-btn"
            @click="openCloneDialog"
          >
            <Copy class="h-3.5 w-3.5" />
            نسخ وإعادة إرسال
          </Button>
          <Button
            v-if="request.customs_declaration && canDownloadCustomsDeclaration"
            variant="outline"
            size="sm"
            :disabled="requestsStore.downloadingCustoms"
            @click="downloadCustomsDeclaration"
          >
            {{ requestsStore.downloadingCustoms ? 'جارٍ التنزيل…' : 'تنزيل البيان' }}
          </Button>
          <Button variant="outline" size="sm" as-child data-testid="print-request-btn">
            <NuxtLink :to="`/requests/${id}/print`" aria-label="طباعة الطلب">
              <Printer class="h-3.5 w-3.5" />
              طباعة
            </NuxtLink>
          </Button>
          <Button
            variant="outline"
            size="sm"
            data-testid="header-audit-link-btn"
            aria-label="انتقل إلى سجل الأحداث"
            @click="onTabChange('activity_log')"
          >
            <ClipboardList class="h-3.5 w-3.5" />
            سجل الأحداث
          </Button>
          <template v-if="isCbyAdmin">
            <Button variant="outline" size="sm" as-child data-testid="cby-audit-view-btn">
              <NuxtLink :to="`/audit?request=${id}`">
                <ShieldCheck class="h-3.5 w-3.5" />
                التدقيق
              </NuxtLink>
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-testid="cby-copy-link-btn"
              @click="copyCurrentLink"
            >
              <Link class="h-3.5 w-3.5" />
              نسخ الرابط
            </Button>
          </template>
        </div>
      </div>

      <!-- CBY Admin oversight badge -->
      <div v-if="isCbyAdmin" class="mb-3 flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/30 px-3 py-1 text-xs font-medium text-muted-foreground">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          إشراف فقط، ولا توجد صلاحيات تنفيذية
        </span>
        <span class="text-xs text-muted-foreground">{{ cbySlaState.label }}</span>
      </div>

      <!-- Two-column layout -->
      <div class="detail-layout">
        <!-- Primary content (2/3) -->
        <div class="detail-main">
          <!-- Banners -->
          <div
            v-if="showDirectorVotingActiveBanner || showDirectorReadyToCloseBanner || showDirectorTieBreakBanner || showDirectorReadyToFinalizeBanner || showDirectorFxReadyBanner || showSwiftPreApprovalLockedBanner || showSwiftAwaitingEnableBanner || showSwiftReadyBanner || showSwiftCompletedBanner || isSegregationBlocked || claimError || showActiveReviewBanner || showClaimedByOthersBanner || showBankClaimedByOthersBanner || showUnclaimedBanner || showVotingPendingBanner || showVotedConfirmationBanner || showBankReviewerSupportRejectedBanner || isLocked || isReturnedForCorrection || isBankReturned || isSupportReturned"
            class="banner-area"
          >
            <div
              v-if="showDirectorVotingActiveBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--voting)_24%,transparent)] bg-[color-mix(in_srgb,var(--voting)_7%,var(--background))] text-[color-mix(in_srgb,var(--voting)_78%,var(--foreground))]"
            >
              جلسة التصويت نشطة، وقد صوّت {{ request.votes_cast ?? 0 }} من أصل {{ request.total_voters ?? 0 }}.
            </div>
            <div
              v-else-if="showDirectorReadyToCloseBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--voting)_24%,transparent)] bg-[color-mix(in_srgb,var(--voting)_7%,var(--background))] text-[color-mix(in_srgb,var(--voting)_78%,var(--foreground))] flex items-center justify-between gap-3"
            >
              <span>صوّت جميع الأعضاء، ويمكن إغلاق الجلسة الآن.</span>
              <Button variant="ghost" size="sm" class="h-auto p-0 text-xs font-semibold underline" @click="scrollToActionPanel">إغلاق الجلسة</Button>
            </div>
            <div
              v-else-if="showDirectorTieBreakBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--severity-amber)_30%,transparent)] bg-[color-mix(in_srgb,var(--severity-amber)_7%,var(--background))] text-[color-mix(in_srgb,var(--severity-amber)_80%,var(--foreground))] flex items-center justify-between gap-3"
            >
              <span>حدث تعادل في التصويت، ويتطلب حسم المدير.</span>
              <Button variant="ghost" size="sm" class="h-auto p-0 text-xs font-semibold underline" @click="scrollToActionPanel">حسم التعادل</Button>
            </div>
            <div
              v-else-if="showDirectorReadyToFinalizeBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--voting)_24%,transparent)] bg-[color-mix(in_srgb,var(--voting)_7%,var(--background))] text-[color-mix(in_srgb,var(--voting)_78%,var(--foreground))] flex items-center justify-between gap-3"
            >
              <span>أغلقت الجلسة، والطلب جاهز للإصدار النهائي.</span>
              <Button variant="ghost" size="sm" class="h-auto p-0 text-xs font-semibold underline" @click="scrollToActionPanel">إصدار القرار النهائي</Button>
            </div>
            <div
              v-else-if="showDirectorFxReadyBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--severity-amber)_30%,transparent)] bg-[color-mix(in_srgb,var(--severity-amber)_7%,var(--background))] text-[color-mix(in_srgb,var(--severity-amber)_80%,var(--foreground))] flex items-center justify-between gap-3"
            >
              <span>جاهز لإتمام تأكيد المصارفة الخارجية.</span>
              <Button variant="ghost" size="sm" class="h-auto p-0 text-xs font-semibold underline" @click="onTabChange('fx_confirmation')">ابدأ التأكيد</Button>
            </div>
            <div
              v-else-if="showSwiftPreApprovalLockedBanner"
              class="rounded-lg border border-[var(--locked)]/35 bg-[color-mix(in_srgb,var(--locked)_8%,var(--background))] text-[color-mix(in_srgb,var(--locked)_65%,var(--foreground))]"
            >
              هذا الطلب لم يصل بعد مرحلة السويفت. لا يمكن رفع الوثائق حتى يكتمل اعتماد اللجنة التنفيذية.
            </div>
            <div
              v-else-if="showSwiftReadyBanner"
              class="rounded-lg border border-[color-mix(in_srgb,var(--swift)_26%,transparent)] bg-[color-mix(in_srgb,var(--swift)_7%,var(--background))] text-[color-mix(in_srgb,var(--swift)_72%,var(--foreground))] flex items-center justify-between gap-3"
            >
              <span>الطلب جاهز لرفع وثائق السويفت.</span>
              <NuxtLink :to="`/requests/${id}/swift`" class="text-xs font-semibold underline">
                ابدأ الرفع
              </NuxtLink>
            </div>
            <div
              v-else-if="showSwiftAwaitingEnableBanner"
              class="rounded-lg border border-[var(--locked)]/35 bg-[color-mix(in_srgb,var(--locked)_8%,var(--background))] text-[color-mix(in_srgb,var(--locked)_65%,var(--foreground))]"
            >
              في انتظار الإتاحة، وسيُفعَّل رفع وثائق السويفت بعد الانتقال إلى مرحلة الانتظار.
            </div>
            <div
              v-else-if="showSwiftCompletedBanner"
              class="rounded-lg border border-[var(--locked)]/35 bg-[color-mix(in_srgb,var(--locked)_8%,var(--background))] text-[color-mix(in_srgb,var(--locked)_65%,var(--foreground))]"
            >
              تم تسليم السويفت، وانتقلت المسؤولية إلى مدير اللجنة التنفيذية لإتمام تأكيد المصارفة الخارجية.
            </div>
            <SegregationBlockedBanner v-if="isSegregationBlocked" />
            <LoadErrorAlert
              v-else-if="claimError"
              :message="claimError"
              title="تعذّرت المطالبة بالمراجعة"
              :show-retry="claimErrorRetryable"
              @retry="handleManualClaim"
            />
            <ActiveReviewBanner
              v-else-if="showActiveReviewBanner"
              :claimed-until="request.claimed_until"
              :heartbeat-active="showActiveReviewBanner"
              @release="handleReleaseClaim"
            />
            <ClaimedByOthersBanner v-else-if="showClaimedByOthersBanner" :claimer-name="request.claimed_by?.name ?? ''" />
            <ClaimedByOthersBanner v-else-if="showBankClaimedByOthersBanner" :claimer-name="request.claimed_by?.name ?? ''" />
            <UnclaimedBanner v-else-if="showUnclaimedBanner" />
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
            <!-- BANK_REVIEWER: support has rejected this request — follow-up decision required -->
            <div
              v-else-if="showBankReviewerSupportRejectedBanner"
              class="rounded-lg border border-[var(--severity-amber)] bg-[var(--severity-amber)]/5 flex items-start gap-3"
              role="alert"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0 text-[var(--severity-amber)] mt-0.5" aria-hidden="true">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-foreground">رُفض الطلب من لجنة المساندة</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                  يجب اتخاذ قرار: إبقاء الرفض نهائياً أو إعادة الطلب للمدخل للتعديل وإعادة التقديم.
                </p>
              </div>
            </div>
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
              <Button
                variant="ghost"
                size="sm"
                class="dup-widget-toggle h-auto p-0"
                :aria-expanded="duplicateWidgetExpanded"
                data-testid="dup-widget-toggle"
                @click="duplicateWidgetExpanded = !duplicateWidgetExpanded"
              >
                {{ duplicateWidgetExpanded ? 'إخفاء التفاصيل' : 'إظهار التفاصيل' }}
              </Button>
            </div>
            <div v-if="duplicateWidgetExpanded" class="dup-widget-body" data-testid="dup-widget-body">
              <!-- Full view: CBY_ADMIN and SUPPORT_COMMITTEE -->
              <template v-if="duplicateWidgetFull">
                <Table class="dup-widget-table">
                  <TableHeader>
                    <TableRow>
                      <TableHead class="text-right">الرقم المرجعي</TableHead>
                      <TableHead class="text-right">البنك</TableHead>
                      <TableHead class="text-right">المبلغ</TableHead>
                      <TableHead class="text-right">العملة</TableHead>
                      <TableHead class="text-right">التاريخ</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="warn in duplicateWarnings" :key="warn.id">
                      <TableCell>
                        <NuxtLink v-if="warn.id" :to="`/requests/${warn.id}`" class="font-mono text-xs text-primary hover:underline">
                          {{ warn.reference_number ?? '—' }}
                        </NuxtLink>
                        <span v-else class="font-mono text-xs">{{ warn.reference_number ?? '—' }}</span>
                      </TableCell>
                      <TableCell class="text-sm">{{ warn.bank_name ?? '—' }}</TableCell>
                      <TableCell class="text-sm font-mono">{{ warn.amount?.toLocaleString('ar') ?? '—' }}</TableCell>
                      <TableCell class="text-sm">{{ warn.currency ?? '—' }}</TableCell>
                      <TableCell class="text-xs text-muted-foreground">
                        {{ warn.created_at ? new Date(warn.created_at).toLocaleDateString('ar-YE') : '—' }}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
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
          <div v-if="supportReturnHint" class="support-return-hint" role="note">
            <RotateCcw class="h-4 w-4 flex-shrink-0" aria-hidden="true" />
            <span class="support-return-hint__text">إعادة بعد عودة من المساندة</span>
            <span v-if="supportReturnHint.comment" class="support-return-hint__comment">، {{ supportReturnHint.comment }}</span>
            <Button variant="ghost" size="sm" class="ms-auto h-auto p-0 text-primary underline text-xs font-semibold" @click="openSupportReturnHistory">
              عرض التعليق في السجل
            </Button>
          </div>

          <!-- VotingPanel inline for executive roles in voting stages -->
          <div v-if="showVotingPanelInline" class="card card--no-padding voting-inline">
            <VotingPanel
              :request-id="id"
              :request-status="request.status"
              :user-role="userRole"
            />
          </div>

          <!-- Tab navigation + panels -->
          <Tabs :model-value="activeTab" class="mt-6" dir="rtl" @update:model-value="v => onTabChange(v as TabKey)">
            <TabsList>
              <TabsTrigger
                v-for="tab in tabs"
                :key="tab.key"
                :value="tab.key"
              >
                {{ tab.label }}
              </TabsTrigger>
            </TabsList>

            <!-- المعلومات tab -->
            <TabsContent value="overview" class="tab-panel mt-5" role="tabpanel" aria-label="المعلومات">
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
                    <Button
                      v-if="[UserRole.COMMITTEE_DIRECTOR, UserRole.CBY_ADMIN, UserRole.BANK_REVIEWER].includes(userRole)"
                      variant="outline"
                      size="sm"
                      as-child
                    >
                      <NuxtLink :to="`/requests/${id}/customs-preview`">معاينة البيان</NuxtLink>
                    </Button>
                    <Button
                      v-if="canDownloadCustomsDeclaration"
                      size="sm"
                      :disabled="requestsStore.downloadingCustoms"
                      @click="downloadCustomsDeclaration"
                    >
                      {{ requestsStore.downloadingCustoms ? 'جارٍ التنزيل…' : 'تنزيل PDF' }}
                    </Button>
                    <!-- BANK_ADMIN: locked PDF — visible but not downloadable -->
                    <Button
                      v-else-if="userRole === UserRole.BANK_ADMIN"
                      variant="outline"
                      size="sm"
                      disabled
                      class="text-muted-foreground"
                      :title="'تنزيل PDF مخصص لمسؤول البنك المركزي والمديرين الموافقين فقط'"
                      aria-disabled="true"
                      data-testid="fx-pdf-locked"
                    >
                      <Lock class="h-3 w-3" aria-hidden="true" />
                      تنزيل PDF (مقيد)
                    </Button>
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
            </TabsContent>

            <!-- الوثائق tab -->
            <TabsContent value="documents" class="tab-panel mt-5" role="tabpanel" aria-label="الوثائق">
              <div class="card">
                <div class="flex items-center justify-between gap-3 mb-3">
                  <h2 class="card-title">المستندات المرفوعة</h2>
                  <Button
                    v-if="showSwiftUploadShortcut"
                    variant="ghost"
                    size="sm"
                    as-child
                  >
                    <NuxtLink :to="`/requests/${id}/swift`">رفع وثائق السويفت</NuxtLink>
                  </Button>
                </div>
                <DocumentChecklist
                  :documents="requestsStore.documents"
                  :customs-declaration="request.customs_declaration ?? null"
                  :user-role="userRole"
                  :request-status="request.status"
                  :loading="requestsStore.loadingDocuments || !requestsStore.documentsLoaded"
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
                  v-if="showSwiftFxLockedRow || showBankAdminFxLockedRow"
                  class="mt-3 flex items-center justify-between gap-2 rounded-lg border border-[var(--locked)]/35 bg-[color-mix(in_srgb,var(--locked)_8%,var(--background))] px-3 py-2 text-[color-mix(in_srgb,var(--locked)_65%,var(--foreground))]"
                  data-testid="fx-confirmation-locked-row"
                >
                  <div class="flex items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span class="text-xs font-medium">نموذج تأكيد المصارفة الخارجية</span>
                  </div>
                  <span
                    class="text-xs"
                    :title="showBankAdminFxLockedRow ? 'تنزيل تأكيد المصارفة الخارجية مقيد لأدوار CBY المختصة فقط.' : 'مخصص لمدير اللجنة التنفيذية.'"
                  >مقيّد</span>
                </div>
              </div>
            </TabsContent>

            <TabsContent
              v-if="showDirectorFxTab"
              value="fx_confirmation"
              class="tab-panel mt-5"
              role="tabpanel"
              aria-label="تأكيد المصارفة"
            >
              <div class="card space-y-4">
                <h2 class="card-title">تأكيد المصارفة الخارجية</h2>

                <!-- FX lifecycle mini-strip -->
                <ol
                  class="flex items-center gap-0 rounded-lg border border-border overflow-hidden text-xs"
                  aria-label="مراحل تأكيد المصارفة"
                  data-testid="fx-lifecycle-strip"
                >
                  <!-- Step 1: Template generated (always true once tab is visible) -->
                  <li class="flex flex-1 items-center gap-1.5 px-3 py-2 bg-[var(--severity-green)]/8 text-[var(--severity-green)]">
                    <span class="h-4 w-4 flex-shrink-0 rounded-full bg-[var(--severity-green)] flex items-center justify-center text-white text-[10px] font-bold leading-none">1</span>
                    <span class="font-medium">تم إنشاء النموذج</span>
                  </li>
                  <span class="h-6 w-px bg-border flex-shrink-0" aria-hidden="true" />

                  <!-- Step 2: Template downloaded (fxTemplateChecksum set) -->
                  <li
                    class="flex flex-1 items-center gap-1.5 px-3 py-2"
                    :class="fxTemplateChecksum
                      ? 'bg-[var(--severity-green)]/8 text-[var(--severity-green)]'
                      : 'bg-muted text-muted-foreground'"
                  >
                    <span
                      class="h-4 w-4 flex-shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold leading-none"
                      :class="fxTemplateChecksum ? 'bg-[var(--severity-green)] text-white' : 'bg-muted-foreground/30 text-muted-foreground'"
                    >2</span>
                    <span class="font-medium">تم التنزيل</span>
                  </li>
                  <span class="h-6 w-px bg-border flex-shrink-0" aria-hidden="true" />

                  <!-- Step 3: Signed externally (implicit — no backend signal, shown pending) -->
                  <li
                    class="flex flex-1 items-center gap-1.5 px-3 py-2"
                    :class="(fxSignedFile || request?.has_fx_request_document)
                      ? 'bg-[var(--severity-green)]/8 text-[var(--severity-green)]'
                      : 'bg-muted text-muted-foreground'"
                  >
                    <span
                      class="h-4 w-4 flex-shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold leading-none"
                      :class="(fxSignedFile || request?.has_fx_request_document) ? 'bg-[var(--severity-green)] text-white' : 'bg-muted-foreground/30 text-muted-foreground'"
                    >3</span>
                    <span class="font-medium">التوقيع الخارجي</span>
                  </li>
                  <span class="h-6 w-px bg-border flex-shrink-0" aria-hidden="true" />

                  <!-- Step 4: Signed PDF uploaded (has_fx_request_document) -->
                  <li
                    class="flex flex-1 items-center gap-1.5 px-3 py-2"
                    :class="request?.has_fx_request_document
                      ? 'bg-[var(--severity-green)]/8 text-[var(--severity-green)]'
                      : 'bg-muted text-muted-foreground'"
                  >
                    <span
                      class="h-4 w-4 flex-shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold leading-none"
                      :class="request?.has_fx_request_document ? 'bg-[var(--severity-green)] text-white' : 'bg-muted-foreground/30 text-muted-foreground'"
                    >4</span>
                    <span class="font-medium">رُفع الموقّع</span>
                  </li>
                  <span class="h-6 w-px bg-border flex-shrink-0" aria-hidden="true" />

                  <!-- Step 5: Completed -->
                  <li
                    class="flex flex-1 items-center gap-1.5 px-3 py-2"
                    :class="(request?.status === RequestStatus.CUSTOMS_DECLARATION_ISSUED || request?.status === RequestStatus.COMPLETED)
                      ? 'bg-[var(--severity-green)]/8 text-[var(--severity-green)]'
                      : 'bg-muted text-muted-foreground'"
                  >
                    <span
                      class="h-4 w-4 flex-shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold leading-none"
                      :class="(request?.status === RequestStatus.CUSTOMS_DECLARATION_ISSUED || request?.status === RequestStatus.COMPLETED) ? 'bg-[var(--severity-green)] text-white' : 'bg-muted-foreground/30 text-muted-foreground'"
                    >5</span>
                    <span class="font-medium">مكتمل</span>
                  </li>
                </ol>

                <div class="rounded-lg border border-border p-3 space-y-2">
                  <p class="text-sm font-semibold">الخطوة 1: تنزيل نموذج التأكيد</p>
                  <p class="text-xs text-muted-foreground">نزّل النموذج النظامي المولد للطلب الحالي.</p>
                  <Button
                    :disabled="fxGeneratingTemplate"
                    class="bg-primary text-white"
                    @click="handleDownloadFxTemplate"
                  >
                    {{ fxGeneratingTemplate ? 'جارٍ التنزيل…' : 'تنزيل نموذج تأكيد المصارفة الخارجية' }}
                  </Button>
                  <p v-if="fxTemplateChecksum" class="text-xs text-muted-foreground break-all">
                    SHA-256: {{ fxTemplateChecksum }}
                  </p>
                </div>

                <div class="rounded-lg border border-border p-3 space-y-2 bg-muted">
                  <p class="text-sm font-semibold">الخطوة 2: الطباعة والتوقيع الخارجي</p>
                  <p class="text-xs text-muted-foreground">اطبع النموذج، وقّعه وختمه ورقياً، ثم امسحه ضوئياً بصيغة PDF واحفظه لرفعه في الخطوة التالية.</p>
                </div>

                <div class="rounded-lg border border-border p-3 space-y-3">
                  <p class="text-sm font-semibold">الخطوة 3: رفع النموذج الموقع وإتمام الطلب</p>
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
                    :disabled="(request.status !== RequestStatus.FX_CONFIRMATION_PENDING && !fxSignedFile) || fxCompleting"
                    @click="handleCompleteFxConfirmation"
                  >
                    {{ fxCompleting ? 'جارٍ الإتمام…' : 'إتمام تأكيد المصارفة' }}
                  </Button>
                  <div v-if="fxFlowError" class="flex items-start gap-2 rounded-md border border-[var(--severity-red)]/20 bg-[var(--severity-red)]/5 p-2.5">
                    <p class="flex-1 text-xs text-[var(--severity-red)]">{{ fxFlowError }}</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-auto shrink-0 p-0 text-xs font-semibold text-destructive hover:text-destructive"
                      @click="handleCompleteFxConfirmation"
                    >
                      إعادة المحاولة
                    </Button>
                  </div>
                  <div
                    v-if="fxFlowSuccess"
                    class="rounded-md border border-[var(--severity-green)]/20 bg-[var(--severity-green)]/5 p-3 space-y-1.5"
                  >
                    <p class="text-sm font-semibold text-[var(--severity-green)]">
                      تم إتمام تأكيد المصارفة بنجاح
                    </p>
                    <p class="text-xs text-muted-foreground">
                      انتقل الطلب إلى حالة الإغلاق. يمكنك العودة إلى قائمة الطلبات لمتابعة الطلبات الأخرى.
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      class="mt-1"
                      @click="router.push('/requests')"
                    >
                      العودة إلى قائمة الطلبات
                    </Button>
                  </div>
                </div>
              </div>
            </TabsContent>

            <!-- الأطراف tab: actors + workflow timeline + audit trail -->
            <TabsContent value="parties" class="tab-panel mt-5" role="tabpanel" aria-label="الأطراف">
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

                <div v-if="requestsStore.loadingHistory || !requestsStore.historyLoaded" class="space-y-3" aria-busy="true">
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
            </TabsContent>

            <!-- Activity log tab -->
            <TabsContent value="activity_log" class="tab-panel mt-5" role="tabpanel" aria-label="سجل الأحداث">
              <div id="audit-trail" class="card">
                <h2 class="card-title">سجل الأحداث</h2>

                <div v-if="requestsStore.loadingHistory || !requestsStore.historyLoaded" class="space-y-3" aria-busy="true">
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
            </TabsContent>
          </Tabs>
        </div>

        <!-- Right rail (1/3) -->
        <aside class="detail-rail">
          <!-- Workflow progress -->
          <div class="rail-card">
            <WorkflowProgress
              :current-status="request.status"
              :user-role="userRole"
              :is-claimed-by-me="request.is_claimed_by_me ?? undefined"
            />
          </div>

          <!-- Available actions -->
          <div v-if="hasActions" class="rail-card rail-card--actions">
            <p class="rail-card__title">إجراءات متاحة لك</p>
            <ActionsPanel
              ref="actionsPanelRef"
              :request="request"
              :user-role="userRole"
              @action-completed="onActionCompleted"
            />
          </div>

          <div v-if="showSwiftActionCard" class="rail-card">
            <p class="rail-card__title">إجراءات السويفت</p>
            <template v-if="request.status === RequestStatus.WAITING_FOR_SWIFT">
              <Button variant="ghost" size="sm" as-child class="ps-0">
                <NuxtLink :to="`/requests/${id}/swift`">رفع وثائق السويفت</NuxtLink>
              </Button>
            </template>
            <template v-else-if="request.status === RequestStatus.EXECUTIVE_APPROVED">
              <p class="text-sm text-muted-foreground">في انتظار الإتاحة</p>
            </template>
            <template v-else>
              <p class="text-sm text-muted-foreground">تم تسليم السويفت، ولا توجد إجراءات إضافية.</p>
            </template>
          </div>

          <div v-if="isDirectorCustomsPhase" class="rail-card">
            <FxConfirmationCard
              :request="request"
              @action-completed="onActionCompleted"
            />
          </div>

          <!-- CBY Admin: Current Blocker (highest priority) -->
          <div
            v-if="isCbyAdmin && cbyBlockerText"
            class="rail-card border-[color-mix(in_srgb,var(--severity-amber)_35%,transparent)] bg-[color-mix(in_srgb,var(--severity-amber)_5%,var(--background))]"
          >
            <p class="rail-card__title text-[var(--severity-amber)]">العائق الحالي</p>
            <p class="text-sm leading-relaxed">{{ cbyBlockerText }}</p>
          </div>

          <!-- CBY Admin: Intelligence Panel -->
          <div v-if="isCbyAdmin" class="rail-card">
            <p class="rail-card__title">لوحة الاستخبارات</p>
            <ul class="quick-info-list">
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">العمر</span>
                  <span class="quick-info-value">
                    {{ cbyAgeHours > 24 ? `${Math.floor(cbyAgeHours / 24)} يوم` : `${Math.floor(cbyAgeHours)} ساعة` }}
                  </span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">SLA</span>
                  <span class="quick-info-value" :style="{ color: cbySlaState.color }">{{ cbySlaState.label }}</span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">الممثل المسؤول</span>
                  <span class="quick-info-value">{{ ROLE_LABELS[request.current_owner_role] ?? '—' }}</span>
                </div>
              </li>
              <li v-if="(request.duplicate_warnings?.length ?? 0) > 0" class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true" style="color: var(--severity-amber)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">علامات المخاطر</span>
                  <span class="quick-info-value" style="color: var(--severity-amber)">{{ request.duplicate_warnings?.length }} فاتورة مكررة</span>
                </div>
              </li>
              <li class="quick-info-item">
                <span class="quick-info-icon" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </span>
                <div class="quick-info-content">
                  <span class="quick-info-label">آخر نشاط</span>
                  <span class="quick-info-value">{{ formatDate(request.updated_at) }}</span>
                </div>
              </li>
            </ul>
            <div class="mt-3 pt-3 border-t border-border">
              <Button variant="ghost" size="sm" as-child class="h-auto p-0 text-xs text-primary">
                <NuxtLink :to="`/audit?request=${id}`">عرض سجل التدقيق المرتبط</NuxtLink>
              </Button>
            </div>
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

        </aside>
      </div>
      </div>
    </template>

    <AlertDialog :open="showCloneDialog" @update:open="handleCloneDialogOpenChange">
      <AlertDialogContent >
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

    <!-- Keyboard shortcut legend -->
    <Dialog v-model:open="showShortcutLegend">
      <DialogContent class="max-w-sm" dir="rtl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Keyboard class="h-4 w-4" />
            اختصارات لوحة المفاتيح
          </DialogTitle>
          <DialogDescription>اضغط أي مفتاح لإغلاق هذا النافذة</DialogDescription>
        </DialogHeader>
        <div class="space-y-2 text-sm">
          <div class="flex items-center justify-between gap-4 rounded-md bg-muted/40 px-3 py-2">
            <span class="text-muted-foreground">تنفيذ الإجراء الرئيسي</span>
            <kbd class="rounded border bg-background px-2 py-0.5 font-mono text-xs">Ctrl+Enter</kbd>
          </div>
          <div class="flex items-center justify-between gap-4 rounded-md bg-muted/40 px-3 py-2">
            <span class="text-muted-foreground">الطلب التالي في القائمة</span>
            <kbd class="rounded border bg-background px-2 py-0.5 font-mono text-xs">Alt+←</kbd>
          </div>
          <div class="flex items-center justify-between gap-4 rounded-md bg-muted/40 px-3 py-2">
            <span class="text-muted-foreground">الطلب السابق في القائمة</span>
            <kbd class="rounded border bg-background px-2 py-0.5 font-mono text-xs">Alt+→</kbd>
          </div>
          <div class="flex items-center justify-between gap-4 rounded-md bg-muted/40 px-3 py-2">
            <span class="text-muted-foreground">عرض/إخفاء هذه النافذة</span>
            <kbd class="rounded border bg-background px-2 py-0.5 font-mono text-xs">?</kbd>
          </div>
        </div>
      </DialogContent>
    </Dialog>
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

/* Page header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
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


/* Two-column layout */
.detail-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: clamp(20px, 2.4vw, 32px);
  align-items: start;
}

@media (min-width: 1024px) {
  .detail-layout {
    grid-template-columns: 2fr 1fr;
  }
}

.detail-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  position: sticky;
  top: 90px;
}

/* Banners */
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

.support-return-hint__comment {
  font-size: 13px;
  color: var(--primary);
  font-weight: 400;
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
  color: color-mix(in srgb, var(--warning) 70%, var(--foreground));
  font-size: 12px;
  font-weight: 600;
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

/* Inline voting panel */
.voting-inline {
  margin-bottom: 16px;
}

/* Tab panels */
.tab-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Cards */
.card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 24px;
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

/* Detail grid — canonical field order, 2 columns */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin: 0;
  gap: 20px 16px;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 0;
  border-bottom: none;
}

.detail-row--customs {
  border: 1px solid var(--border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--muted) 80%, var(--background));
  padding: 10px 12px;
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
  gap: 16px;
  position: sticky;
  top: 90px;
}

.rail-card {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 20px;
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


@media (max-width: 640px) {
  .detail-page {
    padding: 16px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
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
  .banner-area,
  .detail-rail {
    display: none !important;
  }

  .detail-layout {
    grid-template-columns: 1fr;
  }

  .detail-page,
  .tab-panel,
  .card {
    border: 0;
    padding: 0;
    box-shadow: none;
  }
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
