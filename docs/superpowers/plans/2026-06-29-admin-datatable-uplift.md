# Admin Pages DataTable Uplift

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign 3 admin pages (entities, roles, cby-staff/IdentityUsersPage) from plain shadcn Table to the full DataTable pattern established by teams.vue and merchants.vue.

**Architecture:** Each page gets: TanStack `useVueTable` + `DataTable` wrapper component with `DataTableToolbar` (search + faceted filters + column visibility + export), `DataTablePagination`, `DataTableRowActions`, `MetricGrid`/`MetricCard` KPI strip, proper `<Empty>` states, and `<Alert>` error with retry. Composables that lack `loading`/`error` refs get them added. Existing create/edit Dialog logic is preserved but upgraded to use `FormField`/`FormItem`/`FormLabel`/`FormControl`/`FormMessage` pattern.

**Tech Stack:** Vue 4 + TanStack Vue Table + shadcn-vue DataTable sub-components + VeeValidate + Zod

## Global Constraints

- All imports from `@/components/ui/data-table` barrel
- RTL-first, Arabic labels
- `ScreenGuard` wraps every page
- `PageHeader` with `breadcrumbs` prop
- Semantic color tokens only (no raw Tailwind colors)
- `AlertDialog` for destructive confirmations (deactivate/delete)
- `DataTableRowActions` for row-level actions (not inline Button clusters)
- Existing API endpoints unchanged — only frontend redesign
- Tests use source-read pattern (same as TeamsPage.test.ts)
- `useTableExport` for CSV/Excel/JSON export

## File Structure

### Modified files:
1. `app/composables/useGovernanceBanks.ts` — add `loading`, `error`, `updateBank`, `setBankActive`
2. `app/composables/useGovernanceRoles.ts` — add `loading`, `error`, `updateRole`, `setRoleActive`, `deleteRole`
3. `app/composables/useIdentityUsers.ts` — add `loading`, `error`
4. `app/pages/admin/entities.vue` — full rewrite to DataTable pattern
5. `app/pages/admin/roles.vue` — full rewrite to DataTable pattern
6. `app/components/admin/IdentityUsersPage.vue` — full rewrite to DataTable pattern
7. `app/tests/unit/pages/EntitiesGovernancePage.test.ts` — update assertions for DataTable
8. `app/tests/unit/pages/GovernanceRolesPage.test.ts` — update assertions for DataTable
9. `app/tests/unit/pages/IdentityUsersPages.test.ts` — update assertions for DataTable

### Unchanged files:
- `app/pages/admin/cby-staff.vue` — thin wrapper, stays as-is
- `app/pages/bank/users.vue` — thin wrapper, stays as-is

---

### Task 1: Upgrade `useGovernanceBanks` composable

**Files:**
- Modify: `app/composables/useGovernanceBanks.ts`

**Interfaces:**
- Consumes: `useApi()` from `@/composables/useApi`, `Bank` from `@/types/models`
- Produces: `{ banks, loading, error, fetchBanks, createBank, updateBank, setBankActive }` — used by Task 4 (entities page)

- [ ] **Step 1: Add loading/error refs and try/catch to fetchBanks**

```ts
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { Bank } from '@/types/models'

export function useGovernanceBanks() {
  const api = useApi()
  const banks = ref<Bank[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchBanks = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: Bank[] }>('/api/v1/banks')
      banks.value = response.data
    } catch (cause: unknown) {
      banks.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل البنوك.')
    } finally {
      loading.value = false
    }
  }

  const createBank = async (payload: {
    code: string
    name: string
    license_number?: string
    swift_code?: string
    status: 'ACTIVE' | 'SUSPENDED'
  }) => {
    const response = await api.post<{ data: Bank }>('/api/v1/banks', payload)
    banks.value = [response.data, ...banks.value]
    return response.data
  }

  const updateBank = async (
    bank: Bank,
    payload: { name: string; license_number?: string; swift_code?: string },
  ) => {
    const response = await api.put<{ data: Bank }>(`/api/v1/banks/${bank.id}`, {
      ...payload,
      version: bank.version,
    })
    banks.value = banks.value.map((b) => (b.id === response.data.id ? response.data : b))
    return response.data
  }

  const setBankActive = async (bank: Bank, active: boolean) => {
    const response = await api.post<{ data: Bank }>(
      `/api/v1/banks/${bank.id}/${active ? 'activate' : 'deactivate'}`,
    )
    banks.value = banks.value.map((b) => (b.id === response.data.id ? response.data : b))
    return response.data
  }

  return { banks, loading, error, fetchBanks, createBank, updateBank, setBankActive }
}
```

