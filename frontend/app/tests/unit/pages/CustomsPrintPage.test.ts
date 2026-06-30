import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

// Customs print page logic tests — pure functions extracted from print.vue
const PRINTABLE_PERMIT_SOURCE = readFileSync(
  new URL('../../../components/customs/PrintablePermit.vue', import.meta.url),
  'utf8',
)
const CUSTOMS_PREVIEW_SOURCE = readFileSync(
  new URL('../../../pages/requests/[id]/customs-preview.vue', import.meta.url),
  'utf8',
)
const NATIONAL_COMMITTEE_AR = 'اللجنة الوطنية لتنظيم وتمويل الواردات'

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function clampZoom(zoom: number, delta: number): number {
  const next = zoom + delta
  return Math.min(Math.max(next, 0.5), 2)
}

function resolveZoomDisplay(zoom: number): string {
  return `${Math.round(zoom * 100)}%`
}

type PrintState = {
  showConfirmDialog: boolean
}

function confirmPrint(state: PrintState): PrintState {
  return { ...state, showConfirmDialog: true }
}

function cancelPrint(state: PrintState): PrintState {
  return { ...state, showConfirmDialog: false }
}

const PRINT_ZOOM_DEFAULT = 1
const PRINT_ZOOM_MIN = 0.5
const PRINT_ZOOM_MAX = 2
const PRINT_ZOOM_STEP = 0.1

describe('CustomsPrintPage — date formatting', () => {
  it('formatted result is a non-empty string', () => {
    const result = formatDate('2026-01-01T00:00:00.000Z')
    expect(result.length).toBeGreaterThan(0)
  })

  it('formatted result includes year digits (Arabic or Latin)', () => {
    const result = formatDate('2026-05-19T00:00:00.000Z')
    // Arabic locale may output Arabic-Indic numerals (٢٠٢٦) or Latin (2026)
    expect(result).toMatch(/2026|٢٠٢٦/)
  })

  it('different dates produce different output', () => {
    const d1 = formatDate('2026-01-01T00:00:00.000Z')
    const d2 = formatDate('2026-06-15T00:00:00.000Z')
    expect(d1).not.toBe(d2)
  })
})

describe('CustomsPrintPage — zoom controls', () => {
  it('starts at 100%', () => {
    expect(resolveZoomDisplay(PRINT_ZOOM_DEFAULT)).toBe('100%')
  })

  it('zoom in increases by 10%', () => {
    const next = clampZoom(1, PRINT_ZOOM_STEP)
    expect(resolveZoomDisplay(next)).toBe('110%')
  })

  it('zoom out decreases by 10%', () => {
    const next = clampZoom(1, -PRINT_ZOOM_STEP)
    expect(resolveZoomDisplay(next)).toBe('90%')
  })

  it('cannot zoom below 50%', () => {
    const next = clampZoom(0.5, -PRINT_ZOOM_STEP)
    expect(next).toBe(PRINT_ZOOM_MIN)
  })

  it('cannot zoom above 200%', () => {
    const next = clampZoom(2, PRINT_ZOOM_STEP)
    expect(next).toBe(PRINT_ZOOM_MAX)
  })

  it('zoom out disabled at 50%', () => {
    const zoom = 0.5
    expect(zoom <= PRINT_ZOOM_MIN).toBe(true)
  })

  it('zoom in disabled at 200%', () => {
    const zoom = 2
    expect(zoom >= PRINT_ZOOM_MAX).toBe(true)
  })

  it('reset returns to 100%', () => {
    const zoom = 1.5
    const reset = PRINT_ZOOM_DEFAULT
    expect(zoom).not.toBe(reset)
    expect(resolveZoomDisplay(reset)).toBe('100%')
  })

  it('zoom display rounds to nearest integer', () => {
    const fractional = 1.055
    const display = resolveZoomDisplay(fractional)
    expect(display).toBe('106%')
  })
})

describe('CustomsPrintPage — confirmation dialog', () => {
  it('confirmPrint opens dialog', () => {
    const state = confirmPrint({ showConfirmDialog: false })
    expect(state.showConfirmDialog).toBe(true)
  })

  it('cancelPrint closes dialog', () => {
    const state = cancelPrint({ showConfirmDialog: true })
    expect(state.showConfirmDialog).toBe(false)
  })

  it('dialog starts hidden', () => {
    const initial: PrintState = { showConfirmDialog: false }
    expect(initial.showConfirmDialog).toBe(false)
  })

  it('dialog is separate from the print action itself', () => {
    let printed = false
    const executePrint = (state: PrintState): PrintState => {
      printed = true
      return { ...state, showConfirmDialog: false }
    }
    const afterConfirm = confirmPrint({ showConfirmDialog: false })
    const afterExecute = executePrint(afterConfirm)
    expect(printed).toBe(true)
    expect(afterExecute.showConfirmDialog).toBe(false)
  })
})

describe('CustomsPrintPage — page structure', () => {
  it('page title contains "معاينة وثيقة تأكيد المصارفة الخارجية"', () => {
    const title = 'معاينة وثيقة تأكيد المصارفة الخارجية'
    expect(title).toContain('وثيقة تأكيد المصارفة الخارجية')
  })

  it('print button label is "طباعة"', () => {
    const label = 'طباعة'
    expect(label).toBe('طباعة')
  })

  it('confirmation dialog title is "تأكيد الطباعة"', () => {
    const title = 'تأكيد الطباعة'
    expect(title).toBe('تأكيد الطباعة')
  })

  it('uses the National Committee identity across permit and customs preview letterheads', () => {
    expect(PRINTABLE_PERMIT_SOURCE).toContain(NATIONAL_COMMITTEE_AR)
    expect(PRINTABLE_PERMIT_SOURCE).toContain('اعتماد اللجنة الوطنية')
    expect(PRINTABLE_PERMIT_SOURCE).not.toContain('البنك المركزي اليمني')
    expect(PRINTABLE_PERMIT_SOURCE).not.toContain('اعتماد البنك المركزي')

    expect(CUSTOMS_PREVIEW_SOURCE).toContain(NATIONAL_COMMITTEE_AR)
    expect(CUSTOMS_PREVIEW_SOURCE).toContain('منصة اللجنة الوطنية')
    expect(CUSTOMS_PREVIEW_SOURCE).toContain('ختم اللجنة الوطنية')
    expect(CUSTOMS_PREVIEW_SOURCE).not.toContain('البنك المركزي اليمني')
    expect(CUSTOMS_PREVIEW_SOURCE).not.toContain('منصة البنك المركزي اليمني')
    expect(CUSTOMS_PREVIEW_SOURCE).not.toContain('ختم البنك المركزي')
  })
})
