import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useTemporaryUploads } from '@/composables/useTemporaryUploads'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDel = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, patch: vi.fn(), put: vi.fn(), del: mockDel }),
}))

describe('useTemporaryUploads', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
    mockDel.mockReset()
  })

  it('upload posts a multipart form and returns the token result', async () => {
    mockPost.mockResolvedValue({
      success: true,
      data: { token: 'tok-1', expires_at: '2026-07-15T00:00:00Z' },
    })
    const { upload } = useTemporaryUploads()
    const file = new File(['content'], 'invoice.pdf', { type: 'application/pdf' })

    const result = await upload(file, 10, 3, 'session-abc')

    expect(mockPost).toHaveBeenCalledWith('/api/v1/temporary-uploads', expect.any(FormData))
    const formData = mockPost.mock.calls[0]?.[1] as FormData
    expect(formData.get('file')).toBe(file)
    expect(formData.get('workflow_version_id')).toBe('10')
    expect(formData.get('field_id')).toBe('3')
    expect(formData.get('upload_session_token')).toBe('session-abc')
    expect(result.token).toBe('tok-1')
  })

  it('status fetches the scan status for a token', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        token: 'tok-1',
        scan_status: 'clean',
        original_name: 'invoice.pdf',
        size: 1024,
        expires_at: '2026-07-15T00:00:00Z',
      },
    })
    const { status } = useTemporaryUploads()

    const result = await status('tok-1')

    expect(mockGet).toHaveBeenCalledWith('/api/v1/temporary-uploads/tok-1')
    expect(result.scan_status).toBe('clean')
  })

  it('release deletes the temporary upload by token', async () => {
    mockDel.mockResolvedValue(undefined)
    const { release } = useTemporaryUploads()

    await release('tok-1')

    expect(mockDel).toHaveBeenCalledWith('/api/v1/temporary-uploads/tok-1')
  })
})
