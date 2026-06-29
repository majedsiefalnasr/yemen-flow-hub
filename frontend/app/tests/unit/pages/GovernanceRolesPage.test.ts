import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('governance roles page', () => {
  it('renders DataTable with organization filter and create dialog', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/roles.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="roles">')
    expect(source).toContain('selectedOrgFilter')
    expect(source).toContain('<DataTable')
    expect(source).toContain('<DataTableToolbar')
    expect(source).toContain('<DataTablePagination')
    expect(source).toContain('MetricGrid')
    expect(source).toContain('<Dialog')
  })
})
