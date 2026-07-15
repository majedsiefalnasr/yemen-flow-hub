// @vitest-environment jsdom
//
// Dedicated file (not folded into workflows-new-request-versionid.test.ts) so
// only useApi is mocked — useEngineFormSchema runs for real here, proving the
// page's error state is driven by fetchInitialSchema's actual rethrow (see
// useEngineFormSchema.ts) rather than a hand-rolled rejected composable mock
// that could pass even if the rethrow wiring were removed.
import { describe, expect, it, vi } from 'vitest'
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

const mockApiGet = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockApiGet, post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() }),
}))

const stubs = {
  NuxtLink: true,
  EngineRequestWizard: { name: 'EngineRequestWizard', template: '<div data-stub="wizard" />' },
}

describe('workflows/new-request/[versionId].vue — real useEngineFormSchema failure propagation', () => {
  it('renders the coded error state when the real composable rethrows', async () => {
    mockApiGet.mockRejectedValue({ status: 403, data: { message: 'ممنوع' } })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(mockApiGet).toHaveBeenCalledWith('/api/v1/engine-requests/initial-form-schema/10')
    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('403')
  })

  it('renders the wizard when the real composable resolves with field groups', async () => {
    mockApiGet.mockResolvedValue({
      data: { field_groups: [{ id: 1, name: 'g', label: 'مجموعة', sort_order: 0, fields: [] }] },
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(true)
  })

  it('renders the wizard when a FILE field has multiple: false', async () => {
    mockApiGet.mockResolvedValue({
      data: {
        field_groups: [
          {
            id: 1,
            name: 'g',
            label: 'مجموعة',
            sort_order: 0,
            fields: [{ id: 1, key: 'doc', type: 'FILE', multiple: false }],
          },
        ],
      },
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(true)
  })

  it('renders the coded 422 error state instead of the wizard when the backend rejects a multiple:true FILE field', async () => {
    // useTemporaryUploadLifecycle/DynamicForm track one upload entry per
    // field key: a second file against a multiple:true field would silently
    // overwrite the first entry's tracking and orphan its server-side
    // reservation. This is now rejected server-side (see
    // WorkflowVersionValidator's INITIAL_STAGE_UNSUPPORTED_MULTI_FILE_FIELD
    // publish-time check and EngineRequestSubmissionService/
    // initialFormSchema's matching runtime guards) rather than detected
    // client-side, so the page must surface the real 422 status the backend
    // returns instead of a generic 500.
    mockApiGet.mockRejectedValue({
      status: 422,
      data: {
        message: 'Unsupported multi-file field',
        error_code: 'INITIAL_STAGE_UNSUPPORTED_MULTI_FILE_FIELD',
      },
    })

    const wrapper = mount(WorkflowsNewVersionPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-stub="wizard"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('422')
  })
})
