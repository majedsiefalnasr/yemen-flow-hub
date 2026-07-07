<!-- app/components/workflow/EngineActionsRail.vue -->
<script setup lang="ts">
import type { WorkflowGraphEdge } from '@/types/models'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Field, FieldLabel } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { AlertCircle } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { useTransitionConfirm } from '@/composables/useTransitionConfirm'

const REQUIRED_COMMENT_MESSAGE = 'يجب إدخال ملاحظة قبل تنفيذ هذا الإجراء.'

const props = defineProps<{
  availableActions: WorkflowGraphEdge[]
  canAct: boolean
  claimRequiredButNotHeld: boolean
  showClaimButton: boolean
  busy: boolean
  viewOnly?: boolean
}>()

const emit = defineEmits<{ run: [transitionId: number, requiresComment: boolean]; claim: [] }>()
const comment = defineModel<string>('comment', { default: '' })
const commentError = ref<string | null>(null)

watch(comment, (value) => {
  if (commentError.value && value.trim()) {
    commentError.value = null
  }
})

function actionDisabledReason(action: WorkflowGraphEdge): string | undefined {
  if (!props.canAct || props.busy) return undefined
  if (action.requires_comment && !comment.value.trim()) {
    return REQUIRED_COMMENT_MESSAGE
  }
  return undefined
}

function isActionDisabled(_action: WorkflowGraphEdge): boolean {
  return !props.canAct || props.busy
}

const {
  confirmOpen,
  pendingMessage,
  confirmIfNeeded,
  confirmPending,
  cancelPending,
} = useTransitionConfirm()

async function onActionClick(action: WorkflowGraphEdge) {
  if (!props.canAct || props.busy) return

  if (action.requires_comment && !comment.value.trim()) {
    commentError.value = REQUIRED_COMMENT_MESSAGE
    return
  }
  commentError.value = null

  const confirmed = await confirmIfNeeded(action)
  if (!confirmed) return

  emit('run', action.id, action.requires_comment)
}

function onConfirmDialogAction() {
  confirmPending()
}
</script>

<template>
  <!-- Sticky offset clears the h-14 topbar; opaque background + z-29 so scrolled
       content stays behind it (kept under the z-30 topbar). -->
  <Card dir="rtl" class="bg-card sticky top-16 z-29 border-0 shadow">
    <CardHeader class="pb-2">
      <CardTitle class="text-sm font-semibold">إجراءات المرحلة</CardTitle>
    </CardHeader>
    <CardContent class="space-y-3">
      <p v-if="viewOnly" class="text-muted-foreground text-xs">
        لا تملك صلاحية تنفيذ إجراءات على المرحلة الحالية. يمكنك الاطلاع على الطلب وسجله.
      </p>

      <template v-else>
        <Button v-if="showClaimButton" class="w-full" :disabled="busy" @click="emit('claim')">
          المتابعة على هذا الطلب
        </Button>

        <Alert v-if="claimRequiredButNotHeld" role="status">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء.</AlertDescription>
        </Alert>

        <Field>
          <FieldLabel for="action-comment">ملاحظات</FieldLabel>
          <Textarea id="action-comment" v-model="comment" rows="3" :disabled="!canAct" />
        </Field>

        <Alert v-if="commentError" variant="destructive" role="alert">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>{{ commentError }}</AlertDescription>
        </Alert>

        <div class="flex flex-col gap-2">
          <Button
            v-for="action in availableActions"
            :key="action.id"
            :disabled="isActionDisabled(action)"
            :title="actionDisabledReason(action)"
            @click="onActionClick(action)"
          >
            {{ action.action_name ?? action.action_code }}
          </Button>
          <p v-if="!availableActions.length" class="text-muted-foreground text-xs">
            لا توجد إجراءات متاحة في هذه المرحلة.
          </p>
        </div>
      </template>
    </CardContent>
  </Card>

  <AlertDialog v-model:open="confirmOpen">
    <AlertDialogContent dir="rtl">
      <AlertDialogHeader>
        <AlertDialogTitle>تأكيد الإجراء</AlertDialogTitle>
        <AlertDialogDescription>{{ pendingMessage }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="cancelPending">إلغاء</AlertDialogCancel>
        <AlertDialogAction @click="onConfirmDialogAction">تأكيد</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
