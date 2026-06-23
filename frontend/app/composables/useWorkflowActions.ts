import { ref } from 'vue'
import type { PaginatedResponse, WorkflowAction, WorkflowActionKind } from '@/types/models'
import { useApi } from '@/composables/useApi'

type ListOptions = {
  page?: number
  search?: string
  sort?: 'code' | 'name' | 'kind' | 'is_active' | 'created_at'
  direction?: 'asc' | 'desc'
}

export function useWorkflowActions() {
  const api = useApi()
  const actions = ref<WorkflowAction[]>([])
  const meta = ref<PaginatedResponse<WorkflowAction>['meta'] | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchActions = async (options: ListOptions = {}) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<WorkflowAction>>(
        '/api/v1/workflow-actions',
        {
          query: {
            page: options.page ?? 1,
            per_page: 25,
            search: options.search ?? '',
            sort: options.sort ?? 'code',
            direction: options.direction ?? 'asc',
          },
        },
      )
      if (token === requestToken) {
        actions.value = response.data
        meta.value = response.meta
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        actions.value = []
        meta.value = null
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل كتالوج الإجراءات.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createAction = async (payload: {
    code: string
    name: string
    kind: WorkflowActionKind
  }) => {
    const response = await api.post<{ data: WorkflowAction }>('/api/v1/workflow-actions', payload)
    actions.value = [response.data, ...actions.value]
    return response.data
  }

  const updateAction = async (action: WorkflowAction, payload: { name: string }) => {
    const response = await api.put<{ data: WorkflowAction }>(
      `/api/v1/workflow-actions/${action.id}`,
      { ...payload, version: action.version },
    )
    actions.value = actions.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const setActionActive = async (action: WorkflowAction, active: boolean) => {
    const response = await api.post<{ data: WorkflowAction }>(
      `/api/v1/workflow-actions/${action.id}/${active ? 'activate' : 'deactivate'}`,
      { version: action.version },
    )
    actions.value = actions.value.map((item) =>
      item.id === response.data.id ? response.data : item,
    )
    return response.data
  }

  const deleteAction = async (action: WorkflowAction) => {
    await api.del(`/api/v1/workflow-actions/${action.id}`)
    actions.value = actions.value.filter((item) => item.id !== action.id)
  }

  return {
    actions,
    meta,
    loading,
    error,
    fetchActions,
    createAction,
    updateAction,
    setActionActive,
    deleteAction,
  }
}
