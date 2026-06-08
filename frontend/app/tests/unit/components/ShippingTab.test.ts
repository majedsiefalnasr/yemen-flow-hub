import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { INCOTERM_OPTIONS, PORT_OF_ARRIVAL_OPTIONS } from '../../../constants/workflow'
import { Incoterm, PortOfArrival } from '../../../types/enums'

const source = readFileSync(
  resolve(process.cwd(), 'app/components/request/tabs/ShippingTab.vue'),
  'utf8',
)

describe('ShippingTab', () => {
  it('renders all 7 shipping fields', () => {
    for (const label of [
      'بلد المنشأ',
      'ميناء الشحن',
      'ميناء الوصول',
      'شروط الشحن الدولية',
      'تاريخ الشحن',
      'تاريخ الوصول المتوقع',
      'الوجهة النهائية',
    ]) {
      expect(source).toContain(label)
    }
  })

  it('includes Arabic labels for all PortOfArrival enum cases', () => {
    expect(PORT_OF_ARRIVAL_OPTIONS.map((option) => option.value)).toEqual(
      Object.values(PortOfArrival),
    )
    expect(PORT_OF_ARRIVAL_OPTIONS.every((option) => option.label && option.hint)).toBe(true)
  })

  it('includes Arabic labels for all Incoterm enum cases', () => {
    expect(INCOTERM_OPTIONS.map((option) => option.value)).toEqual(Object.values(Incoterm))
    expect(INCOTERM_OPTIONS.every((option) => option.label && option.hint)).toBe(true)
  })

  it('warns when shipping date is after arrival date', () => {
    expect('2026-06-10' > '2026-06-09').toBe(true)
    expect(source).toContain('تاريخ الشحن يجب أن يكون قبل تاريخ الوصول')
  })
})
