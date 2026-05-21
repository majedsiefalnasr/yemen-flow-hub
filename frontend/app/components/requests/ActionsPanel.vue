<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ImportRequest } from '../../types/models'
import { useRequestsStore } from '../../stores/requests.store'
import { useVotingStore } from '../../stores/voting.store'

const props = defineProps<{
  request: ImportRequest
  userRole: UserRole
}>()

const emit = defineEmits<{
  'action-completed': []
}>()

const requestsStore = useRequestsStore()
const votingStore = useVotingStore()

const showRejectForm = ref(false)
const rejectReason = ref('')
const rejectReasonError = ref('')
const actionError = ref('')

const showBankReturnModal = ref(false)
const bankReturnComment = ref('')
const bankReturnCommentError = ref('')

const showBankRejectTerminalModal = ref(false)
const bankRejectTerminalComment = ref('')
const bankRejectTerminalCommentError = ref('')

const showSupportReturnModal = ref(false)
const supportReturnComment = ref('')
const supportReturnCommentError = ref('')

// Director session lifecycle state
const showCloseConfirm = ref(false)
const showOverrideModal = ref(false)
const overrideDecision = ref<'APPROVE' | 'REJECT' | null>(null)
const overrideJustification = ref('')
const overrideDecisionError = ref('')
const overrideJustificationError = ref('')

// Clear transient error state when the request transitions to a new status
watch(() => props.request.status, () => {
  actionError.value = ''
  resetRejectForm()
  resetBankReturnModal()
  resetBankRejectTerminalModal()
  resetSupportReturnModal()
  resetDirectorState()
})

const performingAction = computed(() => requestsStore.performingAction)

// Determine which panel to show
const showBankReviewerActions = computed(() =>
  props.userRole === UserRole.BANK_REVIEWER
  && (props.request.status === RequestStatus.SUBMITTED || props.request.status === RequestStatus.BANK_REVIEW),
)

const showDataEntryActions = computed(() =>
  (props.userRole === UserRole.DATA_ENTRY || props.userRole === UserRole.BANK_ADMIN)
  && (props.request.status === RequestStatus.DRAFT
    || props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL
    || props.request.status === RequestStatus.BANK_RETURNED
    || props.request.status === RequestStatus.SUPPORT_RETURNED),
)

const showSupportCommitteeActions = computed(() =>
  props.userRole === UserRole.SUPPORT_COMMITTEE
  && props.request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
  && props.request.is_claimed_by_me,
)

const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

const showDirectorVotingActions = computed(() =>
  props.userRole === UserRole.COMMITTEE_DIRECTOR
  && DIRECTOR_VOTING_STATUSES.has(props.request.status),
)

const showDirectorCustomsActions = computed(() =>
  props.userRole === UserRole.COMMITTEE_DIRECTOR
  && props.request.status === RequestStatus.EXECUTIVE_APPROVED,
)

const showAnyActions = computed(() =>
  showBankReviewerActions.value
  || showDataEntryActions.value
  || showSupportCommitteeActions.value
  || showDirectorVotingActions.value
  || showDirectorCustomsActions.value
)

function resetRejectForm() {
  showRejectForm.value = false
  rejectReason.value = ''
  rejectReasonError.value = ''
}

function resetBankReturnModal() {
  showBankReturnModal.value = false
  bankReturnComment.value = ''
  bankReturnCommentError.value = ''
}

function resetBankRejectTerminalModal() {
  showBankRejectTerminalModal.value = false
  bankRejectTerminalComment.value = ''
  bankRejectTerminalCommentError.value = ''
}

async function handleBankRejectTerminalConfirm() {
  bankRejectTerminalCommentError.value = ''
  if (bankRejectTerminalComment.value.trim().length < 3) {
    bankRejectTerminalCommentError.value = 'سبب الرفض مطلوب ويجب أن يكون 3 أحرف على الأقل.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.bankRejectTerminal(props.request.id, bankRejectTerminalComment.value.trim())
    resetBankRejectTerminalModal()
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر تنفيذ الرفض النهائي.'
    resetBankRejectTerminalModal()
  }
}

function resetSupportReturnModal() {
  showSupportReturnModal.value = false
  supportReturnComment.value = ''
  supportReturnCommentError.value = ''
}

