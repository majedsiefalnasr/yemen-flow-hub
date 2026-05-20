import { describe, it, expect } from 'vitest'

// ── File validation logic (mirrors WizardStep3.vue) ───────────────────────────

const MAX_SIZE_MB = 10
const ALLOWED_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg']

function validateFile(file: File): string | null {
  if (!ALLOWED_TYPES.includes(file.type)) {
    return 'يجب أن يكون الملف بصيغة PDF أو JPG'
  }
  if (file.size > MAX_SIZE_MB * 1024 * 1024) {
    return `حجم الملف يتجاوز الحد الأقصى (${MAX_SIZE_MB}MB)`
  }
  return null
}

function makeFile(name: string, type: string, sizeBytes: number): File {
  const blob = new Blob(['x'.repeat(Math.min(sizeBytes, 10))], { type })
  return Object.defineProperties(new File([blob], name, { type }), {
    size: { value: sizeBytes, configurable: true },
  })
}

// ── Upload zones definition ───────────────────────────────────────────────────

const ZONES = [
  { key: 'proforma_invoice', required: true },
  { key: 'commercial_register', required: true },
  { key: 'tax_card', required: true },
  { key: 'extra_docs', required: false },
]

describe('WizardStep3 — zone definitions', () => {
  it('has 4 zones', () => expect(ZONES.length).toBe(4))
  it('first 3 zones are required', () => {
    const required = ZONES.filter(z => z.required)
    expect(required.length).toBe(3)
  })
  it('last zone (extra_docs) is optional', () => {
    expect(ZONES[3]!.required).toBe(false)
  })
})

describe('WizardStep3 — file validation', () => {
  it('accepts PDF file', () => {
    const file = makeFile('invoice.pdf', 'application/pdf', 1024)
    expect(validateFile(file)).toBeNull()
  })

  it('accepts JPG file', () => {
    const file = makeFile('doc.jpg', 'image/jpeg', 1024)
    expect(validateFile(file)).toBeNull()
  })

  it('rejects PNG file', () => {
    const file = makeFile('image.png', 'image/png', 1024)
    expect(validateFile(file)).toBe('يجب أن يكون الملف بصيغة PDF أو JPG')
  })

  it('rejects Word document', () => {
    const file = makeFile('doc.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1024)
    expect(validateFile(file)).toBe('يجب أن يكون الملف بصيغة PDF أو JPG')
  })

  it('rejects file over 10MB', () => {
    const file = makeFile('large.pdf', 'application/pdf', 11 * 1024 * 1024)
    expect(validateFile(file)).toContain('يتجاوز الحد الأقصى')
  })

  it('accepts file exactly at 10MB', () => {
    const file = makeFile('edge.pdf', 'application/pdf', 10 * 1024 * 1024)
    expect(validateFile(file)).toBeNull()
  })

  it('rejects file just over 10MB', () => {
    const file = makeFile('over.pdf', 'application/pdf', 10 * 1024 * 1024 + 1)
    expect(validateFile(file)).toBeTruthy()
  })
})

describe('WizardStep3 — upload state labels', () => {
  const LABELS: Record<string, string> = {
    proforma_invoice: 'الفاتورة الأولية',
    commercial_register: 'السجل التجاري',
    tax_card: 'البطاقة الضريبية',
    extra_docs: 'مستندات إضافية',
  }

  it('all 4 zones have Arabic labels', () => {
    for (const key of Object.keys(LABELS)) {
      expect(LABELS[key]).toBeTruthy()
    }
  })
})
