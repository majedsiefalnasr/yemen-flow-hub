import { vi, describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const mockUploadDocument = vi.fn()
const mockFetchRequestDocuments = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({
    fetchRequests: vi.fn(),
    fetchRequest: vi.fn(),
    createRequest: vi.fn(),
    updateRequest: vi.fn(),
    uploadDocument: mockUploadDocument,
    performWorkflowAction: vi.fn(),
    fetchRequestDocuments: mockFetchRequestDocuments,
    uploadSwift: vi.fn(),
    generateCustomsDeclaration: vi.fn(),
    downloadCustomsDeclaration: vi.fn(),
    fetchRequestHistory: vi.fn(),
  }),
}))

describe('requests.store uploadDocument', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('initial state has uploading=false and uploadError=null', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    expect(store.uploading).toBe(false)
    expect(store.uploadError).toBeNull()
  })

  it('sets uploading=true during upload and resets to false on success', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    let uploadingDuringCall = false
    mockUploadDocument.mockImplementation(async () => {
      uploadingDuringCall = store.uploading
    })
    mockFetchRequestDocuments.mockResolvedValue([])

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })
    await store.uploadDocument(1, file)

    expect(uploadingDuringCall).toBe(true)
    expect(store.uploading).toBe(false)
  })

  it('clears uploadError before uploading', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    store.uploadError = 'previous error'
    mockUploadDocument.mockResolvedValue(undefined)
    mockFetchRequestDocuments.mockResolvedValue([])

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })
    await store.uploadDocument(1, file)

    expect(store.uploadError).toBeNull()
  })

  it('calls uploadDocument composable with correct arguments (id, file)', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockUploadDocument.mockResolvedValue(undefined)
    mockFetchRequestDocuments.mockResolvedValue([])

    const file = new File(['data'], 'invoice.pdf', { type: 'application/pdf' })
    await store.uploadDocument(42, file)

    expect(mockUploadDocument).toHaveBeenCalledWith(42, file)
  })

  it('calls loadDocuments after successful upload to refresh the document list', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockUploadDocument.mockResolvedValue(undefined)
    mockFetchRequestDocuments.mockResolvedValue([])

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })
    await store.uploadDocument(5, file)

    expect(mockFetchRequestDocuments).toHaveBeenCalledWith(5)
  })

  it('sets uploadError and re-throws on API failure', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockUploadDocument.mockRejectedValue(new Error('Network error'))

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })

    await expect(store.uploadDocument(1, file)).rejects.toThrow()
    expect(store.uploadError).toBeTruthy()
    expect(store.uploadError).toContain('تعذّر')
  })

  it('sets uploading=false after failure (finally block)', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockUploadDocument.mockRejectedValue(new Error('fail'))

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })

    try {
      await store.uploadDocument(1, file)
    } catch {
      // expected
    }

    expect(store.uploading).toBe(false)
  })

  it('does NOT call loadDocuments when upload fails', async () => {
    const { useRequestsStore } = await import('../../../stores/requests.store')
    const store = useRequestsStore()

    mockUploadDocument.mockRejectedValue(new Error('fail'))

    const file = new File(['data'], 'test.pdf', { type: 'application/pdf' })

    try {
      await store.uploadDocument(1, file)
    } catch {
      // expected
    }

    expect(mockFetchRequestDocuments).not.toHaveBeenCalled()
  })
})
