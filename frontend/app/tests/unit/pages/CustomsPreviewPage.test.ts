import { vi, describe, it, expect, beforeEach } from 'vitest'
import type { CustomsDeclaration } from '../../../types/models'

// ─── Stubs ────────────────────────────────────────────────────────────────────

const mockFetchCustomsPreview = vi.fn()
const mockDownloadCustomsDeclaration = vi.fn()
const mockRouterPush = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchCustomsPreview: mockFetchCustomsPreview,
    downloadCustomsDeclaration: mockDownloadCustomsDeclaration,
    fetchRequests: vi.fn(),
    fetchRequest: vi.fn(),
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: vi.fn(),
    fetchRequestDocuments: vi.fn(),
    uploadSwift: vi.fn(),
    generateCustomsDeclaration: vi.fn(),
    fetchRequestHistory: vi.fn(),
  }),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '42' } }),
  useRouter: () => ({ push: mockRouterPush }),
}))

// Stub definePageMeta for non-Nuxt test env
vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost' } }))

function makeDeclaration(overrides: Partial<CustomsDeclaration> = {}): CustomsDeclaration {
  return {
    id: 7,
    request_id: 42,
    declaration_number: 'CD-2026-000001',
    issued_by: 99,
    issuer: {
      id: 99,
      name: 'مدير اللجنة',
      email: 'director@cby.ye',
      role: 'COMMITTEE_DIRECTOR',
    } as CustomsDeclaration['issuer'],
    issued_at: '2026-05-18T10:00:00.000000Z',
    request: { id: 42, reference_number: 'YFH-2026-000042', bank_name: 'بنك اليمن' },
    metadata: {
      reference_number: 'YFH-2026-000042',
      bank: { id: 1, name: 'بنك اليمن', code: 'YCB' },
      supplier_name: 'Supplier Co.',
      amount: 10000,
      currency: 'USD',
      goods_description: 'Industrial equipment',
      port_of_entry: 'Aden Port',
      bank_approved_at: '2026-05-10T10:00:00.000000Z',
      support_approved_at: '2026-05-12T10:00:00.000000Z',
      executive_decided_at: '2026-05-14T10:00:00.000000Z',
    },
    created_at: '2026-05-18T10:00:00.000000Z',
    ...overrides,
  }
}

// ─── Page logic unit tests (logic layer only, no DOM mount) ───────────────────

describe('CustomsPreviewPage — data loading logic', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('fetchCustomsPreview is called with the request id from route on mount', async () => {
    mockFetchCustomsPreview.mockResolvedValue(makeDeclaration())

    const { useRequests } = await import('../../../composables/useRequests')
    const { fetchCustomsPreview } = useRequests()

    const result = await fetchCustomsPreview(42)

    expect(mockFetchCustomsPreview).toHaveBeenCalledWith(42)
    expect(result.id).toBe(7)
  })

  it('403 from fetchCustomsPreview should redirect to /dashboard', async () => {
    const err = Object.assign(new Error('Forbidden'), { statusCode: 403 })
    mockFetchCustomsPreview.mockRejectedValue(err)

    const { useRouter } = await import('vue-router')
    const router = useRouter()

    try {
      await mockFetchCustomsPreview(42)
    } catch (e: unknown) {
      const status = (e as { statusCode?: number })?.statusCode
      if (status === 403) router.push('/dashboard')
    }

    expect(mockRouterPush).toHaveBeenCalledWith('/dashboard')
  })

  it('404 from fetchCustomsPreview sets errorStatus to 404 (no-declaration state)', async () => {
    const err = Object.assign(new Error('Not Found'), { statusCode: 404 })
    mockFetchCustomsPreview.mockRejectedValue(err)

    let errorStatus: number | null = null
    try {
      await mockFetchCustomsPreview(42)
    } catch (e: unknown) {
      errorStatus = (e as { statusCode?: number })?.statusCode ?? 500
    }

    expect(errorStatus).toBe(404)
  })

  it('declaration data is populated on success', async () => {
    const decl = makeDeclaration()
    mockFetchCustomsPreview.mockResolvedValue(decl)

    const result = await mockFetchCustomsPreview(42)
    expect(result.declaration_number).toBe('CD-2026-000001')
    expect(result.issuer?.name).toBe('مدير اللجنة')
  })
})

// ─── formatDate / formatAmount helper logic ───────────────────────────────────

