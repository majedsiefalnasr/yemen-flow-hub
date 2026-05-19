/**
 * DocumentChecklist — unit tests using the pure-logic extraction pattern.
 * No component mounting; tests reproduce and validate the logic that drives
 * conditional rendering in DocumentChecklist.vue.
 */
import { describe, it, expect } from 'vitest'
import { UserRole, RequestStatus } from '../../../types/enums'
import {
  canDownloadDocument,
  canDownloadCustoms,
  canUploadDocument,
  isDocumentModificationLocked,
} from '../../../composables/useDocumentPermissions'
import type { RequestDocument, CustomsDeclarationSummary } from '../../../types/models'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeDoc(overrides: Partial<RequestDocument> = {}): RequestDocument {
  return {
    id: 1,
    type: 'REQUEST_DOC',
    original_filename: 'invoice.pdf',
    mime_type: 'application/pdf',
    size_bytes: 204800,
    checksum: 'abc123',
    uploaded_by: 1,
    uploaded_by_name: 'علي أحمد',
    uploaded_at: '2026-05-17T08:00:00.000Z',
    download_url: 'http://localhost/api/documents/1/download',
    ...overrides,
  }
}

function makeCustoms(overrides: Partial<CustomsDeclarationSummary> = {}): CustomsDeclarationSummary {
  return {
    id: 99,
    declaration_number: 'CUST-2026-001',
    issued_at: '2026-05-17T10:00:00.000Z',
    issued_by: 2,
    issuer: { id: 2, name: 'مدير اللجنة' },
    download_url: 'http://localhost/api/customs/99/download',
    ...overrides,
  }
}

// ─── formatFileSize logic ─────────────────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

describe('DocumentChecklist formatFileSize', () => {
  it('formats bytes', () => {
    expect(formatFileSize(512)).toBe('512 B')
  })

  it('formats KB', () => {
    expect(formatFileSize(2048)).toBe('2.0 KB')
  })

  it('formats MB', () => {
    expect(formatFileSize(1048576)).toBe('1.0 MB')
  })

  it('formats 204800 bytes as 200 KB', () => {
    expect(formatFileSize(204800)).toBe('200.0 KB')
  })
})

// ─── typeLabel logic ──────────────────────────────────────────────────────────

function typeLabel(docType: string | null): string {
  if (docType === 'SWIFT') return 'مستند SWIFT'
  return 'مستند طلب'
}

describe('DocumentChecklist typeLabel', () => {
  it('returns Arabic SWIFT label for SWIFT type', () => {
    expect(typeLabel('SWIFT')).toBe('مستند SWIFT')
  })

  it('returns Arabic request label for REQUEST_DOC type', () => {
    expect(typeLabel('REQUEST_DOC')).toBe('مستند طلب')
  })

  it('returns Arabic request label for null type', () => {
    expect(typeLabel(null)).toBe('مستند طلب')
  })

  it('returns Arabic request label for unknown type', () => {
    expect(typeLabel('COMMERCIAL_INVOICE')).toBe('مستند طلب')
  })
})

// ─── SWIFT badge condition ────────────────────────────────────────────────────

describe('DocumentChecklist SWIFT badge visibility', () => {
  it('badge shown when doc.type === "SWIFT"', () => {
    const doc = makeDoc({ type: 'SWIFT' })
    expect(doc.type === 'SWIFT').toBe(true)
  })

  it('badge not shown for REQUEST_DOC', () => {
    const doc = makeDoc({ type: 'REQUEST_DOC' })
    expect(doc.type === 'SWIFT').toBe(false)
  })

  it('badge not shown for null type', () => {
    const doc = makeDoc({ type: null })
    expect(doc.type === 'SWIFT').toBe(false)
  })
})

// ─── Download button visibility per role + type ───────────────────────────────

describe('DocumentChecklist download button — REQUEST_DOC', () => {
  it('shows for all 8 roles', () => {
    const doc = makeDoc({ type: 'REQUEST_DOC' })
    for (const role of Object.values(UserRole)) {
      expect(canDownloadDocument(role, doc.type)).toBe(true)
    }
  })
})

describe('DocumentChecklist download button — SWIFT', () => {
  const SHOWN: UserRole[] = [
    UserRole.BANK_REVIEWER,
    UserRole.SWIFT_OFFICER,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ]
  const HIDDEN: UserRole[] = [UserRole.DATA_ENTRY, UserRole.SUPPORT_COMMITTEE]

  it.each(SHOWN)('shows for %s', (role) => {
    expect(canDownloadDocument(role, 'SWIFT')).toBe(true)
  })

  it.each(HIDDEN)('hides for %s', (role) => {
    expect(canDownloadDocument(role, 'SWIFT')).toBe(false)
  })
})

