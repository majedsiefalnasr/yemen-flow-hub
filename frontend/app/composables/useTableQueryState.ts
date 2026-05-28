import { useRoute, useRouter } from 'vue-router'

export interface TableQuerySchema {
  search?: string
  page?: number
  perPage?: number
  sortBy?: string
  sortOrder?: 'asc' | 'desc'
  [key: string]: string | number | string[] | undefined
}

const DEFAULT_PAGE = 1
const DEFAULT_PER_PAGE = 20

export function useTableQueryState(defaults: TableQuerySchema = {}) {
  const route = useRoute()
  const router = useRouter()

  function getQuery(): TableQuerySchema {
    const q = route.query as Record<string, string | string[] | null | undefined>

    return {
      search: (q.search as string) || defaults.search || '',
      page: q.page ? Number(q.page) : (defaults.page ?? DEFAULT_PAGE),
      perPage: q.perPage ? Number(q.perPage) : (defaults.perPage ?? DEFAULT_PER_PAGE),
      sortBy: (q.sortBy as string) || defaults.sortBy || '',
      sortOrder: ((q.sortOrder as string) === 'asc' || (q.sortOrder as string) === 'desc')
        ? (q.sortOrder as 'asc' | 'desc')
        : (defaults.sortOrder ?? 'desc'),
      ...Object.keys(defaults)
        .filter(k => !['search', 'page', 'perPage', 'sortBy', 'sortOrder'].includes(k))
        .reduce((acc, key) => {
          const value = q[key]
          if (value !== undefined && value !== null) {
            if (Array.isArray(value)) {
              acc[key] = value.filter((entry): entry is string => typeof entry === 'string')
            } else {
              acc[key] = typeof value === 'string' && value.includes(',') ? value.split(',') : value
            }
          } else if (defaults[key] !== undefined) {
            acc[key] = defaults[key]
          }
          return acc
        }, {} as TableQuerySchema),
    }
  }

  const queryState = computed(() => getQuery())

  async function setQuery(updates: Partial<TableQuerySchema>, mode: 'push' | 'replace' = 'replace') {
    const current = getQuery()
    const merged = { ...current, ...updates }

    const query: Record<string, string | undefined> = {}
    for (const [key, value] of Object.entries(merged)) {
      if (
        value === undefined
        || value === null
        || value === ''
        || (value === DEFAULT_PAGE && key === 'page')
        || (value === DEFAULT_PER_PAGE && key === 'perPage')
      ) {
        query[key] = undefined
      } else if (Array.isArray(value)) {
        query[key] = value.length ? value.join(',') : undefined
      } else {
        query[key] = String(value)
      }
    }

    if (mode === 'push') {
      await router.push({ query })
    } else {
      await router.replace({ query })
    }
  }

  async function setSearch(value: string) {
    await setQuery({ search: value, page: 1 }, 'replace')
  }

  async function setPage(page: number) {
    await setQuery({ page }, 'push')
  }

  async function setPerPage(perPage: number) {
    await setQuery({ perPage, page: 1 }, 'replace')
  }

  async function setSort(sortBy: string, sortOrder: 'asc' | 'desc') {
    await setQuery({ sortBy, sortOrder }, 'replace')
  }

  async function setFilter(key: string, value: string | string[] | undefined) {
    await setQuery({ [key]: value, page: 1 }, 'replace')
  }

  async function resetFilters() {
    await setQuery({ ...defaults, page: 1 }, 'replace')
  }

  const hasActiveFilters = computed(() => {
    const q = queryState.value
    return Boolean(
      q.search
      || (q.page && q.page > 1)
      || Object.keys(q)
        .filter(key => !['page', 'perPage'].includes(key))
        .some((key) => {
          const value = q[key]
          const defaultValue = defaults[key]
          if (Array.isArray(value)) return value.length > 0
          return value !== undefined && value !== '' && value !== defaultValue
        }),
    )
  })

  return {
    queryState,
    setQuery,
    setSearch,
    setPage,
    setPerPage,
    setSort,
    setFilter,
    resetFilters,
    hasActiveFilters,
  }
}
