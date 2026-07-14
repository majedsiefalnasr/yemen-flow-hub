import { ref } from 'vue'
import type {
  AvailableWorkflow,
  EngineDuplicateWarning,
  EngineRequest,
  PaginatedResponse,
} from '@/types/models'
import { useApi } from '@/composables/useApi'
import { extractApiErrorMessage } from '@/utils/apiErrors'

export type ListOptions = {
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
  claimed?: string
  created_from?: string
  created_to?: string
}

export function useEngineRequests() {
  const api = useApi()

  const instances = ref<EngineRequest[]>([])
  const instancesMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const queue = ref<EngineRequest[]>([])
  const queueMeta = ref<PaginatedResponse<EngineRequest>['meta'] | null>(null)
  const availableWorkflows = ref<AvailableWorkflow[]>([])
  const current = ref<EngineRequest | null>(null)
  const currentWarnings = ref<EngineDuplicateWarning[]>([])
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

  /**
   * Deferred-creation submission: one atomic call that creates the request,
   * promotes any upload_tokens into real documents, and executes the initial
   * transition together — there is no separate "create a blank draft" step.
   * idempotencyKey must be a client-generated UUID kept stable across
   * retries of the same attempt (see useEngineWizard's key lifecycle); the
   * backend requires it and uses it to make a retried submit safe to repeat.
   */
  const submit = async (
    idempotencyKey: string,
    payload: {
      workflow_version_id: number
      merchant_id?: number | null
      data: Record<string, unknown>
      upload_tokens?: string[]
    },
  ): Promise<{ data: EngineRequest; warnings: EngineDuplicateWarning[] }> => {
    const response = await api.post<{
      success: boolean
      data: EngineRequest
      warnings?: EngineDuplicateWarning[]
    }>('/api/v1/engine-requests', payload, {
      headers: { 'Idempotency-Key': idempotencyKey },
    })
    return { data: response.data, warnings: response.warnings ?? [] }
  }

  const show = async (id: number): Promise<EngineRequest> => {
    const response = await api.get<{
      success: boolean
      data: EngineRequest
      warnings?: EngineDuplicateWarning[]
    }>(`/api/v1/engine-requests/${id}`)
    current.value = response.data
    currentWarnings.value = response.warnings ?? []
    return response.data
  }

  return {
    instances,
    instancesMeta,
    queue,
    queueMeta,
    availableWorkflows,
    current,
    currentWarnings,
    loading,
    error,
    fetchList,
    fetchQueue,
    fetchAvailableWorkflows,
    submit,
    show,
  }
}
