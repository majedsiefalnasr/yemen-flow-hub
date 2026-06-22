<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { Plus } from 'lucide-vue-next'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip'
import { useReferenceData } from '@/composables/useReferenceData'
import type { ReferenceTable, ReferenceValue } from '@/types/models'

definePageMeta({
  middleware: ['auth', 'screen'],
  requiredScreen: 'reference_data',
})

const {
  referenceTables,
  referenceValues,
  loading,
  fetchReferenceTables,
  fetchReferenceValues,
  createReferenceTable,
  updateReferenceTable,
  setReferenceTableActive,
  deleteReferenceTable,
  createReferenceValue,
  updateReferenceValue,
  setReferenceValueActive,
  deleteReferenceValue,
} = useReferenceData()

const selectedTable = ref<ReferenceTable | null>(null)

const tableDialogOpen = ref(false)
const editingTable = ref<ReferenceTable | null>(null)

const valueDialogOpen = ref(false)
const editingValue = ref<ReferenceValue | null>(null)

const tableForm = useForm({
  validationSchema: toTypedSchema(
    z.object({
      key: z.string().min(2).max(100),
      label: z.string().min(2).max(255),
    }),
  ),
})

const valueForm = useForm({
  validationSchema: toTypedSchema(
    z.object({
      key: z.string().min(1).max(100),
      label: z.string().min(1).max(255),
    }),
  ),
})

const submitTable = tableForm.handleSubmit(async (values) => {
  try {
    if (editingTable.value) {
      await updateReferenceTable(editingTable.value, { label: values.label })
      toast.success('تم تحديث الجدول المرجعي')
    } else {
      await createReferenceTable(values)
      toast.success('تم إنشاء الجدول المرجعي')
    }
    tableDialogOpen.value = false
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حفظ الجدول المرجعي'))
  }
})

const submitValue = valueForm.handleSubmit(async (values) => {
  if (!selectedTable.value) return
  try {
    if (editingValue.value) {
      await updateReferenceValue(editingValue.value, { label: values.label })
      toast.success('تم تحديث القيمة المرجعية')
    } else {
      await createReferenceValue({ ...values, reference_table_id: selectedTable.value.id })
      toast.success('تم إنشاء القيمة المرجعية')
    }
    valueDialogOpen.value = false
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حفظ القيمة المرجعية'))
  }
})

function openCreateTable() {
  editingTable.value = null
  tableForm.resetForm({ values: { key: '', label: '' } })
  tableDialogOpen.value = true
}

function openEditTable(table: ReferenceTable) {
  editingTable.value = table
  tableForm.resetForm({ values: { key: table.key, label: table.label } })
  tableDialogOpen.value = true
}

async function selectTable(table: ReferenceTable) {
  selectedTable.value = table
  await fetchReferenceValues(table.id)
}

async function removeTable(table: ReferenceTable) {
  try {
    await deleteReferenceTable(table)
    toast.success('تم حذف الجدول المرجعي')
    if (selectedTable.value?.id === table.id) {
      selectedTable.value = null
    }
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حذف الجدول المرجعي'))
  }
}

function openCreateValue() {
  editingValue.value = null
  valueForm.resetForm({ values: { key: '', label: '' } })
  valueDialogOpen.value = true
}

function openEditValue(value: ReferenceValue) {
  editingValue.value = value
  valueForm.resetForm({ values: { key: value.key, label: value.label } })
  valueDialogOpen.value = true
}

async function removeValue(value: ReferenceValue) {
  try {
    await deleteReferenceValue(value)
    toast.success('تم حذف القيمة المرجعية')
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حذف القيمة المرجعية'))
  }
}

onMounted(() => fetchReferenceTables())
</script>

