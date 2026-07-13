import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('staff page route permissions', () => {
  it('uses staff VIEW screen middleware instead of a BANK_ADMIN route guard', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/staff.vue'), 'utf8')

    expect(source).toContain("middleware: ['auth', 'screen']")
    expect(source).toContain("requiredScreen: 'staff'")
    expect(source).toContain("requiredCapability: 'VIEW'")
    expect(source).not.toContain('requiredRoles: [UserRole.BANK_ADMIN]')
  })
})
