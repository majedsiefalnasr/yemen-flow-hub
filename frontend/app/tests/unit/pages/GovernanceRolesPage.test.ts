import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('governance roles page', () => {
  it('renders an organization-scoped role list and create dialog', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/roles.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="roles">')
    expect(source).toContain('<Select v-model="selectedOrganization">')
    expect(source).toContain('<Table>')
    expect(source).toContain('<Dialog v-model:open="dialogOpen">')
  })
})