- [ ] **Step 2: Verify no typecheck regression**

Run: `cd frontend && pnpm exec tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors related to useGovernanceBanks

---

### Task 2: Upgrade `useGovernanceRoles` composable

**Files:**
- Modify: `app/composables/useGovernanceRoles.ts`

**Interfaces:**
- Consumes: `useApi()`, `GovernanceRole` from `@/types/models`
- Produces: `{ roles, loading, error, fetchRoles, createRole, updateRole, setRoleActive, deleteRole }` — used by Task 5 (roles page)

- [ ] **Step 1: Full rewrite with loading/error/CRUD**

```ts
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import type { GovernanceRole } from '@/types/models'

export function useGovernanceRoles() {
  const api = useApi()
  const roles = ref<GovernanceRole[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchRoles = async (organizationId?: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: GovernanceRole[] }>('/api/v1/roles', {
        query: organizationId ? { organization_id: organizationId } : {},
      })
      roles.value = response.data
    } catch (cause: unknown) {
      roles.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الأدوار.')
    } finally {
      loading.value = false
    }
  }

  const createRole = async (payload: { organization_id: number; code: string; name: string }) => {
    const response = await api.post<{ data: GovernanceRole }>('/api/v1/roles', payload)
    roles.value = [response.data, ...roles.value]
    return response.data
  }

  const updateRole = async (role: GovernanceRole, payload: { name: string }) => {
    const response = await api.put<{ data: GovernanceRole }>(`/api/v1/roles/${role.id}`, {
      ...payload,
      version: role.version,
    })
    roles.value = roles.value.map((r) => (r.id === response.data.id ? response.data : r))
    return response.data
  }

  const setRoleActive = async (role: GovernanceRole, active: boolean) => {
    const response = await api.post<{ data: GovernanceRole }>(
      `/api/v1/roles/${role.id}/${active ? 'activate' : 'deactivate'}`,
    )
    roles.value = roles.value.map((r) => (r.id === response.data.id ? response.data : r))
    return response.data
  }

  const deleteRole = async (role: GovernanceRole) => {
    await api.del(`/api/v1/roles/${role.id}`)
    roles.value = roles.value.filter((r) => r.id !== role.id)
  }

  return { roles, loading, error, fetchRoles, createRole, updateRole, setRoleActive, deleteRole }
}
```

- [ ] **Step 2: Verify no typecheck regression**

Run: `cd frontend && pnpm exec tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors related to useGovernanceRoles

---

### Task 3: Upgrade `useIdentityUsers` composable

**Files:**
- Modify: `app/composables/useIdentityUsers.ts`

**Interfaces:**
- Consumes: `useApi()`, `GovernanceUser` from `@/types/models`
- Produces: `{ users, loading, error, fetchUsers, createUser, deactivateUser, resetPassword, resetMfa }` — used by Task 6 (IdentityUsersPage)

- [ ] **Step 1: Add loading/error refs and try/catch to fetchUsers**

Wrap `fetchUsers` in loading/error pattern (same as useTeams). Keep `createUser`, `deactivateUser`, `resetPassword`, `resetMfa` unchanged — they throw on failure and callers handle via toast.

```ts
const loading = ref(false)
const error = ref<string | null>(null)

const fetchUsers = async (filters: Record<string, string | number> = {}) => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get<{ data: GovernanceUser[] }>('/api/v1/users', { query: filters })
    users.value = response.data
  } catch (cause: unknown) {
    users.value = []
    error.value = extractApiErrorMessage(cause, 'تعذر تحميل المستخدمين.')
  } finally {
    loading.value = false
  }
}
```

Add `loading` and `error` to the return statement.

- [ ] **Step 2: Verify no typecheck regression**

Run: `cd frontend && pnpm exec tsc --noEmit --pretty 2>&1 | head -20`

---

### Task 4: Redesign `admin/entities.vue` (Banks page)

**Files:**
- Modify: `app/pages/admin/entities.vue` (full rewrite)
- Modify: `app/tests/unit/pages/EntitiesGovernancePage.test.ts`

**Interfaces:**
- Consumes: `useGovernanceBanks()` from Task 1, `useTableExport()`, `useOrganizations()`
- Produces: Standalone page, no downstream consumers

