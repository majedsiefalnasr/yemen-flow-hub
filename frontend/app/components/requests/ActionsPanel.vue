<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ImportRequest } from '../../types/models'
import { useRequestsStore } from '../../stores/requests.store'

const props = defineProps<{
  request: ImportRequest
  userRole: UserRole
}>()

const emit = defineEmits<{
  'action-completed': []
}>()

const requestsStore = useRequestsStore()

const showRejectForm = ref(false)
const rejectReason = ref('')
const rejectReasonError = ref('')
const actionError = ref('')

// Clear transient error state when the request transitions to a new status
watch(() => props.request.status, () => {
  actionError.value = ''
  resetRejectForm()
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

const showAnyActions = computed(() => showBankReviewerActions.value || showDataEntryActions.value)

function resetRejectForm() {
  showRejectForm.value = false
  rejectReason.value = ''
  rejectReasonError.value = ''
}

async function handleBeginReview() {
  await dispatchAction('bank-review')
}

async function handleApprove() {
  await dispatchAction('bank-approve')
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
  await dispatchAction('bank-reject', rejectReason.value.trim())
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
</style>
