<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, ALL_ROLES, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { Card } from '@/components/ui/card'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/roles'],
})

type Permission = {
  key: string
  label: string
  roles: UserRole[]
}

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)

const ALL_PERMISSIONS: Permission[] = [
  {
    key: 'request.create',
    label: 'إنشاء طلب تمويل',
    roles: [UserRole.DATA_ENTRY],
  },
  {
    key: 'request.review',
    label: 'مراجعة الطلبات',
    roles: [UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN],
  },
  {
    key: 'request.approve',
    label: 'اعتماد الطلبات',
    roles: [UserRole.BANK_ADMIN, UserRole.SUPPORT_COMMITTEE, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'request.reject',
    label: 'رفض الطلبات',
    roles: [UserRole.BANK_REVIEWER, UserRole.BANK_ADMIN, UserRole.SUPPORT_COMMITTEE, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'swift.upload',
    label: 'رفع وثيقة السويفت',
    roles: [UserRole.SWIFT_OFFICER],
  },
  {
    key: 'voting.cast',
    label: 'التصويت على الطلبات',
    roles: [UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'voting.finalize',
    label: 'إغلاق التصويت ونشر القرار',
    roles: [UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'customs.issue',
    label: 'إصدار إذن بيان جمركي',
    roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'reports.view',
    label: 'عرض التقارير',
    roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN],
  },
  {
    key: 'audit.view',
    label: 'عرض سجل التدقيق',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    key: 'merchants.manage',
    label: 'التجار',
    roles: [UserRole.BANK_ADMIN, UserRole.CBY_ADMIN],
  },
  {
    key: 'users.manage',
    label: 'إدارة المستخدمين',
    roles: [UserRole.BANK_ADMIN, UserRole.CBY_ADMIN],
  },
  {
    key: 'entities.manage',
    label: 'إدارة البنوك',
    roles: [UserRole.CBY_ADMIN],
  },
  {
    key: 'docrules.manage',
    label: 'إدارة قواعد المستندات',
    roles: [UserRole.CBY_ADMIN],
  },
]

function hasPermission(role: UserRole, permission: Permission) {
  return permission.roles.includes(role)
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <h1 class="page-title sr-only">مصفوفة الأدوار والصلاحيات</h1>
    <PageHeader
      title="مصفوفة الأدوار والصلاحيات"
      subtitle="عرض صلاحيات كل دور (للقراءة فقط في الوقت الحالي)"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الأدوار والصلاحيات' }]"
    />

    <div class="mb-3 flex items-center gap-2">
      <span class="read-only-badge inline-flex items-center rounded-full border bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
        قراءة فقط
      </span>
    </div>

    <Card class="overflow-x-auto border-0 p-0 shadow">
      <Table class="w-full min-w-[640px] text-sm">
        <TableHeader class="bg-muted/40">
          <TableRow>
            <TableHead class="sticky end-0 min-w-[260px] bg-muted/40 p-3 text-end">
              الصلاحيات
            </TableHead>
            <TableHead
              v-for="role in ALL_ROLES"
              :key="role"
              :data-role="role"
              class="min-w-[120px] p-3 text-center"
            >
              <div class="text-[11px] font-medium leading-tight">
                {{ ROLE_LABELS[role] }}
              </div>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="permission in ALL_PERMISSIONS"
            :key="permission.key"
            :data-permission="permission.key"
            class="border-t hover:bg-muted/20"
          >
            <TableCell class="sticky end-0 bg-background p-3 font-medium">
              {{ permission.label }}
              <Badge
                variant="outline"
                class="me-2 font-mono text-[9px]"
              >
                {{ permission.key }}
              </Badge>
            </TableCell>
            <TableCell
              v-for="role in ALL_ROLES"
              :key="`${role}-${permission.key}`"
              class="p-3 text-center align-top"
            >
              <Checkbox
                class="perm-checkbox"
                :model-value="hasPermission(role, permission)"
                disabled
              />
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </Card>
  </div>
</template>