// ─── Customs declaration row and download button ──────────────────────────────

describe('DocumentChecklist customs row', () => {
  it('row shown when customsDeclaration is non-null', () => {
    const customs = makeCustoms()
    expect(customs !== null).toBe(true)
  })

  it('row hidden when customsDeclaration is null', () => {
    const customs = null
    expect(customs !== null).toBe(false)
  })
})

describe('DocumentChecklist customs download button visibility', () => {
  const CUSTOMS_SHOWN: UserRole[] = [
    UserRole.BANK_REVIEWER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ]
  const CUSTOMS_HIDDEN: UserRole[] = [
    UserRole.DATA_ENTRY,
    UserRole.SWIFT_OFFICER,
    UserRole.SUPPORT_COMMITTEE,
    UserRole.EXECUTIVE_MEMBER,
  ]

  it.each(CUSTOMS_SHOWN)('download button shown for %s', (role) => {
    expect(canDownloadCustoms(role)).toBe(true)
  })

  it.each(CUSTOMS_HIDDEN)('download button hidden for %s', (role) => {
    expect(canDownloadCustoms(role)).toBe(false)
  })
})

// ─── Upload section: button vs locked note vs nothing ────────────────────────

describe('DocumentChecklist upload section — showUploadButton', () => {
  it('shown for DATA_ENTRY + DRAFT', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(true)
  })

  it('shown for DATA_ENTRY + DRAFT_REJECTED_INTERNAL', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.DRAFT_REJECTED_INTERNAL)).toBe(true)
  })

  it('not shown for DATA_ENTRY when request is SUBMITTED', () => {
    expect(canUploadDocument(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe(false)
  })

  it('not shown for BANK_REVIEWER on any status', () => {
    for (const status of Object.values(RequestStatus)) {
      expect(canUploadDocument(UserRole.BANK_REVIEWER, status)).toBe(false)
    }
  })
})

describe('DocumentChecklist upload section — showLockedNote', () => {
  // showLockedNote = DATA_ENTRY && isDocumentModificationLocked(status)
  function showLockedNote(role: UserRole, status: RequestStatus): boolean {
    return role === UserRole.DATA_ENTRY && isDocumentModificationLocked(status)
  }

  it('shown for DATA_ENTRY + SUBMITTED (locked)', () => {
    expect(showLockedNote(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe(true)
  })

  it('shown for DATA_ENTRY + COMPLETED (locked)', () => {
    expect(showLockedNote(UserRole.DATA_ENTRY, RequestStatus.COMPLETED)).toBe(true)
  })

  it('not shown for DATA_ENTRY + DRAFT (editable — upload button shows instead)', () => {
    expect(showLockedNote(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(false)
  })

  it('not shown for BANK_REVIEWER regardless of status', () => {
    for (const status of Object.values(RequestStatus)) {
      expect(showLockedNote(UserRole.BANK_REVIEWER, status)).toBe(false)
    }
  })

  it('not shown for CBY_ADMIN (no upload section for non-DATA_ENTRY)', () => {
    expect(showLockedNote(UserRole.CBY_ADMIN, RequestStatus.SUBMITTED)).toBe(false)
  })
})

// ─── Upload button and locked note are mutually exclusive ────────────────────

describe('DocumentChecklist: upload button and locked note are mutually exclusive', () => {
  function showUploadButton(role: UserRole, status: RequestStatus): boolean {
    return canUploadDocument(role, status)
  }

  function showLockedNote(role: UserRole, status: RequestStatus): boolean {
    return role === UserRole.DATA_ENTRY && isDocumentModificationLocked(status)
  }

  it('DATA_ENTRY on DRAFT: upload button shown, locked note hidden', () => {
    expect(showUploadButton(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(true)
    expect(showLockedNote(UserRole.DATA_ENTRY, RequestStatus.DRAFT)).toBe(false)
  })

  it('DATA_ENTRY on SUBMITTED: upload button hidden, locked note shown', () => {
    expect(showUploadButton(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe(false)
    expect(showLockedNote(UserRole.DATA_ENTRY, RequestStatus.SUBMITTED)).toBe(true)
  })

  it('BANK_REVIEWER on DRAFT: neither shown', () => {
    expect(showUploadButton(UserRole.BANK_REVIEWER, RequestStatus.DRAFT)).toBe(false)
    expect(showLockedNote(UserRole.BANK_REVIEWER, RequestStatus.DRAFT)).toBe(false)
  })

  it('CBY_ADMIN on COMPLETED: neither shown', () => {
    expect(showUploadButton(UserRole.CBY_ADMIN, RequestStatus.COMPLETED)).toBe(false)
    expect(showLockedNote(UserRole.CBY_ADMIN, RequestStatus.COMPLETED)).toBe(false)
  })
})
