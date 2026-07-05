<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ChevronDown, ChevronUp, FolderTree, ListChecks, Lock, Plus, Trash2 } from 'lucide-vue-next'
import type {
  DynamicFieldSource,
  FieldDefinition,
  FieldGroup,
  FieldType,
  WorkflowVersion,
} from '@/types/models'
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
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { useReferenceData } from '@/composables/useReferenceData'
import { useWorkflowFields } from '@/composables/useWorkflowFields'

const props = defineProps<{ version: WorkflowVersion }>()

const {
  groups,
  error,
  fetchGroups,
  createGroup,
  createField,
  deleteField,
  updateField,
  deleteGroup,
  persistGroupOrder,
} = useWorkflowFields()
const { referenceTables, fetchReferenceTables } = useReferenceData()

const editable = props.version.state === 'DRAFT'

const groupDialogOpen = ref(false)
const fieldDialogOpen = ref(false)
const deletingField = ref<FieldDefinition | null>(null)
const deletingGroup = ref<FieldGroup | null>(null)
const movingGroup = ref(false)

const groupName = ref('')
const groupLabel = ref('')

const fieldGroupId = ref<number | null>(null)
const fieldKey = ref('')
const fieldLabel = ref('')
const fieldType = ref<FieldType>('TEXT')
const fieldMinValue = ref('')
const fieldMaxValue = ref('')
const fieldDynamicSource = ref<DynamicFieldSource | ''>('')
const fieldReferenceTableId = ref<string>('')
const fieldRequired = ref(false)
const formError = ref<string | null>(null)

const FIELD_TYPES: FieldType[] = [
  'TEXT',
  'NUMBER',
  'DATE',
  'SELECT',
  'DYNAMIC_SELECT',
  'TEXTAREA',
  'FILE',
  'CURRENCY',
  'CHECKBOX',
]

const typeLabels: Record<FieldType, string> = {
  TEXT: 'نص',
  NUMBER: 'رقم',
  DATE: 'تاريخ',
  SELECT: 'قائمة',
  DYNAMIC_SELECT: 'قائمة ديناميكية',
  TEXTAREA: 'نص طويل',
  FILE: 'ملف',
  CURRENCY: 'عملة',
  CHECKBOX: 'مربع اختيار',
}

const sourceLabels: Record<DynamicFieldSource, string> = {
  MERCHANTS: 'التجار',
  MERCHANT_COMPANIES: 'شركات التجار',
  REFERENCE_DATA: 'بيانات مرجعية',
}

const isNumeric = computed(() => fieldType.value === 'NUMBER' || fieldType.value === 'CURRENCY')
const isDynamic = computed(() => fieldType.value === 'DYNAMIC_SELECT')
const needsReferenceTable = computed(
  () => isDynamic.value && fieldDynamicSource.value === 'REFERENCE_DATA',
)

// Flat list of all fields across groups, ordered by group then field sort_order.
const flatFields = computed(() =>
  groups.value.flatMap((group) => group.fields.map((field) => ({ field, group }))),
)

const fieldCount = (groupId: number) =>
  flatFields.value.filter((row) => row.group.id === groupId).length

function openGroupDialog() {
  groupName.value = ''
  groupLabel.value = ''
  groupDialogOpen.value = true
}

function openFieldDialog(groupId: number | null) {
  fieldGroupId.value = groupId
  fieldKey.value = ''
  fieldLabel.value = ''
  fieldType.value = 'TEXT'
  fieldMinValue.value = ''
  fieldMaxValue.value = ''
  fieldDynamicSource.value = ''
  fieldReferenceTableId.value = ''
  fieldRequired.value = false
  formError.value = null
  fieldDialogOpen.value = true
}

async function submitGroup() {
  if (!groupName.value || !groupLabel.value) return
  try {
    await createGroup(props.version.id, {
      name: groupName.value,
      label: groupLabel.value,
      sort_order: groups.value.length,
    })
    toast.success('تمت إضافة المجموعة')
    groupDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ المجموعة'))
  }
}

