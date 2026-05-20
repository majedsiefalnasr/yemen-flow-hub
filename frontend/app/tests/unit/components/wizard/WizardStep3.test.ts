import { describe, it, expect } from 'vitest'

const MAX_SIZE_MB = 10
const ALLOWED_TYPES = ['application/pdf']
const ALLOWED_EXTENSIONS = ['.pdf']

function validateFile(file: File): string | null {
  const normalizedName = file.name.toLowerCase()
  const hasAllowedExtension = ALLOWED_EXTENSIONS.some(extension => normalizedName.endsWith(extension))

  if (!ALLOWED_TYPES.includes(file.type) && !hasAllowedExtension) {
    return 'يجب أن يكون الملف بصيغة PDF فقط'
  }

  if (file.size > MAX_SIZE_MB * 1024 * 1024) {
    return `حجم الملف يتجاوز الحد الأقصى (${MAX_SIZE_MB}MB)`
  }

  return null
}

function makeFile(name: string, type: string, sizeBytes: number): File {
  const blob = new Blob(['x'.repeat(Math.min(sizeBytes, 32))], { type })
  return Object.defineProperties(new File([blob], name, { type }), {
    size: { value: sizeBytes, configurable: true },
  })
}

describe('WizardStep3 — accepted file contract', () => {
  it('accepts PDF MIME type', () => {
    const file = makeFile('invoice.pdf', 'application/pdf', 1024)
    expect(validateFile(file)).toBeNull()
  })

  it('accepts .pdf files when the browser leaves MIME type empty', () => {
    const file = makeFile('scan.pdf', '', 1024)
    expect(validateFile(file)).toBeNull()
  })

  it('rejects JPG files', () => {
    const file = makeFile('scan.jpg', 'image/jpeg', 1024)
    expect(validateFile(file)).toBe('يجب أن يكون الملف بصيغة PDF فقط')
  })

  it('rejects files without a PDF MIME type or extension', () => {
    const file = makeFile('manifest.bin', 'application/octet-stream', 1024)
    expect(validateFile(file)).toBe('يجب أن يكون الملف بصيغة PDF فقط')
  })

  it('rejects files over 10MB', () => {
    const file = makeFile('large.pdf', 'application/pdf', 11 * 1024 * 1024)
    expect(validateFile(file)).toContain('يتجاوز الحد الأقصى')
  })
})
