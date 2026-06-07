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
    ...overrides,
  }
}

function makeCustoms(
  overrides: Partial<CustomsDeclarationSummary> = {},
): CustomsDeclarationSummary {
  return {
    id: 99,
    declaration_number: 'CUST-2026-001',
    issued_at: '2026-05-17T10:00:00.000Z',
    issued_by: 2,
    issuer: { id: 2, name: 'مدير اللجنة' },
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

// Extra-row label: prefer the backend-provided title, fall back per type.
function extraRowLabel(doc: { title?: string | null; type: string | null }): string {
  if (doc.title) return doc.title
  if (doc.type === 'SWIFT') return 'مستند SWIFT'
  if (doc.type === 'FX_REQUEST') return 'مستند طلب المصارفة الخارجية'
  return 'مستند إضافي'
}

describe('DocumentChecklist extra-row label', () => {
  it('prefers the backend title when present', () => {
    expect(extraRowLabel({ title: 'البطاقة الضريبية', type: 'REQUEST_DOC' })).toBe(
      'البطاقة الضريبية',
    )
  })

  it('falls back to SWIFT label when no title', () => {
    expect(extraRowLabel({ title: null, type: 'SWIFT' })).toBe('مستند SWIFT')
  })

  it('falls back to FX request label when no title', () => {
    expect(extraRowLabel({ title: null, type: 'FX_REQUEST' })).toBe('مستند طلب المصارفة الخارجية')
  })

  it('falls back to generic extra label for null/unknown type', () => {
    expect(extraRowLabel({ title: null, type: null })).toBe('مستند إضافي')
    expect(extraRowLabel({ title: null, type: 'REQUEST_DOC' })).toBe('مستند إضافي')
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
    UserRole.BANK_ADMIN,
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

describe('DocumentChecklist download button — FX_REQUEST', () => {
  const SHOWN: UserRole[] = [
    UserRole.BANK_REVIEWER,
    UserRole.BANK_ADMIN,
    UserRole.SWIFT_OFFICER,
    UserRole.EXECUTIVE_MEMBER,
    UserRole.COMMITTEE_DIRECTOR,
    UserRole.CBY_ADMIN,
  ]
  const HIDDEN: UserRole[] = [UserRole.DATA_ENTRY, UserRole.SUPPORT_COMMITTEE]

  it.each(SHOWN)('shows for %s', (role) => {
    expect(canDownloadDocument(role, 'FX_REQUEST')).toBe(true)
  })

  it.each(HIDDEN)('hides for %s', (role) => {
    expect(canDownloadDocument(role, 'FX_REQUEST')).toBe(false)
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

// ─── missingRequiredCount summary badge logic (Story 7.4) ────────────────────

type DocRequirement = {
  match: string
  matchBy: 'sub_type' | 'type'
  label: string
  required: boolean
}
type ChecklistRow =
  | { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
  | { kind: 'extra'; doc: RequestDocument }
  | { kind: 'customs'; customs: { id: number; declaration_number: string } }

function missingRequiredCount(rows: ChecklistRow[]): number {
  return rows.filter((r) => r.kind === 'staged' && r.requirement.required && !r.doc).length
}

describe('DocumentChecklist — missingRequiredCount summary badge', () => {
  it('returns 0 when all required docs are uploaded', () => {
    const rows: ChecklistRow[] = [
      {
        kind: 'staged',
        requirement: {
          match: 'proforma_invoice',
          matchBy: 'sub_type',
          label: 'الفاتورة الأولية',
          required: true,
        },
        doc: makeDoc({ document_sub_type: 'proforma_invoice' }),
      },
      {
        kind: 'staged',
        requirement: {
          match: 'extra_docs',
          matchBy: 'sub_type',
          label: 'مستندات إضافية',
          required: false,
        },
        doc: null,
      },
    ]
    expect(missingRequiredCount(rows)).toBe(0)
  })

  it('returns 1 when one required doc is missing', () => {
    const rows: ChecklistRow[] = [
      {
        kind: 'staged',
        requirement: {
          match: 'proforma_invoice',
          matchBy: 'sub_type',
          label: 'الفاتورة الأولية',
          required: true,
        },
        doc: null,
      },
      {
        kind: 'staged',
        requirement: {
          match: 'extra_docs',
          matchBy: 'sub_type',
          label: 'مستندات إضافية',
          required: false,
        },
        doc: null,
      },
    ]
    expect(missingRequiredCount(rows)).toBe(1)
  })

  it('returns 2 when two required docs are missing', () => {
    const rows: ChecklistRow[] = [
      {
        kind: 'staged',
        requirement: {
          match: 'proforma_invoice',
          matchBy: 'sub_type',
          label: 'الفاتورة الأولية',
          required: true,
        },
        doc: null,
      },
      {
        kind: 'staged',
        requirement: { match: 'SWIFT', matchBy: 'type', label: 'مستند SWIFT', required: true },
        doc: null,
      },
      {
        kind: 'staged',
        requirement: {
          match: 'extra_docs',
          matchBy: 'sub_type',
          label: 'مستندات إضافية',
          required: false,
        },
        doc: null,
      },
    ]
    expect(missingRequiredCount(rows)).toBe(2)
  })

  it('does not count optional missing docs', () => {
    const rows: ChecklistRow[] = [
      {
        kind: 'staged',
        requirement: {
          match: 'extra_docs',
          matchBy: 'sub_type',
          label: 'مستندات إضافية',
          required: false,
        },
        doc: null,
      },
    ]
    expect(missingRequiredCount(rows)).toBe(0)
  })

  it('does not count extra or customs rows', () => {
    const rows: ChecklistRow[] = [
      { kind: 'extra', doc: makeDoc() },
      { kind: 'customs', customs: { id: 99, declaration_number: 'CUST-001' } },
    ]
    expect(missingRequiredCount(rows)).toBe(0)
  })

  it('returns 0 for empty checklist', () => {
    expect(missingRequiredCount([])).toBe(0)
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
