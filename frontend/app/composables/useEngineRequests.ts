import { ref } from 'vue'
import type { AvailableWorkflow, EngineRequest, PaginatedResponse } from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

type ListOptions = {
  page?: number
  per_page?: number
  workflow_id?: number
  workflow_version_id?: number
  stage_id?: number
  bank_id?: number
  merchant_id?: number
  status?: string
  search?: string
  sla_status?: string
}

export function useEngineRequests() {
  const api = useApi()

  const instances = ref<EngineRequest[]>([])
  const instancesMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const queue = ref<EngineRequest[]>([])
  const queueMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const availableWorkflows = ref<AvailableWorkflow[]>([])
  const current = ref<EngineRequest | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchList = async (options: ListOptions = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<EngineRequest>>('/api/v1/engine-requests', {
        query: { page: options.page ?? 1, per_page: options.per_page ?? 25, ...options },
      })
      instances.value = response.data
      instancesMeta.value = response.meta
    } catch (cause: unknown) {
      instances.value = []
      instancesMeta.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل الطلبات.')
    } finally {
      loading.value = false
    }
  }

  const fetchQueue = async (options: ListOptions = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<EngineRequest>>(
        '/api/v1/engine-requests/my-queue',
        {
          query: { page: options.page ?? 1, per_page: options.per_page ?? 25, ...options },
        },
      )
      queue.value = response.data
      queueMeta.value = response.meta
    } catch (cause: unknown) {
      queue.value = []
      queueMeta.value = null
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل طابور العمل.')
    } finally {
      loading.value = false
    }
  }

  const fetchAvailableWorkflows = async () => {
    error.value = null
    try {
      const response = await api.get<{ data: AvailableWorkflow[] }>(
        '/api/v1/engine-requests/available-workflows',
      )
      availableWorkflows.value = response.data
    } catch (cause: unknown) {
      availableWorkflows.value = []
      error.value = extractApiErrorMessage(cause, 'تعذر تحميل مسارات العمل المتاحة.')
    }
  }

  const create = async (payload: {
    workflow_version_id: number
    bank_id?: number | null
    merchant_id?: number | null
    data: Record<string, unknown>
  }): Promise<EngineRequest> => {
    const response = await api.post<{ success: boolean; data: EngineRequest }>(
      '/api/v1/engine-requests',
      payload,
    )
    return response.data
  }

  const show = async (id: number): Promise<EngineRequest> => {
    const response = await api.get<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${id}`,
    )
    current.value = response.data
    return response.data
  }

  const saveDraft = async (
    id: number,
    data: Record<string, unknown>,
    version: number,
  ): Promise<EngineRequest> => {
    const response = await api.patch<{ success: boolean; data: EngineRequest }>(
      `/api/v1/engine-requests/${id}/draft`,
      { data, version },
    )
    current.value = response.data
    return response.data
  }

  return {
    instances,
    instancesMeta,
    queue,
    queueMeta,
    availableWorkflows,
    current,
    loading,
    error,
    fetchList,
    fetchQueue,
    fetchAvailableWorkflows,
    create,
    show,
    saveDraft,
  }
}