async function submitField() {
  if (fieldGroupId.value === null || !fieldKey.value || !fieldLabel.value) {
    formError.value = 'المجموعة والرمز والاسم مطلوبة.'
    return
  }
  try {
    await createField(props.version.id, {
      field_group_id: fieldGroupId.value,
      key: fieldKey.value,
      label: fieldLabel.value,
      type: fieldType.value,
      min_value: isNumeric.value && fieldMinValue.value ? Number(fieldMinValue.value) : null,
      max_value: isNumeric.value && fieldMaxValue.value ? Number(fieldMaxValue.value) : null,
      dynamic_source: isDynamic.value && fieldDynamicSource.value ? fieldDynamicSource.value : null,
      reference_table_id:
        needsReferenceTable.value && fieldReferenceTableId.value
          ? Number(fieldReferenceTableId.value)
          : null,
      is_required: fieldRequired.value,
    })
    toast.success('تمت إضافة الحقل')
    fieldDialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الحقل')
  }
}

async function confirmDeleteField() {
  if (!deletingField.value) return
  try {
    await deleteField(props.version.id, deletingField.value)
    toast.success('تم حذف الحقل')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الحقل'))
  } finally {
    deletingField.value = null
  }
}

async function confirmDeleteGroup() {
  if (!deletingGroup.value) return
  try {
    await deleteGroup(props.version.id, deletingGroup.value)
    toast.success('تم حذف المجموعة')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف المجموعة'))
  } finally {
    deletingGroup.value = null
  }
}

async function changeFieldGroup(field: FieldDefinition, newGroupId: number) {
  if (newGroupId === field.field_group_id) return
  try {
    await updateField(props.version.id, field, { field_group_id: newGroupId })
    toast.success('تم نقل الحقل إلى المجموعة الجديدة')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر نقل الحقل'))
  }
}

async function moveGroup(index: number, direction: -1 | 1) {
  const target = index + direction
  if (target < 0 || target >= groups.value.length) return
  // Optimistic reorder: swap sort_order locally.
  const reordered = [...groups.value]
  const a = reordered[index]
  const b = reordered[target]
  if (!a || !b) return
  reordered[index] = { ...a, sort_order: b.sort_order }
  reordered[target] = { ...b, sort_order: a.sort_order }
  groups.value = reordered.sort((x, y) => x.sort_order - y.sort_order)

  movingGroup.value = true
  try {
    await persistGroupOrder(
      props.version.id,
      groups.value.map((g) => g.id),
    )
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ ترتيب المجموعات'))
    await fetchGroups(props.version.id)
  } finally {
    movingGroup.value = false
  }
}

