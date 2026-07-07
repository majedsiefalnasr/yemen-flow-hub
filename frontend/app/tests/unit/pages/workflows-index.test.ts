import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('workflows index page', () => {
  it('passes search query param to load instead of client filtering', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/pages/workflows/index.vue'), 'utf8')

    expect(source).not.toContain('filteredRows')
    expect(source).toContain('search:')
    expect(source).toContain('loadStats')
    expect(source).toContain('page-count')
    expect(source).toContain('store.stats')
  })
})
