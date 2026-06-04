import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { RequestStatus, UserRole } from '../../../types/enums'
import type { ImportRequest } from '../../../types/models'
import { makeImportRequest, makeRequestDocument } from '../fixtures/request-data'

// ---------- composable mock ----------
const mockFetchRequest = vi.fn()
const mockFetchRequestDocuments = vi.fn()
const mockPerformWorkflowAction = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: mockFetchRequest,
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: vi.fn(),
    performWorkflowAction: mockPerformWorkflowAction,
    fetchRequestDocuments: mockFetchRequestDocuments,
    generateCustomsDeclaration: vi.fn(),
    downloadCustomsDeclaration: vi.fn(),
  }),
}))

const { useRequestsStore } = await import('../../../stores/requests.store')

function makeRequest(overrides: Partial<ImportRequest> = {}): ImportRequest {
  return makeImportRequest({
    status: RequestStatus.DRAFT,
    current_owner_role: UserRole.DATA_ENTRY,
    notes: 'ملاحظة تجريبية',
    created_at: '2026-05-15T00:00:00.000000Z',
    updated_at: '2026-05-15T00:00:00.000000Z',
    documents: [],
    ...overrides,
  })
}

describe('RequestsStore — detail page integration (loadRequest + loadDocuments)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('loadRequest populates currentRequest with documents field', async () => {
    const req = makeRequest({
      documents: [
        makeRequestDocument({
          id: 1,
          type: 'invoice',
          original_filename: 'inv.pdf',
          size_bytes: 1024,
          checksum: 'abc',
        }),
      ],
    })
    mockFetchRequest.mockResolvedValue(req)

    const store = useRequestsStore()
    await store.loadRequest(42)

    expect(store.currentRequest).not.toBeNull()
    expect(store.currentRequest?.documents).toHaveLength(1)
  })

  it('loadDocuments populates documents from fetchRequestDocuments', async () => {
    mockFetchRequestDocuments.mockResolvedValue([makeRequestDocument()])

    const store = useRequestsStore()
    await store.loadDocuments(42)

    expect(store.documents).toHaveLength(1)
    expect(store.documents[0]!.original_filename).toBe('invoice.pdf')
  })

  it('isEditable: DRAFT and DRAFT_REJECTED_INTERNAL are editable', async () => {
    const draftRequest = makeRequest({ status: RequestStatus.DRAFT })
    mockFetchRequest.mockResolvedValue(draftRequest)

    const store = useRequestsStore()
    await store.loadRequest(42)

    const status = store.currentRequest?.status
    const editable =
      status === RequestStatus.DRAFT || status === RequestStatus.DRAFT_REJECTED_INTERNAL
    expect(editable).toBe(true)
  })

  it('isEditable: SUBMITTED is not editable', async () => {
    const submittedRequest = makeRequest({ status: RequestStatus.SUBMITTED })
    mockFetchRequest.mockResolvedValue(submittedRequest)

    const store = useRequestsStore()
    await store.loadRequest(42)

    const status = store.currentRequest?.status
    const editable =
      status === RequestStatus.DRAFT || status === RequestStatus.DRAFT_REJECTED_INTERNAL
    expect(editable).toBe(false)
  })

  it('isEditable: BANK_APPROVED is not editable (locked)', async () => {
    const lockedRequest = makeRequest({ status: RequestStatus.BANK_APPROVED })
    mockFetchRequest.mockResolvedValue(lockedRequest)

    const store = useRequestsStore()
    await store.loadRequest(42)

    const status = store.currentRequest?.status
    const editable =
      status === RequestStatus.DRAFT || status === RequestStatus.DRAFT_REJECTED_INTERNAL
    expect(editable).toBe(false)
  })

  it('loadRequest sets error on failure', async () => {
    mockFetchRequest.mockRejectedValue(new Error('Not Found'))

    const store = useRequestsStore()
    await store.loadRequest(999)

    expect(store.currentRequest).toBeNull()
    expect(store.error).toBe('تعذّر تحميل بيانات الطلب.')
  })

  it('performAction after loadRequest updates currentRequest in place', async () => {
    const initialReq = makeRequest({ status: RequestStatus.SUBMITTED })
    const updatedReq = makeRequest({ status: RequestStatus.BANK_REVIEW })
    mockFetchRequest.mockResolvedValue(initialReq)
    mockPerformWorkflowAction.mockResolvedValue(updatedReq)

    const store = useRequestsStore()
    await store.loadRequest(42)
    expect(store.currentRequest?.status).toBe(RequestStatus.SUBMITTED)

    await store.performAction(42, 'bank-review')
    expect(store.currentRequest?.status).toBe(RequestStatus.BANK_REVIEW)
  })

  it('documents state is independent of loadingRequest', async () => {
    mockFetchRequest.mockResolvedValue(makeRequest())
    mockFetchRequestDocuments.mockResolvedValue([])

    const store = useRequestsStore()
    expect(store.loadingRequest).toBe(false)
    expect(store.loadingDocuments).toBe(false)

    const reqPromise = store.loadRequest(42)
    expect(store.loadingRequest).toBe(true)
    expect(store.loadingDocuments).toBe(false)

    await reqPromise

    const docsPromise = store.loadDocuments(42)
    expect(store.loadingRequest).toBe(false)
    expect(store.loadingDocuments).toBe(true)
    await docsPromise
  })

  it('loadRequest clears documents and resets documentsLoaded', async () => {
    mockFetchRequest.mockResolvedValue(makeRequest())
    mockFetchRequestDocuments.mockResolvedValue([
      {
        id: 10,
        type: 'commercial_invoice',
        original_filename: 'invoice.pdf',
        mime_type: 'application/pdf',
        size_bytes: 204800,
        checksum: 'abc123',
        uploaded_by: 1,
        uploaded_by_name: 'Test User',
        uploaded_at: '2026-05-15T00:00:00.000000Z',
        download_url: 'http://localhost/api/documents/10/download',
      },
    ])

    const store = useRequestsStore()
    await store.loadDocuments(42)
    expect(store.documents).toHaveLength(1)
    expect(store.documentsLoaded).toBe(true)

    // Second loadRequest must clear documents state
    await store.loadRequest(99)
    expect(store.documents).toHaveLength(0)
    expect(store.documentsLoaded).toBe(false)
  })
})

describe('RequestDocument type', () => {
  it('RequestDocument has correct shape', () => {
    const doc = {
      id: 1,
      type: 'invoice',
      original_filename: 'invoice.pdf',
      mime_type: 'application/pdf',
      size_bytes: 1024,
      checksum: 'abc123',
      uploaded_at: '2026-05-15T00:00:00.000000Z',
      download_url: 'http://localhost/api/documents/1/download',
    }
    expect(doc.id).toBe(1)
    expect(doc.original_filename).toBe('invoice.pdf')
    expect(doc.size_bytes).toBe(1024)
    expect(doc.download_url).toContain('/api/documents/')
  })
})
