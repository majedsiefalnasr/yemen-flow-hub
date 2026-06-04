/**
 * DocumentChecklist — stage-docs mapping and checklist merge logic.
 * Pure logic tests, no component mounting.
 *
 * The checklist is built from the request's real wizard document slots (matched by
 * persisted `document_sub_type`) plus the CBY-side SWIFT / FX documents (matched by
 * `type`). This mirrors the logic in DocumentChecklist.vue.
 */
import { describe, it, expect } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { RequestDocument } from '../../../types/models'

// ── Mirror of component logic ─────────────────────────────────────────────────

type DocRequirement = {
  match: string
  matchBy: 'sub_type' | 'type'
  label: string
  required: boolean
}

const BANK_WIZARD_DOCS: DocRequirement[] = [
  {
    match: 'confirmation_request',
    matchBy: 'sub_type',
    label: 'طلب وثيقة التأكيد',
    required: true,
  },
  {
    match: 'proforma_invoice',
    matchBy: 'sub_type',
    label: 'الفاتورة الأولية (Proforma Invoice)',
    required: true,
  },
  { match: 'commercial_register', matchBy: 'sub_type', label: 'السجل التجاري', required: true },
  { match: 'tax_card', matchBy: 'sub_type', label: 'البطاقة الضريبية', required: true },
]

const CBY_DOCS: DocRequirement[] = [
  { match: 'SWIFT', matchBy: 'type', label: 'مستند SWIFT', required: true },
  { match: 'FX_REQUEST', matchBy: 'type', label: 'مستند طلب المصارفة الخارجية', required: true },
]

