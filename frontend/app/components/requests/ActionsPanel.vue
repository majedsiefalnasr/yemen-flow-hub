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
  resetDirectorState()
})

const performingAction = computed(() => requestsStore.performingAction)

// Determine which panel to show
const showBankReviewerActions = computed(() =>
  props.userRole === UserRole.BANK_REVIEWER
  && (props.request.status === RequestStatus.SUBMITTED || props.request.status === RequestStatus.BANK_REVIEW),
)

const showDataEntryActions = computed(() =>
  props.userRole === UserRole.DATA_ENTRY
  && (props.request.status === RequestStatus.DRAFT || props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL),
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

const showAnyActions = computed(() =>
  showBankReviewerActions.value
  || showDataEntryActions.value
  || showSupportCommitteeActions.value
  || showDirectorVotingActions.value,
)

function resetRejectForm() {
  showRejectForm.value = false
  rejectReason.value = ''
  rejectReasonError.value = ''
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

    <!-- BANK_REVIEWER: BANK_REVIEW → approve or reject -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.BANK_REVIEW">
      <template v-if="!showRejectForm">
        <div class="actions-row">
          <button
            class="action-btn action-btn--approve"
            :disabled="performingAction"
            @click="handleApprove"
          >
            {{ performingAction ? 'جارٍ التنفيذ…' : 'موافقة' }}
          </button>
          <button
            class="action-btn action-btn--reject"
            :disabled="performingAction"
            @click="handleRejectClick"
          >
            رفض
          </button>
        </div>
      </template>

      <!-- Rejection reason form -->
      <template v-else>
        <div class="reject-form">
          <label class="reject-label" for="reject-reason">سبب الرفض <span class="required" aria-hidden="true">*</span></label>
          <textarea
            id="reject-reason"
            v-model="rejectReason"
            class="reject-textarea"
            rows="3"
            placeholder="اكتب سبب الرفض هنا…"
            :aria-invalid="!!rejectReasonError"
            :aria-describedby="rejectReasonError ? 'reject-reason-error' : undefined"
          />
          <p v-if="rejectReasonError" id="reject-reason-error" class="reject-error" role="alert">{{ rejectReasonError }}</p>
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

    <!-- SUPPORT_COMMITTEE: SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me → approve or reject -->
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
  </div>
</template>

<style scoped>
.actions-panel {
  background: #ffffff;
  border-top: 1px solid #d2d2d7;
  padding: 20px 24px;
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
  height: 44px;
  min-width: 120px;
  padding: 0 20px;
  border-radius: 12px;
  font-size: 15px;
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
  background: #0071e3;
  color: #ffffff;
}

.action-btn--primary:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--approve {
  background: #34c759;
  color: #ffffff;
}

.action-btn--approve:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--reject {
  background: #ff3b30;
  color: #ffffff;
}

.action-btn--reject:hover:not(:disabled) {
  opacity: 0.88;
}

.action-btn--secondary {
  background: #f5f5f7;
  color: #1d1d1f;
  border: 1px solid #d2d2d7;
}

.action-btn--secondary:hover:not(:disabled) {
  background: #e5e5ea;
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
</style>
