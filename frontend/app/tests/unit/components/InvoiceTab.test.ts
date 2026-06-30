import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(
  resolve(process.cwd(), 'app/components/request/tabs/InvoiceTab.vue'),
  'utf8',
)

function partialPercentageError(value: number): string | null {
  return value >= 5 && value < 100 ? null : 'النسبة الجزئية يجب أن تكون بين 5% و 100% (غير شاملة)'
}

describe('InvoiceTab', () => {
  it('sets Full coverage percentage to 100 and makes the field readonly/disabled', () => {
    expect(source).toContain('request_percentage:')
    expect(source).toContain("value === CoverageType.FULL ? '100.00'")
    expect(source).toContain('percentageReadonly')
    expect(source).toContain(':readonly="percentageReadonly"')
    expect(source).toContain(':disabled="percentageDisabled"')
    expect(source).toContain('التغطية الكاملة تستلزم نسبة 100%')
  })

  it('validates Partial coverage percentage range', () => {
    expect(partialPercentageError(4.99)).toBe(
      'النسبة الجزئية يجب أن تكون بين 5% و 100% (غير شاملة)',
    )
    expect(partialPercentageError(100)).toBe('النسبة الجزئية يجب أن تكون بين 5% و 100% (غير شاملة)')
    expect(partialPercentageError(5)).toBeNull()
    expect(partialPercentageError(25.5)).toBeNull()
  })

  it('disables percentage input until coverage type is selected', () => {
    expect(source).toContain(
      'const percentageDisabled = computed(() => !props.modelValue.coverage_type)',
    )
    expect(source).toContain('اختر نوع التغطية أولاً')
  })

  it('renders all 11 invoice fields with Arabic labels', () => {
    for (const label of [
      'نوع التغطية',
      'نسبة التمويل %',
      'عملة الطلب',
      'المبلغ المطلوب',
      'نوع الفاتورة',
      'عملة الفاتورة',
      'وحدة القياس',
      'إجمالي مبلغ الفاتورة',
      'البضاعة / السلعة',
      'اسم الشركة المصدِّرة',
      'موقع الشركة المصدِّرة',
    ]) {
      expect(source).toContain(label)
    }
  })
})
