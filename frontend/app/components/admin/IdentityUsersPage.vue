<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { AlertCircle } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
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
import { useGovernanceRoles } from '@/composables/useGovernanceRoles'
import { useGovernanceBanks } from '@/composables/useGovernanceBanks'
import { useIdentityUsers } from '@/composables/useIdentityUsers'
import { useAuthStore } from '@/stores/auth.store'
import type { GovernanceUser } from '@/types/models'

const props = defineProps<{ audience: 'committee' | 'bank' }>()
const auth = useAuthStore()
const { organizations, fetchOrganizations } = useOrganizations()
const { teams, fetchTeams } = useTeams()
const { roles, fetchRoles } = useGovernanceRoles()
const { banks, fetchBanks } = useGovernanceBanks()
const { users, fetchUsers, createUser, deactivateUser, resetPassword, resetMfa } =
  useIdentityUsers()
const dialogOpen = ref(false)
const orgMissing = ref(false)

const form = useForm({
  validationSchema: toTypedSchema(
    z
      .object({
        organization_id: z.number().int().positive(),
        team_id: z.number().int().positive(),
        role_id: z.number().int().positive(),
        bank_id: z.number().int().positive().nullable(),
        name: z.string().min(2),
        email: z.string().email(),
        phone: z.string().optional(),
        password: z.string().min(8),
      })
      .superRefine((values, ctx) => {
        // Bank is required iff the chosen org is commercial_banks, and forbidden
        // otherwise — enforce the one-bank-iff-bank-org invariant in the form,
        // not just by conditionally rendering the field.
        const org = organizations.value.find((item) => item.id === values.organization_id)
        const isBankOrg = org?.code === 'commercial_banks'
        if (isBankOrg && !values.bank_id) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['bank_id'], message: 'البنك مطلوب' })
        }
        if (!isBankOrg && values.bank_id) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['bank_id'],
            message: 'لا يسمح بتحديد بنك لهذه المؤسسة',
          })
        }
      }),
  ),
})
const [organizationId] = form.defineField('organization_id')
const [teamId] = form.defineField('team_id')
const [roleId] = form.defineField('role_id')
const [bankId] = form.defineField('bank_id')
const [name] = form.defineField('name')
const [email] = form.defineField('email')
const [phone] = form.defineField('phone')
const [password] = form.defineField('password')
const selectedOrganization = computed(() =>
  organizations.value.find((item) => item.id === Number(organizationId.value)),
)
const bankRequired = computed(() => selectedOrganization.value?.code === 'commercial_banks')

watch(organizationId, async (value) => {
  teamId.value = 0
  roleId.value = 0
  bankId.value = null
  if (value) await Promise.all([fetchTeams(Number(value)), fetchRoles(Number(value))])
})

const submit = form.handleSubmit(async (values) => {
  try {
    await createUser(values)
    toast.success('تم إنشاء المستخدم')
    dialogOpen.value = false
    form.resetForm()
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إنشاء المستخدم'))
  }
})

async function onResetPassword(user: GovernanceUser): Promise<void> {
  try {
    const password = await resetPassword(user)
    toast.success(`كلمة المرور المؤقتة: ${password}`, { duration: 15000 })
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إعادة تعيين كلمة المرور'))
  }
}

async function onResetMfa(user: GovernanceUser): Promise<void> {
  try {
    await resetMfa(user)
    toast.success('تم إعادة تعيين المصادقة الثنائية')
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إعادة تعيين المصادقة الثنائية'))
  }
}

function isSelf(user: GovernanceUser): boolean {
  return user.id === auth.user?.id
}

async function onDeactivate(user: GovernanceUser): Promise<void> {
  try {
    await deactivateUser(user)
    toast.success('تم إيقاف المستخدم')
  } catch (error) {
    toast.error(extractApiErrorMessage(error, 'تعذّر إيقاف المستخدم'))
  }
}

onMounted(async () => {
  await Promise.all([fetchOrganizations(), fetchBanks()])
  const targetCode = props.audience === 'bank' ? 'commercial_banks' : 'national_committee'
  const targetOrg = organizations.value.find((item) => item.code === targetCode)
  if (!targetOrg) {
    orgMissing.value = true
    return
  }
  organizationId.value = targetOrg.id
  await fetchUsers(props.audience === 'bank' ? { organization_id: targetOrg.id } : {})
})
</script>

