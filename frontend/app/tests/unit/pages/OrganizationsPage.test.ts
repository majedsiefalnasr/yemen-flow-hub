import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('organizations admin page', () => {
  it('uses ScreenGuard, shadcn table, and a create/edit dialog', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/orgs.vue'), 'utf8')

    expect(source).toContain('<ScreenGuard screen="organizations">')
    expect(source).toContain('<Table>')
    expect(source).toContain('<Dialog v-model:open="dialogOpen">')
    expect(source).toContain('toTypedSchema')
  })
})
