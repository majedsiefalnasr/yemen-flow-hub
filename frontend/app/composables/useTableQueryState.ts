import { useRoute, useRouter } from 'vue-router'

export interface TableQueryState {
  search: string
  page: number
  pageSize: number
  sort: string
  sortDir: 'asc' | 'desc'
  filters: Record<string, string[]>
}

const DEFAULT_PAGE_SIZE = 20

/**
 * Syncs table filter, sort, and pagination state to the URL query string.
 * Uses `replace` for search/filter changes (no history entry) and `push`
 * for explicit page navigation.
 */
export function useTableQueryState(defaults?: Partial<TableQueryState>) {
  const route = useRoute()
  const router = useRouter()

  function getQuery() {
    return route.query as Record<string, string | string[]>
  }

  const search = computed({
    get: () => String(getQuery().q ?? ''),
    set: (val: string) => push({ q: val || undefined, page: undefined }),
  })

  const page = computed({
    get: () => Number(getQuery().page ?? 1),
    set: (val: number) => push({ page: val === 1 ? undefined : String(val) }, true),
  })

  const pageSize = computed({
    get: () => Number(getQuery().per_page ?? defaults?.pageSize ?? DEFAULT_PAGE_SIZE),
    set: (val: number) => push({ per_page: val === DEFAULT_PAGE_SIZE ? undefined : String(val), page: undefined }),
  })

  const sort = computed({
    get: () => String(getQuery().sort ?? defaults?.sort ?? ''),
    set: (val: string) => push({ sort: val || undefined, page: undefined }),
  })

  const sortDir = computed({
    get: (): 'asc' | 'desc' => (getQuery().dir === 'desc' ? 'desc' : 'asc'),
    set: (val: 'asc' | 'desc') => push({ dir: val === 'asc' ? undefined : val, page: undefined }),
  })

  function getFilters(): Record<string, string[]> {
    const result: Record<string, string[]> = {}
    const q = getQuery()
    for (const key in q) {
      if (key.startsWith('f_')) {
        const col = key.slice(2)
        const val = q[key]
        result[col] = Array.isArray(val) ? val : val ? [val] : []
      }
    }
    return result
  }

  function setFilter(column: string, values: string[]) {
    push({ [`f_${column}`]: values.length ? values : undefined, page: undefined })
  }

  function resetFilters() {
    const toRemove: Record<string, undefined> = { q: undefined, page: undefined }
    for (const key in getQuery()) {
      if (key.startsWith('f_')) toRemove[key] = undefined
    }
    push(toRemove)
  }

  async function push(params: Record<string, string | string[] | undefined>, navigate = false) {
    const query = { ...route.query, ...params }
    // Remove undefined keys
    for (const k in query) {
      if (query[k] === undefined) delete query[k]
    }
    await (navigate ? router.push : router.replace)({ query })
  }

  return {
    search,
    page,
    pageSize,
    sort,
    sortDir,
    getFilters,
    setFilter,
    resetFilters,
  }
}