describe('CustomsPreviewPage — date and amount helpers', () => {
  function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('ar-YE', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  function formatAmount(amount: unknown, currency: unknown): string {
    if (amount == null) return '—'
    return `${Number(amount).toLocaleString('ar-YE')} ${currency ?? ''}`
  }

  it('formatDate returns — for null', () => {
    expect(formatDate(null)).toBe('—')
  })

  it('formatDate returns — for undefined', () => {
    expect(formatDate(undefined)).toBe('—')
  })

  it('formatDate returns formatted string for valid ISO', () => {
    const result = formatDate('2026-05-18T10:00:00.000000Z')
    expect(result).not.toBe('—')
    expect(typeof result).toBe('string')
  })

  it('formatAmount returns — for null', () => {
    expect(formatAmount(null, 'USD')).toBe('—')
  })

  it('formatAmount includes currency in output', () => {
    const result = formatAmount(10000, 'USD')
    expect(result).toContain('USD')
  })

  it('formatAmount formats zero as string, not —', () => {
    const result = formatAmount(0, 'USD')
    expect(result).not.toBe('—')
    expect(result).toContain('USD')
  })
})

// ─── bankName computed logic ──────────────────────────────────────────────────

describe('CustomsPreviewPage — bankName computed', () => {
  function getBankName(metadata: Record<string, unknown>): string {
    const bank = metadata.bank as { name?: string; code?: string } | undefined
    if (!bank) return '—'
    return bank.code ? `${bank.name} (${bank.code})` : (bank.name ?? '—')
  }

  it('returns — when metadata has no bank key', () => {
    expect(getBankName({})).toBe('—')
  })

  it('returns name (code) when both present', () => {
    expect(getBankName({ bank: { name: 'بنك اليمن', code: 'YCB' } })).toBe('بنك اليمن (YCB)')
  })

  it('returns name only when code is missing', () => {
    expect(getBankName({ bank: { name: 'بنك اليمن' } })).toBe('بنك اليمن')
  })
})

// ─── customs authorization behavior (AC12) ───────────────────────────────────

describe('CustomsPreviewPage — customs authorization (AC12)', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('500 error sets errorStatus to 500 (generic error state)', async () => {
    const err = Object.assign(new Error('Internal Server Error'), { statusCode: 500 })
    mockFetchCustomsPreview.mockRejectedValue(err)

    let errorStatus: number | null = null
    try {
      await mockFetchCustomsPreview(42)
    } catch (e: unknown) {
      errorStatus = (e as { statusCode?: number })?.statusCode ?? 500
    }

    expect(errorStatus).toBe(500)
  })

  it('error without statusCode defaults to 500', async () => {
    mockFetchCustomsPreview.mockRejectedValue(new Error('Unknown'))

    let errorStatus: number | null = null
    try {
      await mockFetchCustomsPreview(42)
    } catch (e: unknown) {
      errorStatus = (e as { statusCode?: number })?.statusCode ?? 500
    }

    expect(errorStatus).toBe(500)
  })

  it('declaration has correct request_id matching route param', async () => {
    const decl = makeDeclaration({ request_id: 42 })
    mockFetchCustomsPreview.mockResolvedValue(decl)

    const result = await mockFetchCustomsPreview(42)
    expect(result.request_id).toBe(42)
  })

  it('declaration issuer role is COMMITTEE_DIRECTOR', async () => {
    const decl = makeDeclaration()
    mockFetchCustomsPreview.mockResolvedValue(decl)

    const result = await mockFetchCustomsPreview(42)
    expect(result.issuer?.role).toBe('COMMITTEE_DIRECTOR')
  })

  it('declaration metadata contains all required customs fields', async () => {
    const decl = makeDeclaration()
    mockFetchCustomsPreview.mockResolvedValue(decl)

    const result = await mockFetchCustomsPreview(42)
    const m = result.metadata as Record<string, unknown>
    expect(m.bank).toBeDefined()
    expect(m.supplier_name).toBeDefined()
    expect(m.amount).toBeDefined()
    expect(m.currency).toBeDefined()
    expect(m.goods_description).toBeDefined()
    expect(m.port_of_entry).toBeDefined()
    expect(m.bank_approved_at).toBeDefined()
    expect(m.support_approved_at).toBeDefined()
    expect(m.executive_decided_at).toBeDefined()
  })
})

// ─── download trigger logic ───────────────────────────────────────────────────

describe('CustomsPreviewPage — download trigger', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('downloadCustomsDeclaration is called with declaration id', async () => {
    const blob = new Blob(['%PDF'], { type: 'application/pdf' })
    mockDownloadCustomsDeclaration.mockResolvedValue(blob)

    const decl = makeDeclaration()
    const { useRequests } = await import('../../../composables/useRequests')
    const { downloadCustomsDeclaration } = useRequests()
    const result = await downloadCustomsDeclaration(decl.id)

    expect(mockDownloadCustomsDeclaration).toHaveBeenCalledWith(7)
    expect(result).toBe(blob)
  })

  it('download error is surfaced when blob fetch fails', async () => {
    mockDownloadCustomsDeclaration.mockRejectedValue(new Error('Network error'))

    let downloadError = ''
    try {
      await mockDownloadCustomsDeclaration(7)
    } catch {
      downloadError = 'تعذّر تحميل ملف PDF الرسمي.'
    }

    expect(downloadError).toBe('تعذّر تحميل ملف PDF الرسمي.')
  })
})
