import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('reference data admin page', () => {
  const source = readFileSync(resolve(process.cwd(), 'app/pages/admin/reference-data.vue'), 'utf8')

  it('uses ScreenGuard, shadcn table, and create/edit dialogs', () => {
    expect(source).toContain('<ScreenGuard screen="reference_data">')
    expect(source).toContain('<Table>')
    expect(source).toContain('<Dialog v-model:open="tableDialogOpen">')
    expect(source).toContain('<Dialog v-model:open="valueDialogOpen">')
    expect(source).toContain('toTypedSchema')
  })

  it('disables the key field when editing', () => {
    expect(source).toContain(':disabled="Boolean(editingTable)"')
    expect(source).toContain(':disabled="Boolean(editingValue)"')
  })

  it('gates delete affordance for system-protected records', () => {
    expect(source).toContain('v-if="table.is_system"')
    expect(source).toContain('v-if="value.is_system"')
    expect(source).toContain('لا يمكن حذف جدول نظامي')
    expect(source).toContain('لا يمكن حذف قيمة نظامية')
  })
})
