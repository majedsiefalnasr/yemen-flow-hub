<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
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
import { useRequests } from '../../../composables/useRequests'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import { useVotingStore } from '../../../stores/voting.store'
import { useClaimLifecycle } from '../../../composables/useClaimLifecycle'
import { canDownloadCustoms } from '../../../composables/useDocumentPermissions'
import { STATUS_LABELS } from '../../../constants/workflow'
import StatusBadge from '../../../components/ui/StatusBadge.vue'
import LockedBanner from '../../../components/ui/LockedBanner.vue'
import CorrectionBanner from '../../../components/ui/CorrectionBanner.vue'
import ActiveReviewBanner from '../../../components/ui/ActiveReviewBanner.vue'
import ClaimedByOthersBanner from '../../../components/ui/ClaimedByOthersBanner.vue'
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

type TabKey = 'overview' | 'documents' | 'parties'
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
const DRAFT_EDITOR_ROLES = new Set([UserRole.DATA_ENTRY, UserRole.BANK_ADMIN])

// VotingPanel is shown inline above tabs for executive/director roles in voting stages
const showVotingPanelInline = computed(() =>
  !!request.value
  && EXECUTIVE_ROLES.has(userRole.value)
  && VOTING_STAGE_STATUSES.has(request.value.status),
)

const tabs = computed((): Array<{ key: TabKey; label: string }> => [
  { key: 'overview', label: 'المعلومات' },
  { key: 'documents', label: 'الوثائق' },
  { key: 'parties', label: 'الأطراف' },
])

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
const isReturnedForCorrection = computed(() =>
  request.value?.status === RequestStatus.DRAFT_REJECTED_INTERNAL,
)
const isBankReturned = computed(() => request.value?.status === RequestStatus.BANK_RETURNED)
const isSupportReturned = computed(() => request.value?.status === RequestStatus.SUPPORT_RETURNED)

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