async function handleBankReturnConfirm() {
  bankReturnCommentError.value = ''
  if (bankReturnComment.value.trim().length < 3) {
    bankReturnCommentError.value = 'التعليق مطلوب ويجب أن يكون 3 أحرف على الأقل.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.bankReturn(props.request.id, bankReturnComment.value.trim())
    resetBankReturnModal()
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إعادة الطلب للمدخل.'
    resetBankReturnModal()
  }
}

async function handleSupportReturnConfirm() {
  supportReturnCommentError.value = ''
  if (supportReturnComment.value.trim().length < 3) {
    supportReturnCommentError.value = 'التعليق مطلوب ويجب أن يكون 3 أحرف على الأقل.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.supportReturn(props.request.id, supportReturnComment.value.trim())
    resetSupportReturnModal()
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إعادة الطلب للمدخل.'
    resetSupportReturnModal()
  }
}

function resetDirectorState() {
  showCloseConfirm.value = false
  showOverrideModal.value = false
  overrideDecision.value = null
  overrideJustification.value = ''
  overrideDecisionError.value = ''
  overrideJustificationError.value = ''
}

async function handleOpenSession() {
  actionError.value = ''
  try {
    await votingStore.openSession(props.request.id)
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر فتح جلسة التصويت.'
  }
}

async function handleCloseSession() {
  actionError.value = ''
  try {
    await votingStore.closeSession(props.request.id)
    showCloseConfirm.value = false
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إغلاق جلسة التصويت.'
    showCloseConfirm.value = false
  }
}

async function handleFinalizeDecision() {
  actionError.value = ''
  try {
    await votingStore.finalizeDecision(props.request.id)
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إصدار القرار النهائي.'
  }
}

async function handleDirectorOverride() {
  overrideDecisionError.value = ''
  overrideJustificationError.value = ''

  if (!overrideDecision.value) {
    overrideDecisionError.value = 'يجب اختيار قرار (موافقة أو رفض).'
    return
  }
  if (overrideJustification.value.trim().length < 10) {
    overrideJustificationError.value = 'المبرر مطلوب ويجب أن يكون 10 أحرف على الأقل.'
    return
  }

  actionError.value = ''
  try {
    await votingStore.directorOverride(props.request.id, overrideDecision.value, overrideJustification.value.trim())
    resetDirectorState()
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر تنفيذ قرار التجاوز.'
    resetDirectorState()
  }
}

async function handleIssueCustomsDeclaration() {
  actionError.value = ''
  try {
    await requestsStore.issueCustomsDeclaration(props.request.id)
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إصدار البيان الجمركي.'
  }
}

async function handleBeginReview() {
  await dispatchAction('bank-review')
}

async function handleApprove() {
  await dispatchAction('bank-approve')
}

async function handleSupportApprove() {
  await dispatchAction('support-approve')
}

function handleRejectClick() {
  showRejectForm.value = true
  rejectReasonError.value = ''
}

async function handleRejectConfirm() {
  if (!rejectReason.value.trim()) {
    rejectReasonError.value = 'سبب الرفض مطلوب.'
    return
  }
  const action = showSupportCommitteeActions.value ? 'support-reject' : 'bank-reject'
  await dispatchAction(action, rejectReason.value.trim())
}

async function dispatchAction(action: string, reason?: string) {
  actionError.value = ''

  try {
    await requestsStore.performAction(props.request.id, action, reason)
    resetRejectForm()
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر تنفيذ الإجراء. يرجى المحاولة مرة أخرى.'
  }
}
</script>

