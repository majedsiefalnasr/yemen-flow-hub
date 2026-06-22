<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useGovernanceBanks } from '@/composables/useGovernanceBanks'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'banks' })

const { banks, fetchBanks, createBank } = useGovernanceBanks()
const dialogOpen = ref(false)
const form = useForm({
  validationSchema: toTypedSchema(
    z.object({
      code: z.string().min(2),
      name: z.string().min(2),
      license_number: z.string().optional(),
      swift_code: z.string().optional(),
      status: z.enum(['ACTIVE', 'SUSPENDED']),
    }),
  ),
  initialValues: { status: 'ACTIVE' as const },
})
const [code] = form.defineField('code')
const [name] = form.defineField('name')
const [licenseNumber] = form.defineField('license_number')
const [swiftCode] = form.defineField('swift_code')
const submit = form.handleSubmit(async (values) => {
  try {
    await createBank(values)
    toast.success('تم إنشاء البنك')
    dialogOpen.value = false
    form.resetForm({ values: { status: 'ACTIVE' } })
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إنشاء البنك'))
  }
})
onMounted(fetchBanks)
</script>

<template>
  <ScreenGuard screen="banks">
    <div class="space-y-6">
      <PageHeader title="البنوك" description="إدارة البنوك التجارية وبيانات الترخيص والسويفت">
        <template #actions><Button @click="dialogOpen = true">إضافة بنك</Button></template>
      </PageHeader>
      <Table>
        <TableHeader
          ><TableRow
            ><TableHead>الرمز</TableHead><TableHead>الاسم</TableHead><TableHead>الترخيص</TableHead
            ><TableHead>SWIFT</TableHead><TableHead>الحالة</TableHead></TableRow
          ></TableHeader
        >
        <TableBody>
          <TableRow v-for="bank in banks" :key="bank.id">
            <TableCell>{{ bank.code }}</TableCell
            ><TableCell>{{ bank.name_ar }}</TableCell>
            <TableCell>{{ bank.license_number || '—' }}</TableCell
            ><TableCell>{{ bank.swift_code || '—' }}</TableCell>
            <TableCell
              ><Badge :variant="bank.status === 'ACTIVE' ? 'default' : 'secondary'">{{
                bank.status
              }}</Badge></TableCell
            >
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <Dialog v-model:open="dialogOpen">
      <DialogContent>
        <DialogHeader><DialogTitle>إضافة بنك</DialogTitle></DialogHeader>
        <form class="space-y-4" @submit="submit">
          <Input v-model="code" placeholder="الرمز" />
          <Input v-model="name" placeholder="الاسم" />
          <Input v-model="licenseNumber" placeholder="رقم الترخيص" />
          <Input v-model="swiftCode" placeholder="رمز SWIFT" />
          <DialogFooter><Button type="submit">حفظ</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </ScreenGuard>
</template>
