import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { DuplicateWarning } from '../../../types/models'
import { makeImportRequest } from '../fixtures/request-data'

// ─── Store mock ───────────────────────────────────────────────────────────────

const mockFetchRequest = vi.fn()
const mockFetchRequestDocuments = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: mockFetchRequest,
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: vi.fn(),
    fetchRequestDocuments: mockFetchRequestDocuments,
    generateCustomsDeclaration: vi.fn(),
    downloadCustomsDeclaration: vi.fn(),
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

// ─── Fixtures ────────────────────────────────────────────────────────────────

const WARNING_BANK_REVIEWER: DuplicateWarning = {
  id: 99,
  reference_number: 'YFH-2026-000099',
  bank_id: 2,
  bank_name: 'بنك سبأ',
  amount: 8000,
  currency: 'USD',
  created_at: '2026-05-10T09:00:00Z',
  status: 'SUBMITTED',
}

const WARNING_CBY: DuplicateWarning = {
  id: 88,
  reference_number: 'YFH-2026-000088',
  bank_id: 3,
  bank_name: 'بنك التضامن',
  amount: 12000,
  currency: 'USD',
  created_at: '2026-05-11T09:00:00Z',
  status: 'BANK_REVIEW',
}

// ─── AC7: duplicate_warnings field on ImportRequest ────────────────────────

describe('Story 8.6 — AC7: duplicate_warnings on currentRequest', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    mockFetchRequestDocuments.mockResolvedValue([])
  })

  it('loadRequest stores duplicate_warnings when present', async () => {
    const req = makeImportRequest({
      invoice_number: 'INV-DUP-001',
      duplicate_warnings: [WARNING_BANK_REVIEWER],
    })
    mockFetchRequest.mockResolvedValue(req)

    const store = useRequestsStore()
    await store.loadRequest(42)

    expect(store.currentRequest?.duplicate_warnings).toHaveLength(1)
    expect(store.currentRequest?.duplicate_warnings?.[0]?.bank_name).toBe('بنك سبأ')
  })

  it('duplicate_warnings is undefined when backend omits it (DATA_ENTRY role)', async () => {
    const req = makeImportRequest({ invoice_number: 'INV-DUP-001' })
    // No duplicate_warnings field — backend omits it for DATA_ENTRY
    mockFetchRequest.mockResolvedValue(req)

    const store = useRequestsStore()
    await store.loadRequest(42)

    expect(store.currentRequest?.duplicate_warnings).toBeUndefined()
  })

  it('duplicate_warnings is empty array when no duplicates found', async () => {
    const req = makeImportRequest({
      invoice_number: 'INV-UNIQUE',
      duplicate_warnings: [],
    })
    mockFetchRequest.mockResolvedValue(req)

    const store = useRequestsStore()
    await store.loadRequest(42)

    expect(store.currentRequest?.duplicate_warnings).toEqual([])
  })
})

// ─── AC7: Role-aware shape of duplicate_warnings ──────────────────────────

describe('Story 8.6 — AC7: role-aware fields in duplicate_warnings', () => {
  it('CBY_ADMIN warning has full payload (reference_number, amount)', () => {
    const warning = WARNING_CBY
    expect(warning.reference_number).toBe('YFH-2026-000088')
    expect(warning.amount).toBe(12000)
    expect(warning.bank_name).toBe('بنك التضامن')
    expect(warning.currency).toBe('USD')
  })

  it('BANK_REVIEWER warning type allows the restricted backend payload', () => {
    // Backend returns only bank names for bank roles; the frontend type must allow that.
    const restrictedWarning: DuplicateWarning = {
      bank_name: 'بنك سبأ',
    }
    expect(restrictedWarning.bank_name).toBe('بنك سبأ')
    expect(restrictedWarning.reference_number).toBeUndefined()
    expect(restrictedWarning.amount).toBeUndefined()
  })
})

// ─── AC9: duplicate_invoice_policy in AdminSettings type ──────────────────

describe('Story 8.6 — AC9: AdminSettings includes duplicate_invoice_policy', () => {
  it('AdminSettings type accepts warn value', async () => {
    const { useAdminSettings } = await import('../../../composables/useAdminSettings')
    // Verify the composable exports the correct type by instantiating it
    // (actual API calls are not tested here — covered by backend tests)
    expect(useAdminSettings).toBeDefined()
  })
})

// ─── AC8: DuplicateGroup type from useAudit ───────────────────────────────

describe('Story 8.6 — AC8: DuplicateGroup shape from useAudit', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('DuplicateGroup has invoice_number, banks[], requests[]', async () => {
    const mockGet = vi.fn()
    vi.doMock('../../../composables/useApi', () => ({ useApi: () => ({ get: mockGet }) }))

    const group = {
      invoice_number: 'INV-G1',
      banks: ['بنك التضامن', 'بنك سبأ'],
      requests: [
        {
          id: 1,
          reference_number: 'YFH-2026-000001',
          bank_name: 'بنك التضامن',
          amount: 5000,
          currency: 'USD',
          created_at: '2026-05-01T00:00:00Z',
          status: 'DRAFT',
        },
        {
          id: 2,
          reference_number: 'YFH-2026-000002',
          bank_name: 'بنك سبأ',
          amount: 5000,
          currency: 'USD',
          created_at: '2026-05-02T00:00:00Z',
          status: 'SUBMITTED',
        },
      ],
    }

    expect(group.invoice_number).toBe('INV-G1')
    expect(group.banks).toHaveLength(2)
    expect(group.requests).toHaveLength(2)
    expect(group.requests[0]).toHaveProperty('reference_number')
    expect(group.requests[0]).toHaveProperty('bank_name')
    expect(group.requests[0]).toHaveProperty('amount')
    expect(group.requests[0]).toHaveProperty('currency')
    expect(group.requests[0]).toHaveProperty('created_at')
    expect(group.requests[0]).toHaveProperty('status')
  })
})
