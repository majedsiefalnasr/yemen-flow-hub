// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { vi, describe, it, expect, beforeEach } from 'vitest'
import GlobalSearch from '../../../components/layout/GlobalSearch.vue'

const mockPush = vi.hoisted(() => vi.fn())
const mockSearch = vi.hoisted(() => vi.fn())
const mockFetchRecent = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useSearch', async () => {
  const { ref } = await import('vue')
  const results = ref({ requests: [], users: [], banks: [], customs: [] })
  const recentSearches = ref<string[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const activeFilter = ref('all')

  return {
    __searchTestState: {
      results,
      recentSearches,
      loading,
      error,
      activeFilter,
      search: mockSearch,
      fetchRecent: mockFetchRecent,
    },
    useSearch: () => ({
      results,
      recentSearches,
      loading,
      error,
      activeFilter,
      search: mockSearch,
      fetchRecent: mockFetchRecent,
    }),
  }
})

vi.mock('../../../components/ui/Icon.vue', () => ({
  default: {
    props: ['name'],
    template: '<span class="icon-stub" :data-icon="name" />',
  },
}))

vi.stubGlobal('useRouter', () => ({ push: mockPush }))

const SAMPLE_RESULTS = {
  requests: [
    {
      id: 1,
      reference_number: 'REF-001',
      bank_id: 1,
      bank_name: 'Bank A',
      status: 'SUBMITTED',
      supplier_name: 'Alpha Supplier',
      amount: 50000,
      currency: 'USD',
      created_at: null,
    },
  ],
  users: [
    {
      id: 2,
      name: 'Ahmed',
      email: 'ahmed@test.com',
      role: 'DATA_ENTRY',
      role_label: 'موظف إدخال',
      bank_id: 1,
      bank_name: 'Bank A',
      is_active: true,
    },
  ],
  banks: [{ id: 3, name: 'Bank Alpha', code: 'YCBA', is_active: true }],
  customs: [
    {
      id: 4,
      declaration_number: 'DECL-001',
      issued_at: null,
      request_id: 5,
      reference_number: 'REF-005',
    },
  ],
}

async function state() {
  return ((await import('../../../composables/useSearch')) as any).__searchTestState
}

async function resetState() {
  const searchState = await state()
  searchState.results.value = { requests: [], users: [], banks: [], customs: [] }
  searchState.recentSearches.value = []
  searchState.loading.value = false
  searchState.error.value = null
  searchState.activeFilter.value = 'all'
}

describe('GlobalSearch', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    await resetState()
  })

  it('fetches recent searches on mount and input focus', async () => {
    const wrapper = mount(GlobalSearch)

    expect(mockFetchRecent).toHaveBeenCalledTimes(1)

    await wrapper.get('input').trigger('focus')

    expect(mockFetchRecent).toHaveBeenCalledTimes(2)
  })

  it('calls search when the user types and opens the dropdown', async () => {
    const searchState = await state()
    searchState.results.value = SAMPLE_RESULTS
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('alpha')

    expect(mockSearch).toHaveBeenCalledWith('alpha')
    expect(wrapper.text()).toContain('REF-001')
    expect(wrapper.text()).toContain('Alpha Supplier')
  })

  it('renders recent searches on empty focus and searches when one is clicked', async () => {
    const searchState = await state()
    searchState.recentSearches.value = ['alpha', 'beta']
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').trigger('focus')

    expect(wrapper.text()).toContain('عمليات البحث الأخيرة')
    expect(wrapper.text()).toContain('alpha')

    await wrapper.findAll('button.search-recent-item')[0]!.trigger('click')

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('alpha')
    expect(mockSearch).toHaveBeenCalledWith('alpha')
  })

  it('filters visible result groups by chip selection', async () => {
    const searchState = await state()
    searchState.results.value = SAMPLE_RESULTS
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('alpha')
    await wrapper
      .findAll('button.search-chip')
      .find((button) => button.text() === 'المستخدمون')!
      .trigger('click')
    await nextTick()

    expect(wrapper.text()).toContain('Ahmed')
    expect(wrapper.text()).not.toContain('REF-001')
  })

  it('shows the Arabic empty state when a 2+ character query has no results', async () => {
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('zz')

    expect(wrapper.text()).toContain('لا توجد نتائج لـ «zz»')
  })

  it('shows a spinner while loading', async () => {
    const searchState = await state()
    searchState.loading.value = true
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('al')

    expect(wrapper.find('.search-spinner').exists()).toBe(true)
  })

  it('closes the dropdown with Escape', async () => {
    const searchState = await state()
    searchState.results.value = SAMPLE_RESULTS
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('alpha')
    expect(wrapper.find('.search-dropdown').exists()).toBe(true)

    await wrapper.get('input').trigger('keydown', { key: 'Escape' })

    expect(wrapper.find('.search-dropdown').exists()).toBe(false)
  })

  it('deep-links request and customs results to the related request pages', async () => {
    const searchState = await state()
    searchState.results.value = SAMPLE_RESULTS
    const wrapper = mount(GlobalSearch)

    await wrapper.get('input').setValue('alpha')

    await wrapper.findAll('button.search-result-item')[0]!.trigger('click')
    expect(mockPush).toHaveBeenCalledWith('/workflows/instances/1')

    await wrapper.get('input').setValue('alpha')
    await wrapper
      .findAll('button.search-chip')
      .find((button) => button.text() === 'وثائق تأكيد المصارفة')!
      .trigger('click')
    await wrapper.find('button.search-result-item').trigger('click')

    expect(mockPush).toHaveBeenCalledWith('/workflows/instances/5')
  })
})
