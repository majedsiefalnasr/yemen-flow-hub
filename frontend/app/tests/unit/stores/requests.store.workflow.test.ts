import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus, UserRole } from '../../../types/enums'
import { makeImportRequest, makeRequestDocument } from '../fixtures/request-data'

const mockPerformWorkflowAction = vi.fn()
const mockFetchRequestDocuments = vi.fn()
const mockFetchRequest = vi.fn()
const mockBankRejectTerminal = vi.fn()
const mockSupportForwardToExecutive = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: mockFetchRequest,
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: mockPerformWorkflowAction,
    fetchRequestDocuments: mockFetchRequestDocuments,
    bankRejectTerminal: mockBankRejectTerminal,
    supportForwardToExecutive: mockSupportForwardToExecutive,
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

const REQUEST_FIXTURE = makeImportRequest({
  merchant: { id: 5, name: 'شركة الأمل', commercial_register: '12345' },
  status: RequestStatus.BANK_REVIEW,
  current_owner_role: UserRole.BANK_REVIEWER,
  submitted_by: 2,
  reviewed_by: 3,
  created_at: '2026-05-15T00:00:00.000000Z',
  updated_at: '2026-05-15T00:00:00.000000Z',
  documents: [],
})

const DOCUMENT_FIXTURE = makeRequestDocument()

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
    mockPerformWorkflowAction.mockReturnValue(
      new Promise((r) => {
        resolve = r
      }),
    )

    const store = useRequestsStore()
    const first = store.performAction(42, 'bank-review')

    await expect(store.performAction(42, 'bank-approve')).rejects.toThrow(
      'إجراء قيد التنفيذ بالفعل',
    )

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
    expect(store.documents[0]!.id).toBe(10)
  })

  it('clears documents before fetching', async () => {
    mockFetchRequestDocuments.mockResolvedValue([DOCUMENT_FIXTURE])

    const store = useRequestsStore()
    store.documents = [{ ...DOCUMENT_FIXTURE, id: 99 }]
    await store.loadDocuments(42)

    expect(store.documents).toHaveLength(1)
    expect(store.documents[0]!.id).toBe(10)
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

describe('requests.store — bankRejectTerminal', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('updates currentRequest with BANK_REJECTED response', async () => {
    mockBankRejectTerminal.mockResolvedValue({
      ...REQUEST_FIXTURE,
      status: RequestStatus.BANK_REJECTED,
      bank_reject_comment: 'رفض نهائي',
    })

    const store = useRequestsStore()
    await store.bankRejectTerminal(42, 'رفض نهائي')

    expect(mockBankRejectTerminal).toHaveBeenCalledWith(42, 'رفض نهائي')
    expect(store.currentRequest?.status).toBe(RequestStatus.BANK_REJECTED)
    expect(store.performingAction).toBe(false)
  })

  it('sets store error and rethrows when bankRejectTerminal fails', async () => {
    mockBankRejectTerminal.mockRejectedValue(new Error('Forbidden'))

    const store = useRequestsStore()
    await expect(store.bankRejectTerminal(42, 'رفض نهائي')).rejects.toThrow('Forbidden')

    expect(store.error).toBe('تعذّر تنفيذ الرفض النهائي.')
    expect(store.performingAction).toBe(false)
  })
})

describe('requests.store — supportForwardToExecutive (Story 17-E.2)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('updates currentRequest with the SUPPORT_APPROVED response', async () => {
    mockSupportForwardToExecutive.mockResolvedValue({
      ...REQUEST_FIXTURE,
      status: RequestStatus.SUPPORT_APPROVED,
    })

    const store = useRequestsStore()
    await store.supportForwardToExecutive(42, 'مستوفٍ للشروط')

    expect(mockSupportForwardToExecutive).toHaveBeenCalledWith(42, 'مستوفٍ للشروط')
    expect(store.currentRequest?.status).toBe(RequestStatus.SUPPORT_APPROVED)
    expect(store.performingAction).toBe(false)
  })

  it('sets the store error and rethrows on failure', async () => {
    mockSupportForwardToExecutive.mockRejectedValue(new Error('Forbidden'))

    const store = useRequestsStore()
    await expect(store.supportForwardToExecutive(42, 'x')).rejects.toThrow('Forbidden')

    expect(store.error).toBe('تعذّر إرسال الطلب إلى اللجنة التنفيذية.')
    expect(store.performingAction).toBe(false)
  })

  it('blocks concurrent actions while one is in progress', async () => {
    let resolve!: (v: unknown) => void
    mockSupportForwardToExecutive.mockReturnValue(
      new Promise((r) => {
        resolve = r
      }),
    )

    const store = useRequestsStore()
    const first = store.supportForwardToExecutive(42, 'مستوفٍ')
    await expect(store.supportForwardToExecutive(42, 'مستوفٍ')).rejects.toThrow(
      'إجراء قيد التنفيذ بالفعل',
    )
    resolve({ ...REQUEST_FIXTURE, status: RequestStatus.SUPPORT_APPROVED })
    await first
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
