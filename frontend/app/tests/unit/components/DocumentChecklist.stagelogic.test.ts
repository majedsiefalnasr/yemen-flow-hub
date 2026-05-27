/**
 * DocumentChecklist — stage-docs mapping and checklist merge logic (Story 6.6).
 * Pure logic tests, no component mounting.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { RequestDocument } from '../../../types/models'

// ── Mirror of component logic ─────────────────────────────────────────────────

type DocRequirement = { type: string; label: string; required: boolean }

const STAGE_DOCS: Record<string, DocRequirement[]> = {
  [RequestStatus.DRAFT]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.DRAFT_REJECTED_INTERNAL]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.SUBMITTED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.BANK_REVIEW]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.BANK_APPROVED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_PENDING]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REVIEW_IN_PROGRESS]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_APPROVED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SUPPORT_REJECTED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  ],
  [RequestStatus.WAITING_FOR_SWIFT]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
  [RequestStatus.SWIFT_UPLOADED]: [
    { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
    { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
    { type: 'SWIFT', label: 'مستند SWIFT', required: true },
  ],
}

const VOTING_AND_BEYOND_DOCS: DocRequirement[] = [
  { type: 'COMMERCIAL_INVOICE', label: 'فاتورة تجارية', required: true },
  { type: 'PACKING_LIST', label: 'قائمة التعبئة', required: false },
  { type: 'SWIFT', label: 'مستند SWIFT', required: true },
]

const VOTING_AND_BEYOND = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
  RequestStatus.COMPLETED,
])

function getStageDocs(status: RequestStatus): DocRequirement[] {
  if (VOTING_AND_BEYOND.has(status)) return VOTING_AND_BEYOND_DOCS
  return STAGE_DOCS[status] ?? []
}

function makeDoc(type: string, overrides: Partial<RequestDocument> = {}): RequestDocument {
  return {
    id: 1,
    type,
    original_filename: `${type.toLowerCase()}.pdf`,
    mime_type: 'application/pdf',
    size_bytes: 102400,
    checksum: 'abc',
    uploaded_by: 1,
    uploaded_by_name: 'علي',
    uploaded_at: '2026-05-19T10:00:00.000Z',
    download_url: '/api/documents/1/download',
    ...overrides,
  }
}

type ChecklistRow =
  | { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
  | { kind: 'extra'; doc: RequestDocument }

function buildChecklist(status: RequestStatus, documents: RequestDocument[]): ChecklistRow[] {
  const stageDocs = getStageDocs(status)
  const rows: ChecklistRow[] = []

  const uploadedByType = new Map<string, RequestDocument>()
  const extraDocs: RequestDocument[] = []

  function uploadedAtMs(doc: RequestDocument): number {
    const ts = Date.parse(doc.uploaded_at ?? '')
    return Number.isNaN(ts) ? 0 : ts
  }

  for (const doc of documents) {
    const t = doc.type ?? 'REQUEST_DOC'
    const isStagedType = stageDocs.some(r => r.type === t)
    if (!isStagedType) {
      extraDocs.push(doc)
      continue
    }

    const existing = uploadedByType.get(t)
    if (!existing) {
      uploadedByType.set(t, doc)
      continue
    }

    if (uploadedAtMs(doc) >= uploadedAtMs(existing)) {
      extraDocs.push(existing)
      uploadedByType.set(t, doc)
    }
    else {
      extraDocs.push(doc)
    }
  }

  for (const req of stageDocs) {
    rows.push({ kind: 'staged', requirement: req, doc: uploadedByType.get(req.type) ?? null })
  }

  for (const doc of extraDocs) {
    rows.push({ kind: 'extra', doc })
  }

  return rows
}

// ── Stage docs mapping ────────────────────────────────────────────────────────

describe('DocumentChecklist — getStageDocs', () => {
  it('DRAFT returns 2 entries: COMMERCIAL_INVOICE (required) + PACKING_LIST (optional)', () => {
    const docs = getStageDocs(RequestStatus.DRAFT)
    expect(docs).toHaveLength(2)
    expect(docs[0]).toMatchObject({ type: 'COMMERCIAL_INVOICE', required: true })
    expect(docs[1]).toMatchObject({ type: 'PACKING_LIST', required: false })
  })

  it('SUBMITTED returns same 2 entries as DRAFT', () => {
    const docs = getStageDocs(RequestStatus.SUBMITTED)
    expect(docs).toHaveLength(2)
    expect(docs.map(d => d.type)).toEqual(['COMMERCIAL_INVOICE', 'PACKING_LIST'])
  })

  it('BANK_APPROVED adds SWIFT as required (3 entries)', () => {
    const docs = getStageDocs(RequestStatus.BANK_APPROVED)
    expect(docs).toHaveLength(3)
    const swift = docs.find(d => d.type === 'SWIFT')
    expect(swift).toBeDefined()
    expect(swift!.required).toBe(true)
  })

  it('WAITING_FOR_SWIFT includes SWIFT as required', () => {
    const docs = getStageDocs(RequestStatus.WAITING_FOR_SWIFT)
    const swift = docs.find(d => d.type === 'SWIFT')
    expect(swift?.required).toBe(true)
  })

  it('SWIFT_UPLOADED includes SWIFT as required', () => {
    const docs = getStageDocs(RequestStatus.SWIFT_UPLOADED)
    expect(docs.find(d => d.type === 'SWIFT')).toBeDefined()
  })

  it('SUPPORT_REVIEW_PENDING includes SWIFT as required', () => {
    const docs = getStageDocs(RequestStatus.SUPPORT_REVIEW_PENDING)
    expect(docs.find(d => d.type === 'SWIFT')?.required).toBe(true)
  })

  it('SUPPORT_REVIEW_IN_PROGRESS includes SWIFT as required', () => {
    const docs = getStageDocs(RequestStatus.SUPPORT_REVIEW_IN_PROGRESS)
    expect(docs.find(d => d.type === 'SWIFT')?.required).toBe(true)
  })

  it('SUPPORT_APPROVED includes SWIFT as required', () => {
    const docs = getStageDocs(RequestStatus.SUPPORT_APPROVED)
    expect(docs.find(d => d.type === 'SWIFT')?.required).toBe(true)
  })

  it('SUPPORT_REJECTED does not include SWIFT requirement', () => {
    const docs = getStageDocs(RequestStatus.SUPPORT_REJECTED)
    expect(docs.find(d => d.type === 'SWIFT')).toBeUndefined()
  })

  it('EXECUTIVE_VOTING_OPEN returns voting-and-beyond set (3 entries)', () => {
    const docs = getStageDocs(RequestStatus.EXECUTIVE_VOTING_OPEN)
    expect(docs).toHaveLength(3)
    expect(docs.map(d => d.type)).toEqual(['COMMERCIAL_INVOICE', 'PACKING_LIST', 'SWIFT'])
  })

  it('COMPLETED returns voting-and-beyond set', () => {
    const docs = getStageDocs(RequestStatus.COMPLETED)
    expect(docs).toHaveLength(3)
  })

  it('EXECUTIVE_REJECTED returns voting-and-beyond set', () => {
    const docs = getStageDocs(RequestStatus.EXECUTIVE_REJECTED)
    expect(docs).toHaveLength(3)
  })
})

// ── Checklist merge logic ─────────────────────────────────────────────────────

describe('DocumentChecklist — buildChecklist merge logic', () => {
  it('uploaded doc matched to staged requirement row', () => {
    const docs = [makeDoc('COMMERCIAL_INVOICE', { id: 1 })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const staged = rows.find(r => r.kind === 'staged' && r.requirement.type === 'COMMERCIAL_INVOICE') as { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
    expect(staged).toBeDefined()
    expect(staged.doc).not.toBeNull()
    expect(staged.doc!.id).toBe(1)
  })

  it('missing required doc shows as staged row with null doc', () => {
    const rows = buildChecklist(RequestStatus.DRAFT, [])
    const invoice = rows.find(r => r.kind === 'staged' && r.requirement.type === 'COMMERCIAL_INVOICE') as { kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }
    expect(invoice).toBeDefined()
    expect(invoice.doc).toBeNull()
  })

  it('unrecognized doc type becomes extra row', () => {
    const docs = [makeDoc('CERTIFICATE_OF_ORIGIN', { id: 99 })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const extra = rows.find(r => r.kind === 'extra') as { kind: 'extra'; doc: RequestDocument }
    expect(extra).toBeDefined()
    expect(extra.doc.id).toBe(99)
  })

  it('DRAFT stage produces 2 staged rows + 0 extras when no docs uploaded', () => {
    const rows = buildChecklist(RequestStatus.DRAFT, [])
    const staged = rows.filter(r => r.kind === 'staged')
    const extra = rows.filter(r => r.kind === 'extra')
    expect(staged).toHaveLength(2)
    expect(extra).toHaveLength(0)
  })

  it('BANK_APPROVED stage produces 3 staged rows when all uploaded', () => {
    const docs = [
      makeDoc('COMMERCIAL_INVOICE', { id: 1 }),
      makeDoc('PACKING_LIST', { id: 2 }),
      makeDoc('SWIFT', { id: 3 }),
    ]
    const rows = buildChecklist(RequestStatus.BANK_APPROVED, docs)
    const staged = rows.filter(r => r.kind === 'staged') as Array<{ kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }>
    expect(staged).toHaveLength(3)
    expect(staged.every(r => r.doc !== null)).toBe(true)
  })

  it('REQUEST_DOC type treated as extra (not a stage requirement type)', () => {
    const docs = [makeDoc('REQUEST_DOC', { id: 5 })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const extra = rows.filter(r => r.kind === 'extra')
    expect(extra).toHaveLength(1)
  })

  it('duplicate type: latest upload wins staged row, older becomes extra', () => {
    const docs = [
      makeDoc('COMMERCIAL_INVOICE', { id: 1, uploaded_at: '2026-05-19T10:00:00.000Z' }),
      makeDoc('COMMERCIAL_INVOICE', { id: 2, uploaded_at: '2026-05-19T12:00:00.000Z' }),
    ]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const staged = rows.filter(r => r.kind === 'staged' && r.requirement.type === 'COMMERCIAL_INVOICE') as Array<{ kind: 'staged'; requirement: DocRequirement; doc: RequestDocument | null }>
    const extras = rows.filter(r => r.kind === 'extra')
    expect(staged[0]?.doc?.id).toBe(2)
    expect((extras[0] as { kind: 'extra'; doc: RequestDocument })?.doc?.id).toBe(1)
  })
})

// ── Upload status badge logic ─────────────────────────────────────────────────

describe('DocumentChecklist — upload status badge label', () => {
  function badgeLabel(doc: RequestDocument | null, required: boolean): string {
    if (doc) return 'مرفوع'
    return required ? 'مطلوب' : 'غير مطلوب'
  }

  it('uploaded doc → "مرفوع"', () => {
    expect(badgeLabel(makeDoc('COMMERCIAL_INVOICE'), true)).toBe('مرفوع')
  })

  it('missing required doc → "مطلوب"', () => {
    expect(badgeLabel(null, true)).toBe('مطلوب')
  })

  it('missing optional doc → "غير مطلوب"', () => {
    expect(badgeLabel(null, false)).toBe('غير مطلوب')
  })

  it('uploaded optional doc → "مرفوع" (not optional label)', () => {
    expect(badgeLabel(makeDoc('PACKING_LIST'), false)).toBe('مرفوع')
  })
})
