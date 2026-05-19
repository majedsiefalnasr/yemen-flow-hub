<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import { useVotingStore } from '../../../stores/voting.store'
import { useClaimLifecycle } from '../../../composables/useClaimLifecycle'
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

type TabKey = 'overview' | 'documents' | 'parties' | 'votes'
const activeTab = ref<TabKey>('overview')

const VOTING_STAGE_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const VOTING_TAB_STATUSES = new Set([
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

const request = computed(() => requestsStore.currentRequest)
const userRole = computed(() => auth.user?.role ?? UserRole.DATA_ENTRY)

const showVotesTab = computed(() =>
  !!request.value && VOTING_TAB_STATUSES.has(request.value.status),
)

const tabs = computed((): Array<{ key: TabKey; label: string }> => [
  { key: 'overview', label: 'المعلومات' },
  { key: 'documents', label: 'الوثائق' },
  { key: 'parties', label: 'الأطراف' },
  ...(showVotesTab.value ? [{ key: 'votes' as TabKey, label: 'التصويت' }] : []),
])

const isEditable = computed(() => {
  const s = request.value?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL
})

const EXECUTIVE_ROLES = new Set([UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR])
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

type LockedBannerVariant = 'locked' | 'readonly' | 'pending'

const lockedBannerVariant = computed((): LockedBannerVariant | null => {
  if (!request.value) return null
  const s = request.value.status
  if (TERMINAL_STATUSES.has(s)) return 'locked'
  if (userRole.value === UserRole.BANK_REVIEWER && ACTIONABLE_REVIEWER_STATUSES.has(s)) return null
  // Executive roles viewing voting stages have full access — no banner
  if (EXECUTIVE_ROLES.has(userRole.value) && VOTING_STAGE_STATUSES.has(s)) return null
  if (READONLY_STATUSES.has(s)) return 'readonly'
  if (PENDING_STATUSES.has(s)) return 'pending'
  return null
})

const isLocked = computed(() => lockedBannerVariant.value !== null)
const isReturnedForCorrection = computed(() => request.value?.status === RequestStatus.DRAFT_REJECTED_INTERNAL)

watch(showVotesTab, (visible) => {
  if (!visible && activeTab.value === 'votes') {
    activeTab.value = 'overview'
  }
})

const canEdit = computed(
  () => userRole.value === UserRole.DATA_ENTRY && isEditable.value,
)

// Downloading state per document id
const downloadingIds = ref<Set<number>>(new Set())
const downloadErrors = ref<Record<number, string>>({})
const customsDownloadError = ref('')           // overview tab customs card
const checklistCustomsDownloadError = ref('') // documents tab DocumentChecklist

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

  if (!isMounted) return // navigated away during load

  if (requestsStore.error || !requestsStore.currentRequest) {
    await router.replace('/requests')
    return
  }

  // Auto-claim lifecycle for SUPPORT_COMMITTEE
  if (isSupportCommittee.value && requestsStore.currentRequest) {
    const req = requestsStore.currentRequest

    if (req.can_be_claimed) {
      // Attempt to claim the unclaimed request
      const claimed = await claimRequest(id)

      if (!isMounted) {
        // Component was destroyed while claim was in-flight — release immediately
        if (claimed) await releaseRequest(id)
        return
      }

      if (claimed) {
        isActiveReviewer.value = true
        startHeartbeat(id, handleSessionExpired, handleClaimLost)

        // Reload to get authoritative claim state from server
        await requestsStore.loadRequest(id)

        if (!isMounted) return

        if (requestsStore.error || !requestsStore.currentRequest) {
          // Reload failed after successful claim — release and bail
          isActiveReviewer.value = false
          stopHeartbeat(id)
          await releaseRequest(id)
          await router.replace('/requests')
          return
        }
      }
      else {
        // Claim failed (e.g. 409 race) — reload to get authoritative state so
        // ClaimedByOthersBanner reflects the actual server ownership
        await requestsStore.loadRequest(id)
        if (!isMounted) return

        if (sessionExpired.value) {
          await handleSessionExpired()
          return
        }
      }
    }
    else if (req.is_claimed && req.is_claimed_by_me) {
      // Resume branch: client reports we own the claim, but TTL may have expired.
      // Verify with server before starting heartbeat.
      const alive = await verifyClaimAlive(id)

      if (!isMounted) return

      if (alive) {
        isActiveReviewer.value = true
        startHeartbeat(id, handleSessionExpired, handleClaimLost)
      }
      else {
        // Claim expired or session invalid — reload to show authoritative UI state
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
})

onBeforeUnmount(() => {
  isMounted = false
  stopHeartbeat(id)
  if (isActiveReviewer.value) {
    // Best-effort release — fire-and-forget; TTL auto-expire recovers misses
    releaseRequest(id)
  }
})

async function onTabChange(key: TabKey) {
  activeTab.value = key
  if (key === 'documents' && !requestsStore.documentsLoaded && !requestsStore.loadingDocuments) {
    await requestsStore.loadDocuments(id)
  }
  if (key === 'votes' && !votingStore.votingDetail && !votingStore.loadingDetail) {
    await votingStore.loadVotingDetail(id)
  }
  if (key === 'parties' && !requestsStore.historyLoaded && !requestsStore.loadingHistory) {
    await requestsStore.loadHistory(id)
  }
}

async function onActionCompleted() {
  await requestsStore.loadRequest(id)
  customsDownloadError.value = ''
  syncActiveReviewState()
  // Re-fetch documents if the user is on the documents tab
  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id)
  }
  if (activeTab.value === 'parties') {
    await requestsStore.loadHistory(id)
  }
  // Reload voting detail when a director action completes (session open/close/finalize/override)
  if (votingStore.votingDetail || activeTab.value === 'votes') {
    await votingStore.loadVotingDetail(id)
  }
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
    // uploadError is set on the store by the action; component reads it from there
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

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar-YE')} ${currency}`
}

function actorLabel(id: number | null | undefined): string {
  if (id === null || id === undefined) return '—'
  return `#${id}`
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
      <!-- Page header -->
      <div class="page-header">
        <div class="page-header__right">
          <NuxtLink to="/requests" class="back-link" aria-label="العودة إلى القائمة">
            ← العودة إلى القائمة
          </NuxtLink>
          <h1 class="page-title">{{ request.reference_number }}</h1>
        </div>
        <div class="page-header__actions">
          <StatusBadge :status="request.status" :role="userRole" />
          <NuxtLink
            v-if="canEdit"
            :to="`/requests/${request.id}/edit`"
            class="edit-btn"
            aria-label="تعديل الطلب"
          >
            تعديل
          </NuxtLink>
        </div>
      </div>

      <!-- Banners -->
      <div v-if="claimError || showActiveReviewBanner || showClaimedByOthersBanner || isLocked || isReturnedForCorrection" class="banner-area">
        <!-- Claim error (highest priority — explicit action required) -->
        <div v-if="claimError" class="claim-error-banner" role="alert" aria-live="assertive" dir="rtl">
          <span class="claim-error-icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          </span>
          <span>{{ claimError }}</span>
        </div>
        <ActiveReviewBanner v-else-if="showActiveReviewBanner" />
        <ClaimedByOthersBanner v-else-if="showClaimedByOthersBanner" :claimer-name="request.claimed_by?.name ?? ''" />
        <LockedBanner v-else-if="isLocked && lockedBannerVariant" :variant="lockedBannerVariant" />
        <CorrectionBanner v-else-if="isReturnedForCorrection" />
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
                <dt class="detail-label">رقم الطلب</dt>
                <dd class="detail-value">{{ request.reference_number }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">البنك</dt>
                <dd class="detail-value">{{ request.bank_name ?? '—' }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">العملة</dt>
                <dd class="detail-value">{{ request.currency }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">المبلغ</dt>
                <dd class="detail-value">{{ formatAmount(request.amount, request.currency) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">المورّد</dt>
                <dd class="detail-value">{{ request.supplier_name }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">وصف البضاعة</dt>
                <dd class="detail-value">{{ request.goods_description }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">ميناء الدخول</dt>
                <dd class="detail-value">{{ request.port_of_entry }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">ملاحظات</dt>
                <dd class="detail-value">{{ request.notes ?? '—' }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">تاريخ الإنشاء</dt>
                <dd class="detail-value">{{ formatDate(request.created_at) }}</dd>
              </div>
            </dl>
          </div>

          <div v-if="request.status === RequestStatus.COMPLETED && request.customs_declaration" class="card customs-card">
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

        <!-- Votes tab -->
        <section v-else-if="activeTab === 'votes'" class="tab-panel" role="tabpanel" aria-label="التصويت">
          <div class="card card--no-padding">
            <VotingPanel
              :request-id="id"
              :request-status="request.status"
              :user-role="userRole"
            />
          </div>
        </section>

        <!-- الأطراف tab: actors + workflow timeline + audit trail -->
        <section v-else-if="activeTab === 'parties'" class="tab-panel" role="tabpanel" aria-label="الأطراف">
          <!-- Workflow actors -->
          <div class="card">
            <h2 class="card-title">فريق العمل</h2>
            <dl class="detail-grid">
              <div class="detail-row">
                <dt class="detail-label">أنشأ الطلب</dt>
                <dd class="detail-value">{{ actorLabel(request.created_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">قدّم الطلب</dt>
                <dd class="detail-value">{{ actorLabel(request.submitted_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">المراجع الداخلي</dt>
                <dd class="detail-value">{{ actorLabel(request.reviewed_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">وافق</dt>
                <dd class="detail-value">{{ actorLabel(request.approved_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">مراجع لجنة الدعم</dt>
                <dd class="detail-value">{{ request.claimed_by?.name ?? '—' }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">رفع SWIFT</dt>
                <dd class="detail-value">{{ actorLabel(request.swift_uploaded_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">رفض الطلب</dt>
                <dd class="detail-value">{{ actorLabel(request.rejected_by) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">أعاد التقديم</dt>
                <dd class="detail-value">{{ actorLabel(request.resubmitted_by) }}</dd>
              </div>
            </dl>
          </div>

          <!-- Workflow timeline -->
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

          <!-- Audit trail -->
          <div class="card">
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

      <!-- Actions panel (sticky at bottom) -->
      <ActionsPanel
        :request="request"
        :user-role="userRole"
        @action-completed="onActionCompleted"
      />
    </template>
  </div>
</template>

<style scoped>
.detail-page {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 100%;
}

/* Header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 24px 24px 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-header__right {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-link {
  color: #0071e3;
  text-decoration: none;
  font-size: 14px;
}

.back-link:hover {
  text-decoration: underline;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #1d1d1f;
  margin: 0;
}

.edit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  background: #0071e3;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: opacity 0.15s;
}

.edit-btn:hover {
  opacity: 0.88;
}

/* Banners */
.banner-area {
  padding: 0 24px 12px;
}

.claim-error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fff0ef;
  border: 1px solid #ff3b3033;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  color: #cc0000;
  direction: rtl;
}

.claim-error-icon {
  display: flex;
  flex-shrink: 0;
}

/* Tabs */
.tab-nav {
  display: flex;
  gap: 4px;
  padding: 0 24px 0;
  border-bottom: 1px solid #d2d2d7;
  overflow-x: auto;
}

.tab-btn {
  height: 44px;
  padding: 0 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 15px;
  color: #6e6e73;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
}

.tab-btn--active {
  color: #0071e3;
  border-bottom-color: #0071e3;
  font-weight: 600;
}

.tab-btn:hover:not(.tab-btn--active) {
  color: #1d1d1f;
}

/* Tab content */
.tab-content {
  flex: 1;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tab-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Cards */
.card {
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px 24px;
}

.card--no-padding {
  padding: 0;
  overflow: hidden;
}

.card-title {
  font-size: 16px;
  font-weight: 700;
  color: #1d1d1f;
  margin: 0 0 16px 0;
}

/* Detail grid */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 24px;
  margin: 0;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.detail-label {
  font-size: 12px;
  color: #6e6e73;
  font-weight: 500;
}

.detail-value {
  font-size: 15px;
  color: #1d1d1f;
  word-break: break-word;
}

.detail-value--approved {
  color: #34c759;
  font-weight: 700;
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
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #0071e3;
  border-radius: 8px;
  background: transparent;
  color: #0071e3;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
}

.customs-preview-link:hover {
  background: #f0f7ff;
}

.customs-download {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 18px;
  border: 0;
  border-radius: 8px;
  background: #0071e3;
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.customs-download:hover:not(:disabled) {
  opacity: 0.88;
}

.customs-download:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Documents — error displayed in Overview tab's customs section */
.docs-error {
  color: #ff3b30;
  font-size: 14px;
  text-align: center;
  padding: 24px 0;
}

.history-loading {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.history-error {
  color: #ff3b30;
  font-size: 14px;
  text-align: center;
  padding: 24px 0;
}

/* Skeleton */
.skeleton-container {
  padding: 24px;
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
}

@media print {
  .back-link,
  .edit-btn,
  .tab-nav,
  .banner-area,
  .customs-download,
  .customs-preview-link,
  .actions-panel {
    display: none !important;
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
</style>