Pattern source: `admin/teams.vue` (744 lines). Banks page is structurally identical — entity list with code/name/org/status columns, create/edit dialog, activate/deactivate toggle, delete confirmation.

- [ ] **Step 1: Rewrite entities.vue**

Full DataTable rewrite with:
- `PageHeader` with breadcrumbs `[{ label: 'الرئيسية', to: '/' }, { label: 'البنوك' }]`
- `MetricGrid` with 3 cards: total / active / suspended
- `DataTable` with columns: select (checkbox), bank name+code composite cell, license, SWIFT code, status, organization, actions
- `DataTableToolbar` with search, status faceted filter, column visibility, export
- `DataTableRowActions` with edit/activate/deactivate/delete
- `DataTablePagination`
- Create/edit `Dialog` with `FormField` pattern (code, name, license_number, swift_code, organization select)
- `AlertDialog` for delete confirmation
- `Alert` error state with retry
- `Empty` state for zero results

Column definitions:
```ts
const columns: ColumnDef<Bank>[] = [
  // select checkbox
  // bank (composite: name + code)
  // license_number
  // swift_code
  // organization (accessorFn: row => row.organization?.name ?? '—')
  // status (filterFn for ACTIVE/SUSPENDED, activeStatusCell render)
  // actions (DataTableRowActions)
]
```

- [ ] **Step 2: Update test**

```ts
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('entities governance page', () => {
  it('renders a DataTable with bank fields behind ScreenGuard', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/entities.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="banks">')
    expect(source).toContain('license_number')
    expect(source).toContain('swift_code')
    expect(source).toContain('<DataTable')
    expect(source).toContain('<DataTableToolbar')
    expect(source).toContain('<DataTablePagination')
    expect(source).toContain('MetricGrid')
    expect(source).toContain('<Dialog')
  })
})
```

- [ ] **Step 3: Run test**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/pages/EntitiesGovernancePage.test.ts`
Expected: PASS

- [ ] **Step 4: Lint touched files**

Run: `cd frontend && pnpm exec eslint app/pages/admin/entities.vue --fix`

---

### Task 5: Redesign `admin/roles.vue`

**Files:**
- Modify: `app/pages/admin/roles.vue` (full rewrite)
- Modify: `app/tests/unit/pages/GovernanceRolesPage.test.ts`

**Interfaces:**
- Consumes: `useGovernanceRoles()` from Task 2, `useOrganizations()`, `useTableExport()`
- Produces: Standalone page, no downstream consumers

- [ ] **Step 1: Rewrite roles.vue**

Full DataTable rewrite with:
- `PageHeader` with breadcrumbs `[{ label: 'الرئيسية', to: '/' }, { label: 'الأدوار' }]`
- Organization select filter above DataTable (same as teams.vue pattern)
- `MetricGrid` with 3 cards: total / active / inactive
- `DataTable` with columns: select, role name+code composite, organization, status, actions
- `DataTableToolbar` with search, status faceted filter, column visibility, export
- `DataTableRowActions` with edit/activate/deactivate/delete
- `DataTablePagination`
- Create/edit `Dialog` with `FormField` pattern (organization_id select, code, name)
- `AlertDialog` for delete confirmation
- `Alert` error state with retry
- `Empty` state

- [ ] **Step 2: Update test**

```ts
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('governance roles page', () => {
  it('renders DataTable with organization filter and create dialog', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/roles.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="roles">')
    expect(source).toContain('<Select v-model="selectedOrgFilter">')
    expect(source).toContain('<DataTable')
    expect(source).toContain('<DataTableToolbar')
    expect(source).toContain('<DataTablePagination')
    expect(source).toContain('MetricGrid')
    expect(source).toContain('<Dialog')
  })
})
```

- [ ] **Step 3: Run test**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/pages/GovernanceRolesPage.test.ts`
Expected: PASS

- [ ] **Step 4: Lint touched files**

Run: `cd frontend && pnpm exec eslint app/pages/admin/roles.vue --fix`

---

### Task 6: Redesign `IdentityUsersPage.vue`

**Files:**
- Modify: `app/components/admin/IdentityUsersPage.vue` (full rewrite)
- Modify: `app/tests/unit/pages/IdentityUsersPages.test.ts`

