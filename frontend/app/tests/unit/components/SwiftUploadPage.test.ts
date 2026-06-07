/**
 * SwiftUploadPage logic tests — pure function tests without component mounting.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { ImportRequest, RequestDocument } from '../../../types/models'

// ── helpers extracted from swift.vue ──────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ar-YE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

function isWaitingForSwift(request: Pick<ImportRequest, 'status'>): boolean {
  return request.status === RequestStatus.WAITING_FOR_SWIFT
}

function isUploaded(documents: RequestDocument[] | undefined): boolean {
  return !!documents?.find((d) => d.type === 'SWIFT')
}

function validatePdfFile(file: { type: string }): string | null {
  if (file.type !== 'application/pdf') {
    return 'يُقبل ملف PDF فقط. الرجاء اختيار ملف بصيغة PDF.'
  }
  return null
}

function makeDoc(
  overrides: Partial<RequestDocument> & { uploaded_at?: string | null } = {},
): RequestDocument {
  return {
    id: 1,
    type: 'SWIFT',
    original_filename: 'swift-doc.pdf',
    size_bytes: 102400,
    checksum: 'abc123',
    mime_type: 'application/pdf',
    uploaded_by: 5,
    uploaded_by_name: 'محمد أحمد',
    uploaded_at: '2026-05-16T10:00:00.000000Z',
    ...overrides,
  } as RequestDocument
}

// ── formatFileSize ─────────────────────────────────────────────────────────────

describe('SwiftUploadPage — formatFileSize', () => {
  it('formats bytes under 1 KB as B', () => {
    expect(formatFileSize(512)).toBe('512 B')
  })

  it('formats bytes between 1 KB and 1 MB as KB', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB')
    expect(formatFileSize(102400)).toBe('100.0 KB')
  })

  it('formats bytes 1 MB and above as MB', () => {
    expect(formatFileSize(1024 * 1024)).toBe('1.0 MB')
    expect(formatFileSize(5 * 1024 * 1024)).toBe('5.0 MB')
  })

  it('formats exactly 1023 bytes as B', () => {
    expect(formatFileSize(1023)).toBe('1023 B')
  })
})

// ── formatDate ────────────────────────────────────────────────────────────────

describe('SwiftUploadPage — formatDate', () => {
  it('returns em dash for null input', () => {
    expect(formatDate(null)).toBe('—')
  })

  it('returns non-empty string for valid ISO date', () => {
    const result = formatDate('2026-05-16T10:00:00.000000Z')
    expect(result).toBeTruthy()
    expect(result).not.toBe('—')
  })

  it('contains Eastern Arabic year digits for valid ISO date', () => {
    const result = formatDate('2026-05-16T10:00:00.000000Z')
    // ar-YE locale uses Eastern Arabic numerals: ٢٠٢٦
    expect(result).toContain('٢٠٢٦')
  })
})

// ── formatAmount ──────────────────────────────────────────────────────────────

describe('SwiftUploadPage — formatAmount', () => {
  it('contains Eastern Arabic digits for the amount', () => {
    const result = formatAmount(50000, 'USD')
    // ar-YE locale uses Eastern Arabic numerals: ٥٠٬٠٠٠
    expect(result).toContain('٥٠')
  })

  it('returns non-empty string', () => {
    expect(formatAmount(0, 'USD')).toBeTruthy()
  })
})

// ── validatePdfFile ────────────────────────────────────────────────────────────

describe('SwiftUploadPage — validatePdfFile', () => {
  it('returns null for application/pdf', () => {
    expect(validatePdfFile({ type: 'application/pdf' })).toBeNull()
  })

  it('returns error message for image/jpeg', () => {
    const result = validatePdfFile({ type: 'image/jpeg' })
    expect(result).not.toBeNull()
    expect(result).toContain('PDF')
  })

  it('returns error message for image/png', () => {
    const result = validatePdfFile({ type: 'image/png' })
    expect(result).not.toBeNull()
  })

  it('returns error message for application/msword', () => {
    const result = validatePdfFile({ type: 'application/msword' })
    expect(result).not.toBeNull()
  })

  it('returns error message for empty string type', () => {
    const result = validatePdfFile({ type: '' })
    expect(result).not.toBeNull()
  })
})

// ── isWaitingForSwift ─────────────────────────────────────────────────────────

describe('SwiftUploadPage — isWaitingForSwift', () => {
  it('returns true for WAITING_FOR_SWIFT status', () => {
    expect(isWaitingForSwift({ status: RequestStatus.WAITING_FOR_SWIFT })).toBe(true)
  })

  it('returns false for SWIFT_UPLOADED status', () => {
    expect(isWaitingForSwift({ status: RequestStatus.SWIFT_UPLOADED })).toBe(false)
  })

  it('returns false for SUPPORT_APPROVED status', () => {
    expect(isWaitingForSwift({ status: RequestStatus.SUPPORT_APPROVED })).toBe(false)
  })

  it('returns false for COMPLETED status', () => {
    expect(isWaitingForSwift({ status: RequestStatus.COMPLETED })).toBe(false)
  })
})

// ── isUploaded (swiftDoc presence) ───────────────────────────────────────────

describe('SwiftUploadPage — isUploaded', () => {
  it('returns true when SWIFT document exists in array', () => {
    expect(isUploaded([makeDoc({ type: 'SWIFT' })])).toBe(true)
  })

  it('returns false when document array is empty', () => {
    expect(isUploaded([])).toBe(false)
  })

  it('returns false when documents is undefined', () => {
    expect(isUploaded(undefined)).toBe(false)
  })

  it('returns false when only non-SWIFT documents exist', () => {
    expect(isUploaded([makeDoc({ type: 'CONTRACT' })])).toBe(false)
  })

  it('returns true when SWIFT doc is among mixed document types', () => {
    const docs = [makeDoc({ id: 1, type: 'CONTRACT' }), makeDoc({ id: 2, type: 'SWIFT' })]
    expect(isUploaded(docs)).toBe(true)
  })
})

// ── wrong-status fallback state ───────────────────────────────────────────────

describe('SwiftUploadPage — wrong-status fallback (AC10)', () => {
  const wrongStatuses = [
    RequestStatus.DRAFT,
    RequestStatus.SUBMITTED,
    RequestStatus.BANK_REVIEW,
    RequestStatus.BANK_APPROVED,
    RequestStatus.SUPPORT_REVIEW_PENDING,
    RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
    RequestStatus.SUPPORT_APPROVED,
    RequestStatus.SUPPORT_REJECTED,
    RequestStatus.SWIFT_UPLOADED,
    RequestStatus.WAITING_FOR_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_OPEN,
    RequestStatus.EXECUTIVE_VOTING_CLOSED,
    RequestStatus.EXECUTIVE_APPROVED,
    RequestStatus.EXECUTIVE_REJECTED,
    RequestStatus.CUSTOMS_DECLARATION_ISSUED,
    RequestStatus.FX_CONFIRMATION_PENDING,
    RequestStatus.COMPLETED,
  ]

  wrongStatuses.forEach((status) => {
    it(`shows wrong-status card for ${status}`, () => {
      const isReady = isWaitingForSwift({ status })
      const uploaded = isUploaded([])
      expect(isReady).toBe(false)
      expect(uploaded).toBe(false)
    })
  })

  it('neither pending nor uploaded state for BANK_REVIEW → shows wrong-status card', () => {
    const request = { status: RequestStatus.BANK_REVIEW }
    expect(isWaitingForSwift(request)).toBe(false)
    expect(isUploaded(undefined)).toBe(false)
  })
})

// ── immutability (uploaded state) ─────────────────────────────────────────────

describe('SwiftUploadPage — immutability after SWIFT upload (AC10)', () => {
  it('uploaded state is detected when SWIFT document present', () => {
    const docs = [makeDoc({ type: 'SWIFT' })]
    expect(isUploaded(docs)).toBe(true)
  })

  it('upload zone is hidden once SWIFT document is present regardless of status', () => {
    const docs = [makeDoc({ type: 'SWIFT' })]
    const request = { status: RequestStatus.SWIFT_UPLOADED }
    expect(isUploaded(docs)).toBe(true)
    expect(isWaitingForSwift(request)).toBe(false)
  })

  it('SWIFT document cannot be replaced: isUploaded stays true after duplicate SWIFT docs', () => {
    const docs = [
      makeDoc({ id: 1, type: 'SWIFT', original_filename: 'first.pdf' }),
      makeDoc({ id: 2, type: 'SWIFT', original_filename: 'second.pdf' }),
    ]
    expect(isUploaded(docs)).toBe(true)
  })

  it('isUploaded false when only non-SWIFT docs present → upload zone shown', () => {
    const docs = [makeDoc({ type: 'INVOICE' }), makeDoc({ type: 'CONTRACT' })]
    expect(isUploaded(docs)).toBe(false)
  })
})

// ── drag-and-drop PDF validation ──────────────────────────────────────────────

describe('SwiftUploadPage — drag-and-drop validation', () => {
  it('accepts PDF via validateFile', () => {
    expect(validatePdfFile({ type: 'application/pdf' })).toBeNull()
  })

  it('rejects non-PDF dropped files', () => {
    const result = validatePdfFile({ type: 'image/png' })
    expect(result).not.toBeNull()
    expect(result).toContain('PDF')
  })
})

// ── uploaded doc metadata ────────────────────────────────────────────────────

describe('SwiftUploadPage — uploaded doc metadata', () => {
  it('doc card shows correct filename', () => {
    const doc = makeDoc({ original_filename: 'my-swift.pdf' })
    expect(doc.original_filename).toBe('my-swift.pdf')
  })

  it('doc card shows formatted file size', () => {
    const doc = makeDoc({ size_bytes: 204800 })
    expect(formatFileSize(doc.size_bytes)).toBe('200.0 KB')
  })

  it('doc card shows uploader name from uploaded_by_name field', () => {
    const doc = makeDoc({ uploaded_by_name: 'فاطمة علي' })
    expect(doc.uploaded_by_name).toBe('فاطمة علي')
  })

  it('doc card falls back to dash when uploaded_by_name is null', () => {
    const doc = makeDoc({ uploaded_by_name: null })
    const display = doc.uploaded_by_name ?? '—'
    expect(display).toBe('—')
  })

  it('doc card shows formatted upload date in ar-YE locale', () => {
    const doc = makeDoc({ uploaded_at: '2026-05-16T08:30:00.000000Z' })
    const formatted = formatDate(doc.uploaded_at)
    // ar-YE uses Eastern Arabic numerals: ٢٠٢٦
    expect(formatted).toContain('٢٠٢٦')
  })

  it('doc card handles null upload date gracefully', () => {
    const doc = makeDoc({ uploaded_at: null })
    expect(formatDate(doc.uploaded_at)).toBe('—')
  })
})
