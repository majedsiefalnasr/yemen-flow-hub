<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { AlertCircle, Loader2 } from 'lucide-vue-next'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ImportRequest } from '../../types/models'
import { useRequestsStore } from '../../stores/requests.store'
import { useVotingStore } from '../../stores/voting.store'
import { isV2Rule } from '../../composables/useRequests'
import { Button } from '../ui/button'
import { Textarea } from '../ui/textarea'
import { Alert, AlertDescription, AlertTitle } from '../ui/alert'
import { Tooltip, TooltipContent, TooltipTrigger } from '../ui/tooltip'
import {
  NOT_ELIGIBLE_LABEL_AR,
  NOT_ELIGIBLE_SUPPORT_LABEL,
  NOT_ELIGIBLE_REASON_LABEL,
  NOT_ELIGIBLE_FINAL_REASON_LABEL,
  NOT_ELIGIBLE_REQUEST_LABEL,
} from '../../constants/workflow'
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

// Story 17-E.2: Support Committee forward-only (new-rule requests)
const showSupportForwardModal = ref(false)
const supportForwardComment = ref('')
const supportForwardCommentError = ref('')

// Era gate (Epic 17-E): new National Committee rules (voting_rule_version = 2)
// vs legacy (version 1). Drives reviewer no-reject (17-E.1), Support forward-only
// (17-E.2), and Director tie-break/override removal (17-E.3) on this panel.
const isV2 = computed(() => isV2Rule(props.request))

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
watch(
  () => props.request.status,
  () => {
    actionError.value = ''
    resetRejectForm()
    resetBankReturnModal()
    resetBankRejectTerminalModal()
    resetSupportReturnModal()
    resetSupportForwardModal()
    resetDirectorState()
    showApproveModal.value = false
    approveNote.value = ''
    showSupportRejectedReturnModal.value = false
    supportRejectedReturnReason.value = ''
  },
)

const performingAction = computed(() => requestsStore.performingAction)

// Determine which panel to show
const showBankReviewerActions = computed(() => {
  if (props.userRole !== UserRole.BANK_REVIEWER) return false
  if (props.request.status === RequestStatus.SUBMITTED) return true
  // BANK_REVIEW: only the reviewer who holds the claim may act
  if (props.request.status === RequestStatus.BANK_REVIEW) return !!props.request.is_claimed_by_me
  return false
})

const showBankReviewerSupportRejectedActions = computed(
  () =>
    props.userRole === UserRole.BANK_REVIEWER &&
    props.request.status === RequestStatus.SUPPORT_REJECTED,
)

const showDataEntryActions = computed(
  () =>
    props.userRole === UserRole.DATA_ENTRY &&
    (props.request.status === RequestStatus.DRAFT ||
      props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL ||
      props.request.status === RequestStatus.BANK_RETURNED ||
      props.request.status === RequestStatus.SUPPORT_RETURNED),
)

// BANK_ADMIN: edit-only fallback for drafts they created; never sees BANK_RETURNED / SUPPORT_RETURNED actions
const showBankAdminDraftActions = computed(
  () =>
    props.userRole === UserRole.BANK_ADMIN &&
    (props.request.status === RequestStatus.DRAFT ||
      props.request.status === RequestStatus.DRAFT_REJECTED_INTERNAL),
)

const showSupportCommitteeActions = computed(
  () =>
    props.userRole === UserRole.SUPPORT_COMMITTEE &&
    props.request.status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS &&
    props.request.is_claimed_by_me,
)

