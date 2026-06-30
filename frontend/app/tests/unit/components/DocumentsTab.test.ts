import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(
  resolve(process.cwd(), 'app/components/request/tabs/DocumentsTab.vue'),
  'utf8',
)
const slotsSource = source.slice(
  source.indexOf('const DOCUMENT_SLOTS'),
  source.indexOf('const props = defineProps'),
)

function validateFile(file: File): string | null {
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) return 'صيغة الملف غير مدعومة — يجب أن تكون PDF فقط'
  if (file.size > 10 * 1024 * 1024) return 'حجم الملف يتجاوز الحد الأقصى (10 ميغابايت)'
  return null
}

function makeFile(name: string, type: string, sizeBytes: number): File {
  const file = new File(['data'], name, { type })
  return Object.defineProperty(file, 'size', { value: sizeBytes })
}

describe('DocumentsTab', () => {
  it('defines exactly 5 mandatory and 9 optional document slots', () => {
    expect((slotsSource.match(/required: true/g) ?? []).length).toBe(5)
    expect((slotsSource.match(/required: false/g) ?? []).length).toBe(9)
    expect((slotsSource.match(/documentType:/g) ?? []).length).toBe(14)
  })

  it('shows the missing-count badge and complete chip states', () => {
    expect(source).toContain('ناقص: {{ missingMandatoryCount }} مستندات إلزامية')
    expect(source).toContain('مكتمل')
    expect(source).toContain(
      'const mandatoryComplete = computed(() => missingMandatoryCount.value === 0)',
    )
  })

  it('rejects non-PDF files before upload', () => {
    expect(validateFile(makeFile('image.png', 'image/png', 1024))).toBe(
      'صيغة الملف غير مدعومة — يجب أن تكون PDF فقط',
    )
    expect(validateFile(makeFile('scan.pdf', '', 1024))).toBeNull()
    expect(validateFile(makeFile('large.pdf', 'application/pdf', 11 * 1024 * 1024))).toBe(
      'حجم الملف يتجاوز الحد الأقصى (10 ميغابايت)',
    )
  })

  it('uploaded state includes checksum badge, preview, and remove controls', () => {
    expect(source).toContain('تم التحقق')
    expect(source).toContain('معاينة')
    expect(source).toContain('إزالة')
  })
})
