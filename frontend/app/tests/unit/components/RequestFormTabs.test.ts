import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(
  resolve(process.cwd(), 'app/components/request/RequestFormTabs.vue'),
  'utf8',
)

describe('RequestFormTabs', () => {
  it('defines the five tabs in the required order', () => {
    const labels = ['بيانات أساسية', 'بيانات الفاتورة', 'بيانات الشحن', 'الوثائق', 'سجل سير العمل']
    let cursor = 0

    for (const label of labels) {
      const index = source.indexOf(label, cursor)
      expect(index).toBeGreaterThanOrEqual(cursor)
      cursor = index + label.length
    }
  })

  it('starts on the Basic tab and exposes the footer actions', () => {
    expect(source).toContain("const activeTab = ref('basic')")
    expect(source).toContain('← السابق')
    expect(source).toContain('حفظ كمسودة')
    expect(source).toContain('التالي →')
    expect(source).toContain('إرسال للمراجعة')
  })

  it('uses the tab components rather than the legacy wizard', () => {
    expect(source).toContain('BasicInfoTab')
    expect(source).toContain('InvoiceTab')
    expect(source).toContain('ShippingTab')
    expect(source).toContain('DocumentsTab')
    expect(source).toContain('WorkflowHistoryTab')
    expect(source).not.toContain('RequestWizard')
  })
})
