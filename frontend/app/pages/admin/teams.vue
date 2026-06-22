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
import { useOrganizations } from '@/composables/useOrganizations'
import { useTeams } from '@/composables/useTeams'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'teams' })

const { organizations, fetchOrganizations } = useOrganizations()
const { teams, fetchTeams, createTeam } = useTeams()
const dialogOpen = ref(false)
const selectedOrganization = ref<string>('')
const form = useForm({
  validationSchema: toTypedSchema(
    z.object({
      organization_id: z.number().int().positive(),
      code: z.string().min(2),
      name: z.string().min(2),
    }),
  ),
})
const [organizationId] = form.defineField('organization_id')
const [code] = form.defineField('code')
const [name] = form.defineField('name')
const submit = form.handleSubmit(async (values) => {
  try {
    await createTeam(values)
    toast.success('تم إنشاء الفريق')
    dialogOpen.value = false
    form.resetForm()
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إنشاء الفريق'))
  }
})

// Default the create dialog's org to the currently filtered org.
const dialogOrganizationId = computed({
  get: () => String(organizationId.value ?? ''),
  set: (value: string) => {
    organizationId.value = value ? Number(value) : 0
  },
})

watch(dialogOpen, (open) => {
  if (open && selectedOrganization.value) organizationId.value = Number(selectedOrganization.value)
})

watch(selectedOrganization, (value) => {
  organizationId.value = value ? Number(value) : 0
  fetchTeams(value ? Number(value) : undefined)
})
onMounted(async () => {
  await fetchOrganizations()
  selectedOrganization.value = String(organizations.value[0]?.id ?? '')
})
</script>

<template>
  <ScreenGuard screen="teams">
    <div class="space-y-6">
      <PageHeader title="الفرق" description="إدارة الفرق ضمن كل مؤسسة">
        <template #actions><Button @click="dialogOpen = true">إضافة فريق</Button></template>
      </PageHeader>
      <Select v-model="selectedOrganization">
        <SelectTrigger><SelectValue placeholder="اختر المؤسسة" /></SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="organization in organizations"
            :key="organization.id"
            :value="String(organization.id)"
          >
            {{ organization.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <Table>
        <TableHeader
          ><TableRow
            ><TableHead>الرمز</TableHead><TableHead>الاسم</TableHead
            ><TableHead>المؤسسة</TableHead></TableRow
          ></TableHeader
        >
        <TableBody>
          <TableRow v-for="team in teams" :key="team.id">
            <TableCell>{{ team.code }}</TableCell
            ><TableCell>{{ team.name }}</TableCell
            ><TableCell>{{ team.organization?.name ?? '—' }}</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
    <Dialog v-model:open="dialogOpen">
      <DialogContent>
        <DialogHeader><DialogTitle>إضافة فريق</DialogTitle></DialogHeader>
        <form class="space-y-4" @submit="submit">
          <Select v-model="dialogOrganizationId">
            <SelectTrigger><SelectValue placeholder="اختر المؤسسة" /></SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="organization in organizations"
                :key="organization.id"
                :value="String(organization.id)"
                >{{ organization.name }}</SelectItem
              >
            </SelectContent>
          </Select>
          <Input v-model="code" name="code" placeholder="الرمز" />
          <Input v-model="name" name="name" placeholder="الاسم" />
          <DialogFooter><Button type="submit">حفظ</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </ScreenGuard>
</template>
