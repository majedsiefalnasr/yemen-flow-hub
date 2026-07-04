<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Lock, Plus, Trash2, Users } from 'lucide-vue-next'
import type { StagePermission, WorkflowStage, WorkflowVersion } from '@/types/models'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { useGovernanceRoles } from '@/composables/useGovernanceRoles'
import { useOrganizations } from '@/composables/useOrganizations'
import { useStagePermissions } from '@/composables/useStagePermissions'
import { useTeams } from '@/composables/useTeams'

const props = defineProps<{ stage: WorkflowStage; version: WorkflowVersion }>()

const { permissions, error, fetchPermissions, createPermission, deletePermission } =
  useStagePermissions()
const { organizations, fetchOrganizations } = useOrganizations()
const { roles, fetchRoles } = useGovernanceRoles()
const { teams, fetchTeams } = useTeams()

const editable = props.version.state === 'DRAFT'
const dialogOpen = ref(false)
const deleting = ref<StagePermission | null>(null)

const organizationId = ref<string>('')
const teamId = ref<string>('')
const roleId = ref<string>('')
const accessLevel = ref<'VIEW' | 'EXECUTE'>('EXECUTE')
const displayLabel = ref('')
const formError = ref<string | null>(null)

watch(organizationId, (value) => {
  teamId.value = ''
  roleId.value = ''
  if (value) {
    fetchRoles(Number(value))
    fetchTeams(Number(value))
  }
})

const orgName = (id: number | null) =>
  id === null ? 'الكل' : (organizations.value.find((o) => o.id === id)?.name ?? `#${id}`)
const roleName = (id: number | null) =>
  id === null ? '—' : (roles.value.find((r) => r.id === id)?.name ?? `#${id}`)

function openCreate() {
  organizationId.value = ''
  teamId.value = ''
  roleId.value = ''
  accessLevel.value = 'EXECUTE'
  displayLabel.value = ''
  formError.value = null
  dialogOpen.value = true
}

async function submit() {
  if (!displayLabel.value || (!organizationId.value && !teamId.value && !roleId.value)) {
    formError.value = 'حدّد على الأقل جهة أو فريقاً أو دوراً، وأدخل تسمية.'
    return
  }
  try {
    await createPermission(props.stage.id, {
      organization_id: organizationId.value ? Number(organizationId.value) : null,
      team_id: teamId.value ? Number(teamId.value) : null,
      role_id: roleId.value ? Number(roleId.value) : null,
      access_level: accessLevel.value,
      display_label: displayLabel.value,
    })
    toast.success('تمت إضافة الصلاحية')
    dialogOpen.value = false
  } catch (cause) {
    formError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الصلاحية')
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await deletePermission(deleting.value)
    toast.success('تم حذف الصلاحية')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الصلاحية'))
  } finally {
    deleting.value = null
  }
}

onMounted(() => {
  fetchPermissions(props.stage.id)
  fetchOrganizations()
})
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="font-section text-xs font-semibold">صلاحيات المرحلة</h4>
      <ScreenGuard v-if="editable" screen="workflow_designer" capability="CREATE">
        <Button size="sm" variant="outline" @click="openCreate">
          <Plus class="h-3.5 w-3.5" />إضافة صلاحية
        </Button>
      </ScreenGuard>
    </div>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <Empty v-else-if="permissions.length === 0" class="py-6">
      <EmptyMedia variant="icon">
        <Users />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد صلاحيات</EmptyTitle>
        <EmptyDescription>امنح صلاحيات الوصول لتظهر هذه المرحلة في الدوري.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="border-border overflow-hidden rounded-md border">
      <Table
        class="[&_td]:py-3.5 [&_td:first-child]:ps-4 [&_td:last-child]:pe-4 [&_th:first-child]:ps-4 [&_th:last-child]:pe-4"
      >
        <TableHeader>
          <TableRow class="bg-muted/50 hover:bg-muted/50">
            <TableHead class="text-right">التسمية</TableHead>
            <TableHead class="text-right">الجهة</TableHead>
            <TableHead class="text-right">الدور</TableHead>
            <TableHead class="text-right">المستوى</TableHead>
            <TableHead class="text-left">إجراء</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="permission in permissions" :key="permission.id" class="even:bg-muted/30">
            <TableCell class="font-medium">{{ permission.display_label }}</TableCell>
            <TableCell class="text-muted-foreground">{{
              orgName(permission.organization_id)
            }}</TableCell>
            <TableCell class="text-muted-foreground">{{ roleName(permission.role_id) }}</TableCell>
            <TableCell>
              <Badge
                v-if="permission.access_level === 'EXECUTE'"
                class="border border-[var(--brand-color)]/30 bg-[var(--brand-color)]/10 text-[var(--brand-color)]"
              >
                تنفيذ
              </Badge>
              <Badge v-else variant="secondary">عرض</Badge>
            </TableCell>
            <TableCell class="text-left" @click.stop>
              <div class="flex items-center justify-end gap-0.5">
                <ScreenGuard v-if="editable" screen="workflow_designer" capability="DELETE">
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="حذف الصلاحية"
                        @click="deleting = permission"
                      >
                        <Trash2 class="h-3.5 w-3.5 text-[var(--severity-red)]" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>حذف الصلاحية</TooltipContent>
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

    <Dialog v-model:open="dialogOpen">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>إضافة صلاحية مرحلة</DialogTitle>
          <DialogDescription>تُشتق صلاحيات الطلبات والدوري من هذه الصفوف.</DialogDescription>
        </DialogHeader>

        <div class="flex flex-col gap-4 py-2">
          <div class="flex flex-col gap-1.5">
            <Label>التسمية الظاهرة</Label>
            <Input v-model="displayLabel" placeholder="مراجعو البنك" />
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الجهة</Label>
            <Select v-model="organizationId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر الجهة (اختياري)"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="org in organizations" :key="org.id" :value="String(org.id)">
                  {{ org.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الفريق</Label>
            <Select v-model="teamId" :disabled="!organizationId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر الفريق (اختياري)"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="team in teams" :key="team.id" :value="String(team.id)">
                  {{ team.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>الدور</Label>
            <Select v-model="roleId" :disabled="!organizationId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر الدور (اختياري)"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="role in roles" :key="role.id" :value="String(role.id)">
                  {{ role.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label>مستوى الوصول</Label>
            <Select v-model="accessLevel">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="VIEW">عرض</SelectItem>
                <SelectItem value="EXECUTE">تنفيذ</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <p v-if="formError" class="text-xs text-[var(--severity-red)]" role="alert">
            {{ formError }}
          </p>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="dialogOpen = false">إلغاء</Button>
          <Button @click="submit">حفظ</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <AlertDialog :open="deleting !== null" @update:open="(open) => !open && (deleting = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد حذف الصلاحية</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم حذف الصلاحية «{{ deleting?.display_label }}» نهائياً.
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