**Interfaces:**
- Consumes: `useIdentityUsers()` from Task 3, `useOrganizations()`, `useTeams()`, `useGovernanceRoles()`, `useGovernanceBanks()`, `useAuthStore()`, `useTableExport()`
- Produces: Used by `admin/cby-staff.vue` (audience="committee") and `bank/users.vue` (audience="bank")

This is the most complex page — cascading org→team/role selects in the create dialog, bank field conditional on org type, row actions include reset password + reset MFA + deactivate.

- [ ] **Step 1: Rewrite IdentityUsersPage.vue**

Full DataTable rewrite with:
- `PageHeader` with breadcrumbs, audience-aware title
- `MetricGrid` with 3 cards: total users / active / inactive (based on `is_active`)
- `DataTable` with columns: select, user name+email composite, organization, team, role, bank (hidden if audience="committee"), status (is_active), actions
- `DataTableToolbar` with search (name/email), status faceted filter (active/inactive), column visibility, export
- `DataTableRowActions` with: reset password, reset MFA, deactivate (destructive, with AlertDialog confirm, hidden for self)
- `DataTablePagination`
- Create `Dialog` with `FormField` pattern preserving cascading selects (org→team+role, bank conditional)
- `Alert` error state with retry
- `Empty` state

Row actions array:
```ts
const userActions: RowAction<GovernanceUser>[] = [
  { label: 'إعادة كلمة المرور', onClick: (row) => onResetPassword(row.original) },
  { label: 'إعادة MFA', onClick: (row) => onResetMfa(row.original) },
  {
    label: 'إيقاف',
    destructive: true,
    hidden: (row) => isSelf(row.original),
    confirm: {
      title: 'تأكيد إيقاف المستخدم',
      description: 'سيتم إيقاف حساب المستخدم وإنهاء جلساته الحالية.',
      confirmLabel: 'تأكيد الإيقاف',
    },
    onClick: (row) => onDeactivate(row.original),
  },
]
```

- [ ] **Step 2: Update test**

```ts
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('identity user management pages', () => {
  it('uses DataTable with cascading selects and ScreenGuard', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'app/components/admin/IdentityUsersPage.vue'),
      'utf8',
    )
    expect(source).toContain('<ScreenGuard screen="users">')
    expect(source).toContain('watch(organizationId')
    expect(source).toContain('v-if="bankRequired"')
    expect(source).toContain('resetPassword')
    expect(source).toContain('resetMfa')
    expect(source).toContain('<DataTable')
    expect(source).toContain('<DataTableToolbar')
    expect(source).toContain('<DataTablePagination')
    expect(source).toContain('MetricGrid')
  })

  it('exposes committee and bank routes', () => {
    expect(
      readFileSync(resolve(process.cwd(), 'app/pages/admin/cby-staff.vue'), 'utf8'),
    ).toContain('audience="committee"')
    expect(readFileSync(resolve(process.cwd(), 'app/pages/bank/users.vue'), 'utf8')).toContain(
      'audience="bank"',
    )
  })
})
```

- [ ] **Step 3: Run test**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/pages/IdentityUsersPages.test.ts`
Expected: PASS

- [ ] **Step 4: Lint touched files**

Run: `cd frontend && pnpm exec eslint app/components/admin/IdentityUsersPage.vue --fix`

---

### Task 7: Final verification

**Files:** All modified files from Tasks 1-6

- [ ] **Step 1: Run all 3 page tests together**

Run: `cd frontend && pnpm exec vitest run app/tests/unit/pages/EntitiesGovernancePage.test.ts app/tests/unit/pages/GovernanceRolesPage.test.ts app/tests/unit/pages/IdentityUsersPages.test.ts`
Expected: All PASS

- [ ] **Step 2: Run typecheck**

Run: `cd frontend && pnpm typecheck`
Expected: No new errors

- [ ] **Step 3: Lint all touched files**

Run: `cd frontend && pnpm exec eslint app/composables/useGovernanceBanks.ts app/composables/useGovernanceRoles.ts app/composables/useIdentityUsers.ts app/pages/admin/entities.vue app/pages/admin/roles.vue app/components/admin/IdentityUsersPage.vue --fix`

- [ ] **Step 4: Format check**

Run: `cd frontend && pnpm exec prettier app/composables/useGovernanceBanks.ts app/composables/useGovernanceRoles.ts app/composables/useIdentityUsers.ts app/pages/admin/entities.vue app/pages/admin/roles.vue app/components/admin/IdentityUsersPage.vue --write`
