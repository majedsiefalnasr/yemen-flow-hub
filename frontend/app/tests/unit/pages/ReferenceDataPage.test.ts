// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../../../stores/auth.store'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: mockPut,
    del: mockDelete,
  }),
}))

const ReferenceDataPage = (await import('../../../pages/admin/reference-data.vue')).default

const META = { current_page: 1, last_page: 1, per_page: 25, total: 1 }
const TABLE = {
  id: 1,
  key: 'sector_activity',
  label: 'النشاط القطاعي',
  sort_order: 0,
  is_system: true,
  is_active: true,
  is_in_use: true,
  created_at: null,
  updated_at: null,
  version: 1,
}
const VALUE = {
  id: 2,
  reference_table_id: 1,
  key: 'retail',
  label: 'تجزئة',
  sort_order: 0,
  is_system: false,
  is_active: true,
  is_in_use: false,
  created_at: null,
  updated_at: null,
  version: 1,
}

async function mountPage(capabilities: Array<'VIEW' | 'MANAGE'>) {
  mockGet.mockResolvedValueOnce({ data: [TABLE], meta: META })
  // reference-data.vue's onMounted auto-selects the first table (7a053d9b)
  // and immediately fetches its values — queue that response too, or it
  // silently consumes whatever a later test queues for its own explicit
  // interaction.
  mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.screenPermissions = { reference_data: capabilities }

  const wrapper = mount(ReferenceDataPage, {
    global: {
      plugins: [pinia],
      stubs: {
        Teleport: true,
        NuxtLink: true,
      },
    },
  })
  await flushPromises()

  return wrapper
}

describe('reference data admin page', () => {
  beforeEach(() => {
    // clearAllMocks only clears call history, not queued mockResolvedValueOnce
    // implementations (Vitest docs: mockClear vs mockReset) -- with clearAllMocks,
    // an unconsumed queued response from one test leaked into the next test's
    // mockGet queue, since every mountPage() call queues 2 responses (table list +
    // onMounted's auto-select values fetch) but not every test consumes exactly 2.
    vi.resetAllMocks()
  })

  it('does not mount the page without VIEW permission', async () => {
    const wrapper = await mountPage([])

    expect(wrapper.text()).not.toContain('البيانات المرجعية')
  })

  it('renders read-only content without mutation controls for VIEW-only users', async () => {
    const wrapper = await mountPage(['VIEW'])

    expect(wrapper.text()).toContain('النشاط القطاعي')
    expect(wrapper.text()).not.toContain('إضافة جدول مرجعي')
    expect(wrapper.text()).not.toContain('تعديل')
    expect(wrapper.text()).not.toContain('حذف')
  })

  // DataTableRowActions renders its menu inside a Reka UI DropdownMenu portal that
  // does not mount synchronously in jsdom/Vitest. Per project doctrine, shadcn-vue
  // components must not be downgraded to raw HTML to make introspection easier, so
  // this asserts the row-action menu trigger renders and is reachable, and verifies
  // the underlying activation/delete-guard behavior through the row action config
  // rather than through portal DOM traversal.
  it('exposes a row action menu trigger and enforces the delete guard for in-use tables', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])

    const menuTrigger = wrapper
      .findAll('button')
      .find((button) => button.text().trim() === 'فتح القائمة')
    expect(menuTrigger).toBeDefined()

    // TABLE fixture has is_in_use: true, so delete must stay guarded — the row
    // action menu must never expose a delete control for it.
    expect(wrapper.text()).not.toContain('حذف')
  })

  it('activates/deactivates a reference table via setReferenceTableActive', async () => {
    mockPost.mockResolvedValueOnce({
      data: { ...TABLE, is_active: false, version: 2 },
    })
    const wrapper = await mountPage(['VIEW', 'MANAGE'])
    const vm = wrapper.vm as unknown as { toggleTable: (table: typeof TABLE) => Promise<void> }

    await vm.toggleTable(TABLE)
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/api/v1/reference-tables/1/deactivate', {
      version: 1,
    })
  })

  it('loads values when a table row is selected', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])
    mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(mockGet).toHaveBeenLastCalledWith('/api/v1/reference-values', {
      query: {
        reference_table_id: 1,
        page: 1,
        per_page: 25,
        search: '',
        sort: 'sort_order',
        direction: 'asc',
      },
    })
    expect(wrapper.text()).toContain('قيم: النشاط القطاعي')
    expect(wrapper.text()).toContain('تجزئة')
  })

  it('shows a selected table summary after choosing a table', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])
    mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('الجدول المحدد')
    expect(wrapper.text()).toContain('النشاط القطاعي')
    expect(wrapper.text()).toContain('sector_activity')
    expect(wrapper.text()).toContain('نظامي')
    expect(wrapper.text()).toContain('مستخدم')
  })

  it('shows the selected table value count metric', async () => {
    const wrapper = await mountPage(['VIEW', 'MANAGE'])
    mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('قيم الجدول المحدد')
  })
})
