<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { Pencil, Plus, Shield, Trash2 } from 'lucide-vue-next'
import type { WorkflowStage, WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import StagePermissionEditor from '@/components/workflow/StagePermissionEditor.vue'
import StageFieldRuleMatrix from '@/components/workflow/StageFieldRuleMatrix.vue'
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
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useWorkflowStages } from '@/composables/useWorkflowStages'

const props = defineProps<{ version: WorkflowVersion }>()

const { stages, loading, error, fetchStages, createStage, updateStage, deleteStage } =
  useWorkflowStages()

const editable = props.version.state === 'DRAFT'
const dialogOpen = ref(false)
const editing = ref<WorkflowStage | null>(null)
const deleting = ref<WorkflowStage | null>(null)
const isInitial = ref(false)
const isFinal = ref(false)
const permissionsStageId = ref<number | null>(null)

function togglePermissions(stageId: number) {
  permissionsStageId.value = permissionsStageId.value === stageId ? null : stageId
}

const stageSchema = toTypedSchema(
  z.object({
    code: z
      .string()
      .min(1, 'الرمز مطلوب')
      .regex(/^[A-Za-z0-9_-]+$/, 'يسمح بالحروف والأرقام والشرطات فقط'),
    name: z.string().min(1, 'الاسم مطلوب'),
    sla_duration_minutes: z.coerce.number().int().min(1).optional(),
  }),
)

const form = useForm({ validationSchema: stageSchema })

function openCreate() {
  editing.value = null
  isInitial.value = false
  isFinal.value = false
  form.resetForm({ values: { code: '', name: '', sla_duration_minutes: undefined } })
  dialogOpen.value = true
}

function openEdit(stage: WorkflowStage) {
  editing.value = stage
  isInitial.value = stage.is_initial
  isFinal.value = stage.is_final
  form.resetForm({
    values: {
      code: stage.code,
      name: stage.name,
      sla_duration_minutes: stage.sla_duration_minutes ?? undefined,
    },
  })
  dialogOpen.value = true
}

const onSubmit = form.handleSubmit(async (values) => {
  try {
    const payload = {
      code: values.code,
      name: values.name,
      sla_duration_minutes: values.sla_duration_minutes ?? null,
      is_initial: isInitial.value,
      is_final: isFinal.value,
    }
    if (editing.value) {
      await updateStage(editing.value, payload)
      toast.success('تم تحديث المرحلة')
    } else {
      await createStage(props.version.id, payload)
      toast.success('تمت إضافة المرحلة')
    }
    dialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ المرحلة'))
  }
})

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await deleteStage(deleting.value)
    toast.success('تم حذف المرحلة')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف المرحلة'))
  } finally {
    deleting.value = null
  }
}

onMounted(() => fetchStages(props.version.id))
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="font-section text-sm font-semibold">المراحل</h3>
      <ScreenGuard v-if="editable" screen="workflow_designer" capability="CREATE">
        <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة مرحلة</Button>
      </ScreenGuard>
    </div>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <Empty v-else-if="!loading && stages.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل</EmptyTitle>
        <EmptyDescription>أضف مراحل لتعريف خطوات سير العمل.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <Table v-else>
      <TableHeader>
        <TableRow>
          <TableHead class="text-right">الترتيب</TableHead>
          <TableHead class="text-right">الرمز</TableHead>
          <TableHead class="text-right">الاسم</TableHead>
          <TableHead class="text-right">النوع</TableHead>
          <TableHead class="text-right">إجراء</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="stage in stages" :key="stage.id">
          <TableCell class="font-mono">{{ stage.sort_order }}</TableCell>
          <TableCell class="font-mono">{{ stage.code }}</TableCell>
          <TableCell>{{ stage.name }}</TableCell>
          <TableCell>
            <Badge v-if="stage.is_initial" variant="secondary">بداية</Badge>
            <Badge v-if="stage.is_final" variant="secondary">نهاية</Badge>
          </TableCell>
          <TableCell @click.stop>
            <Button
              size="sm"
              variant="ghost"
              aria-label="صلاحيات المرحلة"
              @click="togglePermissions(stage.id)"
            >
              <Shield class="h-3.5 w-3.5" />
            </Button>
            <ScreenGuard v-if="editable" screen="workflow_designer" capability="UPDATE">
              <Button size="sm" variant="ghost" aria-label="تعديل المرحلة" @click="openEdit(stage)">
                <Pencil class="h-3.5 w-3.5" />
              </Button>
            </ScreenGuard>
            <ScreenGuard v-if="editable" screen="workflow_designer" capability="DELETE">
              <Button size="sm" variant="ghost" aria-label="حذف المرحلة" @click="deleting = stage">
                <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
              </Button>
            </ScreenGuard>
            <span v-if="!editable" class="text-muted-foreground text-xs">مقفلة</span>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>

    <template v-for="stage in stages" :key="`perms-${stage.id}`">
      <div
        v-if="permissionsStageId === stage.id"
        class="border-border bg-muted/30 mt-2 space-y-4 rounded-md border p-3"
      >
        <StagePermissionEditor :stage="stage" :version="version" />
        <StageFieldRuleMatrix :stage="stage" :version="version" />
      </div>
    </template>

    <Dialog v-model:open="dialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل المرحلة' : 'إضافة مرحلة' }}</DialogTitle>
          <DialogDescription>عرّف بيانات المرحلة ضمن النسخة المسودة.</DialogDescription>
        </DialogHeader>

        <form class="flex flex-col gap-4" @submit="onSubmit">
          <FormField v-slot="{ componentField }" name="code">
            <FormItem>
              <FormLabel>الرمز</FormLabel>
              <FormControl>
                <Input v-bind="componentField" placeholder="intake" dir="ltr" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="name">
            <FormItem>
              <FormLabel>الاسم</FormLabel>
              <FormControl>
                <Input v-bind="componentField" placeholder="الاستلام" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="sla_duration_minutes">
            <FormItem>
              <FormLabel>مدة الإنجاز (دقائق)</FormLabel>
              <FormControl>
                <Input v-bind="componentField" type="number" min="1" placeholder="120" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
              <Checkbox id="stage-initial" v-model:checked="isInitial" />
              <Label for="stage-initial">مرحلة البداية</Label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox id="stage-final" v-model:checked="isFinal" />
              <Label for="stage-final">مرحلة النهاية</Label>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="dialogOpen = false">إلغاء</Button>
            <Button type="submit" :disabled="form.isSubmitting.value">حفظ</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <AlertDialog :open="deleting !== null" @update:open="(open) => !open && (deleting = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف المرحلة</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف المرحلة «{{ deleting?.name }}» نهائياً.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deleting = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete">تأكيد الحذف</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
