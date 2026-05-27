<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { AlertCircle, Loader2 } from 'lucide-vue-next'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ImportRequest } from '../../types/models'
import { useRequestsStore } from '../../stores/requests.store'
import { useVotingStore } from '../../stores/voting.store'
import { Button } from '../ui/button'
import { Textarea } from '../ui/textarea'
import { Alert, AlertDescription } from '../ui/alert'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '../ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '../ui/alert-dialog'

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

// Approve dialog state
const showApproveModal = ref(false)
const approveNote = ref('')

// SUPPORT_REJECTED follow-up state
const showSupportRejectedReturnModal = ref(false)
const supportRejectedReturnReason = ref('')

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
  showApproveModal.value = false
  approveNote.value = ''
  showSupportRejectedReturnModal.value = false
  supportRejectedReturnReason.value = ''
})

const performingAction = computed(() => requestsStore.performingAction)

// Determine which panel to show
const showBankReviewerActions = computed(() =>
  props.userRole === UserRole.BANK_REVIEWER
  && (props.request.status === RequestStatus.SUBMITTED || props.request.status === RequestStatus.BANK_REVIEW),
)

const showBankReviewerSupportRejectedActions = computed(() =>
  props.userRole === UserRole.BANK_REVIEWER
  && props.request.status === RequestStatus.SUPPORT_REJECTED,
)

const showDataEntryActions = computed(() =>
  props.userRole === UserRole.DATA_ENTRY
  && (props.request.status === RequestStatus.DRAFT
    || props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL
    || props.request.status === RequestStatus.BANK_RETURNED
    || props.request.status === RequestStatus.SUPPORT_RETURNED),
)

// BANK_ADMIN: edit-only fallback for drafts they created; never sees BANK_RETURNED / SUPPORT_RETURNED actions
const showBankAdminDraftActions = computed(() =>
  props.userRole === UserRole.BANK_ADMIN
  && (props.request.status === RequestStatus.DRAFT
    || props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL),
)

const showSupportCommitteeActions = computed(() =>
  props.userRole === UserRole.SUPPORT_COMMITTEE
  && props.request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS
  && props.request.is_claimed_by_me,
)

const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

const showDirectorVotingActions = computed(() =>
  props.userRole === UserRole.COMMITTEE_DIRECTOR
  && DIRECTOR_VOTING_STATUSES.has(props.request.status),
)

const allExecutiveVotesCast = computed(() => {
  if (props.request.status !== RequestStatus.EXECUTIVE_VOTING_OPEN) return false
  const detail = votingStore.votingDetail
  if (!detail) return false
  return detail.tally.total_cast >= detail.total_members
})

const showDirectorCustomsActions = computed(() =>
  props.userRole === UserRole.COMMITTEE_DIRECTOR
  && props.request.status === RequestStatus.EXECUTIVE_APPROVED,
)

