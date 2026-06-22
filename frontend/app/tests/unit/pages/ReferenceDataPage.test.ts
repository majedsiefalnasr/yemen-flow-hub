// @vitest-environment jsdom

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
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

async function mountPage(capabilities: Array<'VIEW' | 'CREATE' | 'UPDATE' | 'DELETE'>) {
  mockGet.mockResolvedValueOnce({ data: [TABLE], meta: META })
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

function buttonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((button) => button.text().trim() === text)
}

describe('reference data admin page', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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

  it('executes permitted activation and exposes protected delete state', async () => {
    mockPost.mockResolvedValueOnce({
      data: { ...TABLE, is_active: false, version: 2 },
    })
    const wrapper = await mountPage(['VIEW', 'CREATE', 'UPDATE', 'DELETE'])

    const deactivate = buttonByText(wrapper, 'إيقاف')
    expect(deactivate).toBeDefined()
    await deactivate!.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/api/v1/reference-tables/1/deactivate', {
      version: 1,
    })
    const deleteButton = buttonByText(wrapper, 'حذف')
    expect(deleteButton?.attributes('disabled')).toBeDefined()
  })

  it('loads values when a table row is selected', async () => {
    const wrapper = await mountPage(['VIEW', 'CREATE', 'UPDATE', 'DELETE'])
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
})
