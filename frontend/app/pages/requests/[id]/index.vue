<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserRole, RequestStatus } from '../../../types/enums'
import { useAuthStore } from '../../../stores/auth.store'
import { useRequestsStore } from '../../../stores/requests.store'
import { useClaimLifecycle } from '../../../composables/useClaimLifecycle'
import StatusBadge from '../../../components/ui/StatusBadge.vue'
import LockedBanner from '../../../components/ui/LockedBanner.vue'
import CorrectionBanner from '../../../components/ui/CorrectionBanner.vue'
import ActiveReviewBanner from '../../../components/ui/ActiveReviewBanner.vue'
import ClaimedByOthersBanner from '../../../components/ui/ClaimedByOthersBanner.vue'
import ActionsPanel from '../../../components/requests/ActionsPanel.vue'

definePageMeta({
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const requestsStore = useRequestsStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

type TabKey = 'overview' | 'documents' | 'timeline' | 'audit'
const activeTab = ref<TabKey>('overview')

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'documents', label: 'المستندات' },
  { key: 'timeline', label: 'مسار العمل' },
  { key: 'audit', label: 'سجل التدقيق' },
]

const request = computed(() => requestsStore.currentRequest)
const userRole = computed(() => auth.user?.role ?? UserRole.DATA_ENTRY)

const isEditable = computed(() => {
  const s = request.value?.status
  return s === RequestStatus.DRAFT || s === RequestStatus.DRAFT_REJECTED_INTERNAL
})

// LockedBanner only for statuses that are truly immutable (no role has any action)
// SUBMITTED and BANK_REVIEW are not locked — BANK_REVIEWER acts on them
const LOCKED_STATUSES = new Set([
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])

const isLocked = computed(() => !!request.value && LOCKED_STATUSES.has(request.value.status))
const isReturnedForCorrection = computed(() => request.value?.status === RequestStatus.DRAFT_REJECTED_INTERNAL)

const canEdit = computed(
  () => userRole.value === UserRole.DATA_ENTRY && isEditable.value,
)

// Downloading state per document id
const downloadingIds = ref<Set<number>>(new Set())
const downloadErrors = ref<Record<number, string>>({})

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
        startHeartbeat(id, handleSessionExpired)

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
        startHeartbeat(id, handleSessionExpired)
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
}

async function onActionCompleted() {
  await requestsStore.loadRequest(id)
  // Re-fetch documents if the user is on the documents tab (action may change document state)
  if (activeTab.value === 'documents') {
    await requestsStore.loadDocuments(id)
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
        <LockedBanner v-else-if="isLocked" :status="request.status" />
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
        <!-- Overview tab -->
        <section v-if="activeTab === 'overview'" class="tab-panel" role="tabpanel" aria-label="نظرة عامة">
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
        </section>

        <!-- Documents tab -->
        <section v-else-if="activeTab === 'documents'" class="tab-panel" role="tabpanel" aria-label="المستندات">
          <div class="card">
            <h2 class="card-title">المستندات المرفوعة</h2>

            <div v-if="requestsStore.loadingDocuments" class="docs-loading" aria-busy="true">
              <div class="skeleton skeleton--line" />
              <div class="skeleton skeleton--line" />
            </div>

            <p v-else-if="requestsStore.documentsError" class="docs-error" role="alert">
              {{ requestsStore.documentsError }}
            </p>

            <p v-else-if="requestsStore.documentsLoaded && requestsStore.documents.length === 0" class="docs-empty">
              لا توجد مستندات مرفوعة بعد.
            </p>

            <ul v-else-if="requestsStore.documents.length > 0" class="docs-list" aria-label="قائمة المستندات">
              <li
                v-for="doc in requestsStore.documents"
                :key="doc.id"
                class="doc-item"
              >
                <div class="doc-info">
                  <span class="doc-name">{{ doc.original_filename }}</span>
                  <span class="doc-meta">
                    {{ formatFileSize(doc.size_bytes) }} · {{ formatDate(doc.uploaded_at) }}
                    <template v-if="doc.uploaded_by_name"> · {{ doc.uploaded_by_name }}</template>
                  </span>
                  <span v-if="downloadErrors[doc.id]" class="doc-download-error" role="alert">
                    {{ downloadErrors[doc.id] }}
                  </span>
                </div>
                <button
                  class="doc-download"
                  :disabled="downloadingIds.has(doc.id)"
                  :aria-label="`تحميل ${doc.original_filename}`"
                  @click="downloadDocument(doc.id, doc.original_filename)"
                >
                  {{ downloadingIds.has(doc.id) ? 'جارٍ التحميل…' : 'تحميل' }}
                </button>
              </li>
            </ul>
          </div>
        </section>

        <!-- Workflow Timeline (placeholder) -->
        <section v-else-if="activeTab === 'timeline'" class="tab-panel" role="tabpanel" aria-label="مسار العمل">
          <div class="card placeholder-card">
            <p class="placeholder-text">مسار العمل — قريباً</p>
          </div>
        </section>

        <!-- Audit History (placeholder) -->
        <section v-else-if="activeTab === 'audit'" class="tab-panel" role="tabpanel" aria-label="سجل التدقيق">
          <div class="card placeholder-card">
            <p class="placeholder-text">سجل التدقيق — قريباً</p>
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

/* Documents */
.docs-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.doc-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #f5f5f7;
  border-radius: 8px;
  gap: 12px;
}

.doc-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  overflow: hidden;
}

.doc-name {
  font-size: 14px;
  font-weight: 500;
  color: #1d1d1f;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.doc-meta {
  font-size: 12px;
  color: #6e6e73;
}

.doc-download-error {
  font-size: 12px;
  color: #ff3b30;
}

.doc-download {
  background: none;
  border: none;
  color: #0071e3;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  flex-shrink: 0;
  padding: 0;
}

.doc-download:hover:not(:disabled) {
  text-decoration: underline;
}

.doc-download:disabled {
  color: #8e8e93;
  cursor: not-allowed;
}

.docs-empty {
  color: #6e6e73;
  font-size: 14px;
  text-align: center;
  padding: 24px 0;
}

.docs-error {
  color: #ff3b30;
  font-size: 14px;
  text-align: center;
  padding: 24px 0;
}

.docs-loading {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* Placeholders */
.placeholder-card {
  text-align: center;
  padding: 48px 24px;
}

.placeholder-text {
  color: #6e6e73;
  font-size: 15px;
  margin: 0;
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

  .page-header {
    flex-direction: column;
  }
}
</style>