<template>
  <ScreenGuard screen="reference_data">
    <TooltipProvider>
      <div class="space-y-6">
        <PageHeader title="البيانات المرجعية" description="إدارة الجداول والقيم المرجعية للنظام">
          <template #actions>
            <ScreenGuard screen="reference_data" capability="CREATE">
              <Button @click="openCreateTable"><Plus class="h-4 w-4" />إضافة جدول مرجعي</Button>
            </ScreenGuard>
          </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>الرمز</TableHead>
                <TableHead>الاسم</TableHead>
                <TableHead>الحالة</TableHead>
                <TableHead>الإجراءات</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="table in referenceTables"
                :key="table.id"
                class="cursor-pointer"
                :class="{ 'bg-muted': selectedTable?.id === table.id }"
                @click="selectTable(table)"
              >
                <TableCell>{{ table.key }}</TableCell>
                <TableCell>{{ table.label }}</TableCell>
                <TableCell>
                  <Badge :variant="table.is_active ? 'default' : 'secondary'">
                    {{ table.is_active ? 'نشط' : 'موقوف' }}
                  </Badge>
                  <Badge v-if="table.is_system" variant="outline" class="ms-1">نظامي</Badge>
                </TableCell>
                <TableCell class="space-x-2 space-x-reverse" @click.stop>
                  <Button variant="outline" size="sm" @click="openEditTable(table)">تعديل</Button>
                  <Button
                    variant="outline"
                    size="sm"
                    :disabled="table.is_system"
                    @click="setReferenceTableActive(table, !table.is_active)"
                  >
                    {{ table.is_active ? 'إيقاف' : 'تفعيل' }}
                  </Button>
                  <Tooltip v-if="table.is_system">
                    <TooltipTrigger as-child>
                      <span>
                        <Button variant="outline" size="sm" disabled>حذف</Button>
                      </span>
                    </TooltipTrigger>
                    <TooltipContent>لا يمكن حذف جدول نظامي</TooltipContent>
                  </Tooltip>
                  <Button v-else variant="outline" size="sm" @click="removeTable(table)">
                    حذف
                  </Button>
                </TableCell>
              </TableRow>
              <TableRow v-if="!loading && referenceTables.length === 0">
                <TableCell colspan="4" class="text-muted-foreground text-center">
                  لا توجد جداول مرجعية.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>

          <div v-if="selectedTable" class="space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold">قيم: {{ selectedTable.label }}</h3>
              <ScreenGuard screen="reference_data" capability="CREATE">
                <Button size="sm" @click="openCreateValue"
                  ><Plus class="h-4 w-4" />إضافة قيمة</Button
                >
              </ScreenGuard>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>الرمز</TableHead>
                  <TableHead>الاسم</TableHead>
                  <TableHead>الحالة</TableHead>
                  <TableHead>الإجراءات</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="value in referenceValues" :key="value.id">
                  <TableCell>{{ value.key }}</TableCell>
                  <TableCell>{{ value.label }}</TableCell>
                  <TableCell>
                    <Badge :variant="value.is_active ? 'default' : 'secondary'">
                      {{ value.is_active ? 'نشطة' : 'موقوفة' }}
                    </Badge>
                    <Badge v-if="value.is_system" variant="outline" class="ms-1">نظامي</Badge>
                  </TableCell>
                  <TableCell class="space-x-2 space-x-reverse">
                    <Button variant="outline" size="sm" @click="openEditValue(value)">تعديل</Button>
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="value.is_system"
                      @click="setReferenceValueActive(value, !value.is_active)"
                    >
                      {{ value.is_active ? 'إيقاف' : 'تفعيل' }}
                    </Button>
                    <Tooltip v-if="value.is_system">
                      <TooltipTrigger as-child>
                        <span>
                          <Button variant="outline" size="sm" disabled>حذف</Button>
                        </span>
                      </TooltipTrigger>
                      <TooltipContent>لا يمكن حذف قيمة نظامية</TooltipContent>
                    </Tooltip>
                    <Button v-else variant="outline" size="sm" @click="removeValue(value)">
                      حذف
                    </Button>
                  </TableCell>
                </TableRow>
                <TableRow v-if="referenceValues.length === 0">
                  <TableCell colspan="4" class="text-muted-foreground text-center">
                    لا توجد قيم لهذا الجدول.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <div v-else class="text-muted-foreground flex items-center justify-center text-sm">
            اختر جدولاً مرجعياً لعرض قيمه.
          </div>
        </div>
      </div>

      <Dialog v-model:open="tableDialogOpen">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{
              editingTable ? 'تعديل الجدول المرجعي' : 'إضافة جدول مرجعي'
            }}</DialogTitle>
            <DialogDescription>الرمز ثابت بعد الإنشاء، ويمكن تعديل الاسم فقط.</DialogDescription>
          </DialogHeader>
          <form class="space-y-4" @submit="submitTable">
            <FormField v-slot="{ componentField }" name="key">
              <FormItem>
                <FormLabel>الرمز</FormLabel>
                <FormControl
                  ><Input v-bind="componentField" :disabled="Boolean(editingTable)"
                /></FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField }" name="label">
              <FormItem>
                <FormLabel>الاسم</FormLabel>
                <FormControl><Input v-bind="componentField" /></FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <DialogFooter><Button type="submit">حفظ</Button></DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog v-model:open="valueDialogOpen">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{
              editingValue ? 'تعديل القيمة المرجعية' : 'إضافة قيمة مرجعية'
            }}</DialogTitle>
            <DialogDescription>الرمز ثابت بعد الإنشاء، ويمكن تعديل الاسم فقط.</DialogDescription>
          </DialogHeader>
          <form class="space-y-4" @submit="submitValue">
            <FormField v-slot="{ componentField }" name="key">
              <FormItem>
                <FormLabel>الرمز</FormLabel>
                <FormControl
                  ><Input v-bind="componentField" :disabled="Boolean(editingValue)"
                /></FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField }" name="label">
              <FormItem>
                <FormLabel>الاسم</FormLabel>
                <FormControl><Input v-bind="componentField" /></FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <DialogFooter><Button type="submit">حفظ</Button></DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </TooltipProvider>
  </ScreenGuard>
</template>