<template>
  <div v-if="showAnyActions" class="actions-panel" dir="rtl" role="region" aria-label="لوحة الإجراءات">
    <!-- Error message -->
    <p v-if="actionError" class="actions-error" role="alert">{{ actionError }}</p>

    <!-- BANK_REVIEWER: SUBMITTED → begin review -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.SUBMITTED">
      <button
        class="action-btn action-btn--primary"
        :disabled="performingAction"
        @click="handleBeginReview"
      >
        {{ performingAction ? 'جارٍ التنفيذ…' : 'البدء بالمراجعة' }}
      </button>
    </template>

    <!-- BANK_REVIEWER: BANK_REVIEW → approve, terminal reject, or return to intake -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.BANK_REVIEW">
      <div class="actions-row">
        <button
          class="action-btn action-btn--approve"
          :disabled="performingAction"
          @click="handleApprove"
        >
          {{ performingAction ? 'جارٍ التنفيذ…' : 'اعتماد' }}
        </button>
        <button
          class="action-btn action-btn--reject"
          :disabled="performingAction"
          @click="showBankRejectTerminalModal = true"
        >
          رفض نهائي
        </button>
        <button
          class="action-btn action-btn--secondary"
          :disabled="performingAction"
          @click="showBankReturnModal = true"
        >
          إعادة للمدخل
        </button>
      </div>
    </template>

    <!-- SUPPORT_COMMITTEE: SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me → approve, reject, or return -->
    <template v-if="showSupportCommitteeActions">
      <template v-if="!showRejectForm">
        <div class="actions-row">
          <button
            class="action-btn action-btn--approve"
            :disabled="performingAction"
            @click="handleSupportApprove"
          >
            {{ performingAction ? 'جارٍ التنفيذ…' : 'اعتماد' }}
          </button>
          <button
            class="action-btn action-btn--reject"
            :disabled="performingAction"
            @click="handleRejectClick"
          >
            رفض
          </button>
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="showSupportReturnModal = true"
          >
            إعادة للمدخل
          </button>
        </div>
      </template>

      <!-- Rejection reason form -->
      <template v-else>
        <div class="reject-form">
          <label class="reject-label" for="reject-reason-support">سبب الرفض <span class="required" aria-hidden="true">*</span></label>
          <textarea
            id="reject-reason-support"
            v-model="rejectReason"
            class="reject-textarea"
            rows="3"
            placeholder="اكتب سبب الرفض هنا…"
            :aria-invalid="!!rejectReasonError"
            :aria-describedby="rejectReasonError ? 'reject-reason-support-error' : undefined"
          />
          <p v-if="rejectReasonError" id="reject-reason-support-error" class="reject-error" role="alert">{{ rejectReasonError }}</p>
          <div class="actions-row">
            <button
              class="action-btn action-btn--reject"
              :disabled="performingAction"
              @click="handleRejectConfirm"
            >
              {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الرفض' }}
            </button>
            <button
              class="action-btn action-btn--secondary"
              :disabled="performingAction"
              @click="resetRejectForm"
            >
              إلغاء
            </button>
          </div>
        </div>
      </template>
    </template>

    <!-- DATA_ENTRY: DRAFT → edit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.DRAFT">
      <NuxtLink :to="`/requests/${request.id}/edit`" class="action-btn action-btn--primary">
        تعديل
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: DRAFT_REJECTED_INTERNAL → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL">
      <NuxtLink :to="`/requests/${request.id}/edit`" class="action-btn action-btn--primary">
        تعديل وإعادة تقديم
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: BANK_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.BANK_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`" class="action-btn action-btn--primary">
        تعديل وإعادة تقديم
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: SUPPORT_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.SUPPORT_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`" class="action-btn action-btn--primary">
        تعديل وإعادة تقديم
      </NuxtLink>
    </template>

    <!-- COMMITTEE_DIRECTOR: WAITING_FOR_VOTING_OPEN → open session -->
    <template v-if="showDirectorVotingActions && request.status === RequestStatus.WAITING_FOR_VOTING_OPEN">
      <button
        class="action-btn action-btn--primary"
        :disabled="votingStore.performingDirectorAction"
        @click="handleOpenSession"
      >
        {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'فتح جلسة التصويت' }}
      </button>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_OPEN → close session or override -->
    <template v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_OPEN">
      <div class="actions-row">
        <!-- Close session with inline confirmation -->
        <template v-if="!showCloseConfirm">
          <button
            class="action-btn action-btn--reject"
            :disabled="votingStore.performingDirectorAction"
            @click="showCloseConfirm = true"
          >
            إغلاق جلسة التصويت
          </button>
          <button
            class="action-btn action-btn--amber"
            :disabled="votingStore.performingDirectorAction"
            @click="showOverrideModal = true"
          >
            تجاوز مدير اللجنة
          </button>
        </template>
        <template v-else>
          <div class="close-confirm">
            <p class="close-confirm__text">هل أنت متأكد من إغلاق جلسة التصويت؟ لن يتمكن الأعضاء من التصويت بعد ذلك.</p>
            <div class="actions-row">
              <button
                class="action-btn action-btn--reject"
                :disabled="votingStore.performingDirectorAction"
                @click="handleCloseSession"
              >
                {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'تأكيد الإغلاق' }}
              </button>
              <button
                class="action-btn action-btn--secondary"
                :disabled="votingStore.performingDirectorAction"
                @click="showCloseConfirm = false"
              >
                إلغاء
              </button>
            </div>
          </div>
        </template>
      </div>

      <!-- Director Override modal (inline) -->
      <div v-if="showOverrideModal" class="override-modal" role="dialog" aria-labelledby="override-modal-title" aria-modal="true">
        <div class="override-modal__content">
          <h3 id="override-modal-title" class="override-modal__title">تجاوز مدير اللجنة</h3>

          <!-- Current tally snapshot -->
          <div v-if="votingStore.votingDetail?.tally" class="tally-snapshot">
            <span class="tally-snapshot__item tally-snapshot__item--approve">
              موافق: {{ votingStore.votingDetail.tally.approve_count }}
            </span>
            <span class="tally-snapshot__item tally-snapshot__item--reject">
              رافض: {{ votingStore.votingDetail.tally.reject_count }}
            </span>
            <span class="tally-snapshot__item tally-snapshot__item--abstain">
              ممتنع/غائب: {{ votingStore.votingDetail.tally.abstain_count + votingStore.votingDetail.tally.auto_abstain_count }}
            </span>
          </div>

          <!-- Decision selection -->
          <fieldset class="override-fieldset">
            <legend class="override-legend">القرار <span class="required" aria-hidden="true">*</span></legend>
            <label class="override-radio-label">
              <input v-model="overrideDecision" type="radio" name="override-decision" value="APPROVE" />
              <span>موافقة</span>
            </label>
            <label class="override-radio-label">
              <input v-model="overrideDecision" type="radio" name="override-decision" value="REJECT" />
              <span>رفض</span>
            </label>
            <p v-if="overrideDecisionError" class="override-error" role="alert">{{ overrideDecisionError }}</p>
          </fieldset>

          <!-- Justification -->
          <div class="override-justify">
            <label class="override-legend" for="override-justification">
              المبرر <span class="required" aria-hidden="true">*</span>
            </label>
            <textarea
              id="override-justification"
              v-model="overrideJustification"
              class="override-textarea"
              rows="4"
              placeholder="اكتب مبرر القرار هنا (10 أحرف على الأقل)…"
              :aria-invalid="!!overrideJustificationError"
            />
            <p v-if="overrideJustificationError" class="override-error" role="alert">{{ overrideJustificationError }}</p>
          </div>

          <!-- Actions -->
          <div class="override-actions">
            <button
              class="action-btn action-btn--amber"
              :disabled="votingStore.performingDirectorAction"
              @click="handleDirectorOverride"
            >
              {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'تأكيد التجاوز' }}
            </button>
            <button
              class="action-btn action-btn--secondary"
              :disabled="votingStore.performingDirectorAction"
              @click="resetDirectorState"
            >
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_CLOSED → finalize decision -->
    <template v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_CLOSED">
      <div v-if="votingStore.votingDetail?.tally" class="tally-snapshot tally-snapshot--standalone">
        <span class="tally-snapshot__item tally-snapshot__item--approve">
          موافق: {{ votingStore.votingDetail.tally.approve_count }}
        </span>
        <span class="tally-snapshot__item tally-snapshot__item--reject">
          رافض: {{ votingStore.votingDetail.tally.reject_count }}
        </span>
        <span class="tally-snapshot__item tally-snapshot__item--abstain">
          ممتنع/غائب: {{ votingStore.votingDetail.tally.abstain_count + votingStore.votingDetail.tally.auto_abstain_count }}
        </span>
      </div>
      <button
        class="action-btn action-btn--primary"
        :disabled="votingStore.performingDirectorAction"
        @click="handleFinalizeDecision"
      >
        {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'إصدار القرار النهائي' }}
      </button>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_APPROVED → issue customs declaration -->
    <template v-if="showDirectorCustomsActions">
      <button
        class="action-btn action-btn--approve"
        :disabled="requestsStore.issuingCustoms"
        @click="handleIssueCustomsDeclaration"
      >
        {{ requestsStore.issuingCustoms ? 'جارٍ الإصدار…' : 'إصدار البيان الجمركي' }}
      </button>
    </template>

    <!-- Bank Return modal -->
    <div
      v-if="showBankReturnModal"
      class="bank-return-modal"
      role="dialog"
      aria-labelledby="bank-return-modal-title"
      aria-modal="true"
    >
      <div class="bank-return-modal__content">
        <h3 id="bank-return-modal-title" class="bank-return-modal__title">إعادة الطلب للمدخل</h3>
        <div class="bank-return-form">
          <label class="reject-label" for="bank-return-comment">
            سبب الإعادة <span class="required" aria-hidden="true">*</span>
          </label>
          <textarea
            id="bank-return-comment"
            v-model="bankReturnComment"
            class="reject-textarea"
            rows="4"
            placeholder="اكتب سبب الإعادة هنا (3 أحرف على الأقل)…"
            :aria-invalid="!!bankReturnCommentError"
            :aria-describedby="bankReturnCommentError ? 'bank-return-comment-error' : undefined"
          />
          <p v-if="bankReturnCommentError" id="bank-return-comment-error" class="reject-error" role="alert">
            {{ bankReturnCommentError }}
          </p>
        </div>
        <div class="bank-return-modal__actions">
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="handleBankReturnConfirm"
          >
            {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الإعادة' }}
          </button>
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="resetBankReturnModal"
          >
            إلغاء
          </button>
        </div>
      </div>
    </div>
    <!-- Bank Reject Terminal modal -->
    <div
      v-if="showBankRejectTerminalModal"
      class="bank-return-modal bank-reject-terminal-modal"
      role="dialog"
      aria-labelledby="bank-reject-terminal-modal-title"
      aria-modal="true"
    >
      <div class="bank-return-modal__content">
        <h3 id="bank-reject-terminal-modal-title" class="bank-return-modal__title bank-reject-terminal-modal__title">
          تأكيد الرفض النهائي
        </h3>
        <p class="bank-reject-terminal-modal__warning">
          تحذير: هذا الإجراء نهائي ولا يمكن التراجع عنه. سيتم رفض الطلب بشكل دائم.
        </p>
        <div class="bank-return-form">
          <label class="reject-label" for="bank-reject-terminal-comment">
            سبب الرفض <span class="required" aria-hidden="true">*</span>
          </label>
          <textarea
            id="bank-reject-terminal-comment"
            v-model="bankRejectTerminalComment"
            class="reject-textarea"
            rows="4"
            placeholder="اكتب سبب الرفض النهائي هنا (3 أحرف على الأقل)…"
            :aria-invalid="!!bankRejectTerminalCommentError"
            :aria-describedby="bankRejectTerminalCommentError ? 'bank-reject-terminal-comment-error' : undefined"
          />
          <p v-if="bankRejectTerminalCommentError" id="bank-reject-terminal-comment-error" class="reject-error" role="alert">
            {{ bankRejectTerminalCommentError }}
          </p>
        </div>
        <div class="bank-return-modal__actions">
          <button
            class="action-btn action-btn--reject"
            :disabled="performingAction"
            @click="handleBankRejectTerminalConfirm"
          >
            {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الرفض النهائي' }}
          </button>
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="resetBankRejectTerminalModal"
          >
            إلغاء
          </button>
        </div>
      </div>
    </div>

    <!-- Support Return modal -->
    <div
      v-if="showSupportReturnModal"
      class="bank-return-modal"
      role="dialog"
      aria-labelledby="support-return-modal-title"
      aria-modal="true"
    >
      <div class="bank-return-modal__content">
        <h3 id="support-return-modal-title" class="bank-return-modal__title">إعادة الطلب للمدخل</h3>
        <div class="bank-return-form">
          <label class="reject-label" for="support-return-comment">
            سبب الإعادة <span class="required" aria-hidden="true">*</span>
          </label>
          <textarea
            id="support-return-comment"
            v-model="supportReturnComment"
            class="reject-textarea"
            rows="4"
            placeholder="اكتب سبب الإعادة هنا (3 أحرف على الأقل)…"
            :aria-invalid="!!supportReturnCommentError"
            :aria-describedby="supportReturnCommentError ? 'support-return-comment-error' : undefined"
          />
          <p v-if="supportReturnCommentError" id="support-return-comment-error" class="reject-error" role="alert">
            {{ supportReturnCommentError }}
          </p>
        </div>
        <div class="bank-return-modal__actions">
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="handleSupportReturnConfirm"
          >
            {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الإعادة' }}
          </button>
          <button
            class="action-btn action-btn--secondary"
            :disabled="performingAction"
            @click="resetSupportReturnModal"
          >
            إلغاء
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.actions-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.actions-row {
  display: flex;
  gap: 12px;
  flex-direction: row-reverse;
  justify-content: flex-start;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  min-width: 100%;
  padding: 0 16px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: opacity 0.15s;
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.action-btn--primary {
  background: #0066cc;
  color: #ffffff;
}

.action-btn--primary:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--approve {
  background: #1b5e20;
  color: #ffffff;
}

.action-btn--approve:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--reject {
  background: #c62828;
  color: #ffffff;
}

.action-btn--reject:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--secondary {
  background: #f5f5f7;
  color: #1c222b;
  border: 1px solid #cccccc;
}

.action-btn--secondary:hover:not(:disabled) {
  background: #e8e8ed;
}

.reject-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reject-label {
  font-size: 14px;
  font-weight: 500;
  color: #6e6e73;
}

.required {
  color: #ff3b30;
}

.reject-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #d2d2d7;
  border-radius: 8px;
  font-size: 15px;
  font-family: inherit;
  color: #1d1d1f;
  background: #ffffff;
  resize: vertical;
  direction: rtl;
}

.reject-textarea:focus {
  outline: none;
  border-color: #0071e3;
}

.reject-error {
  font-size: 13px;
  color: #ff3b30;
  margin: 0;
}

.actions-error {
  font-size: 14px;
  color: #ff3b30;
  background: #fff0ef;
  border: 1px solid #ff3b3033;
  border-radius: 8px;
  padding: 10px 14px;
  margin: 0;
}

.action-btn--amber {
  background: #ff9f0a;
  color: #ffffff;
}

.action-btn--amber:hover:not(:disabled) {
  opacity: 0.88;
}

/* Close session confirm */
.close-confirm {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.close-confirm__text {
  font-size: 14px;
  color: #1d1d1f;
  margin: 0;
}

/* Director override modal */
.override-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.override-modal__content {
  background: #ffffff;
  border-radius: 16px;
  padding: 28px;
  width: 480px;
  max-width: 95vw;
  display: flex;
  flex-direction: column;
  gap: 18px;
  direction: rtl;
}

.override-modal__title {
  font-size: 18px;
  font-weight: 600;
  color: #1d1d1f;
  margin: 0;
}

/* Tally snapshot */
.tally-snapshot {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.tally-snapshot--standalone {
  margin-bottom: 4px;
}

.tally-snapshot__item {
  font-size: 13px;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 6px;
}

.tally-snapshot__item--approve { background: #34c75922; color: #1a8a3a; }
.tally-snapshot__item--reject { background: #ff3b3022; color: #cc2020; }
.tally-snapshot__item--abstain { background: #8e8e9322; color: #5a5a5e; }

/* Override fieldset */
.override-fieldset {
  border: 1px solid #d2d2d7;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.override-legend {
  font-size: 13px;
  font-weight: 500;
  color: #6e6e73;
  padding: 0;
  margin: 0 0 4px;
}

.override-radio-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #1d1d1f;
  cursor: pointer;
}

.override-radio-label input { cursor: pointer; }

.override-justify {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.override-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #d2d2d7;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  color: #1d1d1f;
  background: #ffffff;
  resize: vertical;
  direction: rtl;
}

.override-textarea:focus { outline: none; border-color: #ff9f0a; }

.override-error {
  font-size: 12px;
  color: #ff3b30;
  margin: 0;
}

.override-actions {
  display: flex;
  gap: 10px;
  flex-direction: row-reverse;
  justify-content: flex-start;
}

/* Bank Return modal */
.bank-return-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.bank-return-modal__content {
  background: #ffffff;
  border-radius: 16px;
  padding: 28px;
  width: 480px;
  max-width: 95vw;
  display: flex;
  flex-direction: column;
  gap: 18px;
  direction: rtl;
}

.bank-return-modal__title {
  font-size: 18px;
  font-weight: 600;
  color: #1d1d1f;
  margin: 0;
}

.bank-return-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bank-return-modal__actions {
  display: flex;
  gap: 10px;
  flex-direction: row-reverse;
  justify-content: flex-start;
}

.bank-reject-terminal-modal__title {
  color: #c62828;
}

.bank-reject-terminal-modal__warning {
  font-size: 13px;
  color: #c62828;
  background: #fff0ef;
  border: 1px solid #ff3b3033;
  border-radius: 8px;
  padding: 10px 14px;
  margin: 0;
}
</style>
