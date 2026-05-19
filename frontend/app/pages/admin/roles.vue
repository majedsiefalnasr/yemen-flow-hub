<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { UserRole } from '../../types/enums'
import { ROLE_LABELS } from '../../constants/workflow'
import type { User } from '../../types/models'
import { useUsers } from '../../composables/useUsers'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

const { fetchUsers } = useUsers()
const users = ref<User[]>([])

interface RoleDefinition {
  role: UserRole
  description: string
  permissions: string[]
}

const ROLE_DEFINITIONS: RoleDefinition[] = [
  {
    role: UserRole.DATA_ENTRY,
    description: 'موظف إدخال البيانات في البنك — يُنشئ الطلبات ويرفع المستندات',
    permissions: ['إنشاء طلب تمويل', 'تعديل المسودات', 'رفع المستندات', 'تقديم الطلب', 'عرض طلباته'],
  },
  {
    role: UserRole.BANK_REVIEWER,
    description: 'مراجع البنك — يراجع الطلبات المقدمة ويوافق أو يرفض',
    permissions: ['عرض طلبات البنك', 'الموافقة على الطلب', 'رفض الطلب وإعادته'],
  },
  {
    role: UserRole.BANK_ADMIN,
    description: 'مسؤول البنك — يدير موظفي البنك والتجار والطلبات',
    permissions: ['إدارة موظفي البنك', 'إدارة التجار', 'عرض جميع طلبات البنك', 'إنشاء طلبات نيابةً عن التجار'],
  },
  {
    role: UserRole.SWIFT_OFFICER,
    description: 'مسؤول SWIFT — يرفع رسائل SWIFT للطلبات المعتمدة',
    permissions: ['عرض الطلبات المعتمدة من لجنة الدعم', 'رفع رسالة SWIFT'],
  },
  {
    role: UserRole.SUPPORT_COMMITTEE,
    description: 'عضو لجنة الدعم — يراجع الطلبات ضمن آلية المطالبة',
    permissions: ['مطالبة الطلب للمراجعة', 'الموافقة أو الرفض', 'عرض الطلبات في قائمة الانتظار'],
  },
  {
    role: UserRole.EXECUTIVE_MEMBER,
    description: 'عضو اللجنة التنفيذية — يصوّت على الطلبات في جلسات التصويت',
    permissions: ['عرض جلسات التصويت', 'التصويت بالموافقة أو الرفض أو الامتناع'],
  },
  {
    role: UserRole.COMMITTEE_DIRECTOR,
    description: 'مدير اللجنة التنفيذية — يفتح ويغلق جلسات التصويت ويُصدر القرار النهائي',
    permissions: ['فتح جلسة التصويت', 'إغلاق الجلسة', 'الترجيح عند التعادل', 'إصدار القرار التنفيذي'],
  },
  {
    role: UserRole.CBY_ADMIN,
    description: 'مدير النظام في البنك المركزي — يملك صلاحيات إدارة كاملة على المنصة',
    permissions: ['إدارة جميع المستخدمين', 'إدارة البنوك والجهات', 'عرض التقارير الكاملة', 'إعدادات النظام', 'سجل التدقيق الكامل'],
  },
]

const roleUserCounts = computed<Record<UserRole, number>>(() => {
  const counts = Object.fromEntries(
    Object.values(UserRole).map(role => [role, 0]),
  ) as Record<UserRole, number>

  for (const user of users.value) {
    counts[user.role] = (counts[user.role] ?? 0) + 1
  }

  return counts
})

async function loadUsers() {
  try {
    users.value = await fetchUsers()
  }
  catch {
    users.value = []
  }
}

onMounted(loadUsers)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">الأدوار والصلاحيات</h1>
      <span class="read-only-badge">قراءة فقط</span>
    </div>

    <div class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>الدور</th>
            <th>الوصف</th>
            <th>الصلاحيات</th>
            <th>عدد المستخدمين</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="def in ROLE_DEFINITIONS" :key="def.role" :data-role="def.role">
            <td>
              <span class="badge badge-role">{{ ROLE_LABELS[def.role] }}</span>
              <div class="role-enum">{{ def.role }}</div>
            </td>
            <td class="description-cell">{{ def.description }}</td>
            <td>
              <ul class="permissions-list">
                <li v-for="perm in def.permissions" :key="perm">{{ perm }}</li>
              </ul>
            </td>
            <td class="count-cell">{{ roleUserCounts[def.role] }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
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
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  direction: rtl;
}

.data-table th,
.data-table td {
  padding: 16px;
  text-align: right;
  font-size: 14px;
  vertical-align: top;
}

.data-table th {
  background: #f5f5f7;
  color: var(--color-text-secondary);
  font-weight: 500;
  border-bottom: 1px solid var(--color-border);
}

.data-table td {
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-primary);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-role {
  background: #f0f0f3;
  color: #6e6e73;
}

.role-enum {
  font-size: 11px;
  color: #8e8e93;
  font-family: monospace;
  margin-top: 4px;
}

.description-cell {
  max-width: 320px;
  color: var(--color-text-secondary);
  line-height: 1.5;
}

.permissions-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.permissions-list li {
  font-size: 13px;
  color: var(--color-text-secondary);
  padding-right: 12px;
  position: relative;
}

.permissions-list li::before {
  content: '•';
  position: absolute;
  right: 0;
  color: #0066cc;
}

.count-cell {
  font-weight: 600;
  white-space: nowrap;
}
</style>