onMounted(async () => {
  await fetchGroups(props.version.id)
  fetchReferenceTables()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Groups manager -->
    <Card class="border-0 shadow">
      <CardContent class="space-y-3 p-4">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <FolderTree class="text-primary h-4 w-4" aria-hidden="true" />
            <h3 class="font-section text-sm font-semibold">مجموعات الحقول (تبويبات شاشة الطلب)</h3>
          </div>
          <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
            <Button size="sm" variant="outline" @click="openGroupDialog">
              <Plus class="h-3.5 w-3.5" />إضافة مجموعة
            </Button>
          </ScreenGuard>
        </div>
        <p class="text-muted-foreground text-xs leading-relaxed">
          كل مجموعة تظهر كتبويب في شاشة الطلب. أعد ترتيب المجموعات بالأسهم أو احذفها؛ تُنقل حقول
          المجموعة المحذوفة تلقائياً.
        </p>

        <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

        <Empty v-else-if="groups.length === 0" class="py-8">
          <EmptyMedia variant="icon">
            <FolderTree />
          </EmptyMedia>
          <EmptyHeader>
            <EmptyTitle>لا توجد مجموعات حقول</EmptyTitle>
            <EmptyDescription>أضف مجموعة (تبويب) لتعريف حقول النموذج.</EmptyDescription>
          </EmptyHeader>
          <EmptyContent v-if="editable">
            <ScreenGuard screen="workflow_designer" capability="MANAGE">
              <Button size="sm" variant="outline" @click="openGroupDialog">
                <Plus class="h-3.5 w-3.5" />إضافة مجموعة
              </Button>
            </ScreenGuard>
          </EmptyContent>
        </Empty>

        <div v-else class="space-y-2">
          <div
            v-for="(group, index) in groups"
            :key="group.id"
            class="border-border hover:bg-muted/30 flex items-center gap-2 rounded-lg border px-3 py-2 transition-colors"
          >
            <Badge variant="secondary" class="font-mono text-xs">{{ index + 1 }}</Badge>
            <span class="flex-1 text-sm font-medium">{{ group.label }}</span>
            <Badge v-if="group.name" variant="outline" class="font-mono text-[10px]">
              {{ group.name }}
            </Badge>
            <span class="text-muted-foreground text-xs">{{ fieldCount(group.id) }} حقل</span>
            <Button
              size="icon-sm"
              variant="ghost"
              aria-label="تحريك لأعلى"
              :disabled="index === 0 || movingGroup"
              @click="moveGroup(index, -1)"
            >
              <ChevronUp class="h-4 w-4" />
            </Button>
            <Button
              size="icon-sm"
              variant="ghost"
              aria-label="تحريك لأسفل"
              :disabled="index === groups.length - 1 || movingGroup"
              @click="moveGroup(index, 1)"
            >
              <ChevronDown class="h-4 w-4" />
            </Button>
            <ScreenGuard v-if="editable" screen="workflow_designer" capability="MANAGE">
              <Button
                size="icon-sm"
                variant="ghost"
                aria-label="حذف المجموعة"
                @click="deletingGroup = group"
              >
                <Trash2 class="h-4 w-4 text-[var(--severity-red)]" />
              </Button>
            </ScreenGuard>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Fields -->
    <Card class="border-0 shadow">
      <CardContent class="space-y-3 p-4">
        <div class="flex items-center justify-between gap-2">
          <h3 class="font-section text-sm font-semibold">الحقول</h3>
          <ScreenGuard
            v-if="editable && groups.length > 0"
            screen="workflow_designer"
            capability="MANAGE"
          >
            <Button size="sm" @click="openFieldDialog(groups[0]?.id ?? null)">
              <Plus class="h-3.5 w-3.5" />إضافة حقل
            </Button>
          </ScreenGuard>
        </div>

        <p v-if="groups.length === 0" class="text-muted-foreground p-4 text-center text-sm">
          أضف مجموعة واحدة على الأقل قبل تعريف الحقول.
        </p>

        <div v-else-if="flatFields.length === 0">
          <Empty class="py-8">
            <EmptyMedia variant="icon">
              <ListChecks />
            </EmptyMedia>
            <EmptyHeader>
              <EmptyTitle>لا توجد حقول</EmptyTitle>
              <EmptyDescription>أضف الحقول ثم أسند كل حقل إلى مجموعته.</EmptyDescription>
            </EmptyHeader>
            <EmptyContent v-if="editable && groups.length > 0">
              <ScreenGuard screen="workflow_designer" capability="MANAGE">
                <Button size="sm" @click="openFieldDialog(groups[0]?.id ?? null)">
                  <Plus class="h-3.5 w-3.5" />إضافة حقل
                </Button>
              </ScreenGuard>
            </EmptyContent>
          </Empty>
        </div>

        <div v-else class="border-border overflow-hidden rounded-md border">
          <Table
            class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
          >
            <TableHeader>
              <TableRow class="bg-muted/50 hover:bg-muted/50">
                <TableHead class="text-right">الرمز</TableHead>
                <TableHead class="text-right">الاسم</TableHead>
                <TableHead class="text-right">النوع</TableHead>
                <TableHead class="text-right">المجموعة</TableHead>
                <TableHead class="text-left">إجراء</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="{ field } in flatFields" :key="field.id" class="even:bg-muted/30">
                <TableCell class="text-muted-foreground font-mono text-xs">{{
                  field.key
                }}</TableCell>
                <TableCell>
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium">{{ field.label }}</span>
                    <Badge
                      v-if="field.is_required"
                      variant="outline"
                      class="border-[var(--severity-amber)]/40 text-[var(--severity-amber)]"
                    >
                      مطلوب
                    </Badge>
                    <Badge v-if="field.is_system" variant="outline">نظامي</Badge>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="secondary">{{ typeLabels[field.type] }}</Badge>
                </TableCell>
                <TableCell>
                  <Select
                    :model-value="String(field.field_group_id)"
                    :disabled="!editable"
                    @update:model-value="(v) => changeFieldGroup(field, Number(v))"
                  >
                    <SelectTrigger class="h-8 w-44 text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="group in groups" :key="group.id" :value="String(group.id)">
                        {{ group.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </TableCell>
                <TableCell class="text-left" @click.stop>
                  <div class="flex items-center justify-end gap-0.5">
                    <ScreenGuard
                      v-if="editable && !field.is_system"
                      screen="workflow_designer"
                      capability="MANAGE"
                    >
                      <Tooltip>
                        <TooltipTrigger as-child>
                          <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="حذف الحقل"
                            @click="deletingField = field"
                          >
                            <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>حذف الحقل</TooltipContent>
                      </Tooltip>
                    </ScreenGuard>
                    <span
                      v-else-if="!editable"
                      class="inline-flex items-center gap-1 text-xs text-[var(--locked)]"
                    >
                      <Lock class="h-3 w-3" />مقفلة
                    </span>
                  </div>
                </TableCell>
              </TableRow>
              <TableEmpty v-if="flatFields.length === 0" :columns="5">
                لا توجد حقول بعد.
              </TableEmpty>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <!-- Add group dialog -->
    <Dialog v-model:open="groupDialogOpen">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle>إضافة مجموعة</DialogTitle>
          <DialogDescription>تظهر المجموعات كتبويبات في نموذج الطلب.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label>الرمز</Label>
            <Input v-model="groupName" placeholder="request_data" dir="ltr" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label>التسمية</Label>
            <Input v-model="groupLabel" placeholder="بيانات الطلب" />
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="groupDialogOpen = false">إلغاء</Button>
          <Button @click="submitGroup">حفظ</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Add field dialog -->
    <Dialog v-model:open="fieldDialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>إضافة حقل</DialogTitle>
          <DialogDescription>عرّف الحقل وإعداداته حسب النوع.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label>الرمز</Label>
            <Input v-model="fieldKey" placeholder="amount" dir="ltr" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label>الاسم</Label>
            <Input v-model="fieldLabel" placeholder="المبلغ" />
          </div>

          <div v-if="isNumeric" class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5">
              <Label>الحد الأدنى</Label>
              <Input v-model="fieldMinValue" type="number" />
            </div>
            <div class="flex flex-col gap-1.5">
              <Label>الحد الأقصى</Label>
              <Input v-model="fieldMaxValue" type="number" />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox id="field-required" v-model="fieldRequired" />
            <Label for="field-required">حقل مطلوب</Label>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>المجموعة</Label>
            <Select v-model="fieldGroupId" disabled>
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر المجموعة"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="group in groups" :key="group.id" :value="group.id">
                  {{ group.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>النوع</Label>
            <Select v-model="fieldType">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in FIELD_TYPES" :key="t" :value="t">
                  {{ typeLabels[t] }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="isDynamic" class="flex flex-col gap-1.5">
            <Label>المصدر الديناميكي</Label>
            <Select v-model="fieldDynamicSource">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر المصدر"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem value="MERCHANTS">{{ sourceLabels.MERCHANTS }}</SelectItem>
                <SelectItem value="MERCHANT_COMPANIES">
                  {{ sourceLabels.MERCHANT_COMPANIES }}
                </SelectItem>
                <SelectItem value="REFERENCE_DATA">{{ sourceLabels.REFERENCE_DATA }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="needsReferenceTable" class="flex flex-col gap-1.5">
            <Label>الجدول المرجعي</Label>
            <Select v-model="fieldReferenceTableId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر الجدول"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="table in referenceTables"
                  :key="table.id"
                  :value="String(table.id)"
                >
                  {{ table.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <p v-if="formError" class="text-xs text-[var(--severity-red)]" role="alert">
            {{ formError }}
          </p>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="fieldDialogOpen = false">إلغاء</Button>
          <Button @click="submitField">حفظ</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete field confirm -->
    <AlertDialog
      :open="deletingField !== null"
      @update:open="(open) => !open && (deletingField = null)"
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف الحقل</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف الحقل «{{ deletingField?.label }}» نهائياً.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deletingField = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="confirmDeleteField">تأكيد الحذف</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Delete group confirm -->
    <AlertDialog
      :open="deletingGroup !== null"
      @update:open="(open) => !open && (deletingGroup = null)"
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف المجموعة</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف المجموعة «{{ deletingGroup?.label }}». قد تُفقد الحقول المرتبطة بها أو تُنقل.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="deletingGroup = null">إلغاء</AlertDialogCancel>
          <AlertDialogAction @click="confirmDeleteGroup">تأكيد الحذف</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Loading skeleton (kept for initial fetch) -->
    <div v-if="error === null && groups.length === 0" class="hidden">
      <Skeleton />
    </div>
  </div>
</template>
