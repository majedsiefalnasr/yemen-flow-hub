import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const NATIONAL_COMMITTEE_AR = 'اللجنة الوطنية لتنظيم وتمويل الواردات'
const NATIONAL_COMMITTEE_EN = 'The National Committee for Regulating & Financing Imports'

function source(relativePath: string): string {
  return readFileSync(new URL(`../../${relativePath}`, import.meta.url), 'utf8')
}

describe('residual frontend brand strings', () => {
  it('updates the reset-password hero identity to match login', () => {
    const resetPasswordSource = source('pages/reset-password.vue')

    expect(resetPasswordSource).toContain('{{ orgStore.authority }}')
    expect(resetPasswordSource).toContain(NATIONAL_COMMITTEE_EN)
    expect(resetPasswordSource).not.toContain(NATIONAL_COMMITTEE_AR)
    expect(resetPasswordSource).not.toContain('البنك المركزي اليمني')
    expect(resetPasswordSource).not.toContain('Central Bank of Yemen')
  })

  it('updates wizard guidance and the CBY admin role subtitle', () => {
    expect(source('components/wizard/WizardStep4.vue')).toContain('وفق لوائح اللجنة الوطنية')
    expect(source('pages/dashboard.vue')).toContain('مسؤول اللجنة الوطنية')
  })

  it('leaves the non-visible AvatarPicker generator seed unchanged', () => {
    expect(source('components/shared/AvatarPicker.vue')).toContain("'Yemen Flow Hub'")
  })
})
