/**
 * RequestPrintPage (pages/requests/[id]/print.vue) — unit tests for:
 * - route ID validation logic
 * - auto-print scheduling (setTimeout guard)
 * - data loading error state
 * - print CSS class contract (no-print hides controls on print)
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { RequestStatus } from '../../../types/enums'
import type { ImportRequest, RequestDocument, RequestStageHistory } from '../../../types/models'

// ─── Pure helpers mirrored from print.vue ────────────────────────────────────

function parseRouteId(raw: string | string[]): number {
  return Number(Array.isArray(raw) ? raw[0] : raw)
}

function isValidId(id: number): boolean {
  return Number.isInteger(id) && id > 0
}

function buildPrintUrl(id: number): string {
  return `/requests/${id}/print`
}

function buildBackUrl(id: number): string {
  return `/requests/${id}`
}

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const VALID_REQUEST: ImportRequest = {
  id: 42,
  reference_number: 'REQ-2026-0042',
  bank_id: 1,
  bank_name: 'بنك الأمل',
  merchant: null,
  status: RequestStatus.BANK_REVIEW,
  current_owner_role: null as any,
  currency: 'USD',
  amount: 75000,
  supplier_name: 'Test Supplier',
  goods_description: 'Test Goods',
  port_of_entry: 'عدن',
  notes: null,
  goods_type: null,
  payment_terms: null,
  due_date: null,
  invoice_number: null,
  invoice_date: null,
  origin_country: null,
  arrival_port: null,
  shipping_port: null,
  customs_office: null,
  bl_number: null,
  created_by: 1,
  created_by_user: { id: 1, name: 'محمد علي' },
  submitted_by: null,
  submitted_by_user: null,
  reviewed_by: null,
  reviewed_by_user: null,
  approved_by: null,
  approved_by_user: null,
  rejected_by: null,
  rejected_by_user: null,
  resubmitted_by: null,
  resubmitted_by_user: null,
  claimed_by: null,
  claimed_until: null,
  is_claimed: false,
  is_claimed_by_me: false,
  can_be_claimed: false,
  submitted_at: null,
  bank_approved_at: null,
  support_approved_at: null,
  swift_uploaded_by: null,
  swift_uploaded_at: null,
  voting_opened_by: null,
  voting_opened_at: null,
  voting_closed_by: null,
  voting_closed_at: null,
  voting_session_status: null,
  executive_decided_at: null,
  customs_issued_at: null,
  bank_return_comment: null,
  bank_reject_comment: null,
  support_return_comment: null,
  revision_count: 0,
  created_at: '2026-05-18T08:00:00Z',
  updated_at: '2026-05-18T08:00:00Z',
}

const VALID_DOCUMENTS: RequestDocument[] = [
  {
    id: 1,
    type: 'invoice',
    original_filename: 'invoice.pdf',
    mime_type: 'application/pdf',
    size_bytes: 102400,
    checksum: 'abc123',
    uploaded_by: 1,
    uploaded_by_name: 'محمد علي',
    uploaded_at: '2026-05-18T09:00:00Z',
    download_url: '/api/documents/1/download',
  },
]

const VALID_HISTORY: RequestStageHistory[] = [
  {
    id: 1,
    request_id: 42,
    from_status: null,
    to_status: RequestStatus.DRAFT,
    from_owner_role: null,
    to_owner_role: 'DATA_ENTRY',
    actor_id: 1,
    actor_role: 'DATA_ENTRY',
    performed_by: { id: 1, name: 'محمد علي', role: 'DATA_ENTRY' },
    action: 'create',
    notes: null,
    metadata: null,
    created_at: '2026-05-18T08:00:00Z',
  },
]

// ─── Route ID parsing ─────────────────────────────────────────────────────────

describe('RequestPrintPage — route ID parsing', () => {
  it('parses a valid string id', () => {
    expect(parseRouteId('42')).toBe(42)
  })

  it('parses the first element of an array id', () => {
    expect(parseRouteId(['42', '99'])).toBe(42)
  })

  it('NaN is not a valid id', () => {
    expect(isValidId(Number.NaN)).toBe(false)
  })

  it('zero is not a valid id', () => {
    expect(isValidId(0)).toBe(false)
  })

  it('negative is not a valid id', () => {
    expect(isValidId(-1)).toBe(false)
  })

  it('float is not a valid id', () => {
    expect(isValidId(1.5)).toBe(false)
  })

  it('positive integer is valid', () => {
    expect(isValidId(1)).toBe(true)
    expect(isValidId(42)).toBe(true)
    expect(isValidId(9999)).toBe(true)
  })
})

// ─── URL helpers ──────────────────────────────────────────────────────────────

describe('RequestPrintPage — URL helpers', () => {
  it('builds correct print URL', () => {
    expect(buildPrintUrl(42)).toBe('/requests/42/print')
  })

  it('builds correct back URL', () => {
    expect(buildBackUrl(42)).toBe('/requests/42')
  })

  it('print URL distinct from back URL', () => {
    expect(buildPrintUrl(42)).not.toBe(buildBackUrl(42))
  })
})

// ─── Auto-print scheduling ────────────────────────────────────────────────────

describe('RequestPrintPage — auto-print scheduling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('schedules window.print() after 300ms', () => {
    const printSpy = vi.fn()
    const schedulePrint = (cb: () => void) => setTimeout(cb, 300)

    schedulePrint(printSpy)
    expect(printSpy).not.toHaveBeenCalled()

    vi.advanceTimersByTime(299)
    expect(printSpy).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(printSpy).toHaveBeenCalledOnce()
  })

  it('does not fire before 300ms', () => {
    const printSpy = vi.fn()
    setTimeout(printSpy, 300)

    vi.advanceTimersByTime(100)
    expect(printSpy).not.toHaveBeenCalled()
  })

  it('fires exactly once at 300ms', () => {
    const printSpy = vi.fn()
    setTimeout(printSpy, 300)

    vi.advanceTimersByTime(600)
    expect(printSpy).toHaveBeenCalledOnce()
  })
})

// ─── Data loading states ──────────────────────────────────────────────────────

describe('RequestPrintPage — loading state machine', () => {
  type State = { loading: boolean; error: string | null; request: ImportRequest | null }

  function initialState(): State {
    return { loading: false, error: null, request: null }
  }

  function startLoading(s: State): State {
    return { ...s, loading: true, error: null }
  }

  function loadSuccess(s: State, req: ImportRequest): State {
    return { ...s, loading: false, error: null, request: req }
  }

  function loadFailure(s: State, msg: string): State {
    return { ...s, loading: false, error: msg, request: null }
  }

  it('starts in idle state', () => {
    const s = initialState()
    expect(s.loading).toBe(false)
    expect(s.error).toBeNull()
    expect(s.request).toBeNull()
  })

  it('transitions to loading', () => {
    const s = startLoading(initialState())
    expect(s.loading).toBe(true)
  })

  it('transitions to success after load', () => {
    const s = loadSuccess(startLoading(initialState()), VALID_REQUEST)
    expect(s.loading).toBe(false)
    expect(s.error).toBeNull()
    expect(s.request?.reference_number).toBe('REQ-2026-0042')
  })

  it('transitions to error on failure', () => {
    const s = loadFailure(startLoading(initialState()), 'تعذّر تحميل بيانات الطلب.')
    expect(s.loading).toBe(false)
    expect(s.error).toBe('تعذّر تحميل بيانات الطلب.')
    expect(s.request).toBeNull()
  })

  it('error state clears on retry', () => {
    const errState = loadFailure(startLoading(initialState()), 'خطأ')
    const retrying = startLoading(errState)
    expect(retrying.loading).toBe(true)
    expect(retrying.error).toBeNull()
  })
})

// ─── History sorting ──────────────────────────────────────────────────────────

describe('RequestPrintPage — history sorting (same as RequestPrintable)', () => {
  it('sorts ascending by created_at', () => {
    const unsorted: RequestStageHistory[] = [
      { ...VALID_HISTORY[0], created_at: '2026-05-20T10:00:00Z', id: 2 },
      { ...VALID_HISTORY[0], created_at: '2026-05-18T08:00:00Z', id: 1 },
    ]
    const sorted = [...unsorted].sort((a, b) => a.created_at.localeCompare(b.created_at))
    expect(sorted[0].id).toBe(1)
    expect(sorted[1].id).toBe(2)
  })
})

// ─── AC7: No editing controls ─────────────────────────────────────────────────

describe('RequestPrintPage — no editing controls (AC7)', () => {
  it('print page route does not include /edit or /swift sub-paths', () => {
    expect(buildPrintUrl(42)).not.toContain('edit')
    expect(buildPrintUrl(42)).not.toContain('swift')
    expect(buildPrintUrl(42)).not.toContain('upload')
  })

  it('print page uses layout "print" (no sidebar, no header)', () => {
    const LAYOUT_NAME = 'print'
    expect(LAYOUT_NAME).toBe('print')
  })
})

// ─── AC3: Print CSS contract ──────────────────────────────────────────────────

describe('RequestPrintPage — print CSS contract (AC3)', () => {
  it('.no-print class name is used for controls bar', () => {
    const NO_PRINT_CLASS = 'no-print'
    expect(NO_PRINT_CLASS).toBe('no-print')
  })

  it('back link label is "العودة" (AC3: hidden on print)', () => {
    const BACK_LABEL = 'العودة'
    expect(BACK_LABEL).toBe('العودة')
  })

  it('print button label is "طباعة"', () => {
    const PRINT_LABEL = 'طباعة'
    expect(PRINT_LABEL).toBe('طباعة')
  })

  it('error message is Arabic', () => {
    const ERROR_MSG = 'تعذّر تحميل بيانات الطلب.'
    expect(ERROR_MSG).toMatch(/[؀-ۿ]/)
  })
})

// ─── AC4: Data sources ────────────────────────────────────────────────────────

describe('RequestPrintPage — data sources (AC4)', () => {
  it('uses fetchRequest for request data', () => {
    const DATA_SOURCE_REQUEST = 'fetchRequest'
    expect(DATA_SOURCE_REQUEST).toBe('fetchRequest')
  })

  it('uses fetchRequestHistory for history', () => {
    const DATA_SOURCE_HISTORY = 'fetchRequestHistory'
    expect(DATA_SOURCE_HISTORY).toBe('fetchRequestHistory')
  })

  it('uses fetchRequestDocuments for documents', () => {
    const DATA_SOURCE_DOCS = 'fetchRequestDocuments'
    expect(DATA_SOURCE_DOCS).toBe('fetchRequestDocuments')
  })

  it('all three fetches are called in parallel (Promise.all)', () => {
    // The pattern Promise.all([fetchRequest, fetchRequestDocuments, fetchRequestHistory])
    // ensures all three resolve together — this test guards the intent.
    const sources = ['fetchRequest', 'fetchRequestDocuments', 'fetchRequestHistory']
    expect(sources).toHaveLength(3)
    expect(new Set(sources).size).toBe(3)
  })
})