const showAnyActions = computed(() =>
  showBankReviewerActions.value
  || showBankReviewerSupportRejectedActions.value
  || showDataEntryActions.value
  || showBankAdminDraftActions.value
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
  if (bankRejectTerminalComment.value.trim().length < 20) {
    bankRejectTerminalCommentError.value = 'سبب الرفض النهائي مطلوب ويجب أن يكون 20 حرفاً على الأقل.'
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

async function handleCloseSession() {
  if (!allExecutiveVotesCast.value) {
    actionError.value = 'لا يمكن إغلاق التصويت قبل إتمام تصويت جميع الأعضاء النشطين.'
    showCloseConfirm.value = false
    return
  }

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

async function handleApproveConfirm() {
  const note = approveNote.value.trim()
  await dispatchAction('bank-approve', note || undefined)
  showApproveModal.value = false
  approveNote.value = ''
}

async function handleFinalizeRejection() {
  actionError.value = ''
  try {
    await requestsStore.bankFinalizeRejection(props.request.id)
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إتمام الرفض النهائي.'
  }
}

async function handleSupportRejectedReturnConfirm() {
  actionError.value = ''
  try {
    const reason = supportRejectedReturnReason.value.trim()
    await requestsStore.bankReturnAfterSupportReject(props.request.id, reason || undefined)
    showSupportRejectedReturnModal.value = false
    supportRejectedReturnReason.value = ''
    emit('action-completed')
  }
  catch (err: unknown) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إعادة الطلب للمدخل.'
    showSupportRejectedReturnModal.value = false
  }
}

async function handleBeginReview() {
  await dispatchAction('bank-review')
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
  <div v-if="showAnyActions" class="space-y-4" dir="rtl" role="region" aria-label="لوحة الإجراءات">
    <!-- Error alert -->
    <Alert v-if="actionError" class="border-s-4 border-s-red-600 bg-red-700/10">
      <AlertCircle class="h-4 w-4 text-red-700" />
      <AlertDescription class="text-red-700">{{ actionError }}</AlertDescription>
    </Alert>

    <!-- BANK_REVIEWER: SUBMITTED → begin review -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.SUBMITTED">
      <Button
        class="w-full"
        :disabled="performingAction"
        @click="handleBeginReview"
      >
        <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
        {{ performingAction ? 'جارٍ التنفيذ…' : 'البدء بالمراجعة' }}
      </Button>
    </template>

    <!-- BANK_REVIEWER: BANK_REVIEW → approve (with dialog), terminal reject, or return to intake -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.BANK_REVIEW">
      <div class="flex gap-3 flex-row-reverse">
        <!-- Approve: confirmation dialog with optional note -->
        <Dialog v-model:open="showApproveModal">
          <DialogTrigger as-child>
            <Button
              class="flex-1 bg-[var(--severity-green)]/10 text-[var(--severity-green)] hover:bg-[var(--severity-green)]/20 border border-[var(--severity-green)]/30"
              :disabled="performingAction"
            >
              اعتماد
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>تأكيد اعتماد الطلب</DialogTitle>
              <DialogDescription>
                الطلب: {{ request.reference_number }}
              </DialogDescription>
            </DialogHeader>
            <div>
              <label for="approve-note" class="text-sm font-medium">ملاحظة (اختياري)</label>
              <Textarea
                id="approve-note"
                v-model="approveNote"
                placeholder="أضف ملاحظة تُسجَّل في سجل الأحداث…"
                class="mt-2 min-h-20"
              />
            </div>
            <div class="flex gap-2 justify-end">
              <Button variant="outline" @click="showApproveModal = false; approveNote = ''">إلغاء</Button>
              <Button
                class="bg-[var(--severity-green)] text-white hover:bg-[var(--severity-green)]/90"
                :disabled="performingAction"
                @click="handleApproveConfirm"
              >
                <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الاعتماد' }}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <!-- Terminal reject: destructive dialog with irreversible warning -->
        <Dialog v-model:open="showBankRejectTerminalModal">
          <DialogTrigger as-child>
            <Button
              variant="destructive"
              class="flex-1"
              :disabled="performingAction"
            >
              رفض نهائي
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle class="text-destructive">رفض الطلب نهائياً</DialogTitle>
              <DialogDescription class="text-destructive/80">
                تحذير: هذا الإجراء لا يمكن التراجع عنه. بعد الرفض النهائي لن يتمكن أي طرف من استئناف الطلب أو إعادة تقديمه.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="bank-reject-terminal-comment" class="text-sm font-medium">
                  سبب الرفض النهائي <span class="text-destructive">*</span>
                </label>
                <Textarea
                  id="bank-reject-terminal-comment"
                  v-model="bankRejectTerminalComment"
                  placeholder="اكتب سبب الرفض النهائي هنا (20 حرفاً على الأقل)…"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!bankRejectTerminalCommentError"
                />
                <p v-if="bankRejectTerminalCommentError" class="text-xs text-destructive mt-1">
                  {{ bankRejectTerminalCommentError }}
                </p>
              </div>

              <div class="flex gap-2 justify-end">
                <Button variant="outline" @click="resetBankRejectTerminalModal">إلغاء</Button>
                <Button
                  variant="destructive"
                  :disabled="performingAction"
                  @click="handleBankRejectTerminalConfirm"
                >
                  <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                  {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الرفض النهائي' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog v-model:open="showBankReturnModal">
          <DialogTrigger as-child>
            <Button
              variant="outline"
              class="flex-1"
              :disabled="performingAction"
            >
              إعادة للمدخل
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب للمدخل</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="bank-return-comment" class="text-sm font-medium">
                  سبب الإعادة <span class="text-red-700">*</span>
                </label>
                <Textarea
                  id="bank-return-comment"
                  v-model="bankReturnComment"
                  placeholder="اكتب سبب الإعادة هنا…"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!bankReturnCommentError"
                />
                <p v-if="bankReturnCommentError" class="text-xs text-red-700 mt-1">
                  {{ bankReturnCommentError }}
                </p>
              </div>

              <div class="flex gap-2 justify-end">
                <Button
                  variant="outline"
                  @click="resetBankReturnModal"
                >
                  إلغاء
                </Button>
                <Button
                  :disabled="performingAction"
                  @click="handleBankReturnConfirm"
                >
                  <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                  {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الإعادة' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- SUPPORT_COMMITTEE: SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me → approve, reject, or return -->
    <template v-if="showSupportCommitteeActions">
      <div class="flex gap-3 flex-row-reverse">
        <Button
          class="flex-1 bg-green-50 hover:bg-green-50"
          :disabled="performingAction"
          @click="handleSupportApprove"
        >
          <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
          {{ performingAction ? 'جارٍ التنفيذ…' : 'اعتماد' }}
        </Button>

        <Dialog v-model:open="showRejectForm">
          <DialogTrigger as-child>
            <Button
              variant="destructive"
              class="flex-1"
              :disabled="performingAction"
            >
              رفض
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>رفض الطلب</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="reject-reason-support" class="text-sm font-medium">
                  سبب الرفض <span class="text-red-700">*</span>
                </label>
                <Textarea
                  id="reject-reason-support"
                  v-model="rejectReason"
                  placeholder="اكتب سبب الرفض هنا…"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!rejectReasonError"
                />
                <p v-if="rejectReasonError" class="text-xs text-red-700 mt-1">
                  {{ rejectReasonError }}
                </p>
              </div>

              <div class="flex gap-2 justify-end">
                <Button
                  variant="outline"
                  @click="resetRejectForm"
                >
                  إلغاء
                </Button>
                <Button
                  variant="destructive"
                  :disabled="performingAction"
                  @click="handleRejectConfirm"
                >
                  <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                  {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الرفض' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog v-model:open="showSupportReturnModal">
          <DialogTrigger as-child>
            <Button
              variant="outline"
              class="flex-1"
              :disabled="performingAction"
            >
              إعادة للمدخل
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب للمدخل</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="support-return-comment" class="text-sm font-medium">
                  سبب الإعادة <span class="text-red-700">*</span>
                </label>
                <Textarea
                  id="support-return-comment"
                  v-model="supportReturnComment"
                  placeholder="اكتب سبب الإعادة هنا…"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!supportReturnCommentError"
                />
                <p v-if="supportReturnCommentError" class="text-xs text-red-700 mt-1">
                  {{ supportReturnCommentError }}
                </p>
              </div>

              <div class="flex gap-2 justify-end">
                <Button
                  variant="outline"
                  @click="resetSupportReturnModal"
                >
                  إلغاء
                </Button>
                <Button
                  :disabled="performingAction"
                  @click="handleSupportReturnConfirm"
                >
                  <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                  {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الإعادة' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- DATA_ENTRY: DRAFT → edit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.DRAFT">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: DRAFT_REJECTED_INTERNAL → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل وإعادة تقديم</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: BANK_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.BANK_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل وإعادة تقديم</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: SUPPORT_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.SUPPORT_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل وإعادة تقديم</Button>
      </NuxtLink>
    </template>

    <!-- BANK_ADMIN: DRAFT fallback — edit only, no submit -->
    <template v-if="showBankAdminDraftActions && request.status === RequestStatus.DRAFT">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل المسودة</Button>
      </NuxtLink>
    </template>
    <template v-if="showBankAdminDraftActions && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل المسودة</Button>
      </NuxtLink>
    </template>

    <!-- BANK_REVIEWER: SUPPORT_REJECTED → keep rejected (finalize) or return to data entry -->
    <template v-if="showBankReviewerSupportRejectedActions">
      <div class="flex gap-3 flex-row-reverse">
        <!-- Return to Data Entry: dialog with optional reason -->
        <Dialog v-model:open="showSupportRejectedReturnModal">
          <DialogTrigger as-child>
            <Button variant="outline" class="flex-1" :disabled="performingAction">
              إعادة للمدخل
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب للمدخل للتعديل</DialogTitle>
              <DialogDescription>
                سيُعاد الطلب إلى موظف الإدخال لتصحيح المعلومات وإعادة التقديم.
              </DialogDescription>
            </DialogHeader>
            <div>
              <label for="support-rejected-return-reason" class="text-sm font-medium">ملاحظة (اختياري)</label>
              <Textarea
                id="support-rejected-return-reason"
                v-model="supportRejectedReturnReason"
                placeholder="أضف توجيهات أو ملاحظات للمدخل…"
                class="mt-2 min-h-20"
              />
            </div>
            <div class="flex gap-2 justify-end">
              <Button
                variant="outline"
                @click="showSupportRejectedReturnModal = false; supportRejectedReturnReason = ''"
              >
                إلغاء
              </Button>
              <Button :disabled="performingAction" @click="handleSupportRejectedReturnConfirm">
                <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد الإعادة' }}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <!-- Finalize rejection: irreversible AlertDialog confirmation -->
        <AlertDialog>
          <AlertDialogTrigger as-child>
            <Button variant="destructive" class="flex-1" :disabled="performingAction">
              إبقاء مرفوضاً
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>تأكيد إبقاء الطلب مرفوضاً</AlertDialogTitle>
              <AlertDialogDescription>
                سيصبح قرار رفض لجنة المساندة نهائياً ولا يمكن استئناف الطلب بعد ذلك.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction
                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                :disabled="performingAction"
                @click="handleFinalizeRejection"
              >
                <Loader2 v-if="performingAction" class="h-4 w-4 me-2 animate-spin" />
                {{ performingAction ? 'جارٍ التنفيذ…' : 'تأكيد' }}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_OPEN → close session or override -->
    <template v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_OPEN">
      <p
        v-if="!allExecutiveVotesCast"
        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700"
      >
        لا يمكن الإغلاق قبل اكتمال تصويت جميع الأعضاء النشطين.
      </p>
      <div class="flex gap-3 flex-row-reverse">
        <Dialog v-model:open="showCloseConfirm">
          <DialogTrigger as-child>
            <Button
              variant="destructive"
              class="flex-1"
              :disabled="votingStore.performingDirectorAction || !allExecutiveVotesCast"
            >
              إغلاق جلسة التصويت
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>تأكيد إغلاق جلسة التصويت</DialogTitle>
              <DialogDescription>
                هل أنت متأكد؟ لن يتمكن الأعضاء من التصويت بعد ذلك.
              </DialogDescription>
            </DialogHeader>

            <div class="flex gap-2 justify-end">
              <Button
                variant="outline"
                @click="showCloseConfirm = false"
              >
                إلغاء
              </Button>
              <Button
                variant="destructive"
                :disabled="votingStore.performingDirectorAction"
                @click="handleCloseSession"
              >
                <Loader2 v-if="votingStore.performingDirectorAction" class="h-4 w-4 me-2 animate-spin" />
                {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'تأكيد الإغلاق' }}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog v-model:open="showOverrideModal">
          <DialogTrigger as-child>
            <Button
              class="flex-1 bg-amber-50/100 hover:bg-amber-50"
              :disabled="votingStore.performingDirectorAction"
            >
              تجاوز مدير اللجنة
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>تجاوز مدير اللجنة</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
              <!-- Current tally snapshot -->
              <div v-if="votingStore.votingDetail?.tally" class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-green-50/10 p-2 rounded">
                  <p class="text-sm font-medium text-green-700">موافق</p>
                  <p class="text-lg font-bold text-green-700">{{ votingStore.votingDetail.tally.approve_count }}</p>
                </div>
                <div class="bg-red-700/10 p-2 rounded">
                  <p class="text-sm font-medium text-red-700">رافض</p>
                  <p class="text-lg font-bold text-red-700">{{ votingStore.votingDetail.tally.reject_count }}</p>
                </div>
                <div class="bg-muted p-2 rounded">
                  <p class="text-sm font-medium text-foreground">ممتنع</p>
                  <p class="text-lg font-bold text-muted-foreground">{{ votingStore.votingDetail.tally.abstain_count + votingStore.votingDetail.tally.auto_abstain_count }}</p>
                </div>
              </div>

              <!-- Decision selection -->
              <div class="space-y-2">
                <p class="text-sm font-medium">القرار <span class="text-red-700">*</span></p>
                <div class="space-y-2">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="overrideDecision"
                      type="radio"
                      name="override-decision"
                      value="APPROVE"
                      class="rounded"
                    />
                    <span class="text-sm">موافقة</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="overrideDecision"
                      type="radio"
                      name="override-decision"
                      value="REJECT"
                      class="rounded"
                    />
                    <span class="text-sm">رفض</span>
                  </label>
                </div>
                <p v-if="overrideDecisionError" class="text-xs text-red-700">{{ overrideDecisionError }}</p>
              </div>

              <!-- Justification -->
              <div>
                <label for="override-justification" class="text-sm font-medium">
                  المبرر <span class="text-red-700">*</span>
                </label>
                <Textarea
                  id="override-justification"
                  v-model="overrideJustification"
                  placeholder="اكتب مبرر القرار هنا (10 أحرف على الأقل)…"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!overrideJustificationError"
                />
                <p v-if="overrideJustificationError" class="text-xs text-red-700 mt-1">
                  {{ overrideJustificationError }}
                </p>
              </div>

              <div class="flex gap-2 justify-end">
                <Button
                  variant="outline"
                  @click="resetDirectorState"
                >
                  إلغاء
                </Button>
                <Button
                  class="bg-amber-50/100 hover:bg-amber-50"
                  :disabled="votingStore.performingDirectorAction"
                  @click="handleDirectorOverride"
                >
                  <Loader2 v-if="votingStore.performingDirectorAction" class="h-4 w-4 me-2 animate-spin" />
                  {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'تأكيد التجاوز' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_CLOSED → finalize decision -->
    <template v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_CLOSED">
      <div v-if="votingStore.votingDetail?.tally" class="grid grid-cols-3 gap-2 text-center">
        <div class="bg-green-50/10 p-2 rounded">
          <p class="text-sm font-medium text-green-700">موافق</p>
          <p class="text-lg font-bold text-green-700">{{ votingStore.votingDetail.tally.approve_count }}</p>
        </div>
        <div class="bg-red-700/10 p-2 rounded">
          <p class="text-sm font-medium text-red-700">رافض</p>
          <p class="text-lg font-bold text-red-700">{{ votingStore.votingDetail.tally.reject_count }}</p>
        </div>
        <div class="bg-muted p-2 rounded">
          <p class="text-sm font-medium text-foreground">ممتنع</p>
          <p class="text-lg font-bold text-muted-foreground">{{ votingStore.votingDetail.tally.abstain_count + votingStore.votingDetail.tally.auto_abstain_count }}</p>
        </div>
      </div>
      <Button
        :disabled="votingStore.performingDirectorAction"
        @click="handleFinalizeDecision"
      >
        <Loader2 v-if="votingStore.performingDirectorAction" class="h-4 w-4 me-2 animate-spin" />
        {{ votingStore.performingDirectorAction ? 'جارٍ التنفيذ…' : 'إصدار القرار النهائي' }}
      </Button>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_APPROVED → issue customs declaration -->
    <template v-if="showDirectorCustomsActions">
      <Button
        class="bg-green-50 hover:bg-green-50"
        :disabled="requestsStore.issuingCustoms"
        @click="handleIssueCustomsDeclaration"
      >
        <Loader2 v-if="requestsStore.issuingCustoms" class="h-4 w-4 me-2 animate-spin" />
        {{ requestsStore.issuingCustoms ? 'جارٍ الإصدار…' : 'إصدار البيان الجمركي' }}
      </Button>
    </template>

  </div>
</template>
