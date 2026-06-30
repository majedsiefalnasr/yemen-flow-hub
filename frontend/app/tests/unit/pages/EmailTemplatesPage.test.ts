// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { NotificationTemplateType } from '../../../types/notifications'
import { UserRole } from '../../../types/enums'

vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('useHead', vi.fn())

const routerPush = vi.hoisted(() => vi.fn())
const fetchTemplatesMock = vi.hoisted(() => vi.fn())
const fetchTemplateMock = vi.hoisted(() => vi.fn())
const updateTemplateMock = vi.hoisted(() => vi.fn())
const previewTemplateMock = vi.hoisted(() => vi.fn())
const extractFieldErrorsMock = vi.hoisted(() => vi.fn(() => ({})))
const extractMessageMock = vi.hoisted(() => vi.fn((_err: unknown, fallback: string) => fallback))
const toastSuccessMock = vi.hoisted(() => vi.fn())
const toastErrorMock = vi.hoisted(() => vi.fn())

vi.stubGlobal('useRouter', () => ({ push: routerPush }))
vi.stubGlobal('useRoute', () => ({ params: { type: NotificationTemplateType.REQUEST_APPROVED } }))

vi.mock('../../../composables/useEmailTemplates', () => ({
  useEmailTemplates: () => ({
    fetchTemplates: fetchTemplatesMock,
    fetchTemplate: fetchTemplateMock,
    updateTemplate: updateTemplateMock,
    previewTemplate: previewTemplateMock,
    extractFieldErrors: extractFieldErrorsMock,
    extractMessage: extractMessageMock,
  }),
}))

vi.mock('../../../composables/use-toast', () => ({
  useToast: () => ({
    success: toastSuccessMock,
    error: toastErrorMock,
  }),
}))

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 1, role: UserRole.CBY_ADMIN, name: 'مدير النظام' },
    isCbyAdmin: true,
  }),
}))

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
    changed_at: '2026-06-06T12:00:00.000Z',
  },
  versions: [
    {
      id: 1,
      changed_by: null,
      changed_by_name: null,
      changed_at: '2026-06-06T12:00:00.000Z',
      is_active_version: true,
    },
  ],
}

describe('admin email templates pages', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchTemplatesMock.mockResolvedValue([TEMPLATE_FIXTURE])
    fetchTemplateMock.mockResolvedValue(TEMPLATE_FIXTURE)
    updateTemplateMock.mockResolvedValue({
      ...TEMPLATE_FIXTURE,
      active: { ...TEMPLATE_FIXTURE.active, subject: 'موضوع جديد' },
    })
    previewTemplateMock.mockResolvedValue({
      source: { subject: 'موضوع', body: 'نص {{reference_number}}' },
      rendered: {
        subject: 'موضوع',
        html: '<html dir="rtl"><body>YFH-2026-000123</body></html>',
        text: 'YFH-2026-000123',
        source: 'preview',
        template_version_id: null,
        locale: 'ar',
      },
    })
  })

  it('lists editable templates and links to edit', async () => {
    const page = (await import('../../../pages/admin/email-templates/index.vue')).default
    const wrapper = mount(page, {
      global: { stubs: { PageHeader: true } },
    })

    await flushPromises()

    expect(fetchTemplatesMock).toHaveBeenCalled()
    expect(wrapper.text()).toContain('إشعار موافقة الطلب')
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('تحرير'))
      ?.trigger('click')
    expect(routerPush).toHaveBeenCalledWith('/admin/email-templates/REQUEST_APPROVED')
  })

  it('loads the editor, gates save until dirty, inserts variables, previews, and saves', async () => {
    const page = (await import('../../../pages/admin/email-templates/[type].vue')).default
    const wrapper = mount(page, {
      attachTo: document.body,
      global: { stubs: { PageHeader: true } },
    })

    await flushPromises()

    expect(fetchTemplateMock).toHaveBeenCalledWith(NotificationTemplateType.REQUEST_APPROVED)
    expect(wrapper.text()).toContain('محتوى القالب')

    const saveBeforeDirty = wrapper
      .findAll('button')
      .find((button) => button.text().includes('حفظ إصدار جديد'))
    expect(saveBeforeDirty?.attributes('disabled')).toBeDefined()

    const textarea = wrapper.find('textarea')
    await textarea.setValue('مرحبا ')
    const variableButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('{{reference_number}}'))
    await variableButton?.trigger('click')

    expect((textarea.element as HTMLTextAreaElement).value).toContain('{{reference_number}}')

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('معاينة'))
      ?.trigger('click')
    await flushPromises()
    expect(previewTemplateMock).toHaveBeenCalledWith(NotificationTemplateType.REQUEST_APPROVED, {
      subject: 'موافقة {{reference_number}}',
      body: expect.stringContaining('{{reference_number}}'),
    })
    expect(wrapper.text()).toContain('المصدر المنظف')
    expect(wrapper.text()).toContain('البريد المرئي')

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('حفظ إصدار جديد'))
      ?.trigger('click')
    await flushPromises()

    expect(updateTemplateMock).toHaveBeenCalled()
    expect(toastSuccessMock).toHaveBeenCalledWith('تم حفظ القالب كإصدار جديد.')
  })
})
