import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDel = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDel }),
}))

describe('useEngineRequestDocuments', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockDel.mockReset()
  })

  it('fetchDocuments populates documents', async () => {
    mockGet.mockResolvedValue({ success: true, data: [{ id: 1, original_name: 'a.pdf' }] })
    const { documents, fetchDocuments } = useEngineRequestDocuments()

    await fetchDocuments(5)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/engine-requests/5/documents')
    expect(documents.value).toHaveLength(1)
  })

  it('upload posts FormData with file and field_id', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 2, original_name: 'b.pdf' } })
    const { upload } = useEngineRequestDocuments()
    const file = new File(['x'], 'b.pdf', { type: 'application/pdf' })

    await upload(5, file, 3)

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/engine-requests/5/documents',
      expect.any(FormData),
    )
  })

  it('remove deletes a document by id', async () => {
    mockDel.mockResolvedValue({ success: true })
    const { remove } = useEngineRequestDocuments()

    await remove(5, 2)

    expect(mockDel).toHaveBeenCalledWith('/api/v1/engine-requests/5/documents/2')
  })

  it('downloadUrl builds the correct path', () => {
    const { downloadUrl } = useEngineRequestDocuments()
    expect(downloadUrl(5, 2)).toBe('/api/v1/engine-requests/5/documents/2/download')
  })
})
