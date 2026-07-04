// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { WorkflowVersion } from '../../../types/models'
import { useAuthStore } from '../../../stores/auth.store'

const mockPost = vi.fn()
const mockGet = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const WorkflowPublishPanel = (await import('../../../components/workflow/WorkflowPublishPanel.vue'))
  .default

function makeVersion(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT'): WorkflowVersion {
  return {
    id: 10,
    workflow_definition_id: 1,
    version_number: 1,
    state,
    is_editable: state === 'DRAFT',
    published_at: null,
    created_at: null,
    updated_at: null,
    version: 1,
  }
}

async function mountPanel(state: 'DRAFT' | 'PUBLISHED' = 'DRAFT') {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { workflow_designer: ['VIEW', 'MANAGE'] }

  const wrapper = mount(WorkflowPublishPanel, {
    props: { version: makeVersion(state) },
    global: { plugins: [pinia], stubs: { Teleport: true, NuxtLink: true } },
  })
  await flushPromises()
  return wrapper
}

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((b) => b.text().trim().includes(text))
}

describe('WorkflowPublishPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders nothing for a non-DRAFT version', async () => {
    const wrapper = await mountPanel('PUBLISHED')

    expect(buttonByText(wrapper, 'التحقق من الصحة')).toBeUndefined()
  })

  it('disables publish until validation runs clean', async () => {
    const wrapper = await mountPanel('DRAFT')

    const publishBtn = buttonByText(wrapper, 'نشر النسخة')
    expect(publishBtn?.attributes('disabled')).toBeDefined()
  })

  it('shows the error list when validation returns errors and keeps publish disabled', async () => {
    mockPost.mockResolvedValueOnce({
      data: {
        errors: [{ code: 'NO_FINAL_STAGE', target: 'stages', message: 'لا توجد مرحلة نهائية.' }],
      },
    })
    const wrapper = await mountPanel('DRAFT')

    await buttonByText(wrapper, 'التحقق من الصحة')?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('لا توجد مرحلة نهائية.')
    expect(buttonByText(wrapper, 'نشر النسخة')?.attributes('disabled')).toBeDefined()
  })

  it('enables publish when validation returns no errors', async () => {
    mockPost.mockResolvedValueOnce({ data: { errors: [] } })
    const wrapper = await mountPanel('DRAFT')

    await buttonByText(wrapper, 'التحقق من الصحة')?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('النسخة صالحة')
    expect(buttonByText(wrapper, 'نشر النسخة')?.attributes('disabled')).toBeUndefined()
  })
})
