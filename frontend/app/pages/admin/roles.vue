<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, ALL_ROLES } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'

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
    label: 'إدارة التجار',
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
    <PageHeader
      title="مصفوفة الأدوار والصلاحيات"
      subtitle="عرض صلاحيات كل دور (للقراءة فقط في الوقت الحالي)"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الأدوار والصلاحيات' }]"
    />

    <Card class="overflow-x-auto border-0 p-0 shadow">
      <Table class="w-full min-w-[640px] text-sm">
        <TableHeader class="bg-gray-50/40">
          <TableRow>
            <TableHead class="sticky end-0 min-w-[260px] bg-gray-50/40 p-3 text-end">
              الصلاحية
            </TableHead>
            <TableHead
              v-for="role in ALL_ROLES"
              :key="role"
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
            class="border-t hover:bg-gray-50/20"
          >
            <TableCell class="sticky end-0 bg-white p-3 font-medium">
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
