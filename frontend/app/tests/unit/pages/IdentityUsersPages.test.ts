import { describe, expect, it } from 'vitest'
import { existsSync, readFileSync } from 'node:fs'
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

  it('keeps the committee route and removes the dead bank wrapper route', () => {
    expect(readFileSync(resolve(process.cwd(), 'app/pages/admin/staff.vue'), 'utf8')).toContain(
      'audience="committee"',
    )
    expect(existsSync(resolve(process.cwd(), 'app/pages/bank/users.vue'))).toBe(false)
  })
})
