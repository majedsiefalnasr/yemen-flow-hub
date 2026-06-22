import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('teams admin page', () => {
  it('renders an organization-scoped table and create dialog behind ScreenGuard', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/teams.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="teams">')
    expect(source).toContain('<Select v-model="selectedOrganization">')
    expect(source).toContain('<Table>')
    expect(source).toContain('<Dialog v-model:open="dialogOpen">')
  })
})
