import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('entities governance page', () => {
  it('renders engine bank fields behind ScreenGuard', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/entities.vue'), 'utf8')
    expect(source).toContain('<ScreenGuard screen="banks">')
    expect(source).toContain('license_number')
    expect(source).toContain('swift_code')
    expect(source).toContain('<Table>')
    expect(source).toContain('<Dialog v-model:open="dialogOpen">')
  })
})
