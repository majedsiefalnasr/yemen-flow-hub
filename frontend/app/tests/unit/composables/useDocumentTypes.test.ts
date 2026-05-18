import { vi, describe, it, expect, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: mockPut }),
}))

const { useDocumentTypes } = await import('../../../composables/useDocumentTypes')

const DOC_TYPE_FIXTURE = {
  id: 1,
  slug: 'commercial_invoice',
  name_ar: 'الفاتورة التجارية',
  name_en: 'Commercial Invoice',
  is_required: true,
  is_active: true,
  sort_order: 1,
}

describe('useDocumentTypes — fetchDocumentTypes', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('calls GET /api/document-types', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: [DOC_TYPE_FIXTURE] })
    const { fetchDocumentTypes } = useDocumentTypes()
    await fetchDocumentTypes()
    expect(mockGet).toHaveBeenCalledWith('/api/document-types')
  })

  it('returns array of document types', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: [DOC_TYPE_FIXTURE] })
    const { fetchDocumentTypes } = useDocumentTypes()
    const result = await fetchDocumentTypes()
    expect(result).toHaveLength(1)
    expect(result[0].slug).toBe('commercial_invoice')
    expect(result[0].name_ar).toBe('الفاتورة التجارية')
    expect(result[0].is_required).toBe(true)
  })

  it('returns empty array when no document types exist', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: [] })
    const { fetchDocumentTypes } = useDocumentTypes()
    const result = await fetchDocumentTypes()
    expect(result).toEqual([])
  })

  it('propagates API error', async () => {
    mockGet.mockRejectedValueOnce(new Error('Server error'))
    const { fetchDocumentTypes } = useDocumentTypes()
    await expect(fetchDocumentTypes()).rejects.toThrow('Server error')
  })
})

describe('useDocumentTypes — createDocumentType', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('posts to /api/document-types and returns created type', async () => {
    mockPost.mockResolvedValueOnce({ success: true, data: DOC_TYPE_FIXTURE })
    const { createDocumentType } = useDocumentTypes()
    const result = await createDocumentType({
      slug: 'commercial_invoice',
      name_ar: 'الفاتورة التجارية',
      name_en: 'Commercial Invoice',
    })
    expect(mockPost).toHaveBeenCalledWith('/api/document-types', expect.objectContaining({
      slug: 'commercial_invoice',
      name_ar: 'الفاتورة التجارية',
    }))
    expect(result.id).toBe(1)
  })

  it('propagates validation error on duplicate slug', async () => {
    mockPost.mockRejectedValueOnce({ data: { errors: { slug: ['The slug has already been taken.'] } } })
    const { createDocumentType } = useDocumentTypes()
    await expect(createDocumentType({ slug: 'commercial_invoice', name_ar: 'x', name_en: 'x' })).rejects.toBeTruthy()
  })
})

describe('useDocumentTypes — updateDocumentType', () => {
  beforeEach(() => {
    mockPut.mockReset()
  })

  it('puts to /api/document-types/{id} and returns updated type', async () => {
    const updated = { ...DOC_TYPE_FIXTURE, is_active: false }
    mockPut.mockResolvedValueOnce({ success: true, data: updated })
    const { updateDocumentType } = useDocumentTypes()
    const result = await updateDocumentType(1, { is_active: false })
    expect(mockPut).toHaveBeenCalledWith('/api/document-types/1', expect.objectContaining({ is_active: false }))
    expect(result.is_active).toBe(false)
  })

  it('propagates 403 when user lacks docrules.manage permission', async () => {
    mockPut.mockRejectedValueOnce({ status: 403, data: { message: 'Forbidden' } })
    const { updateDocumentType } = useDocumentTypes()
    await expect(updateDocumentType(1, { is_active: false })).rejects.toBeTruthy()
  })
})