<template>
  <ScreenGuard screen="users">
    <div class="space-y-6">
      <PageHeader
        :title="audience === 'bank' ? 'مستخدمو البنك' : 'مستخدمو اللجنة والإدارة'"
        description="إدارة الهوية المؤسسية للمستخدمين"
      >
        <template #actions
          ><Button :disabled="orgMissing" @click="dialogOpen = true">إضافة مستخدم</Button></template
        >
      </PageHeader>
      <Alert v-if="orgMissing" variant="destructive" role="alert">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل المؤسسة</AlertTitle>
        <AlertDescription
          >لم يتم العثور على المؤسسة المطلوبة. يرجى المحاولة لاحقاً أو التواصل مع
          المسؤول.</AlertDescription
        >
      </Alert>
      <Table>
        <TableHeader
          ><TableRow
            ><TableHead>الاسم</TableHead><TableHead>المؤسسة</TableHead><TableHead>الفريق</TableHead
            ><TableHead>الدور</TableHead><TableHead>البنك</TableHead
            ><TableHead>الإجراءات</TableHead></TableRow
          ></TableHeader
        >
        <TableBody>
          <TableRow v-for="user in users" :key="user.id">
            <TableCell>{{ user.name }}</TableCell
            ><TableCell>{{ user.organization?.name ?? '—' }}</TableCell>
            <TableCell>{{ user.team?.name ?? '—' }}</TableCell
            ><TableCell>{{ user.role?.name ?? '—' }}</TableCell>
            <TableCell>{{ user.bank?.name_ar || '—' }}</TableCell>
            <TableCell class="space-x-2 space-x-reverse">
              <Button variant="outline" size="sm" @click="onResetPassword(user)"
                >إعادة كلمة المرور</Button
              >
              <Button variant="outline" size="sm" @click="onResetMfa(user)">إعادة MFA</Button>
              <AlertDialog>
                <AlertDialogTrigger as-child>
                  <Button variant="destructive" size="sm" :disabled="isSelf(user)">إيقاف</Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>تأكيد إيقاف المستخدم</AlertDialogTitle>
                    <AlertDialogDescription>
                      سيتم إيقاف حساب «{{ user.name }}» وإنهاء جلساته الحالية. لا يمكن إيقاف مستخدم
                      لديه عمل قيد التنفيذ.
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>إلغاء</AlertDialogCancel>
                    <AlertDialogAction @click="onDeactivate(user)">تأكيد الإيقاف</AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Dialog v-model:open="dialogOpen">
      <DialogContent>
        <DialogHeader><DialogTitle>إضافة مستخدم</DialogTitle></DialogHeader>
        <form class="space-y-4" @submit="submit">
          <Select v-model="organizationId">
            <SelectTrigger><SelectValue placeholder="المؤسسة" /></SelectTrigger>
            <SelectContent
              ><SelectItem
                v-for="organization in organizations"
                :key="organization.id"
                :value="organization.id"
                >{{ organization.name }}</SelectItem
              ></SelectContent
            >
          </Select>
          <Select v-model="teamId">
            <SelectTrigger><SelectValue placeholder="الفريق" /></SelectTrigger>
            <SelectContent
              ><SelectItem v-for="team in teams" :key="team.id" :value="team.id">{{
                team.name
              }}</SelectItem></SelectContent
            >
          </Select>
          <Select v-model="roleId">
            <SelectTrigger><SelectValue placeholder="الدور" /></SelectTrigger>
            <SelectContent
              ><SelectItem v-for="role in roles" :key="role.id" :value="role.id">{{
                role.name
              }}</SelectItem></SelectContent
            >
          </Select>
          <Select v-if="bankRequired" v-model="bankId">
            <SelectTrigger><SelectValue placeholder="البنك" /></SelectTrigger>
            <SelectContent
              ><SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id">{{
                bank.name_ar
              }}</SelectItem></SelectContent
            >
          </Select>
          <Input v-model="name" placeholder="الاسم" />
          <Input v-model="email" type="email" placeholder="البريد الإلكتروني" />
          <Input v-model="phone" placeholder="الهاتف" />
          <Input v-model="password" type="password" placeholder="كلمة المرور المؤقتة" />
          <DialogFooter><Button type="submit">حفظ</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </ScreenGuard>
</template>
