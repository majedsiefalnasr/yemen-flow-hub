import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineFxConfirmation } from '@/composables/useEngineFxConfirmation'

const mockPost = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post: mockPost }),
}))

describe('useEngineFxConfirmation', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('declarationDownloadUrl builds the correct path', () => {
    const { declarationDownloadUrl } = useEngineFxConfirmation()
    expect(declarationDownloadUrl(42)).toBe(
      '/api/v1/engine-requests/42/customs-declaration/download',
    )
  })

  it('signedFxDownloadUrl builds the correct path', () => {
    const { signedFxDownloadUrl } = useEngineFxConfirmation()
    expect(signedFxDownloadUrl(42)).toBe(
      '/api/v1/engine-requests/42/customs-declaration/signed-fx-download',
    )
  })

  it('uploadSignedFx posts FormData with signed_document and returns declaration', async () => {
    mockPost.mockResolvedValue({
      success: true,
      data: {
        id: 1,
        declaration_number: 'FX-001',
        issued_at: '2026-07-01T10:00:00Z',
        issued_by: 1,
        issuer: { id: 1, name: 'أحمد' },
        has_signed_fx_doc: true,
        signed_fx_doc_uploaded_at: '2026-07-01T12:00:00Z',
      },
    })
    const { uploadSignedFx, uploading, error } = useEngineFxConfirmation()
    const file = new File(['pdf-data'], 'signed.pdf', { type: 'application/pdf' })

    const result = await uploadSignedFx(42, file)

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/engine-requests/42/fx-confirmation-signed',
      expect.any(FormData),
      { headers: { 'Content-Type': '' } },
    )
    expect(result.has_signed_fx_doc).toBe(true)
    expect(uploading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('uploadSignedFx sets error and re-throws on failure', async () => {
    mockPost.mockRejectedValue({
      data: { message: 'يجب أن يكون الملف بصيغة PDF فقط.' },
    })
    const { uploadSignedFx, uploading, error } = useEngineFxConfirmation()
    const file = new File(['x'], 'bad.txt', { type: 'text/plain' })

    await expect(uploadSignedFx(42, file)).rejects.toBeTruthy()
    expect(error.value).toBe('يجب أن يكون الملف بصيغة PDF فقط.')
    expect(uploading.value).toBe(false)
  })

  it('uploadSignedFx uses fallback error message when server provides none', async () => {
    mockPost.mockRejectedValue({ status: 500 })
    const { uploadSignedFx, error } = useEngineFxConfirmation()
    const file = new File(['x'], 'test.pdf', { type: 'application/pdf' })

    await expect(uploadSignedFx(42, file)).rejects.toBeTruthy()
    expect(error.value).toBe('تعذّر رفع الوثيقة الموقعة.')
  })

  it('uploadSignedFx appends file as signed_document field', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 1 } })
    const { uploadSignedFx } = useEngineFxConfirmation()
    const file = new File(['data'], 'doc.pdf', { type: 'application/pdf' })

    await uploadSignedFx(10, file)

    const call = mockPost.mock.calls[0]!
    const formData = call[1] as FormData
    expect(formData.get('signed_document')).toBe(file)
  })
})