const DIRECTOR_VOTING_STATUSES = new Set([
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

const showDirectorVotingActions = computed(
  () =>
    props.userRole === UserRole.COMMITTEE_DIRECTOR &&
    DIRECTOR_VOTING_STATUSES.has(props.request.status),
)

const allExecutiveVotesCast = computed(() => {
  if (props.request.status !== RequestStatus.EXECUTIVE_VOTING_OPEN) return false
  const detail = votingStore.votingDetail
  if (!detail) return false
  return detail.tally.total_cast >= detail.total_members
})

const showDirectorCustomsActions = computed(() => false)

const showAnyActions = computed(
  () =>
    showBankReviewerActions.value ||
    showBankReviewerSupportRejectedActions.value ||
    showDataEntryActions.value ||
    showBankAdminDraftActions.value ||
    showSupportCommitteeActions.value ||
    showDirectorVotingActions.value ||
    showDirectorCustomsActions.value,
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
    bankRejectTerminalCommentError.value = `اكتب ${NOT_ELIGIBLE_FINAL_REASON_LABEL} بوضوح، 20 حرفا على الأقل.`
    return
  }
  actionError.value = ''
  try {
    await requestsStore.bankRejectTerminal(props.request.id, bankRejectTerminalComment.value.trim())
    resetBankRejectTerminalModal()
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value =
      msg || `تعذّر تصنيف الطلب ${NOT_ELIGIBLE_LABEL_AR} نهائيا. تحقق من حالة الطلب وأعد المحاولة.`
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
    bankReturnCommentError.value = 'اكتب سبب الإعادة قبل إرجاع الطلب للمدخل.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.bankReturn(props.request.id, bankReturnComment.value.trim())
    resetBankReturnModal()
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّرت إعادة الطلب للمدخل. تحقق من حالة الطلب وأعد المحاولة.'
    resetBankReturnModal()
  }
}

async function handleSupportReturnConfirm() {
  supportReturnCommentError.value = ''
  if (supportReturnComment.value.trim().length < 3) {
    supportReturnCommentError.value = 'اكتب سبب الإعادة قبل إرجاع الطلب للمدخل.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.supportReturn(props.request.id, supportReturnComment.value.trim())
    resetSupportReturnModal()
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّرت إعادة الطلب للمدخل. تحقق من حالة الطلب وأعد المحاولة.'
    resetSupportReturnModal()
  }
}

function resetSupportForwardModal() {
  showSupportForwardModal.value = false
  supportForwardComment.value = ''
  supportForwardCommentError.value = ''
}

async function handleSupportForwardConfirm() {
  supportForwardCommentError.value = ''
  if (supportForwardComment.value.trim().length < 3) {
    supportForwardCommentError.value = 'اكتب تعليقا قبل الإرسال إلى اللجنة التنفيذية.'
    return
  }
  actionError.value = ''
  try {
    await requestsStore.supportForwardToExecutive(
      props.request.id,
      supportForwardComment.value.trim(),
    )
    resetSupportForwardModal()
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value =
      msg || 'تعذّر إرسال الطلب إلى اللجنة التنفيذية. تحقق من الحالة وأعد المحاولة.'
    resetSupportForwardModal()
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
    actionError.value = 'لا يمكن إغلاق الجلسة قبل تسجيل تصويت جميع الأعضاء النشطين.'
    showCloseConfirm.value = false
    return
  }

  actionError.value = ''
  try {
    await votingStore.closeSession(props.request.id)
    showCloseConfirm.value = false
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إغلاق جلسة التصويت. حدّث البيانات وأعد المحاولة.'
    showCloseConfirm.value = false
  }
}

async function handleFinalizeDecision() {
  actionError.value = ''
  try {
    await votingStore.finalizeDecision(props.request.id)
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إصدار القرار النهائي. راجع نتيجة التصويت وأعد المحاولة.'
  }
}

async function handleDirectorOverride() {
  overrideDecisionError.value = ''
  overrideJustificationError.value = ''

  if (!overrideDecision.value) {
    overrideDecisionError.value = `اختر قرار التجاوز: موافقة أو ${NOT_ELIGIBLE_LABEL_AR}.`
    return
  }
  if (overrideJustification.value.trim().length < 10) {
    overrideJustificationError.value = 'اكتب مبرر التجاوز بوضوح، 10 أحرف على الأقل.'
    return
  }

  actionError.value = ''
  try {
    await votingStore.directorOverride(
      props.request.id,
      overrideDecision.value,
      overrideJustification.value.trim(),
    )
    resetDirectorState()
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر تسجيل قرار التجاوز. راجع البيانات وأعد المحاولة.'
    resetDirectorState()
  }
}

async function handleIssueCustomsDeclaration() {
  actionError.value = ''
  try {
    await requestsStore.issueCustomsDeclaration(props.request.id)
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر إصدار تأكيد المصارفة الخارجية. أعد المحاولة.'
  }
}

async function handleApproveConfirm() {
  const note = approveNote.value.trim()
  await dispatchAction('bank-approve', note || undefined)
  showApproveModal.value = false
  approveNote.value = ''
}

function cancelApproveModal() {
  showApproveModal.value = false
  approveNote.value = ''
}

async function handleFinalizeRejection() {
  actionError.value = ''
  try {
    await requestsStore.bankFinalizeRejection(props.request.id)
    emit('action-completed')
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || `تعذّر تثبيت نتيجة ${NOT_ELIGIBLE_LABEL_AR} النهائية. أعد المحاولة.`
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
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّرت إعادة الطلب للمدخل. أضف ملاحظة واضحة وأعد المحاولة.'
    showSupportRejectedReturnModal.value = false
  }
}

function cancelSupportRejectedReturnModal() {
  showSupportRejectedReturnModal.value = false
  supportRejectedReturnReason.value = ''
}

async function handleBeginReview() {
  await dispatchAction('bank-review')
}

async function handleSupportApprove() {
  await dispatchAction('support-approve')
}

async function handleRejectConfirm() {
  if (!rejectReason.value.trim()) {
    rejectReasonError.value = `اكتب ${NOT_ELIGIBLE_REASON_LABEL} قبل إيقاف مسار الطلب.`
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
  } catch (err: any) {
    const msg = err instanceof Error ? err.message : ''
    actionError.value = msg || 'تعذّر تنفيذ الإجراء. تحقق من حالة الطلب وأعد المحاولة.'
  }
}

/**
 * Trigger the contextually-primary action for the current role + status.
 * Used by the keyboard shortcut system (Ctrl+Enter).
 */
function triggerPrimaryAction() {
  if (performingAction.value) return
  if (showBankReviewerActions.value && props.request.status === RequestStatus.SUBMITTED) {
    handleBeginReview()
  } else if (showBankReviewerActions.value && props.request.status === RequestStatus.BANK_REVIEW) {
    showApproveModal.value = true
  } else if (showSupportCommitteeActions.value) {
    handleSupportApprove()
  } else if (
    showDirectorVotingActions.value &&
    props.request.status === RequestStatus.EXECUTIVE_VOTING_OPEN
  ) {
    handleCloseSession()
  } else if (
    showDirectorVotingActions.value &&
    props.request.status === RequestStatus.EXECUTIVE_VOTING_CLOSED
  ) {
    handleFinalizeDecision()
  } else if (showDirectorCustomsActions.value) {
    handleIssueCustomsDeclaration()
  }
}

defineExpose({ triggerPrimaryAction })
</script>

<template>
  <div v-if="showAnyActions" class="space-y-4" role="region" aria-label="لوحة الإجراءات">
    <!-- Error alert -->
    <Alert v-if="actionError" class="border-[var(--severity-red)] bg-[var(--color-surface-error)]">
      <AlertCircle class="h-4 w-4 text-[var(--color-text-error)]" />
      <AlertDescription class="text-[var(--color-text-error)]">{{ actionError }}</AlertDescription>
    </Alert>

    <!-- BANK_REVIEWER: SUBMITTED → begin review -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.SUBMITTED">
      <Tooltip>
        <TooltipTrigger as-child>
          <Button class="w-full" :disabled="performingAction" @click="handleBeginReview">
            <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
            {{ performingAction ? 'جارٍ بدء المراجعة...' : 'البدء بالمراجعة' }}
          </Button>
        </TooltipTrigger>
        <TooltipContent
          ><p>
            ينقل الطلب إلى قيد المراجعة البنكية، ثم تظهر قرارات الاعتماد أو
            {{ NOT_ELIGIBLE_LABEL_AR }} أو الإعادة.
          </p>
          <p class="text-muted-foreground mt-1 text-xs">Ctrl+Enter</p></TooltipContent
        >
      </Tooltip>
    </template>

    <!-- BANK_REVIEWER: BANK_REVIEW → approve (with dialog), terminal reject, or return to intake -->
    <template v-if="showBankReviewerActions && request.status === RequestStatus.BANK_REVIEW">
      <div class="flex flex-row-reverse gap-3">
        <!-- Approve: confirmation dialog with optional note -->
        <Dialog v-model:open="showApproveModal">
          <DialogTrigger as-child>
            <Button class="flex-1" :disabled="performingAction"> اعتماد </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>اعتماد الطلب البنكي</DialogTitle>
              <DialogDescription>
                سيتم نقل الطلب {{ request.reference_number }} إلى المرحلة التالية في مسار الاعتماد.
              </DialogDescription>
            </DialogHeader>
            <div>
              <label for="approve-note" class="text-sm font-medium">ملاحظة (اختياري)</label>
              <Textarea
                id="approve-note"
                v-model="approveNote"
                placeholder="اكتب ملاحظة للمراجعة أو اترك الحقل فارغا."
                class="mt-2 min-h-20"
              />
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" @click="cancelApproveModal">إلغاء</Button>
              <Button :disabled="performingAction" @click="handleApproveConfirm">
                <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                {{ performingAction ? 'جارٍ اعتماد الطلب...' : 'اعتماد الطلب' }}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <!-- Terminal reject: destructive dialog with irreversible warning.
             Era gate (17-E.1): bank-stage terminal reject is removed for new-rule
             (voting_rule_version = 2) requests; legacy (v1) requests keep it. -->
        <Dialog v-if="!isV2" v-model:open="showBankRejectTerminalModal">
          <DialogTrigger as-child>
            <Button variant="destructive" class="flex-1" :disabled="performingAction">
              {{ NOT_ELIGIBLE_REQUEST_LABEL }} نهائيا
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle class="text-destructive"
                >{{ NOT_ELIGIBLE_REQUEST_LABEL }} نهائيا</DialogTitle
              >
            </DialogHeader>

            <div class="space-y-4">
              <Alert variant="destructive">
                <AlertTitle>إجراء نهائي لا يمكن التراجع عنه</AlertTitle>
                <AlertDescription
                  >سيغلق تصنيف {{ NOT_ELIGIBLE_LABEL_AR }} النهائي مسار الطلب، ولن يتمكن أي طرف من
                  تعديله أو إعادة تقديمه.</AlertDescription
                >
              </Alert>
              <div>
                <label for="bank-reject-terminal-comment" class="text-sm font-medium">
                  {{ NOT_ELIGIBLE_FINAL_REASON_LABEL }} <span class="text-destructive">*</span>
                </label>
                <Textarea
                  id="bank-reject-terminal-comment"
                  v-model="bankRejectTerminalComment"
                  placeholder="اكتب سببا واضحا يمكن الرجوع إليه في سجل التدقيق."
                  class="mt-2 min-h-24"
                  :aria-invalid="!!bankRejectTerminalCommentError"
                />
                <p v-if="bankRejectTerminalCommentError" class="text-destructive mt-1 text-xs">
                  {{ bankRejectTerminalCommentError }}
                </p>
              </div>

              <div class="flex justify-end gap-2">
                <Button variant="outline" @click="resetBankRejectTerminalModal">إلغاء</Button>
                <Button
                  variant="destructive"
                  :disabled="performingAction"
                  @click="handleBankRejectTerminalConfirm"
                >
                  <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                  {{
                    performingAction
                      ? `جارٍ تصنيف الطلب ${NOT_ELIGIBLE_LABEL_AR}...`
                      : `${NOT_ELIGIBLE_REQUEST_LABEL} نهائيا`
                  }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog v-model:open="showBankReturnModal">
          <DialogTrigger as-child>
            <Button variant="outline" class="flex-1" :disabled="performingAction">
              إعادة للمدخل
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب إلى موظف الإدخال</DialogTitle>
              <DialogDescription>
                سيعود الطلب إلى موظف الإدخال لتصحيح البيانات أو المستندات، ثم إعادة إرساله للمراجعة
                البنكية.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="bank-return-comment" class="text-sm font-medium">
                  سبب الإعادة <span class="text-[var(--color-text-error)]">*</span>
                </label>
                <Textarea
                  id="bank-return-comment"
                  v-model="bankReturnComment"
                  placeholder="اكتب ما يجب تصحيحه قبل إعادة التقديم."
                  class="mt-2 min-h-24"
                  :aria-invalid="!!bankReturnCommentError"
                />
                <p
                  v-if="bankReturnCommentError"
                  class="mt-1 text-xs text-[var(--color-text-error)]"
                >
                  {{ bankReturnCommentError }}
                </p>
              </div>

              <div class="flex justify-end gap-2">
                <Button variant="outline" @click="resetBankReturnModal"> إلغاء </Button>
                <Button :disabled="performingAction" @click="handleBankReturnConfirm">
                  <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                  {{ performingAction ? 'جارٍ إعادة الطلب...' : 'إعادة الطلب' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- SUPPORT_COMMITTEE (legacy v1): SUPPORT_REVIEW_IN_PROGRESS + is_claimed_by_me → approve, reject, or return -->
    <template v-if="showSupportCommitteeActions && !isV2">
      <div class="flex flex-row-reverse gap-3">
        <Button class="flex-1" :disabled="performingAction" @click="handleSupportApprove">
          <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
          {{ performingAction ? 'جارٍ اعتماد الطلب...' : 'اعتماد الطلب' }}
        </Button>

        <Dialog v-model:open="showRejectForm">
          <DialogTrigger as-child>
            <Button variant="destructive" class="flex-1" :disabled="performingAction">
              {{ NOT_ELIGIBLE_LABEL_AR }}
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>{{ NOT_ELIGIBLE_REQUEST_LABEL }} من لجنة المساندة</DialogTitle>
              <DialogDescription>
                سجّل {{ NOT_ELIGIBLE_REASON_LABEL }} قبل إنهاء مراجعة لجنة المساندة.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <Alert variant="destructive">
                <AlertTitle>قرار مؤثر في مسار الطلب</AlertTitle>
                <AlertDescription
                  >سيتوقف الطلب عند تصنيف لجنة المساندة له {{ NOT_ELIGIBLE_LABEL_AR }} حتى يقرر
                  المراجع البنكي إغلاقه نهائيا أو إعادته للتصحيح.</AlertDescription
                >
              </Alert>
              <div>
                <label for="reject-reason-support" class="text-sm font-medium">
                  {{ NOT_ELIGIBLE_REASON_LABEL }}
                  <span class="text-[var(--color-text-error)]">*</span>
                </label>
                <Textarea
                  id="reject-reason-support"
                  v-model="rejectReason"
                  :placeholder="`اكتب ${NOT_ELIGIBLE_REASON_LABEL} بلغة واضحة قابلة للتدقيق.`"
                  class="mt-2 min-h-24"
                  :aria-invalid="!!rejectReasonError"
                />
                <p v-if="rejectReasonError" class="mt-1 text-xs text-[var(--color-text-error)]">
                  {{ rejectReasonError }}
                </p>
              </div>

              <div class="flex justify-end gap-2">
                <Button variant="outline" @click="resetRejectForm"> إلغاء </Button>
                <Button
                  variant="destructive"
                  :disabled="performingAction"
                  @click="handleRejectConfirm"
                >
                  <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                  {{
                    performingAction
                      ? `جارٍ تصنيف الطلب ${NOT_ELIGIBLE_LABEL_AR}...`
                      : NOT_ELIGIBLE_REQUEST_LABEL
                  }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <Dialog v-model:open="showSupportReturnModal">
          <DialogTrigger as-child>
            <Button variant="outline" class="flex-1" :disabled="performingAction">
              إعادة للمدخل
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب إلى موظف الإدخال</DialogTitle>
              <DialogDescription>
                سيعود الطلب إلى موظف الإدخال لمعالجة ملاحظات لجنة المساندة، ثم إعادة الإرسال.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <div>
                <label for="support-return-comment" class="text-sm font-medium">
                  سبب الإعادة <span class="text-[var(--color-text-error)]">*</span>
                </label>
                <Textarea
                  id="support-return-comment"
                  v-model="supportReturnComment"
                  placeholder="اكتب الملاحظات المطلوبة قبل إعادة التقديم."
                  class="mt-2 min-h-24"
                  :aria-invalid="!!supportReturnCommentError"
                />
                <p
                  v-if="supportReturnCommentError"
                  class="mt-1 text-xs text-[var(--color-text-error)]"
                >
                  {{ supportReturnCommentError }}
                </p>
              </div>

              <div class="flex justify-end gap-2">
                <Button variant="outline" @click="resetSupportReturnModal"> إلغاء </Button>
                <Button :disabled="performingAction" @click="handleSupportReturnConfirm">
                  <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                  {{ performingAction ? 'جارٍ إعادة الطلب...' : 'إعادة الطلب' }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- SUPPORT_COMMITTEE (new-rule v2, 17-E.2): forward-only with mandatory comment -->
    <template v-if="showSupportCommitteeActions && isV2">
      <Dialog v-model:open="showSupportForwardModal">
        <DialogTrigger as-child>
          <Button class="w-full" :disabled="performingAction"> إرسال إلى اللجنة التنفيذية </Button>
        </DialogTrigger>
        <DialogContent class="max-w-md">
          <DialogHeader>
            <DialogTitle>إرسال الطلب إلى اللجنة التنفيذية</DialogTitle>
            <DialogDescription>
              سيُحال الطلب {{ request.reference_number }} إلى اللجنة التنفيذية للتصويت. سجّل تعليق
              لجنة المساندة قبل الإرسال.
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4">
            <div>
              <label for="support-forward-comment" class="text-sm font-medium">
                تعليق لجنة المساندة <span class="text-[var(--color-text-error)]">*</span>
              </label>
              <Textarea
                id="support-forward-comment"
                v-model="supportForwardComment"
                placeholder="اكتب ملاحظة لجنة المساندة التي سترافق الإحالة في سجل التدقيق."
                class="mt-2 min-h-24"
                :aria-invalid="!!supportForwardCommentError"
              />
              <p
                v-if="supportForwardCommentError"
                class="mt-1 text-xs text-[var(--color-text-error)]"
              >
                {{ supportForwardCommentError }}
              </p>
            </div>

            <div class="flex justify-end gap-2">
              <Button variant="outline" @click="resetSupportForwardModal"> إلغاء </Button>
              <Button :disabled="performingAction" @click="handleSupportForwardConfirm">
                <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                {{ performingAction ? 'جارٍ الإرسال...' : 'إرسال إلى اللجنة التنفيذية' }}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </template>

    <!-- DATA_ENTRY: DRAFT → edit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.DRAFT">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: DRAFT_REJECTED_INTERNAL → edit & resubmit -->
    <template
      v-if="showDataEntryActions && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL"
    >
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل الطلب وإعادة تقديمه</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: BANK_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.BANK_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل الطلب وإعادة تقديمه</Button>
      </NuxtLink>
    </template>

    <!-- DATA_ENTRY: SUPPORT_RETURNED → edit & resubmit -->
    <template v-if="showDataEntryActions && request.status === RequestStatus.SUPPORT_RETURNED">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل الطلب وإعادة تقديمه</Button>
      </NuxtLink>
    </template>

    <!-- BANK_ADMIN: DRAFT fallback — edit only, no submit -->
    <template v-if="showBankAdminDraftActions && request.status === RequestStatus.DRAFT">
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل المسودة</Button>
      </NuxtLink>
    </template>
    <template
      v-if="showBankAdminDraftActions && request.status === RequestStatus.DRAFT_REJECTED_INTERNAL"
    >
      <NuxtLink :to="`/requests/${request.id}/edit`">
        <Button class="w-full">تعديل المسودة</Button>
      </NuxtLink>
    </template>

    <!-- BANK_REVIEWER: SUPPORT_REJECTED → keep rejected (finalize) or return to data entry -->
    <template v-if="showBankReviewerSupportRejectedActions">
      <div class="flex flex-row-reverse gap-3">
        <!-- Return to Data Entry: dialog with optional reason -->
        <Dialog v-model:open="showSupportRejectedReturnModal">
          <DialogTrigger as-child>
            <Button variant="outline" class="flex-1" :disabled="performingAction">
              إعادة للمدخل للتصحيح
            </Button>
          </DialogTrigger>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>إعادة الطلب إلى موظف الإدخال للتصحيح</DialogTitle>
              <DialogDescription>
                سيعود الطلب إلى موظف الإدخال لتصحيح المعلومات قبل إعادة التقديم.
              </DialogDescription>
            </DialogHeader>
            <div>
              <label for="support-rejected-return-reason" class="text-sm font-medium"
                >ملاحظة (اختياري)</label
              >
              <Textarea
                id="support-rejected-return-reason"
                v-model="supportRejectedReturnReason"
                placeholder="اكتب توجيهات التصحيح أو اترك الحقل فارغا."
                class="mt-2 min-h-20"
              />
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" @click="cancelSupportRejectedReturnModal"> إلغاء </Button>
              <Button :disabled="performingAction" @click="handleSupportRejectedReturnConfirm">
                <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                {{ performingAction ? 'جارٍ إعادة الطلب...' : 'إعادة الطلب' }}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <!-- Finalize Not-Eligible outcome: irreversible AlertDialog confirmation -->
        <AlertDialog>
          <AlertDialogTrigger as-child>
            <Button variant="destructive" class="flex-1" :disabled="performingAction">
              تثبيت {{ NOT_ELIGIBLE_LABEL_AR }}
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>تثبيت {{ NOT_ELIGIBLE_SUPPORT_LABEL }}</AlertDialogTitle>
              <AlertDialogDescription>
                سيصبح تصنيف لجنة المساندة للطلب {{ NOT_ELIGIBLE_LABEL_AR }} قرارا نهائيا، ولن يمكن
                استئناف الطلب بعد ذلك.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction
                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                :disabled="performingAction"
                @click="handleFinalizeRejection"
              >
                <Loader2 v-if="performingAction" class="me-2 h-4 w-4 animate-spin" />
                {{
                  performingAction
                    ? `جارٍ تثبيت ${NOT_ELIGIBLE_LABEL_AR}...`
                    : `تثبيت ${NOT_ELIGIBLE_LABEL_AR}`
                }}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_OPEN → close session or override -->
    <template
      v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_OPEN"
    >
      <p
        v-if="!allExecutiveVotesCast"
        class="rounded-md border border-[var(--severity-amber)]/40 bg-[var(--severity-amber)]/10 px-3 py-2 text-xs text-[var(--severity-amber)]"
      >
        لا يمكن إغلاق الجلسة قبل تسجيل تصويت جميع الأعضاء النشطين.
      </p>
      <div class="flex flex-row-reverse gap-3">
        <AlertDialog v-model:open="showCloseConfirm">
          <AlertDialogTrigger as-child>
            <Button
              variant="destructive"
              class="flex-1"
              :disabled="votingStore.performingDirectorAction || !allExecutiveVotesCast"
            >
              إغلاق الجلسة
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>إغلاق جلسة التصويت</AlertDialogTitle>
              <AlertDialogDescription>
                سيغلق هذا الإجراء باب التصويت على الطلب. لن يتمكن الأعضاء من تعديل أصواتهم بعد
                الإغلاق.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction
                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                :disabled="votingStore.performingDirectorAction"
                @click="handleCloseSession"
              >
                <Loader2
                  v-if="votingStore.performingDirectorAction"
                  class="me-2 h-4 w-4 animate-spin"
                />
                {{ votingStore.performingDirectorAction ? 'جارٍ إغلاق الجلسة...' : 'إغلاق الجلسة' }}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        <!-- Era gate (17-E.3): Director tie-break/override is removed for new-rule
             (voting_rule_version = 2) sessions; they finalize automatically on a
             simple majority. Legacy (v1) sessions keep the override control. -->
        <Dialog v-if="!isV2" v-model:open="showOverrideModal">
          <Tooltip>
            <TooltipTrigger as-child>
              <Button
                variant="outline"
                class="flex-1"
                :disabled="votingStore.performingDirectorAction"
                @click="showOverrideModal = true"
              >
                قرار مدير اللجنة
              </Button>
            </TooltipTrigger>
            <TooltipContent
              ><p>
                يستخدم لحسم التعادل أو اعتماد قرار مخالف لنتيجة التصويت، مع مبرر مكتوب في سجل
                التدقيق.
              </p></TooltipContent
            >
          </Tooltip>
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>قرار مدير اللجنة</DialogTitle>
              <DialogDescription>
                اختر القرار النهائي واكتب مبررا واضحا قبل حفظه في سجل التدقيق.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <!-- Current tally snapshot -->
              <div
                v-if="votingStore.votingDetail?.tally"
                class="grid grid-cols-3 gap-2 text-center"
              >
                <div class="rounded bg-[var(--severity-green)]/10 p-2">
                  <p
                    class="font-section text-xs leading-5 font-medium text-[var(--severity-green)]"
                  >
                    موافق
                  </p>
                  <p
                    class="text-xl leading-7 font-semibold text-[var(--severity-green)] tabular-nums"
                  >
                    {{ votingStore.votingDetail.tally.approve_count }}
                  </p>
                </div>
                <div class="rounded bg-[var(--severity-red)]/10 p-2">
                  <p class="font-section text-xs leading-5 font-medium text-[var(--severity-red)]">
                    {{ NOT_ELIGIBLE_LABEL_AR }}
                  </p>
                  <p
                    class="text-xl leading-7 font-semibold text-[var(--severity-red)] tabular-nums"
                  >
                    {{ votingStore.votingDetail.tally.reject_count }}
                  </p>
                </div>
                <div class="bg-muted rounded p-2">
                  <p class="font-section text-foreground text-xs leading-5 font-medium">ممتنع</p>
                  <p class="text-muted-foreground text-xl leading-7 font-semibold tabular-nums">
                    {{
                      votingStore.votingDetail.tally.abstain_count +
                      votingStore.votingDetail.tally.auto_abstain_count
                    }}
                  </p>
                </div>
              </div>

              <!-- Decision selection -->
              <div class="space-y-2">
                <p class="text-sm font-medium">
                  القرار <span class="text-[var(--color-text-error)]">*</span>
                </p>
                <div class="space-y-2">
                  <label class="flex cursor-pointer items-center gap-2">
                    <input
                      v-model="overrideDecision"
                      type="radio"
                      name="override-decision"
                      value="APPROVE"
                      class="rounded"
                    />
                    <span class="text-sm">موافقة</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2">
                    <input
                      v-model="overrideDecision"
                      type="radio"
                      name="override-decision"
                      value="REJECT"
                      class="rounded"
                    />
                    <span class="text-sm">{{ NOT_ELIGIBLE_LABEL_AR }}</span>
                  </label>
                </div>
                <p v-if="overrideDecisionError" class="text-xs text-[var(--severity-red)]">
                  {{ overrideDecisionError }}
                </p>
              </div>

              <!-- Justification -->
              <div>
                <label for="override-justification" class="text-sm font-medium">
                  مبرر القرار <span class="text-[var(--severity-red)]">*</span>
                </label>
                <Textarea
                  id="override-justification"
                  v-model="overrideJustification"
                  placeholder="اكتب المبرر الذي سيظهر في سجل التدقيق."
                  class="mt-2 min-h-24"
                  :aria-invalid="!!overrideJustificationError"
                />
                <p
                  v-if="overrideJustificationError"
                  class="mt-1 text-xs text-[var(--severity-red)]"
                >
                  {{ overrideJustificationError }}
                </p>
              </div>

              <div class="flex justify-end gap-2">
                <Button variant="outline" @click="resetDirectorState"> إلغاء </Button>
                <Button
                  :disabled="votingStore.performingDirectorAction"
                  @click="handleDirectorOverride"
                >
                  <Loader2
                    v-if="votingStore.performingDirectorAction"
                    class="me-2 h-4 w-4 animate-spin"
                  />
                  {{
                    votingStore.performingDirectorAction ? 'جارٍ حفظ القرار...' : 'حفظ قرار المدير'
                  }}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </template>

    <!-- COMMITTEE_DIRECTOR: EXECUTIVE_VOTING_CLOSED → finalize decision -->
    <template
      v-if="showDirectorVotingActions && request.status === RequestStatus.EXECUTIVE_VOTING_CLOSED"
    >
      <div v-if="votingStore.votingDetail?.tally" class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded bg-[var(--color-surface-success)] p-2">
          <p class="font-section text-xs leading-5 font-medium text-[var(--color-text-success)]">
            موافق
          </p>
          <p class="text-xl leading-7 font-semibold text-[var(--color-text-success)] tabular-nums">
            {{ votingStore.votingDetail.tally.approve_count }}
          </p>
        </div>
        <div class="rounded bg-[var(--color-surface-error)] p-2">
          <p class="font-section text-xs leading-5 font-medium text-[var(--color-text-error)]">
            رافض
          </p>
          <p class="text-xl leading-7 font-semibold text-[var(--color-text-error)] tabular-nums">
            {{ votingStore.votingDetail.tally.reject_count }}
          </p>
        </div>
        <div class="bg-muted rounded p-2">
          <p class="font-section text-foreground text-xs leading-5 font-medium">ممتنع</p>
          <p class="text-muted-foreground text-xl leading-7 font-semibold tabular-nums">
            {{
              votingStore.votingDetail.tally.abstain_count +
              votingStore.votingDetail.tally.auto_abstain_count
            }}
          </p>
        </div>
      </div>
      <AlertDialog>
        <AlertDialogTrigger as-child>
          <Button :disabled="votingStore.performingDirectorAction"> إصدار القرار النهائي </Button>
        </AlertDialogTrigger>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>إصدار القرار النهائي</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم إصدار القرار النهائي بناء على نتيجة التصويت الحالية. راجع الأرقام قبل المتابعة.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              :disabled="votingStore.performingDirectorAction"
              @click="handleFinalizeDecision"
            >
              <Loader2
                v-if="votingStore.performingDirectorAction"
                class="me-2 h-4 w-4 animate-spin"
              />
              {{
                votingStore.performingDirectorAction
                  ? 'جارٍ إصدار القرار...'
                  : 'إصدار القرار النهائي'
              }}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </template>
  </div>
</template>
