import { computed, ref } from 'vue'
import type { PaginatedResponse, ReferenceTable, ReferenceValue } from '@/types/models'
import { useApi } from '@/composables/useApi'

type ListOptions = {
  page?: number
  search?: string
  sort?: 'key' | 'label' | 'sort_order' | 'is_active' | 'created_at'
  direction?: 'asc' | 'desc'
}

export function useReferenceData() {
  const api = useApi()
  const referenceTables = ref<ReferenceTable[]>([])
  const referenceValues = ref<ReferenceValue[]>([])
  const referenceTablesMeta = ref<PaginatedResponse<ReferenceTable>['meta'] | null>(null)
  const referenceValuesMeta = ref<PaginatedResponse<ReferenceValue>['meta'] | null>(null)
  const tablesLoading = ref(false)
  const valuesLoading = ref(false)
  const loading = computed(() => tablesLoading.value || valuesLoading.value)
  const error = ref<string | null>(null)
  let tablesRequestToken = 0
  let valuesRequestToken = 0

  const fetchReferenceTables = async (options: ListOptions = {}) => {
    const token = ++tablesRequestToken
    tablesLoading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<ReferenceTable>>(
        '/api/v1/reference-tables',
        {
          query: {
            page: options.page ?? 1,
            per_page: 25,
            search: options.search ?? '',
            sort: options.sort ?? 'sort_order',
            direction: options.direction ?? 'asc',
          },
        },
      )
      if (token === tablesRequestToken) {
        referenceTables.value = response.data
        referenceTablesMeta.value = response.meta
      }
    } catch (cause: unknown) {
      if (token === tablesRequestToken) {
        referenceTables.value = []
        referenceTablesMeta.value = null
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل الجداول المرجعية.')
      }
    } finally {
      if (token === tablesRequestToken) {
        tablesLoading.value = false
      }
    }
  }

  const fetchReferenceValues = async (referenceTableId: number, options: ListOptions = {}) => {
    const token = ++valuesRequestToken
    referenceValues.value = []
    referenceValuesMeta.value = null
    valuesLoading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<ReferenceValue>>(
        '/api/v1/reference-values',
        {
          query: {
            reference_table_id: referenceTableId,
            page: options.page ?? 1,
            per_page: 25,
            search: options.search ?? '',
            sort: options.sort ?? 'sort_order',
            direction: options.direction ?? 'asc',
          },
        },
      )
      if (token === valuesRequestToken) {
        referenceValues.value = response.data
        referenceValuesMeta.value = response.meta
      }
    } catch (cause: unknown) {
      if (token === valuesRequestToken) {
        referenceValues.value = []
        referenceValuesMeta.value = null
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل القيم المرجعية.')
      }
    } finally {
      if (token === valuesRequestToken) {
        valuesLoading.value = false
      }
    }
  }

  const createReferenceTable = async (payload: { key: string; label: string }) => {
    const response = await api.post<{ data: ReferenceTable }>('/api/v1/reference-tables', payload)
    referenceTables.value = [response.data, ...referenceTables.value]
    return response.data
  }

  const updateReferenceTable = async (
    referenceTable: ReferenceTable,
    payload: { label: string },
  ) => {
    const response = await api.put<{ data: ReferenceTable }>(
      `/api/v1/reference-tables/${referenceTable.id}`,
      { ...payload, version: referenceTable.version },
    )
    referenceTables.value = referenceTables.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const setReferenceTableActive = async (referenceTable: ReferenceTable, active: boolean) => {
    const response = await api.post<{ data: ReferenceTable }>(
      `/api/v1/reference-tables/${referenceTable.id}/${active ? 'activate' : 'deactivate'}`,
      { version: referenceTable.version },
    )
    referenceTables.value = referenceTables.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const deleteReferenceTable = async (referenceTable: ReferenceTable) => {
    await api.del(`/api/v1/reference-tables/${referenceTable.id}`)
    referenceTables.value = referenceTables.value.filter((item) => item.id !== referenceTable.id)
  }

  const createReferenceValue = async (payload: {
    reference_table_id: number
    key: string
    label: string
  }) => {
    const response = await api.post<{ data: ReferenceValue }>('/api/v1/reference-values', payload)
    referenceValues.value = [response.data, ...referenceValues.value]
    return response.data
  }

  const updateReferenceValue = async (
    referenceValue: ReferenceValue,
    payload: { label: string },
  ) => {
    const response = await api.put<{ data: ReferenceValue }>(
      `/api/v1/reference-values/${referenceValue.id}`,
      { ...payload, version: referenceValue.version },
    )
    referenceValues.value = referenceValues.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const setReferenceValueActive = async (referenceValue: ReferenceValue, active: boolean) => {
    const response = await api.post<{ data: ReferenceValue }>(
      `/api/v1/reference-values/${referenceValue.id}/${active ? 'activate' : 'deactivate'}`,
      { version: referenceValue.version },
    )
    referenceValues.value = referenceValues.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const deleteReferenceValue = async (referenceValue: ReferenceValue) => {
    await api.del(`/api/v1/reference-values/${referenceValue.id}`)
    referenceValues.value = referenceValues.value.filter((item) => item.id !== referenceValue.id)
  }

  return {
    referenceTables,
    referenceValues,
    referenceTablesMeta,
    referenceValuesMeta,
    tablesLoading,
    valuesLoading,
    loading,
    error,
    fetchReferenceTables,
    fetchReferenceValues,
    createReferenceTable,
    updateReferenceTable,
    setReferenceTableActive,
    deleteReferenceTable,
    createReferenceValue,
    updateReferenceValue,
    setReferenceValueActive,
    deleteReferenceValue,
  }
}
