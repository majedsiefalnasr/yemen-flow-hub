<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'
import type { WorkflowTransition, WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useWorkflowActions } from '@/composables/useWorkflowActions'
import { useWorkflowStages } from '@/composables/useWorkflowStages'
import { useWorkflowTransitions } from '@/composables/useWorkflowTransitions'

const props = defineProps<{ version: WorkflowVersion }>()

const { transitions, error, fetchTransitions, createTransition, deleteTransition } =
  useWorkflowTransitions()
const { stages, fetchStages } = useWorkflowStages()
const { actions, fetchActions } = useWorkflowActions()

const editable = props.version.state === 'DRAFT'
const dialogOpen = ref(false)
const deleting = ref<WorkflowTransition | null>(null)

const fromStageId = ref<string>('')
const actionId = ref<string>('')
const toStageId = ref<string>('')
const requiresComment = ref(false)
const confirmationMessage = ref('')
const formError = ref<string | null>(null)

const stageName = (id: number) => stages.value.find((s) => s.id === id)?.name ?? `#${id}`
const actionName = (id: number) => actions.value.find((a) => a.id === id)?.name ?? `#${id}`

const canSubmit = computed(
  () => fromStageId.value !== '' && actionId.value !== '' && toStageId.value !== '',
)

function openCreate() {
  fromStageId.value = ''
  actionId.value = ''
  toStageId.value = ''
  requiresComment.value = false
  confirmationMessage.value = ''
  formError.value = null
  dialogOpen.value = true
}

async function submit() {
  if (!canSubmit.value) return
  try {
    await createTransition(props.version.id, {
      from_stage_id: Number(fromStageId.value),
      action_id: Number(actionId.value),
      to_stage_id: Number(toStageId.value),
      requires_comment: requiresComment.value,
      confirmation_message: confirmationMessage.value || null,
    })
    toast.success('تمت إضافة الانتقال')
    dialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الانتقال')
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await deleteTransition(deleting.value)
    toast.success('تم حذف الانتقال')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الانتقال'))
  } finally {
    deleting.value = null
  }
}

onMounted(() => {
  fetchTransitions(props.version.id)
  fetchStages(props.version.id)
  fetchActions()
})
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="font-section text-sm font-semibold">الانتقالات</h3>
      <ScreenGuard v-if="editable" screen="workflow_designer" capability="CREATE">
        <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة انتقال</Button>
      </ScreenGuard>
    </div>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <Empty v-else-if="transitions.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد انتقالات</EmptyTitle>
        <EmptyDescription>اربط المراحل بالإجراءات لتمكين حركة الطلبات.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <Table v-else>
      <TableHeader>
        <TableRow>
          <TableHead class="text-right">من</TableHead>
          <TableHead class="text-right">الإجراء</TableHead>
          <TableHead class="text-right">إلى</TableHead>
          <TableHead class="text-right">تعليق</TableHead>
          <TableHead class="text-right">إجراء</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="transition in transitions" :key="transition.id">
          <TableCell>{{ stageName(transition.from_stage_id) }}</TableCell>
          <TableCell>{{ actionName(transition.action_id) }}</TableCell>
          <TableCell>{{ stageName(transition.to_stage_id) }}</TableCell>
          <TableCell>
            <Badge v-if="transition.requires_comment" variant="secondary">مطلوب</Badge>
          </TableCell>
          <TableCell @click.stop>
            <ScreenGuard v-if="editable" screen="workflow_designer" capability="DELETE">
              <Button
                size="sm"
                variant="ghost"
                aria-label="حذف الانتقال"
                @click="deleting = transition"
              >
                <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
              </Button>
            </ScreenGuard>
            <span v-if="!editable" class="text-muted-foreground text-xs">مقفلة</span>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>

    <Dialog v-model:open="dialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>إضافة انتقال</DialogTitle>
          <DialogDescription>اربط مرحلة المصدر بإجراء ومرحلة الوجهة.</DialogDescription>
        </DialogHeader>

        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label>من المرحلة</Label>
            <Select v-model="fromStageId">
              <SelectTrigger><SelectValue placeholder="اختر المرحلة" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="stage in stages" :key="stage.id" :value="String(stage.id)">
                  {{ stage.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الإجراء</Label>
            <Select v-model="actionId">
              <SelectTrigger><SelectValue placeholder="اختر الإجراء" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="action in actions" :key="action.id" :value="String(action.id)">
                  {{ action.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>إلى المرحلة</Label>
            <Select v-model="toStageId">
              <SelectTrigger><SelectValue placeholder="اختر المرحلة" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="stage in stages" :key="stage.id" :value="String(stage.id)">
                  {{ stage.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox id="requires-comment" v-model:checked="requiresComment" />
            <Label for="requires-comment">يتطلب تعليقاً</Label>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>رسالة التأكيد (اختياري)</Label>
            <Input v-model="confirmationMessage" placeholder="هل أنت متأكد؟" />
          </div>

          <p v-if="formError" class="text-xs text-[var(--severity-red)]" role="alert">
            {{ formError }}
          </p>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="dialogOpen = false">إلغاء</Button>
          <Button :disabled="!canSubmit" @click="submit">
            <ArrowLeft class="h-3.5 w-3.5" />حفظ
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <AlertDialog :open="deleting !== null" @update:open="(open) => !open && (deleting = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف الانتقال</AlertDialogTitle>
          <AlertDialogDescription>سيتم حذف هذا الانتقال نهائياً.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deleting = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete">تأكيد الحذف</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
