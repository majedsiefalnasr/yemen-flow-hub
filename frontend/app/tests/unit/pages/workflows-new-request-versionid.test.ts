// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import WorkflowsNewVersionPage from '@/pages/workflows/new-request/[versionId].vue'

const routerReplace = vi.fn().mockResolvedValue(undefined)
const routerPush = vi.fn()

vi.stubGlobal('useRoute', () => ({
  params: { versionId: '10' },
  query: {},
  path: '/workflows/new-request/10',
}))
vi.stubGlobal('useRouter', () => ({ replace: routerReplace, push: routerPush }))

const mockFetchInitialSchema = vi.fn()
const fieldGroupsRef: { value: unknown[] } = { value: [] }

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: fieldGroupsRef,
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
    fetchInitialSchema: mockFetchInitialSchema,
  }),
}))

const stubs = {
  NuxtLink: true,
  EngineRequestWizard: {
    name: 'EngineRequestWizard',
    template: '<div data-stub="wizard" />',
    props: ['workflowVersionId', 'fieldGroups'],
    emits: ['submitted'],
    data: () => ({ hasUnsavedChanges: false }),
  },
}

describe('workflows/new-request/[versionId].vue', () => {
  beforeEach(() => {
    routerReplace.mockClear()
    routerPush.mockClear()
    mockFetchInitialSchema.mockReset()
    fieldGroupsRef.value = []
  })

  it('fetches the initial schema for the version on mount', async () => {
    mockFetchInitialSchema.mockImplementation(async () => {
      fieldGroupsRef.value = [
        { id: 1, name: 'main', label: 'المعلومات', sort_order: 0, fields: [] },
      ]
    })

    mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(mockFetchInitialSchema).toHaveBeenCalledWith(10)
  })

  it('renders the wizard once the schema loads successfully', async () => {
    mockFetchInitialSchema.mockImplementation(async () => {
      fieldGroupsRef.value = [
        { id: 1, name: 'main', label: 'المعلومات', sort_order: 0, fields: [] },
      ]
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(true)
  })

  it('renders an error state when the schema fetch throws', async () => {
    mockFetchInitialSchema.mockRejectedValue({ status: 403 })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('403')
  })

  it('renders an error state when the schema resolves with no field groups', async () => {
    mockFetchInitialSchema.mockImplementation(async () => {
      fieldGroupsRef.value = []
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('500')
  })

  it('navigates to the new request on submitted', async () => {
    mockFetchInitialSchema.mockImplementation(async () => {
      fieldGroupsRef.value = [
        { id: 1, name: 'main', label: 'المعلومات', sort_order: 0, fields: [] },
      ]
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    await wrapper.findComponent({ name: 'EngineRequestWizard' }).vm.$emit('submitted', 42)
    await flushPromises()

    expect(routerReplace).toHaveBeenCalledWith('/workflows/instances/42')
  })
})
