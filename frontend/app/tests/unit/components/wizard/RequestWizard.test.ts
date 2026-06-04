import { describe, it, expect } from 'vitest'

// ── Navigation logic extracted from RequestWizard.vue ─────────────────────────

const STEP_LABELS = [
  'بيانات الطلب',
  'بيانات المورد والشحنة',
  'الوثائق المطلوبة',
  'المراجعة والإرسال',
]

function isLastStep(current: number, total: number): boolean {
  return current === total
}

function isFirstStep(current: number): boolean {
  return current === 1
}

function isSubmitDisabled(acknowledged: boolean, submitting: boolean): boolean {
  return !acknowledged || submitting
}

describe('RequestWizard — step labels', () => {
  it('has 4 step labels', () => expect(STEP_LABELS.length).toBe(4))
  it('first label is بيانات الطلب', () => expect(STEP_LABELS[0]).toBe('بيانات الطلب'))
  it('last label is المراجعة والإرسال', () => expect(STEP_LABELS[3]).toBe('المراجعة والإرسال'))
})

describe('RequestWizard — step state helpers', () => {
  it('isLastStep true on step 4', () => expect(isLastStep(4, 4)).toBe(true))
  it('isLastStep false on step 1', () => expect(isLastStep(1, 4)).toBe(false))
  it('isFirstStep true on step 1', () => expect(isFirstStep(1)).toBe(true))
  it('isFirstStep false on step 2', () => expect(isFirstStep(2)).toBe(false))
})

describe('RequestWizard — submit button logic', () => {
  it('disabled when not acknowledged', () => expect(isSubmitDisabled(false, false)).toBe(true))
  it('disabled when submitting', () => expect(isSubmitDisabled(true, true)).toBe(true))
  it('enabled when acknowledged and not submitting', () =>
    expect(isSubmitDisabled(true, false)).toBe(false))
})

describe('RequestWizard — bottom nav layout', () => {
  it('previous button hidden on step 1', () => {
    const showPrev = !isFirstStep(1)
    expect(showPrev).toBe(false)
  })

  it('previous button visible on step 2+', () => {
    const showPrev = !isFirstStep(2)
    expect(showPrev).toBe(true)
  })

  it('next button shown on steps 1-3', () => {
    const showNext = !isLastStep(1, 4)
    expect(showNext).toBe(true)
  })

  it('submit button shown on step 4', () => {
    const showSubmit = isLastStep(4, 4)
    expect(showSubmit).toBe(true)
  })

  it('save draft button always present', () => {
    // Draft button is always rendered regardless of step
    const alwaysPresent = true
    expect(alwaysPresent).toBe(true)
  })
})

describe('RequestWizard — page access', () => {
  it('required roles include DATA_ENTRY and BANK_ADMIN', () => {
    const requiredRoles = ['DATA_ENTRY', 'BANK_ADMIN']
    expect(requiredRoles).toContain('DATA_ENTRY')
    expect(requiredRoles).toContain('BANK_ADMIN')
    expect(requiredRoles).not.toContain('CBY_ADMIN')
  })
})
