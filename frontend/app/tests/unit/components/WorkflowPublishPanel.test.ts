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

function makeVersion(
  state: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED' = 'DRAFT',
  overrides: Partial<WorkflowVersion> = {},
): WorkflowVersion {
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
    ...overrides,
  }
}

async function mountPanel(state: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED' = 'DRAFT') {
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

    expect(wrapper.text()).toContain('النسخة جاهزة للنشر')
    expect(buttonByText(wrapper, 'نشر النسخة')?.attributes('disabled')).toBeUndefined()
  })

  it('reacts when the selected version changes between published, draft, and archived', async () => {
    const wrapper = await mountPanel('PUBLISHED')

    expect(buttonByText(wrapper, 'التحقق من الصحة')).toBeUndefined()

    await wrapper.setProps({ version: makeVersion('DRAFT', { id: 11, version_number: 2 }) })
    await flushPromises()
    expect(buttonByText(wrapper, 'التحقق من الصحة')).toBeDefined()

    await wrapper.setProps({ version: makeVersion('ARCHIVED', { id: 12, version_number: 3 }) })
    await flushPromises()
    expect(buttonByText(wrapper, 'التحقق من الصحة')).toBeUndefined()
  })

  it('clears validation state when the selected version changes', async () => {
    mockPost.mockResolvedValueOnce({
      data: {
        errors: [{ code: 'NO_FINAL_STAGE', target: 'stages', message: 'لا توجد مرحلة نهائية.' }],
      },
    })
    const wrapper = await mountPanel('DRAFT')

    await buttonByText(wrapper, 'التحقق من الصحة')?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('لا توجد مرحلة نهائية.')

    await wrapper.setProps({ version: makeVersion('DRAFT', { id: 11, version_number: 2 }) })
    await flushPromises()

    expect(wrapper.text()).not.toContain('لا توجد مرحلة نهائية.')
    expect(buttonByText(wrapper, 'نشر النسخة')?.attributes('disabled')).toBeDefined()
  })

  it('ignores validation results returned for a previously selected version', async () => {
    let resolveValidation!: (value: { data: { errors: [] } }) => void
    mockPost.mockReturnValueOnce(
      new Promise<{ data: { errors: [] } }>((resolve) => {
        resolveValidation = resolve
      }),
    )
    const wrapper = await mountPanel('DRAFT')

    await buttonByText(wrapper, 'التحقق من الصحة')?.trigger('click')
    await wrapper.setProps({ version: makeVersion('DRAFT', { id: 11, version_number: 2 }) })
    resolveValidation({ data: { errors: [] } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('النسخة جاهزة للنشر')
    expect(buttonByText(wrapper, 'نشر النسخة')?.attributes('disabled')).toBeDefined()
  })
})
