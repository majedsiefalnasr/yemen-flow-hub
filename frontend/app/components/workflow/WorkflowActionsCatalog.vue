<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next'
import type { WorkflowAction, WorkflowActionKind } from '@/types/models'
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useWorkflowActions } from '@/composables/useWorkflowActions'
import { NOT_ELIGIBLE_LABEL_AR } from '@/constants/workflow'

const { actions, loading, error, fetchActions, createAction, updateAction, deleteAction } =
  useWorkflowActions()

const dialogOpen = ref(false)
const editing = ref<WorkflowAction | null>(null)
const deleting = ref<WorkflowAction | null>(null)

const KINDS: WorkflowActionKind[] = [
  'DRAFT',
  'APPROVE',
  'REJECT',
  'RETURN',
  'CLOSE',
  'INFO',
  'CUSTOM',
]

const kindLabels: Record<WorkflowActionKind, string> = {
  DRAFT: 'مسودة',
  APPROVE: 'اعتماد',
  REJECT: NOT_ELIGIBLE_LABEL_AR,
  RETURN: 'إعادة',
  CLOSE: 'إغلاق',
  INFO: 'معلومات',
  CUSTOM: 'مخصص',
}

const actionSchema = toTypedSchema(
  z.object({
    code: z
      .string()
      .min(1, 'الرمز مطلوب')
      .regex(/^[A-Za-z0-9_-]+$/, 'يسمح بالحروف والأرقام والشرطات فقط'),
    name: z.string().min(1, 'الاسم مطلوب'),
    kind: z.enum(['DRAFT', 'APPROVE', 'REJECT', 'RETURN', 'CLOSE', 'INFO', 'CUSTOM']),
  }),
)

const form = useForm({ validationSchema: actionSchema })

function openCreate() {
  editing.value = null
  form.resetForm({ values: { code: '', name: '', kind: 'CUSTOM' } })
  dialogOpen.value = true
}

function openEdit(action: WorkflowAction) {
  editing.value = action
  form.resetForm({ values: { code: action.code, name: action.name, kind: action.kind } })
  dialogOpen.value = true
}

const onSubmit = form.handleSubmit(async (values) => {
  try {
    if (editing.value) {
      await updateAction(editing.value, { name: values.name })
      toast.success('تم تحديث الإجراء')
    } else {
      await createAction({ code: values.code, name: values.name, kind: values.kind })
      toast.success('تمت إضافة الإجراء')
    }
    dialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ الإجراء'))
  }
})

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await deleteAction(deleting.value)
    toast.success('تم حذف الإجراء')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الإجراء'))
  } finally {
    deleting.value = null
  }
}

onMounted(() => fetchActions())
</script>

<template>
  <Card class="border-0 shadow">
    <CardHeader class="pb-3">
      <CardTitle class="font-section text-sm font-semibold">كتالوج الإجراءات</CardTitle>
      <CardDescription class="text-xs">
        عرّف الإجراءات التي يستخدمها المنفّذون على الطلبات (اعتماد، رفض، إعادة…). الرمز ثابت بعد
        الإنشاء.
      </CardDescription>
      <CardAction>
        <ScreenGuard screen="workflow_designer" capability="MANAGE">
          <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة إجراء</Button>
        </ScreenGuard>
      </CardAction>
    </CardHeader>
    <CardContent class="px-4 pb-4">
      <Alert v-if="error" variant="destructive" role="alert" class="mb-2">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر التحميل</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>

      <div v-else-if="loading" class="grid gap-2">
        <Skeleton v-for="n in 3" :key="n" class="h-10 w-full rounded-md" />
      </div>

      <Empty v-else-if="actions.length === 0" class="py-10">
        <EmptyMedia variant="icon">
          <Tag />
        </EmptyMedia>
        <EmptyHeader>
          <EmptyTitle>لا توجد إجراءات</EmptyTitle>
          <EmptyDescription>أضف إجراءات لتتمكن من بناء الانتقالات بينها.</EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
          <ScreenGuard screen="workflow_designer" capability="MANAGE">
            <Button size="sm" @click="openCreate"><Plus class="h-3.5 w-3.5" />إضافة إجراء</Button>
          </ScreenGuard>
        </EmptyContent>
      </Empty>

      <div v-else class="border-border overflow-hidden rounded-md border">
        <Table
          class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
        >
          <TableHeader>
            <TableRow class="bg-muted/50 hover:bg-muted/50">
              <TableHead class="text-right">الرمز</TableHead>
              <TableHead class="text-right">الاسم</TableHead>
              <TableHead class="text-right">النوع</TableHead>
              <TableHead class="text-left">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="action in actions" :key="action.id" class="even:bg-muted/30">
              <TableCell class="text-muted-foreground font-mono text-xs">{{
                action.code
              }}</TableCell>
              <TableCell class="font-medium">{{ action.name }}</TableCell>
              <TableCell>
                <div class="flex flex-wrap items-center gap-1">
                  <Badge variant="secondary">{{ kindLabels[action.kind] }}</Badge>
                  <Badge v-if="action.is_system" variant="outline">نظامي</Badge>
                  <Badge v-if="!action.is_active" variant="outline" class="text-muted-foreground"
                    >غير نشط</Badge
                  >
                </div>
              </TableCell>
              <TableCell class="text-left" @click.stop>
                <div class="flex items-center justify-end gap-0.5">
                  <ScreenGuard screen="workflow_designer" capability="MANAGE">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="تعديل الإجراء"
                          @click="openEdit(action)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>تعديل الإجراء</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                  <ScreenGuard screen="workflow_designer" capability="MANAGE">
                    <Tooltip v-if="!action.is_system && !action.is_in_use">
                      <TooltipTrigger as-child>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label="حذف الإجراء"
                          @click="deleting = action"
                        >
                          <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>حذف الإجراء</TooltipContent>
                    </Tooltip>
                  </ScreenGuard>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </CardContent>

    <Dialog v-model:open="dialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل الإجراء' : 'إضافة إجراء' }}</DialogTitle>
          <DialogDescription>الرمز ثابت بعد الإنشاء؛ الاسم قابل للتعديل.</DialogDescription>
        </DialogHeader>

        <form class="flex flex-col gap-4" @submit="onSubmit">
          <FormField v-slot="{ componentField }" name="code">
            <FormItem>
              <FormLabel>الرمز</FormLabel>
              <FormControl>
                <Input
                  v-bind="componentField"
                  placeholder="APPROVE"
                  dir="ltr"
                  :disabled="editing !== null"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="name">
            <FormItem>
              <FormLabel>الاسم</FormLabel>
              <FormControl>
                <Input v-bind="componentField" placeholder="اعتماد" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField }" name="kind">
            <FormItem>
              <FormLabel>النوع</FormLabel>
              <Select v-bind="componentField" :disabled="editing !== null">
                <FormControl>
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="اختر النوع" />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem v-for="kind in KINDS" :key="kind" :value="kind">
                    {{ kindLabels[kind] }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          </FormField>

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
          <AlertDialogTitle>تأكيد حذف الإجراء</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف الإجراء «{{ deleting?.name }}» نهائياً.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deleting = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete">تأكيد الحذف</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </Card>
</template>
