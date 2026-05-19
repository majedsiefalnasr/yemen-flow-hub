import { describe, it, expect } from 'vitest'

type StepStatus = 'future' | 'active' | 'completed'

// ── Logic extracted from WizardStepper ────────────────────────────────────────

function computeStepStatuses(currentStep: number, total: number): StepStatus[] {
  return Array.from({ length: total }, (_, i) => {
    const n = i + 1
    if (n < currentStep) return 'completed'
    if (n === currentStep) return 'active'
    return 'future'
  })
}

function canClickStep(status: StepStatus): boolean {
  return status === 'completed'
}

describe('WizardStepper — step statuses', () => {
  it('step 1 active, rest future on first load', () => {
    const statuses = computeStepStatuses(1, 4)
    expect(statuses).toEqual(['active', 'future', 'future', 'future'])
  })

  it('step 1 completed, step 2 active after advancing', () => {
    const statuses = computeStepStatuses(2, 4)
    expect(statuses).toEqual(['completed', 'active', 'future', 'future'])
  })

  it('all completed, step 4 active on last step', () => {
    const statuses = computeStepStatuses(4, 4)
    expect(statuses).toEqual(['completed', 'completed', 'completed', 'active'])
  })

  it('step labels count matches total steps', () => {
    const STEPS = ['بيانات الطلب', 'بيانات المورد', 'الوثائق', 'المراجعة']
    expect(STEPS.length).toBe(4)
  })
})

describe('WizardStepper — click behaviour', () => {
  it('completed step is clickable', () => {
    expect(canClickStep('completed')).toBe(true)
  })

  it('active step is not clickable', () => {
    expect(canClickStep('active')).toBe(false)
  })

  it('future step is not clickable', () => {
    expect(canClickStep('future')).toBe(false)
  })
})