// Mirror ActionsPanel's showAnyActions to conditionally show the rail actions card
const hasActions = computed(() => {
  if (!request.value) return false
  const s = request.value.status
  const role = userRole.value
  const bankReviewerAction
    = role === UserRole.BANK_REVIEWER
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

// Downloading state per document id
const downloadingIds = ref<Set<number>>(new Set())
const downloadErrors = ref<Record<number, string>>({})
const customsDownloadError = ref('')
const checklistCustomsDownloadError = ref('')

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

const isSupportCommittee = computed(() => userRole.value === UserRole.SUPPORT_COMMITTEE)

const showActiveReviewBanner = computed(
  () => isSupportCommittee.value && isActiveReviewer.value,
)

const showClaimedByOthersBanner = computed(() => {
  if (!isSupportCommittee.value || isActiveReviewer.value) return false
  const req = request.value
  return !!req && req.is_claimed && !req.is_claimed_by_me
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

    if (req.can_be_claimed) {
      const claimed = await claimRequest(id)

      if (!isMounted) {
        if (claimed) await releaseRequest(id)
        return
      }

      if (claimed) {
        isActiveReviewer.value = true
        startHeartbeat(id, handleSessionExpired, handleClaimLost)

        await requestsStore.loadRequest(id)

        if (!isMounted) return

        if (requestsStore.error || !requestsStore.currentRequest) {
          isActiveReviewer.value = false
          stopHeartbeat(id)
          await releaseRequest(id)
          await router.replace('/requests')
          return
        }
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
    else if (req.is_claimed && req.is_claimed_by_me) {
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
  if (key === 'parties' && !requestsStore.historyLoaded && !requestsStore.loadingHistory) {
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
  if (activeTab.value === 'parties') {
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
const { cloneRequest } = useRequests()

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
    <div v-if="requestsStore.loadingRequest" class="skeleton-container" aria-busy="true" aria-label="جارٍ التحميل">
      <div class="skeleton skeleton--title" />
      <div class="skeleton skeleton--line" />
      <div class="skeleton skeleton--line skeleton--short" />
    </div>

    <template v-else-if="request">
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
            v-if="claimError || showActiveReviewBanner || showClaimedByOthersBanner || isLocked || isReturnedForCorrection || isBankReturned || isSupportReturned"
            class="banner-area"
          >
            <div v-if="claimError" class="claim-error-banner" role="alert" aria-live="assertive">
              <span class="claim-error-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </span>
              <span>{{ claimError }}</span>
            </div>
            <ActiveReviewBanner v-else-if="showActiveReviewBanner" />
            <ClaimedByOthersBanner v-else-if="showClaimedByOthersBanner" :claimer-name="request.claimed_by?.name ?? ''" />
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
                        <NuxtLink v-if="warn.id" :to="`/requests/${warn.id}`" style="color: #0066cc;" class="font-mono text-xs">
                          {{ warn.reference_number ?? '—' }}
                        </NuxtLink>
                        <span v-else class="font-mono text-xs">{{ warn.reference_number ?? '—' }}</span>
                      </td>
                      <td class="text-sm">{{ warn.bank_name ?? '—' }}</td>
                      <td class="text-sm font-mono">{{ warn.amount?.toLocaleString('ar') ?? '—' }}</td>
                      <td class="text-sm">{{ warn.currency ?? '—' }}</td>
                      <td class="text-xs" style="color: #6c757d;">
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
                <h2 class="card-title">المستندات المرفوعة</h2>
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
                </dl>
              </div>

              <div class="card">
                <h2 class="card-title">مسار سير العمل</h2>

                <div v-if="requestsStore.loadingHistory" class="history-loading" aria-busy="true">
                  <div class="skeleton skeleton--line" />
                  <div class="skeleton skeleton--line" />
                  <div class="skeleton skeleton--line skeleton--short" />
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

              <div id="audit-trail" class="card">
                <h2 class="card-title">سجل الأحداث</h2>

                <div v-if="requestsStore.loadingHistory" class="history-loading" aria-busy="true">
                  <div class="skeleton skeleton--line" />
                  <div class="skeleton skeleton--line" />
                </div>

                <p v-else-if="requestsStore.historyError" class="history-error" role="alert">
                  {{ requestsStore.historyError }}
                </p>

                <AuditTimeline
                  v-else
                  :entries="requestsStore.history"
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
  padding: 24px;
  direction: rtl;
}

/* Breadcrumbs */
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  font-size: 13px;
}

.breadcrumb-link {
  color: #6c757d;
  text-decoration: none;
  transition: color 0.15s;
}

.breadcrumb-link:hover {
  color: #0066cc;
}

.breadcrumb-sep {
  color: #cccccc;
}

.breadcrumb-current {
  color: #1c222b;
  font-weight: 500;
}

/* Page header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.page-header__main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-header__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #1c222b;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.subtitle-dot {
  color: #cccccc;
}

.print-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid #cccccc;
  border-radius: 16px;
  background: #ffffff;
  color: #1c222b;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.print-btn:hover {
  border-color: #0066cc;
  color: #0066cc;
}

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: 1px solid #cccccc;
  border-radius: 16px;
  background: #ffffff;
  color: #1c222b;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.download-btn:hover:not(:disabled) {
  border-color: #0066cc;
  color: #0066cc;
}

.download-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Two-column layout */
.detail-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
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
  gap: 0;
  min-width: 0;
}

/* Banners */
.banner-area {
  margin-bottom: 12px;
}

.support-return-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #f0f6ff;
  border: 1px solid #0066cc33;
  border-radius: 12px;
  color: #004499;
  font-size: 14px;
  margin-bottom: 12px;
}

.support-return-hint__icon {
  font-size: 16px;
  flex-shrink: 0;
}

.support-return-hint__comment {
  font-size: 13px;
  color: #004499;
  font-weight: 400;
}

.support-return-hint__link {
  margin-inline-start: auto;
  border: 0;
  background: transparent;
  color: #0066cc;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

.support-return-hint__link:hover {
  color: #004499;
}

.dup-widget {
  border: 1px solid #f57f1755;
  border-radius: 12px;
  overflow: hidden;
  background: #fffbf0;
  margin-bottom: 12px;
}

.dup-widget-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 16px;
  background: #fff8e1;
  border-bottom: 1px solid #f57f1733;
}

.dup-widget-heading {
  display: flex;
  align-items: center;
  gap: 10px;
}

.dup-badge {
  font-size: 11px;
  font-weight: 700;
  color: #ffffff;
  background: #f57f17;
  border-radius: 6px;
  padding: 2px 8px;
}

.dup-widget-title {
  font-size: 13px;
  font-weight: 600;
  color: #7c5700;
}

.dup-widget-toggle {
  border: none;
  background: none;
  color: #7c5700;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  padding: 0;
}

.dup-widget-body {
  padding: 12px 16px;
}

.dup-widget-table {
  margin: 0;
}

.dup-widget-summary {
  font-size: 13px;
  color: #6c757d;
  margin: 0;
}

.claim-error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fff0ef;
  border: 1px solid #ff3b3033;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
  color: #c62828;
}

.claim-error-icon {
  display: flex;
  flex-shrink: 0;
}

/* Inline voting panel */
.voting-inline {
  margin-bottom: 16px;
}

/* Tabs */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #cccccc;
  overflow-x: auto;
}

.tab-btn {
  height: 44px;
  padding: 0 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 14px;
  color: #6c757d;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
}

.tab-btn--active {
  color: #0066cc;
  border-bottom-color: #0066cc;
  font-weight: 600;
}

.tab-btn:hover:not(.tab-btn--active) {
  color: #1c222b;
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
  background: #ffffff;
  border: 1px solid #cccccc;
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
  color: #1c222b;
  margin: 0 0 16px 0;
}

/* Detail grid — Lovable field order, 2 columns */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin: 0;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 0;
  border-bottom: 1px solid #f5f5f7;
}

.detail-row:nth-last-child(-n+2) {
  border-bottom: none;
}

.detail-label {
  font-size: 11px;
  color: #6c757d;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-value {
  font-size: 14px;
  color: #1c222b;
  word-break: break-word;
  font-weight: 500;
}

.detail-value--approved {
  color: #1b5e20;
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
  border: 1px solid #0066cc;
  border-radius: 16px;
  background: transparent;
  color: #0066cc;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s;
}

.customs-preview-link:hover {
  background: #f0f7ff;
}

.customs-download {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 16px;
  background: #0066cc;
  color: #ffffff;
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

.docs-error {
  color: #c62828;
  font-size: 13px;
  text-align: center;
  padding: 12px 0 0;
}

.history-loading {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.history-error {
  color: #c62828;
  font-size: 13px;
  text-align: center;
  padding: 24px 0;
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
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 16px;
}

.rail-card--actions {
  padding-bottom: 8px;
}

.rail-card__title {
  font-size: 13px;
  font-weight: 600;
  color: #1c222b;
  margin: 0 0 12px 0;
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
  background: #f5f5f7;
  color: #6c757d;
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
  color: #6c757d;
  font-weight: 500;
}

.quick-info-value {
  font-size: 13px;
  color: #1c222b;
  font-weight: 500;
  word-break: break-word;
}

/* Back link in rail */
.rail-back-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 12px 16px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  color: #6c757d;
  font-size: 13px;
  text-decoration: none;
  transition: color 0.15s, border-color 0.15s;
}

.rail-back-link:hover {
  color: #0066cc;
  border-color: #0066cc;
}

/* Skeleton */
.skeleton-container {
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton {
  background: #e5e5ea;
  border-radius: 6px;
  animation: pulse 1.4s ease-in-out infinite;
}

.skeleton--title {
  height: 28px;
  width: 240px;
}

.skeleton--line {
  height: 18px;
  width: 100%;
}

.skeleton--short {
  width: 60%;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
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
  border: 1px solid #0066cc;
  border-radius: 16px;
  background: #ffffff;
  color: #0066cc;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.clone-btn:hover:not(:disabled) {
  background: #0066cc;
  color: #ffffff;
}

.clone-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.clone-dialog__body {
  font-size: 14px;
  color: #6c757d;
  line-height: 1.6;
}

.clone-dialog__error {
  font-size: 13px;
  color: #c62828;
}

.clone-dialog__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
</style>
