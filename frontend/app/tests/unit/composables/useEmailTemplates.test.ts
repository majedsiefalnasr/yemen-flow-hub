import { describe, expect, it, vi, beforeEach } from 'vitest'
import { NotificationTemplateType } from '../../../types/notifications'

const mockGet = vi.fn()
const mockPut = vi.fn()
const mockPost = vi.fn()
const mockIsApiError = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    put: mockPut,
    post: mockPost,
    isApiError: mockIsApiError,
  }),
}))

const { useEmailTemplates } = await import('../../../composables/useEmailTemplates')

const TEMPLATE_FIXTURE = {
  type: NotificationTemplateType.REQUEST_APPROVED,
  admin_editable: true,
  is_active: true,
  allowed_variables: ['reference_number', 'user_name'],
  active: {
    id: 1,
    subject: 'موافقة {{reference_number}}',
    body: 'مرحبا {{user_name}}',
    changed_by: null,
    changed_by_name: null,
    changed_at: null,
  },
  versions: [],
}

describe('useEmailTemplates', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPut.mockReset()
    mockPost.mockReset()
    mockIsApiError.mockReset()
  })

  it('fetches editable templates from the admin API', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: [TEMPLATE_FIXTURE] })

    const { fetchTemplates } = useEmailTemplates()
    const templates = await fetchTemplates()

    expect(mockGet).toHaveBeenCalledWith('/api/admin/notification-templates')
    expect(templates).toEqual([TEMPLATE_FIXTURE])
  })

  it('fetches one template by type', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: TEMPLATE_FIXTURE })

    const { fetchTemplate } = useEmailTemplates()
    await fetchTemplate(NotificationTemplateType.REQUEST_APPROVED)

    expect(mockGet).toHaveBeenCalledWith('/api/admin/notification-templates/REQUEST_APPROVED')
  })

  it('updates a template through PUT', async () => {
    mockPut.mockResolvedValueOnce({ success: true, data: TEMPLATE_FIXTURE })

    const { updateTemplate } = useEmailTemplates()
    await updateTemplate(NotificationTemplateType.REQUEST_APPROVED, {
      subject: 'موضوع',
      body: 'نص',
    })

    expect(mockPut).toHaveBeenCalledWith('/api/admin/notification-templates/REQUEST_APPROVED', {
      subject: 'موضوع',
      body: 'نص',
    })
  })

  it('requests a preview through POST', async () => {
    mockPost.mockResolvedValueOnce({
      success: true,
      data: {
        source: { subject: 'موضوع', body: 'نص' },
        rendered: {
          subject: 'موضوع',
          html: '<html dir="rtl"></html>',
          text: 'نص',
          source: 'preview',
          template_version_id: null,
          locale: 'ar',
        },
      },
    })

    const { previewTemplate } = useEmailTemplates()
    await previewTemplate(NotificationTemplateType.REQUEST_APPROVED, {
      subject: 'موضوع',
      body: 'نص',
    })

    expect(mockPost).toHaveBeenCalledWith(
      '/api/admin/notification-templates/REQUEST_APPROVED/preview',
      { subject: 'موضوع', body: 'نص' },
    )
  })
})
