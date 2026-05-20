<script setup lang="ts">
import { UserRole } from '../../types/enums'
import { ROLE_LABELS } from '../../constants/workflow'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

interface PermissionRow {
  label: string
  code: string
  roles: Partial<Record<UserRole, boolean>>
}

// Canonical permission matrix — static production constants, no editable permissions in this story.
// Checkboxes are all disabled/readonly.
const PERMISSION_MATRIX: PermissionRow[] = [
  {
    label: 'إنشاء طلب تمويل',
    code: 'request.create',
    roles: {
      [UserRole.DATA_ENTRY]: true,
      [UserRole.BANK_ADMIN]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'مراجعة الطلبات',
    code: 'request.review',
    roles: {
      [UserRole.BANK_REVIEWER]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'اعتماد الطلبات',
    code: 'request.approve',
    roles: {
      [UserRole.BANK_REVIEWER]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'رفض الطلبات',
    code: 'request.reject',
    roles: {
      [UserRole.BANK_REVIEWER]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'رافع وثيقة السويفت',
    code: 'swift.upload',
    roles: {
      [UserRole.SWIFT_OFFICER]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'التصويت على الطلبات',
    code: 'voting.cast',
    roles: {
      [UserRole.EXECUTIVE_MEMBER]: true,
      [UserRole.COMMITTEE_DIRECTOR]: true,
    },
  },
  {
    label: 'إغلاق التصويت ونشر القرار',
    code: 'voting.finalize',
    roles: {
      [UserRole.COMMITTEE_DIRECTOR]: true,
    },
  },
  {
    label: 'إصدار إذن بيان جمركي',
    code: 'customs.issue',
    roles: {
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'عرض التقارير',
    code: 'reports.view',
    roles: {
      [UserRole.BANK_ADMIN]: true,
      [UserRole.SUPPORT_COMMITTEE]: true,
      [UserRole.COMMITTEE_DIRECTOR]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'عرض سجل التدقيق',
    code: 'audit.view',
    roles: {
      [UserRole.BANK_ADMIN]: true,
      [UserRole.SUPPORT_COMMITTEE]: true,
      [UserRole.COMMITTEE_DIRECTOR]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'إدارة التجار',
    code: 'merchants.manage',
    roles: {
      [UserRole.BANK_ADMIN]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'إدارة المستخدمين',
    code: 'users.manage',
    roles: {
      [UserRole.BANK_ADMIN]: true,
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'إدارة البنوك والمراعات',
    code: 'entities.manage',
    roles: {
      [UserRole.CBY_ADMIN]: true,
    },
  },
  {
    label: 'إدارة قواعد المستندات',
    code: 'docrules.manage',
    roles: {
      [UserRole.CBY_ADMIN]: true,
    },
  },
]

// Column order: permission label on right, then roles left-to-right (RTL display reversal handled by CSS)
const ROLE_COLUMNS: UserRole[] = [
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
  UserRole.SWIFT_OFFICER,
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

// Short display labels for matrix columns (space-constrained)
const MATRIX_ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'موظف إدخال البنك التجاري',
  [UserRole.BANK_REVIEWER]: 'مراجع داخلي بالبنك التجاري',
  [UserRole.BANK_ADMIN]: 'مسؤول البنك التجاري',
  [UserRole.SWIFT_OFFICER]: 'موظف السويفت',
  [UserRole.SUPPORT_COMMITTEE]: 'عضو اللجنة المساندة',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو اللجنة التنفيذية',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة',
  [UserRole.CBY_ADMIN]: 'مسؤول النظام (CBY)',
}
</script>

<template>
  <div class="page" dir="rtl">
    <!-- Breadcrumbs -->
    <nav class="breadcrumbs" aria-label="breadcrumb">
      <span class="breadcrumb-item">الرئيسية</span>
      <span class="breadcrumb-sep">/</span>
      <span class="breadcrumb-item breadcrumb-active">الأدوار والصلاحيات</span>
    </nav>

    <!-- Page header -->
    <div class="page-title-row">
      <div class="page-title-group">
        <h1 class="page-title">مصفوفة الأدوار والصلاحيات</h1>
        <p class="page-subtitle">تكون صلاحيات كل دور — التغييرات تلقائية وتُسجّل في سجل التدقيق</p>
      </div>
      <span class="read-only-badge">قراءة فقط</span>
    </div>

    <!-- Matrix card — horizontally scrollable -->
    <div class="card">
      <div class="matrix-scroll">
        <table class="matrix-table" role="grid" aria-label="مصفوفة الأدوار والصلاحيات">
          <thead>
            <tr>
              <th class="perm-col-header" scope="col">الصلاحيات</th>
              <th
                v-for="role in ROLE_COLUMNS"
                :key="role"
                class="role-col-header"
                scope="col"
                :data-role="role"
              >
                {{ MATRIX_ROLE_LABELS[role] }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="perm in PERMISSION_MATRIX" :key="perm.code" :data-permission="perm.code">
              <td class="perm-cell">
                <div class="perm-row">
                  <span class="perm-label">{{ perm.label }}</span>
                  <span class="perm-code">{{ perm.code }}</span>
                </div>
              </td>
              <td
                v-for="role in ROLE_COLUMNS"
                :key="role"
                class="check-cell"
              >
                <div class="check-wrap">
                  <input
                    type="checkbox"
                    class="perm-checkbox"
                    :checked="perm.roles[role] === true"
                    disabled
                    :aria-label="`${perm.label} — ${ROLE_LABELS[role]}`"
                  >
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6c757d;
}

.breadcrumb-sep {
  color: #cccccc;
}

.breadcrumb-active {
  color: #1c222b;
  font-weight: 500;
}

.page-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.page-title-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
}

.read-only-badge {
  display: inline-block;
  padding: 6px 14px;
  background: #f5f5f7;
  color: #8e8e93;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid #cccccc;
  white-space: nowrap;
  flex-shrink: 0;
}

.card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  overflow: hidden;
}

.matrix-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.matrix-table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
  direction: rtl;
}

/* Header row */
.perm-col-header {
  background: #f5f5f7;
  color: #6c757d;
  font-weight: 500;
  font-size: 13px;
  padding: 14px 16px;
  text-align: right;
  border-bottom: 1px solid #cccccc;
  min-width: 220px;
  position: sticky;
  right: 0;
  z-index: 2;
}

.role-col-header {
  background: #f5f5f7;
  color: #6c757d;
  font-weight: 500;
  font-size: 12px;
  padding: 12px 8px;
  text-align: center;
  border-bottom: 1px solid #cccccc;
  min-width: 80px;
  max-width: 100px;
  line-height: 1.4;
  white-space: normal;
  vertical-align: bottom;
}

/* Body rows */
.matrix-table tbody tr:last-child td {
  border-bottom: none;
}

.matrix-table tbody tr:hover {
  background: #fafafa;
}

.perm-cell {
  padding: 12px 16px;
  border-bottom: 1px solid #e8e8ea;
  background: #ffffff;
  position: sticky;
  right: 0;
  z-index: 1;
  vertical-align: middle;
}

.perm-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.perm-label {
  font-size: 14px;
  color: #1c222b;
  white-space: nowrap;
}

.perm-code {
  font-family: monospace;
  font-size: 11px;
  color: #8e8e93;
  background: #f0f0f3;
  padding: 2px 7px;
  border-radius: 6px;
  white-space: nowrap;
}

.check-cell {
  padding: 12px 8px;
  border-bottom: 1px solid #e8e8ea;
  text-align: center;
  vertical-align: middle;
}

.check-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.perm-checkbox {
  width: 18px;
  height: 18px;
  accent-color: #0066cc;
  cursor: default;
}

.perm-checkbox:disabled {
  cursor: default;
  opacity: 1;
}

@media (max-width: 600px) {
  .page-title-row {
    flex-direction: column;
  }
}
</style>
