<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { Plus, Trash2 } from 'lucide-vue-next'
import type {
  DynamicFieldSource,
  FieldDefinition,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useReferenceData } from '@/composables/useReferenceData'
import { useWorkflowFields } from '@/composables/useWorkflowFields'

const props = defineProps<{ version: WorkflowVersion }>()

const { groups, error, fetchGroups, createGroup, createField, deleteField } = useWorkflowFields()
const { referenceTables, fetchReferenceTables } = useReferenceData()

const editable = props.version.state === 'DRAFT'
const activeTab = ref<string>('')
const groupDialogOpen = ref(false)
const fieldDialogOpen = ref(false)
const deleting = ref<FieldDefinition | null>(null)

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

function openGroupDialog() {
  groupName.value = ''
  groupLabel.value = ''
  groupDialogOpen.value = true
}

function openFieldDialog(groupId: number) {
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
    const group = await createGroup(props.version.id, {
      name: groupName.value,
      label: groupLabel.value,
      sort_order: groups.value.length,
    })
    activeTab.value = String(group.id)
    toast.success('تمت إضافة المجموعة')
    groupDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ المجموعة'))
  }
}

async function submitField() {
  if (fieldGroupId.value === null || !fieldKey.value || !fieldLabel.value) {
    formError.value = 'الرمز والاسم مطلوبان.'
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

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await deleteField(props.version.id, deleting.value)
    toast.success('تم حذف الحقل')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الحقل'))
  } finally {
    deleting.value = null
  }
}

onMounted(async () => {
  await fetchGroups(props.version.id)
  if (groups.value.length > 0) {
    activeTab.value = String(groups.value[0]?.id)
  }
  fetchReferenceTables()
})
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="font-section text-sm font-semibold">الحقول والمجموعات</h3>
      <ScreenGuard v-if="editable" screen="workflow_designer" capability="CREATE">
        <Button size="sm" @click="openGroupDialog">
          <Plus class="h-3.5 w-3.5" />إضافة مجموعة
        </Button>
      </ScreenGuard>
    </div>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <Empty v-else-if="groups.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مجموعات حقول</EmptyTitle>
        <EmptyDescription>أضف مجموعة (تبويب) لتعريف حقول النموذج.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <Tabs v-else v-model="activeTab" dir="rtl">
      <TabsList variant="line">
        <TabsTrigger v-for="group in groups" :key="group.id" :value="String(group.id)">
          {{ group.label }}
        </TabsTrigger>
      </TabsList>
      <TabsContent
        v-for="group in groups"
        :key="`content-${group.id}`"
        :value="String(group.id)"
        class="mt-3 space-y-3"
      >
        <div class="flex justify-end">
          <ScreenGuard v-if="editable" screen="workflow_designer" capability="CREATE">
            <Button size="sm" variant="outline" @click="openFieldDialog(group.id)">
              <Plus class="h-3.5 w-3.5" />إضافة حقل
            </Button>
          </ScreenGuard>
        </div>

        <Empty v-if="group.fields.length === 0">
          <EmptyHeader>
            <EmptyTitle>لا توجد حقول</EmptyTitle>
          </EmptyHeader>
        </Empty>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="text-right">الرمز</TableHead>
              <TableHead class="text-right">الاسم</TableHead>
              <TableHead class="text-right">النوع</TableHead>
              <TableHead class="text-right">مطلوب</TableHead>
              <TableHead class="text-right">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="field in group.fields" :key="field.id">
              <TableCell class="font-mono">{{ field.key }}</TableCell>
              <TableCell>{{ field.label }}</TableCell>
              <TableCell>
                <Badge variant="secondary">{{ typeLabels[field.type] }}</Badge>
                <Badge v-if="field.is_system" variant="outline" class="ms-1">نظامي</Badge>
              </TableCell>
              <TableCell>
                <Badge v-if="field.is_required" variant="secondary">مطلوب</Badge>
              </TableCell>
              <TableCell @click.stop>
                <ScreenGuard
                  v-if="editable && !field.is_system"
                  screen="workflow_designer"
                  capability="DELETE"
                >
                  <Button
                    size="sm"
                    variant="ghost"
                    aria-label="حذف الحقل"
                    @click="deleting = field"
                  >
                    <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                  </Button>
                </ScreenGuard>
                <span v-if="!editable" class="text-muted-foreground text-xs">مقفلة</span>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </TabsContent>
    </Tabs>

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
          <div class="flex flex-col gap-1.5">
            <Label>النوع</Label>
            <Select v-model="fieldType">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="t in FIELD_TYPES" :key="t" :value="t">
                  {{ typeLabels[t] }}
                </SelectItem>
              </SelectContent>
            </Select>
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

          <div v-if="isDynamic" class="flex flex-col gap-1.5">
            <Label>المصدر الديناميكي</Label>
            <Select v-model="fieldDynamicSource">
              <SelectTrigger><SelectValue placeholder="اختر المصدر" /></SelectTrigger>
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
              <SelectTrigger><SelectValue placeholder="اختر الجدول" /></SelectTrigger>
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

          <div class="flex items-center gap-2">
            <Checkbox id="field-required" v-model:checked="fieldRequired" />
            <Label for="field-required">حقل مطلوب</Label>
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

    <AlertDialog :open="deleting !== null" @update:open="(open) => !open && (deleting = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف الحقل</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف الحقل «{{ deleting?.label }}» نهائياً.
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
