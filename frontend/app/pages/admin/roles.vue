<script setup lang="ts">
import { h } from 'vue'
import { createColumnHelper } from '@tanstack/vue-table'
import PageHeader from '@/components/layout/PageHeader.vue'
import { ROLE_LABELS, ALL_ROLES, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { Card } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import DataTable from '@/components/ui/data-table/DataTable.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/roles'],
})

type Permission = {
  key: string
  label: string
  roles: UserRole[]
}

type PermissionRow = {
  key: string
  label: string
  roleMap: Record<UserRole, boolean>
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
    roles: [
      UserRole.BANK_REVIEWER,
      UserRole.BANK_ADMIN,
      UserRole.SUPPORT_COMMITTEE,
      UserRole.COMMITTEE_DIRECTOR,
    ],
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

const permissionRows = computed<PermissionRow[]>(() =>
  ALL_PERMISSIONS.map((permission) => ({
    key: permission.key,
    label: permission.label,
    roleMap: ALL_ROLES.reduce(
      (acc, role) => {
        acc[role] = hasPermission(role, permission)
        return acc
      },
      {} as Record<UserRole, boolean>,
    ),
  })),
)

const columnHelper = createColumnHelper<PermissionRow>()

const columns = computed(() => [
  columnHelper.accessor('label', {
    id: 'permission',
    header: () => h('span', 'الصلاحيات'),
    cell: ({ row }) =>
      h('div', { class: 'font-medium' }, [
        row.original.label,
        h(
          Badge,
          { variant: 'outline', class: 'me-2 font-mono text-[9px]' },
          () => row.original.key,
        ),
      ]),
    enableSorting: false,
    meta: {
      headerClass: 'min-w-[260px] bg-muted/40 p-3 text-end',
      cellClass: 'p-3 align-top',
    },
  }),
  ...ALL_ROLES.map((role) =>
    columnHelper.display({
      id: role,
      header: () =>
        h('div', { class: 'text-[11px] font-medium leading-tight text-center' }, ROLE_LABELS[role]),
      cell: ({ row }) =>
        h('div', { class: 'flex justify-center p-1' }, [
          h(Checkbox, {
            class: 'perm-checkbox',
            modelValue: row.original.roleMap[role],
            disabled: true,
          }),
        ]),
      enableSorting: false,
      meta: {
        headerClass: 'min-w-[120px] p-3 text-center',
        cellClass: 'p-2 text-center align-top',
      },
    }),
  ),
])
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
      <span
        class="read-only-badge bg-muted text-muted-foreground inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
      >
        قراءة فقط
      </span>
    </div>

    <Card class="overflow-x-auto border-0 p-0 shadow">
      <DataTable
        :data="permissionRows"
        :columns="columns"
        row-class="hover:bg-muted/20 cursor-default"
      />
    </Card>
  </div>
</template>
