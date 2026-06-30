import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'

const mockPost = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post: mockPost }),
}))

describe('useEngineRequestActions', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('executeAction posts transition payload and returns updated instance', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 5, version: 2 } })
    const { executeAction } = useEngineRequestActions()

    const result = await executeAction(5, 12, 'تمت الموافقة', { invoice_amount: 100 }, 1)

    expect(mockPost).toHaveBeenCalledWith('/api/v1/engine-requests/5/actions', {
      transition_id: 12,
      comment: 'تمت الموافقة',
      data: { invoice_amount: 100 },
      version: 1,
    })
    expect(result.version).toBe(2)
  })

  it('sets conflictError on 409 response', async () => {
    mockPost.mockRejectedValue({ status: 409, data: { message: 'stale' } })
    const { executeAction, conflictError } = useEngineRequestActions()

    await expect(executeAction(5, 12, null, {}, 1)).rejects.toBeTruthy()
    expect(conflictError.value).toBe(true)
  })

  it('sets fieldErrors on 422 response', async () => {
    mockPost.mockRejectedValue({
      status: 422,
      data: { errors: { invoice_amount: ['This field is required.'] } },
    })
    const { executeAction, fieldErrors } = useEngineRequestActions()

    await expect(executeAction(5, 12, null, {}, 1)).rejects.toBeTruthy()
    expect(fieldErrors.value.invoice_amount).toBe('This field is required.')
  })
})
