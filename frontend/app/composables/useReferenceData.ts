import { ref } from 'vue'
import type { ReferenceTable, ReferenceValue } from '@/types/models'
import { useApi } from '@/composables/useApi'

type ListResponse<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export function useReferenceData() {
  const api = useApi()
  const referenceTables = ref<ReferenceTable[]>([])
  const referenceValues = ref<ReferenceValue[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchReferenceTables = async (search = '') => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<ListResponse<ReferenceTable>>('/api/v1/reference-tables', {
        query: { search },
      })
      referenceTables.value = response.data
    } catch (cause: unknown) {
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الجداول المرجعية.')
    } finally {
      loading.value = false
    }
  }

  const fetchReferenceValues = async (referenceTableId: number) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<ListResponse<ReferenceValue>>('/api/v1/reference-values', {
        query: { reference_table_id: referenceTableId },
      })
      referenceValues.value = response.data
    } catch (cause: unknown) {
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل القيم المرجعية.')
    } finally {
      loading.value = false
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
