<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Plus } from 'lucide-vue-next'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import {
  Pagination,
  PaginationContent,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
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
  referenceTablesMeta,
  referenceValuesMeta,
  tablesLoading,
  valuesLoading,
  error,
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
const tablePage = ref(1)
const valuePage = ref(1)

const tableDialogOpen = ref(false)
const editingTable = ref<ReferenceTable | null>(null)

const valueDialogOpen = ref(false)
const editingValue = ref<ReferenceValue | null>(null)
const deletingTable = ref<ReferenceTable | null>(null)
const deletingValue = ref<ReferenceValue | null>(null)

const keySchema = z
  .string()
  .min(1)
  .max(100)
  .regex(/^[\p{L}\p{M}\p{N}_-]+$/u, 'يسمح بالحروف والأرقام والشرطة والشرطة السفلية فقط')

const tableForm = useForm({
  validationSchema: toTypedSchema(
    z.object({
      key: keySchema.min(2),
      label: z.string().min(2).max(255),
    }),
  ),
})

const valueForm = useForm({
  validationSchema: toTypedSchema(
    z.object({
      key: keySchema,
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
  valuePage.value = 1
  await fetchReferenceValues(table.id, { page: valuePage.value })
}

async function changeTablePage(page: number) {
  tablePage.value = page
  selectedTable.value = null
  referenceValues.value = []
  await fetchReferenceTables({ page })
}

async function changeValuePage(page: number) {
  if (!selectedTable.value) return
  valuePage.value = page
  await fetchReferenceValues(selectedTable.value.id, { page })
}

async function toggleTable(table: ReferenceTable) {
  try {
    await setReferenceTableActive(table, !table.is_active)
    if (selectedTable.value?.id === table.id) {
      selectedTable.value = referenceTables.value.find((item) => item.id === table.id) ?? null
    }
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر تحديث حالة الجدول المرجعي'))
  }
}

async function removeTable() {
  if (!deletingTable.value) return
  const table = deletingTable.value
  try {
    await deleteReferenceTable(table)
    toast.success('تم حذف الجدول المرجعي')
    if (selectedTable.value?.id === table.id) {
      selectedTable.value = null
    }
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حذف الجدول المرجعي'))
  } finally {
    deletingTable.value = null
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

async function toggleValue(value: ReferenceValue) {
  try {
    await setReferenceValueActive(value, !value.is_active)
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر تحديث حالة القيمة المرجعية'))
  }
}

async function removeValue() {
  if (!deletingValue.value) return
  const value = deletingValue.value
  try {
    await deleteReferenceValue(value)
    toast.success('تم حذف القيمة المرجعية')
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حذف القيمة المرجعية'))
  } finally {
    deletingValue.value = null
  }
}

async function retryLoad() {
  if (selectedTable.value) {
    await fetchReferenceValues(selectedTable.value.id, { page: valuePage.value })
    return
  }

  await fetchReferenceTables({ page: tablePage.value })
}

onMounted(() => fetchReferenceTables({ page: tablePage.value }))
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

        <Alert v-if="error" variant="destructive" role="alert">
          <AlertCircle class="h-4 w-4" />
          <AlertTitle>تعذّر تحميل البيانات المرجعية</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
          <AlertAction>
            <Button variant="outline" size="sm" @click="retryLoad">إعادة المحاولة</Button>
          </AlertAction>
        </Alert>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="space-y-3">
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
                    <ScreenGuard screen="reference_data" capability="UPDATE">
                      <Button variant="outline" size="sm" @click="openEditTable(table)"
                        >تعديل</Button
                      >
                      <Button variant="outline" size="sm" @click="toggleTable(table)">
                        {{ table.is_active ? 'إيقاف' : 'تفعيل' }}
                      </Button>
                    </ScreenGuard>
                    <ScreenGuard screen="reference_data" capability="DELETE">
                      <Tooltip v-if="table.is_system || table.is_in_use">
                        <TooltipTrigger as-child>
                          <span>
                            <Button variant="outline" size="sm" disabled>حذف</Button>
                          </span>
                        </TooltipTrigger>
                        <TooltipContent>
                          {{
                            table.is_system
                              ? 'لا يمكن حذف جدول نظامي'
                              : 'لا يمكن حذف جدول يحتوي على قيم'
                          }}
                        </TooltipContent>
                      </Tooltip>
                      <Button v-else variant="outline" size="sm" @click="deletingTable = table">
                        حذف
                      </Button>
                    </ScreenGuard>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!tablesLoading && referenceTables.length === 0">
                  <TableCell colspan="4" class="text-muted-foreground text-center">
                    لا توجد جداول مرجعية.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
            <Pagination
              v-if="referenceTablesMeta && referenceTablesMeta.last_page > 1"
              :page="tablePage"
              :total="referenceTablesMeta.total"
              :items-per-page="referenceTablesMeta.per_page"
              @update:page="changeTablePage"
            >
              <PaginationContent>
                <PaginationPrevious />
                <PaginationNext />
              </PaginationContent>
            </Pagination>
          </div>

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
                    <ScreenGuard screen="reference_data" capability="UPDATE">
                      <Button variant="outline" size="sm" @click="openEditValue(value)">
                        تعديل
                      </Button>
                      <Button variant="outline" size="sm" @click="toggleValue(value)">
                        {{ value.is_active ? 'إيقاف' : 'تفعيل' }}
                      </Button>
                    </ScreenGuard>
                    <ScreenGuard screen="reference_data" capability="DELETE">
                      <Tooltip v-if="value.is_system || value.is_in_use">
                        <TooltipTrigger as-child>
                          <span>
                            <Button variant="outline" size="sm" disabled>حذف</Button>
                          </span>
                        </TooltipTrigger>
                        <TooltipContent>
                          {{
                            value.is_system ? 'لا يمكن حذف قيمة نظامية' : 'لا يمكن حذف قيمة مستخدمة'
                          }}
                        </TooltipContent>
                      </Tooltip>
                      <Button v-else variant="outline" size="sm" @click="deletingValue = value">
                        حذف
                      </Button>
                    </ScreenGuard>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!valuesLoading && referenceValues.length === 0">
                  <TableCell colspan="4" class="text-muted-foreground text-center">
                    لا توجد قيم لهذا الجدول.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
            <Pagination
              v-if="referenceValuesMeta && referenceValuesMeta.last_page > 1"
              :page="valuePage"
              :total="referenceValuesMeta.total"
              :items-per-page="referenceValuesMeta.per_page"
              @update:page="changeValuePage"
            >
              <PaginationContent>
                <PaginationPrevious />
                <PaginationNext />
              </PaginationContent>
            </Pagination>
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

      <AlertDialog :open="Boolean(deletingTable)" @update:open="deletingTable = null">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تأكيد حذف الجدول المرجعي</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف الجدول {{ deletingTable?.label }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingTable = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="removeTable">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog :open="Boolean(deletingValue)" @update:open="deletingValue = null">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تأكيد حذف القيمة المرجعية</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف القيمة {{ deletingValue?.label }} نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel @click="deletingValue = null">إلغاء</AlertDialogCancel>
            <AlertDialogAction @click="removeValue">تأكيد الحذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </TooltipProvider>
  </ScreenGuard>
</template>
