// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EngineFxConfirmationPanel from '@/components/workflow/EngineFxConfirmationPanel.vue'
import type { EngineFxPanelCapabilities, CustomsDeclarationSummary } from '@/types/models'

// Mock vue-sonner
vi.mock('vue-sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}))

// Mock useApi (composable uses it but we test download via window.open)
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ post: vi.fn() }),
}))

const baseCapabilities: EngineFxPanelCapabilities = {
  visible: true,
  can_upload_signed_fx: false,
  can_download_declaration: false,
  can_download_signed_fx: false,
}

const baseDeclaration: CustomsDeclarationSummary = {
  id: 1,
  declaration_number: 'FX-2026-001',
  issued_at: '2026-07-01T10:00:00Z',
  issued_by: 1,
  issuer: { id: 1, name: 'أحمد محمد' },
  has_signed_fx_doc: false,
}

function mountPanel(options: {
  capabilities?: Partial<EngineFxPanelCapabilities>
  declaration?: CustomsDeclarationSummary | null
}) {
  const caps = { ...baseCapabilities, ...options.capabilities }
  return mount(EngineFxConfirmationPanel, {
    props: {
      requestId: 42,
      capabilities: caps,
      declaration:
        'declaration' in options
          ? (options.declaration as CustomsDeclarationSummary | null)
          : baseDeclaration,
    },
    attrs: { dir: 'rtl' },
  })
}

describe('EngineFxConfirmationPanel', () => {
  it('renders the panel heading with correct Arabic copy', () => {
    const wrapper = mountPanel({})
    expect(wrapper.text()).toContain('تأكيد المصارفة الخارجية')
  })

  it('shows declaration number when declaration exists', () => {
    const wrapper = mountPanel({})
    expect(wrapper.text()).toContain('FX-2026-001')
  })

  it('shows issuer name when declaration has issuer', () => {
    const wrapper = mountPanel({})
    expect(wrapper.text()).toContain('أحمد محمد')
  })

  it('shows pending badge when signed doc not uploaded', () => {
    const wrapper = mountPanel({ declaration: baseDeclaration })
    expect(wrapper.text()).toContain('لم تُرفَع بعد')
  })

  it('shows uploaded badge when signed doc exists', () => {
    const wrapper = mountPanel({
      declaration: {
        ...baseDeclaration,
        has_signed_fx_doc: true,
        signed_fx_doc_uploaded_at: '2026-07-02T08:00:00Z',
      },
    })
    expect(wrapper.text()).toContain('مرفوعة')
  })

  it('shows "no declaration" message when declaration is null', () => {
    const wrapper = mountPanel({ declaration: null })
    expect(wrapper.text()).toContain('لم يُصدَر إيصال المصارفة الخارجية بعد.')
  })

  it('renders download declaration button when capability is true', () => {
    const wrapper = mountPanel({ capabilities: { can_download_declaration: true } })
    // Button text contains the Arabic label
    const buttons = wrapper.findAll('button')
    const downloadBtn = buttons.find((b) => b.text().includes('تنزيل الإيصال'))
    expect(downloadBtn).toBeTruthy()
  })

  it('does not render download declaration button when capability is false', () => {
    const wrapper = mountPanel({ capabilities: { can_download_declaration: false } })
    const buttons = wrapper.findAll('button')
    const downloadBtn = buttons.find((b) => b.text().includes('تنزيل الإيصال'))
    expect(downloadBtn).toBeUndefined()
  })

  it('renders download signed FX button when capability is true', () => {
    const wrapper = mountPanel({ capabilities: { can_download_signed_fx: true } })
    const buttons = wrapper.findAll('button')
    const downloadBtn = buttons.find((b) => b.text().includes('تنزيل الموقّعة'))
    expect(downloadBtn).toBeTruthy()
  })

  it('renders upload button when capability is true', () => {
    const wrapper = mountPanel({ capabilities: { can_upload_signed_fx: true } })
    const buttons = wrapper.findAll('button')
    const uploadBtn = buttons.find((b) => b.text().includes('رفع الوثيقة الموقّعة'))
    expect(uploadBtn).toBeTruthy()
  })

  it('does not render upload button when capability is false', () => {
    const wrapper = mountPanel({ capabilities: { can_upload_signed_fx: false } })
    const buttons = wrapper.findAll('button')
    const uploadBtn = buttons.find((b) => b.text().includes('رفع الوثيقة الموقّعة'))
    expect(uploadBtn).toBeUndefined()
  })
})
