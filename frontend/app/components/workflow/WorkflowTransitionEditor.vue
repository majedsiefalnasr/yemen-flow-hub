<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ArrowLeft, GitBranch, Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next'
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
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
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

const {
  transitions,
  error,
  fetchTransitions,
  createTransition,
  updateTransition,
  deleteTransition,
} = useWorkflowTransitions()
const { stages, fetchStages } = useWorkflowStages()
const { actions, fetchActions } = useWorkflowActions()

const editable = props.version.state === 'DRAFT'
const dialogOpen = ref(false)
const editing = ref<WorkflowTransition | null>(null)
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
  editing.value = null
  fromStageId.value = ''
  actionId.value = ''
  toStageId.value = ''
  requiresComment.value = false
  confirmationMessage.value = ''
  formError.value = null
  dialogOpen.value = true
}

function openEdit(transition: WorkflowTransition) {
  editing.value = transition
  fromStageId.value = String(transition.from_stage_id)
  actionId.value = String(transition.action_id)
  toStageId.value = String(transition.to_stage_id)
  requiresComment.value = transition.requires_comment
  confirmationMessage.value = transition.confirmation_message ?? ''
  formError.value = null
  dialogOpen.value = true
}

async function submit() {
  if (!canSubmit.value) return
  try {
    if (editing.value) {
      await updateTransition(editing.value, {
        to_stage_id: Number(toStageId.value),
        requires_comment: requiresComment.value,
        confirmation_message: confirmationMessage.value || null,
      })
      toast.success('تم تحديث الانتقال')
    } else {
      await createTransition(props.version.id, {
        from_stage_id: Number(fromStageId.value),
        action_id: Number(actionId.value),
        to_stage_id: Number(toStageId.value),
        requires_comment: requiresComment.value,
        confirmation_message: confirmationMessage.value || null,
      })
      toast.success('تمت إضافة الانتقال')
    }
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
  <Card class="border-0 shadow">
    <CardHeader class="pb-3">
      <CardTitle class="font-section text-sm font-semibold">الانتقالات</CardTitle>
      <CardDescription class="text-xs">
        اربط كل مرحلة بإجراء ومرحلة وجهة لتحديد كيف يتحرك الطلب عبر سير العمل.
      </CardDescription>
      <CardAction>
        <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
          <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة انتقال</Button>
        </ScreenGuard>
      </CardAction>
    </CardHeader>
    <CardContent class="px-4 pb-4">
      <p v-if="error" class="pb-2 text-xs text-[var(--severity-red)]" role="alert">
        {{ error }}
      </p>

      <Empty v-else-if="transitions.length === 0" class="py-10">
        <EmptyMedia variant="icon">
          <GitBranch />
        </EmptyMedia>
        <EmptyHeader>
          <EmptyTitle>لا توجد انتقالات</EmptyTitle>
          <EmptyDescription>اربط المراحل بالإجراءات لتمكين حركة الطلبات.</EmptyDescription>
        </EmptyHeader>
        <EmptyContent v-if="editable">
          <ScreenGuard screen="workflow_designer" capability="MANAGE">
            <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة انتقال</Button>
          </ScreenGuard>
        </EmptyContent>
      </Empty>

      <div v-else class="border-border overflow-hidden rounded-md border">
        <Table
          class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
        >
          <TableHeader>
            <TableRow class="bg-muted/50 hover:bg-muted/50">
              <TableHead class="text-right">من</TableHead>
              <TableHead class="text-right">الإجراء</TableHead>
              <TableHead class="text-right">إلى</TableHead>
              <TableHead class="text-right">تعليق</TableHead>
              <TableHead class="text-left">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="transition in transitions"
              :key="transition.id"
              class="even:bg-muted/30"
            >
              <TableCell class="font-medium">{{ stageName(transition.from_stage_id) }}</TableCell>
              <TableCell>
                <Badge variant="secondary">{{ actionName(transition.action_id) }}</Badge>
              </TableCell>
              <TableCell class="font-medium">{{ stageName(transition.to_stage_id) }}</TableCell>
              <TableCell>
                <Badge v-if="transition.requires_comment" variant="secondary">مطلوب</Badge>
                <span v-else class="text-muted-foreground text-xs">—</span>
              </TableCell>
              <TableCell class="text-left" @click.stop>
                <div class="flex items-center justify-end gap-0.5">
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="تعديل الانتقال"
                          @click="openEdit(transition)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>تعديل الانتقال</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="حذف الانتقال"
                          @click="deleting = transition"
                        >
                          <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>حذف الانتقال</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <span
                    v-if="!editable"
                    class="inline-flex items-center gap-1 text-xs text-[var(--locked)]"
                  >
                    <Lock class="h-3 w-3" />مقفلة
                  </span>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </CardContent>
  </Card>

  <Dialog v-model:open="dialogOpen">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ editing ? 'تعديل انتقال' : 'إضافة انتقال' }}</DialogTitle>
        <DialogDescription>اربط مرحلة المصدر بإجراء ومرحلة الوجهة.</DialogDescription>
      </DialogHeader>

      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1.5">
          <Label>من المرحلة</Label>
          <Select v-model="fromStageId" :disabled="editing !== null">
            <SelectTrigger class="w-full"><SelectValue placeholder="اختر المرحلة" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="stage in stages" :key="stage.id" :value="String(stage.id)">
                {{ stage.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex flex-col gap-1.5">
          <Label>الإجراء</Label>
          <Select v-model="actionId" :disabled="editing !== null">
            <SelectTrigger class="w-full"><SelectValue placeholder="اختر الإجراء" /></SelectTrigger>
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
            <SelectTrigger class="w-full"><SelectValue placeholder="اختر المرحلة" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="stage in stages" :key="stage.id" :value="String(stage.id)">
                {{ stage.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox id="requires-comment" v-model="requiresComment" />
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
</template>
