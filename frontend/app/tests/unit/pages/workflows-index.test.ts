// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import WorkflowsIndexPage from '@/pages/workflows/index.vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'

const fetchListMock = vi.hoisted(() => vi.fn().mockResolvedValue(undefined))
const fetchQueueMock = vi.hoisted(() => vi.fn().mockResolvedValue(undefined))
const fetchStatsMock = vi.hoisted(() => vi.fn().mockResolvedValue(undefined))

vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('navigateTo', vi.fn())

vi.mock('@/composables/useEngineRequests', () => ({
  useEngineRequests: () => ({
    instances: { value: [] },
    instancesMeta: { value: { current_page: 1, last_page: 1, per_page: 25, total: 0 } },
    queue: { value: [] },
    queueMeta: { value: { current_page: 1, last_page: 1, per_page: 25, total: 0 } },
    availableWorkflows: { value: [] },
    current: { value: null },
    loading: { value: false },
    error: { value: null },
    fetchList: fetchListMock,
    fetchQueue: fetchQueueMock,
    fetchAvailableWorkflows: vi.fn(),
    create: vi.fn(),
    show: vi.fn(),
  }),
}))

vi.mock('@/composables/useEngineRequestStats', () => ({
  useEngineRequestStats: () => ({
    stats: { value: null },
    fetchStats: fetchStatsMock,
  }),
}))

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { role: 'CBY_ADMIN' },
    isCbyAdmin: true,
  }),
}))

const shallowStubs = {
  NuxtLink: true,
  RouterLink: true,
  PageHeader: true,
  MetricGrid: true,
  MetricCard: true,
  DataTable: {
    name: 'DataTable',
    template: `
      <div>
        <slot name="toolbar" :table="{ getColumn: () => null }" />
        <slot name="empty" />
        <slot name="pagination" :table="{}" />
      </div>
    `,
  },
  DataTableToolbar: {
    name: 'DataTableToolbar',
    template: '<div data-testid="toolbar" />',
    emits: ['update:search', 'reset'],
  },
  DataTablePagination: true,
  DataTableFacetedFilter: true,
  DataTableViewOptions: true,
  DataTableExport: true,
  Alert: true,
  AlertTitle: true,
  AlertDescription: true,
  AlertAction: true,
  Empty: true,
  EmptyHeader: true,
  EmptyTitle: true,
  EmptyDescription: true,
  EmptyContent: true,
  Button: true,
  Badge: true,
  Popover: true,
  PopoverTrigger: true,
  PopoverContent: true,
  Command: true,
  CommandList: true,
  CommandGroup: true,
  CommandItem: true,
  Separator: true,
}

describe('workflows index page', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    setActivePinia(createPinia())
    fetchListMock.mockClear()
    fetchQueueMock.mockClear()
    fetchStatsMock.mockClear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('passes search query param to fetchList instead of client filtering', async () => {
    const store = useEngineRequestsStore()
    store.instances = []
    store.instancesMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 }

    const wrapper = mount(WorkflowsIndexPage, {
      global: { stubs: shallowStubs },
    })

    await flushPromises()
    fetchListMock.mockClear()

    const toolbar = wrapper.find('[data-testid="toolbar"]')
    expect(toolbar.exists()).toBe(true)
    await wrapper.findComponent({ name: 'DataTableToolbar' }).vm.$emit('update:search', 'INV-9')

    vi.advanceTimersByTime(350)
    await flushPromises()

    expect(fetchListMock).toHaveBeenCalledWith(
      expect.objectContaining({ search: 'INV-9', page: 1 }),
    )
  })
})
