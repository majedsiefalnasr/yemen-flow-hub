<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { Layers, Lock, Pencil, Plus, SlidersHorizontal, Trash2 } from 'lucide-vue-next'
import type { WorkflowStage, WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
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
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
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
const requiresClaim = ref(false)
// Stage whose field-rule matrix is open in a dialog (null = closed).
const fieldRulesStage = ref<WorkflowStage | null>(null)

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
  requiresClaim.value = false
  form.resetForm({ values: { code: '', name: '', sla_duration_minutes: undefined } })
  dialogOpen.value = true
}

function openEdit(stage: WorkflowStage) {
  editing.value = stage
  isInitial.value = stage.is_initial
  isFinal.value = stage.is_final
  requiresClaim.value = stage.requires_claim
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
      requires_claim: requiresClaim.value,
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
  <Card class="border-0 shadow">
    <CardHeader class="pb-3">
      <CardTitle class="font-section text-sm font-semibold">المراحل</CardTitle>
      <CardDescription class="text-xs">
        رتّب مراحل سير العمل، وحدّد مرحلة البداية والنهاية، واضبط صلاحيات كل مرحلة.
      </CardDescription>
      <CardAction>
        <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
          <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة مرحلة</Button>
        </ScreenGuard>
      </CardAction>
    </CardHeader>
    <CardContent class="px-4 pb-4">
      <p v-if="error" class="pb-2 text-xs text-[var(--severity-red)]" role="alert">
        {{ error }}
      </p>

      <Empty v-else-if="!loading && stages.length === 0" class="py-10">
        <EmptyMedia variant="icon">
          <Layers />
        </EmptyMedia>
        <EmptyHeader>
          <EmptyTitle>لا توجد مراحل</EmptyTitle>
          <EmptyDescription>أضف مراحل لتعريف خطوات سير العمل.</EmptyDescription>
        </EmptyHeader>
        <EmptyContent v-if="editable">
          <ScreenGuard screen="workflow_designer" capability="MANAGE">
            <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة مرحلة</Button>
          </ScreenGuard>
        </EmptyContent>
      </Empty>

      <div v-else class="border-border overflow-hidden rounded-md border">
        <Table
          class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
        >
          <TableHeader>
            <TableRow class="bg-muted/50 hover:bg-muted/50">
              <TableHead class="text-right">الترتيب</TableHead>
              <TableHead class="text-right">الرمز</TableHead>
              <TableHead class="text-right">الاسم</TableHead>
              <TableHead class="text-right">النوع</TableHead>
              <TableHead class="text-left">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="stage in stages" :key="stage.id" class="even:bg-muted/30">
              <TableCell class="text-muted-foreground font-mono text-xs">
                {{ stage.sort_order }}
              </TableCell>
              <TableCell class="text-muted-foreground font-mono text-xs">{{
                stage.code
              }}</TableCell>
              <TableCell class="font-medium">{{ stage.name }}</TableCell>
              <TableCell>
                <div class="flex flex-wrap items-center gap-1">
                  <Badge
                    v-if="stage.is_initial"
                    variant="outline"
                    class="border-[var(--severity-green)]/40 text-[var(--severity-green)]"
                  >
                    بداية
                  </Badge>
                  <Badge
                    v-if="stage.is_final"
                    variant="outline"
                    class="border-[var(--brand-color)]/40 text-[var(--brand-color)]"
                  >
                    نهاية
                  </Badge>
                  <Badge v-if="stage.requires_claim" variant="secondary">مطالبة</Badge>
                  <span
                    v-if="!stage.is_initial && !stage.is_final && !stage.requires_claim"
                    class="text-muted-foreground text-xs"
                    >—</span
                  >
                </div>
              </TableCell>
              <TableCell class="text-left" @click.stop>
                <div class="flex items-center justify-end gap-0.5">
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="قواعد الحقول لهذه المرحلة"
                        @click="fieldRulesStage = stage"
                      >
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>قواعد الحقول</TooltipContent>
                  </Tooltip>
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="تعديل المرحلة"
                          @click="openEdit(stage)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>تعديل المرحلة</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="حذف المرحلة"
                          @click="deleting = stage"
                        >
                          <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>حذف المرحلة</TooltipContent>
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

  <!-- Field rules for a single stage (opened from row action) -->
  <Dialog
    :open="fieldRulesStage !== null"
    @update:open="(open) => !open && (fieldRulesStage = null)"
  >
    <DialogContent class="!max-w-3xl">
      <DialogHeader>
        <DialogTitle>قواعد الحقول — {{ fieldRulesStage?.name }}</DialogTitle>
        <DialogDescription>
          حدّد ظهور كل حقل وقابليته للتعديل وإلزاميته في هذه المرحلة.
        </DialogDescription>
      </DialogHeader>
      <div class="-mx-1 max-h-[70vh] overflow-y-auto px-1">
        <StageFieldRuleMatrix v-if="fieldRulesStage" :stage="fieldRulesStage" :version="version" />
      </div>
    </DialogContent>
  </Dialog>

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

        <div class="flex items-center gap-3">
          <Switch id="stage-requires-claim" v-model:checked="requiresClaim" />
          <Label for="stage-requires-claim">يتطلب مطالبة (قفل مرن)</Label>
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
</template>
