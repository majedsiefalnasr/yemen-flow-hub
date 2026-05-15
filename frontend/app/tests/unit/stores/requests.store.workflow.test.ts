import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus } from '../../../types/enums'

const mockPerformWorkflowAction = vi.fn()
const mockFetchRequestDocuments = vi.fn()
const mockFetchRequest = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: mockFetchRequest,
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: mockPerformWorkflowAction,
    fetchRequestDocuments: mockFetchRequestDocuments,
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

const REQUEST_FIXTURE = {
  id: 42,
  reference_number: 'YFH-2026-000042',
  bank_id: 1,
  bank_name: 'بنك اليمن',
  merchant: { id: 5, name: 'شركة الأمل', commercial_register: '12345' },
  status: RequestStatus.BANK_REVIEW,
  current_owner_role: 'BANK_REVIEWER',
  currency: 'USD',
  amount: 50000,
  supplier_name: 'ACME Corp',
  goods_description: 'Electronics',
  port_of_entry: 'Aden',
  notes: null,
  created_by: 1,
  submitted_by: 2,
  reviewed_by: 3,
  approved_by: null,
  rejected_by: null,
  resubmitted_by: null,
  claimed_by: null,
  claimed_until: null,
  is_claimed: false,
  is_claimed_by_me: false,
  can_be_claimed: false,
  submitted_at: null,
  bank_approved_at: null,
  support_approved_at: null,
  swift_uploaded_at: null,
  executive_decided_at: null,
  customs_issued_at: null,
  revision_count: 0,
  created_at: '2026-05-15T00:00:00.000000Z',
  updated_at: '2026-05-15T00:00:00.000000Z',
  documents: [],
}

const DOCUMENT_FIXTURE = {
  id: 10,
  type: 'commercial_invoice',
  original_filename: 'invoice.pdf',
  mime_type: 'application/pdf',
  size_bytes: 204800,
  checksum: 'abc123',
  uploaded_at: '2026-05-15T00:00:00.000000Z',
  download_url: 'http://localhost/api/documents/10/download',
}

describe('requests.store — performAction', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('sets performingAction=true during call, false after', async () => {
    let seenDuring = false
    mockPerformWorkflowAction.mockImplementation(async () => {
      seenDuring = true
      return { ...REQUEST_FIXTURE, status: RequestStatus.BANK_REVIEW }
    })

    const store = useRequestsStore()
    const promise = store.performAction(42, 'bank-review')
    expect(store.performingAction).toBe(true)
    await promise
    expect(store.performingAction).toBe(false)
    expect(seenDuring).toBe(true)
  })

  it('updates currentRequest with API response on success', async () => {
    const updated = { ...REQUEST_FIXTURE, status: RequestStatus.BANK_APPROVED }
    mockPerformWorkflowAction.mockResolvedValue(updated)

    const store = useRequestsStore()
    await store.performAction(42, 'bank-approve')

    expect(store.currentRequest?.status).toBe(RequestStatus.BANK_APPROVED)
    expect(store.performingAction).toBe(false)
  })

  it('passes reason to performWorkflowAction', async () => {
    mockPerformWorkflowAction.mockResolvedValue(REQUEST_FIXTURE)

    const store = useRequestsStore()
    await store.performAction(42, 'bank-reject', 'مستندات ناقصة')

    expect(mockPerformWorkflowAction).toHaveBeenCalledWith(42, 'bank-reject', 'مستندات ناقصة')
  })

  it('sets error and rethrows on failure', async () => {
    mockPerformWorkflowAction.mockRejectedValue(new Error('Forbidden'))

    const store = useRequestsStore()
    await expect(store.performAction(42, 'bank-approve')).rejects.toThrow()
    expect(store.error).toBe('تعذّر تنفيذ الإجراء.')
    expect(store.performingAction).toBe(false)
  })

  it('throws immediately if another action is in progress', async () => {
    let resolve!: (v: unknown) => void
    mockPerformWorkflowAction.mockReturnValue(new Promise(r => { resolve = r }))

    const store = useRequestsStore()
    const first = store.performAction(42, 'bank-review')

    await expect(store.performAction(42, 'bank-approve')).rejects.toThrow('إجراء قيد التنفيذ بالفعل')

    resolve(REQUEST_FIXTURE)
    await first
  })
})

describe('requests.store — loadDocuments', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('sets loadingDocuments=true during fetch, false after', async () => {
    let seenDuring = false
    mockFetchRequestDocuments.mockImplementation(async () => {
      seenDuring = true
      return [DOCUMENT_FIXTURE]
    })

    const store = useRequestsStore()
    const promise = store.loadDocuments(42)
    expect(store.loadingDocuments).toBe(true)
    await promise
    expect(store.loadingDocuments).toBe(false)
    expect(seenDuring).toBe(true)
  })

  it('populates documents array on success', async () => {
    mockFetchRequestDocuments.mockResolvedValue([DOCUMENT_FIXTURE])

    const store = useRequestsStore()
    await store.loadDocuments(42)

    expect(store.documents).toHaveLength(1)
    expect(store.documents[0].id).toBe(10)
  })

  it('clears documents before fetching', async () => {
    mockFetchRequestDocuments.mockResolvedValue([DOCUMENT_FIXTURE])

    const store = useRequestsStore()
    store.documents = [{ ...DOCUMENT_FIXTURE, id: 99 }]
    await store.loadDocuments(42)

    expect(store.documents).toHaveLength(1)
    expect(store.documents[0].id).toBe(10)
  })

  it('keeps documents empty on failure without re-throwing', async () => {
    mockFetchRequestDocuments.mockRejectedValue(new Error('Server error'))

    const store = useRequestsStore()
    await expect(store.loadDocuments(42)).resolves.toBeUndefined()
    expect(store.documents).toEqual([])
    expect(store.loadingDocuments).toBe(false)
  })

  it('sets documentsError on failure', async () => {
    mockFetchRequestDocuments.mockRejectedValue(new Error('Server error'))

    const store = useRequestsStore()
    await store.loadDocuments(42)

    expect(store.documentsError).toBe('تعذّر تحميل المستندات. يرجى المحاولة مرة أخرى.')
    expect(store.documentsLoaded).toBe(false)
  })

  it('sets documentsLoaded=true on success', async () => {
    mockFetchRequestDocuments.mockResolvedValue([DOCUMENT_FIXTURE])

    const store = useRequestsStore()
    await store.loadDocuments(42)

    expect(store.documentsLoaded).toBe(true)
    expect(store.documentsError).toBeNull()
  })
})

describe('requests.store — initial state', () => {
  it('exposes documents, loadingDocuments, performingAction in state', () => {
    setActivePinia(createPinia())
    const store = useRequestsStore()
    expect(store.documents).toEqual([])
    expect(store.loadingDocuments).toBe(false)
    expect(store.performingAction).toBe(false)
    expect(store.documentsLoaded).toBe(false)
    expect(store.documentsError).toBeNull()
  })
})
