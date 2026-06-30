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
    expect(readFileSync(resolve(process.cwd(), 'app/pages/admin/cby-staff.vue'), 'utf8')).toContain(
      'audience="committee"',
    )
    expect(readFileSync(resolve(process.cwd(), 'app/pages/bank/users.vue'), 'utf8')).toContain(
      'audience="bank"',
    )
  })
})