const CBY_STAGE_STATUSES = new Set([
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
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
  return CBY_STAGE_STATUSES.has(status) ? [...BANK_WIZARD_DOCS, ...CBY_DOCS] : [...BANK_WIZARD_DOCS]
}

function docMatchesRequirement(doc: RequestDocument, req: DocRequirement): boolean {
  if (req.matchBy === 'type') return doc.type === req.match
  if (req.match === 'confirmation_request') {
    return doc.document_sub_type === 'confirmation_request' || doc.type === 'CONFIRMATION_REQUEST'
  }
  if (doc.document_sub_type) return doc.document_sub_type === req.match
  // Legacy fallback: REQUEST_DOC without sub_type fills any wizard slot (greedy, in order)
  return doc.type === 'REQUEST_DOC'
}

function makeDoc(overrides: Partial<RequestDocument> = {}): RequestDocument {
  return {
    id: 1,
    type: 'REQUEST_DOC',
    document_sub_type: null,
    title: null,
    original_filename: 'doc.pdf',
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

  const usedDocIds = new Set<number>()
  const slotDoc = new Map<string, RequestDocument>()

  function uploadedAtMs(doc: RequestDocument): number {
    const ts = Date.parse(doc.uploaded_at ?? '')
    return Number.isNaN(ts) ? 0 : ts
  }

  for (const req of stageDocs) {
    let best: RequestDocument | null = null
    for (const doc of documents) {
      if (usedDocIds.has(doc.id)) continue
      if (!docMatchesRequirement(doc, req)) continue
      if (!best || uploadedAtMs(doc) >= uploadedAtMs(best)) best = doc
    }
    if (best) {
      slotDoc.set(req.match, best)
      usedDocIds.add(best.id)
    }
  }

  for (const req of stageDocs) {
    rows.push({ kind: 'staged', requirement: req, doc: slotDoc.get(req.match) ?? null })
  }

  for (const doc of documents) {
    if (!usedDocIds.has(doc.id)) rows.push({ kind: 'extra', doc })
  }

  return rows
}

// ── Stage docs mapping ────────────────────────────────────────────────────────

describe('DocumentChecklist — getStageDocs', () => {
  it('DRAFT returns the 4 bank wizard slots, all required', () => {
    const docs = getStageDocs(RequestStatus.DRAFT)
    expect(docs).toHaveLength(4)
    expect(docs.map((d) => d.match)).toEqual([
      'confirmation_request',
      'proforma_invoice',
      'commercial_register',
      'tax_card',
    ])
    expect(docs.every((d) => d.required)).toBe(true)
  })

  it('SUBMITTED returns the same 4 bank slots as DRAFT', () => {
    const docs = getStageDocs(RequestStatus.SUBMITTED)
    expect(docs.map((d) => d.match)).toEqual([
      'confirmation_request',
      'proforma_invoice',
      'commercial_register',
      'tax_card',
    ])
  })

  it('does NOT include قائمة التعبئة / PACKING_LIST anywhere', () => {
    for (const status of Object.values(RequestStatus)) {
      const docs = getStageDocs(status)
      expect(docs.some((d) => d.label.includes('التعبئة'))).toBe(false)
      expect(docs.some((d) => d.match === 'PACKING_LIST')).toBe(false)
    }
  })

  it('BANK_APPROVED adds SWIFT + FX_REQUEST (6 entries)', () => {
    const docs = getStageDocs(RequestStatus.BANK_APPROVED)
    expect(docs).toHaveLength(6)
    expect(docs.find((d) => d.match === 'SWIFT')?.required).toBe(true)
    expect(docs.find((d) => d.match === 'FX_REQUEST')?.required).toBe(true)
  })

  it('WAITING_FOR_SWIFT includes SWIFT as required', () => {
    expect(
      getStageDocs(RequestStatus.WAITING_FOR_SWIFT).find((d) => d.match === 'SWIFT')?.required,
    ).toBe(true)
  })

  it('SUPPORT_REJECTED stays at the 4 bank slots (no CBY docs)', () => {
    const docs = getStageDocs(RequestStatus.SUPPORT_REJECTED)
    expect(docs).toHaveLength(4)
    expect(docs.find((d) => d.match === 'SWIFT')).toBeUndefined()
  })

  it('EXECUTIVE_VOTING_OPEN returns all 6 entries', () => {
    expect(getStageDocs(RequestStatus.EXECUTIVE_VOTING_OPEN)).toHaveLength(6)
  })

  it('COMPLETED returns all 6 entries', () => {
    expect(getStageDocs(RequestStatus.COMPLETED)).toHaveLength(6)
  })
})

// ── Checklist merge logic ─────────────────────────────────────────────────────

describe('DocumentChecklist — buildChecklist merge logic', () => {
  it('proforma_invoice sub_type fills the proforma slot', () => {
    const docs = [makeDoc({ id: 1, document_sub_type: 'proforma_invoice' })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const slot = rows.find(
      (r) => r.kind === 'staged' && r.requirement.match === 'proforma_invoice',
    ) as Extract<ChecklistRow, { kind: 'staged' }>
    expect(slot.doc?.id).toBe(1)
  })

  it('confirmation_request matches by CONFIRMATION_REQUEST type as well', () => {
    const docs = [makeDoc({ id: 7, type: 'CONFIRMATION_REQUEST', document_sub_type: null })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const slot = rows.find(
      (r) => r.kind === 'staged' && r.requirement.match === 'confirmation_request',
    ) as Extract<ChecklistRow, { kind: 'staged' }>
    expect(slot.doc?.id).toBe(7)
  })

  it('missing required doc shows as staged row with null doc', () => {
    const rows = buildChecklist(RequestStatus.DRAFT, [])
    const slot = rows.find(
      (r) => r.kind === 'staged' && r.requirement.match === 'tax_card',
    ) as Extract<ChecklistRow, { kind: 'staged' }>
    expect(slot.doc).toBeNull()
  })

  it('a document with no recognized sub_type becomes an extra row', () => {
    const docs = [makeDoc({ id: 99, document_sub_type: 'extra_docs' })]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const extra = rows.find((r) => r.kind === 'extra') as Extract<ChecklistRow, { kind: 'extra' }>
    expect(extra.doc.id).toBe(99)
  })

  it('legacy REQUEST_DOC without sub_type fills wizard slots in order', () => {
    // Simulates documents uploaded before sub_type was introduced
    const docs = [
      makeDoc({ id: 10, type: 'CONFIRMATION_REQUEST', document_sub_type: null }),
      makeDoc({ id: 11, type: 'REQUEST_DOC', document_sub_type: null }),
      makeDoc({ id: 12, type: 'REQUEST_DOC', document_sub_type: null }),
      makeDoc({ id: 13, type: 'REQUEST_DOC', document_sub_type: null }),
    ]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const staged = rows.filter((r) => r.kind === 'staged') as Array<
      Extract<ChecklistRow, { kind: 'staged' }>
    >
    // confirmation_request filled by CONFIRMATION_REQUEST type
    expect(staged.find((r) => r.requirement.match === 'confirmation_request')?.doc?.id).toBe(10)
    // remaining REQUEST_DOC docs fill the 3 sub-type slots in order
    expect(staged.filter((r) => r.doc !== null)).toHaveLength(4)
    // no extras — all docs consumed by slots
    expect(rows.filter((r) => r.kind === 'extra')).toHaveLength(0)
  })

  it('DRAFT produces 4 staged rows + 0 extras when no docs uploaded', () => {
    const rows = buildChecklist(RequestStatus.DRAFT, [])
    expect(rows.filter((r) => r.kind === 'staged')).toHaveLength(4)
    expect(rows.filter((r) => r.kind === 'extra')).toHaveLength(0)
  })

  it('BANK_APPROVED with all docs uploaded fills all 6 slots', () => {
    const docs = [
      makeDoc({ id: 1, type: 'CONFIRMATION_REQUEST' }),
      makeDoc({ id: 2, document_sub_type: 'proforma_invoice' }),
      makeDoc({ id: 3, document_sub_type: 'commercial_register' }),
      makeDoc({ id: 4, document_sub_type: 'tax_card' }),
      makeDoc({ id: 5, type: 'SWIFT' }),
      makeDoc({ id: 6, type: 'FX_REQUEST' }),
    ]
    const rows = buildChecklist(RequestStatus.BANK_APPROVED, docs)
    const staged = rows.filter((r) => r.kind === 'staged') as Array<
      Extract<ChecklistRow, { kind: 'staged' }>
    >
    expect(staged).toHaveLength(6)
    expect(staged.every((r) => r.doc !== null)).toBe(true)
  })

  it('duplicate sub_type: latest upload wins the slot, older becomes extra', () => {
    const docs = [
      makeDoc({ id: 1, document_sub_type: 'tax_card', uploaded_at: '2026-05-19T10:00:00.000Z' }),
      makeDoc({ id: 2, document_sub_type: 'tax_card', uploaded_at: '2026-05-19T12:00:00.000Z' }),
    ]
    const rows = buildChecklist(RequestStatus.DRAFT, docs)
    const slot = rows.find(
      (r) => r.kind === 'staged' && r.requirement.match === 'tax_card',
    ) as Extract<ChecklistRow, { kind: 'staged' }>
    const extra = rows.find((r) => r.kind === 'extra') as Extract<ChecklistRow, { kind: 'extra' }>
    expect(slot.doc?.id).toBe(2)
    expect(extra.doc.id).toBe(1)
  })
})
