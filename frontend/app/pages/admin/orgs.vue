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
import { useOrganizations } from '@/composables/useOrganizations'
import type { Organization } from '@/types/models'

definePageMeta({
  middleware: ['auth', 'screen'],
  requiredScreen: 'organizations',
})

const {
  organizations,
  loading,
  fetchOrganizations,
  createOrganization,
  updateOrganization,
  setOrganizationActive,
} = useOrganizations()
const dialogOpen = ref(false)
const editing = ref<Organization | null>(null)

const form = useForm({
  validationSchema: toTypedSchema(
    z.object({
      code: z.string().min(2).max(100),
      name: z.string().min(2).max(255),
    }),
  ),
})

const submit = form.handleSubmit(async (values) => {
  try {
    if (editing.value) {
      await updateOrganization(editing.value, { name: values.name })
      toast.success('تم تحديث المؤسسة')
    } else {
      await createOrganization(values)
      toast.success('تم إنشاء المؤسسة')
    }
    dialogOpen.value = false
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر حفظ المؤسسة'))
  }
})

function openCreate() {
  editing.value = null
  form.resetForm({ values: { code: '', name: '' } })
  dialogOpen.value = true
}

function openEdit(organization: Organization) {
  editing.value = organization
  form.resetForm({ values: { code: organization.code, name: organization.name } })
  dialogOpen.value = true
}

onMounted(() => fetchOrganizations())
</script>

<template>
  <ScreenGuard screen="organizations">
    <div class="space-y-6">
      <PageHeader title="المؤسسات" description="إدارة مستويات الحوكمة المؤسسية">
        <template #actions>
          <ScreenGuard screen="organizations" capability="CREATE">
            <Button @click="openCreate"><Plus class="h-4 w-4" />إضافة مؤسسة</Button>
          </ScreenGuard>
        </template>
      </PageHeader>

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
          <TableRow v-for="organization in organizations" :key="organization.id">
            <TableCell>{{ organization.code }}</TableCell>
            <TableCell>{{ organization.name }}</TableCell>
            <TableCell>
              <Badge :variant="organization.is_active ? 'default' : 'secondary'">
                {{ organization.is_active ? 'نشطة' : 'موقوفة' }}
              </Badge>
            </TableCell>
            <TableCell class="space-x-2 space-x-reverse">
              <Button variant="outline" size="sm" @click="openEdit(organization)">تعديل</Button>
              <Button
                variant="outline"
                size="sm"
                :disabled="organization.is_system"
                @click="setOrganizationActive(organization, !organization.is_active)"
              >
                {{ organization.is_active ? 'إيقاف' : 'تفعيل' }}
              </Button>
            </TableCell>
          </TableRow>
          <TableRow v-if="!loading && organizations.length === 0">
            <TableCell colspan="4" class="text-muted-foreground text-center">
              لا توجد مؤسسات.
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Dialog v-model:open="dialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ editing ? 'تعديل المؤسسة' : 'إضافة مؤسسة' }}</DialogTitle>
          <DialogDescription>الرمز ثابت بعد الإنشاء، ويمكن تعديل الاسم فقط.</DialogDescription>
        </DialogHeader>
        <form class="space-y-4" @submit="submit">
          <FormField v-slot="{ componentField }" name="code">
            <FormItem>
              <FormLabel>الرمز</FormLabel>
              <FormControl
                ><Input v-bind="componentField" :disabled="Boolean(editing)"
              /></FormControl>
              <FormMessage />
            </FormItem>
          </FormField>
          <FormField v-slot="{ componentField }" name="name">
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
  </ScreenGuard>
</template>
