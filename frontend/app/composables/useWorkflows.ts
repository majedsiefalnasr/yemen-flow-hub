import { ref } from 'vue'
import type {
  PaginatedResponse,
  WorkflowDefinition,
  WorkflowValidationError,
  WorkflowVersion,
} from '@/types/models'
import { useApi } from '@/composables/useApi'

type ListOptions = {
  page?: number
  search?: string
  sort?: 'code' | 'name' | 'is_active' | 'created_at'
  direction?: 'asc' | 'desc'
}

export function useWorkflows() {
  const api = useApi()
  const definitions = ref<WorkflowDefinition[]>([])
  const definitionsMeta = ref<PaginatedResponse<WorkflowDefinition>['meta'] | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchDefinitions = async (options: ListOptions = {}) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<PaginatedResponse<WorkflowDefinition>>(
        '/api/v1/workflow-definitions',
        {
          query: {
            page: options.page ?? 1,
            per_page: 25,
            search: options.search ?? '',
            sort: options.sort ?? 'created_at',
            direction: options.direction ?? 'desc',
          },
        },
      )
      if (token === requestToken) {
        definitions.value = response.data
        definitionsMeta.value = response.meta
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        definitions.value = []
        definitionsMeta.value = null
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل مسارات العمل.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createDefinition = async (payload: {
    code: string
    name: string
    description?: string
  }) => {
    const response = await api.post<{ data: WorkflowDefinition }>(
      '/api/v1/workflow-definitions',
      payload,
    )
    definitions.value = [response.data, ...definitions.value]
    return response.data
  }

  const cloneVersion = async (version: WorkflowVersion) => {
    const response = await api.post<{ data: WorkflowVersion }>(
      `/api/v1/workflow-versions/${version.id}/clone`,
      {},
    )
    const clone = response.data
    definitions.value = definitions.value.map((definition) =>
      definition.id === clone.workflow_definition_id
        ? { ...definition, versions: [clone, ...definition.versions] }
        : definition,
    )
    return clone
  }

  const validateVersion = async (versionId: number): Promise<WorkflowValidationError[]> => {
    const response = await api.post<{ data: { errors: WorkflowValidationError[] } }>(
      `/api/v1/workflow-versions/${versionId}/validate`,
      {},
    )
    return response.data.errors
  }

  const publishVersion = async (version: WorkflowVersion) => {
    const response = await api.post<{ data: WorkflowVersion }>(
      `/api/v1/workflow-versions/${version.id}/publish`,
      { version: version.version },
    )
    const published = response.data
    definitions.value = definitions.value.map((definition) =>
      definition.id === published.workflow_definition_id
        ? {
            ...definition,
            versions: definition.versions.map((v) =>
              v.id === published.id
                ? published
                : v.state === 'PUBLISHED'
                  ? { ...v, state: 'ARCHIVED' as const }
                  : v,
            ),
          }
        : definition,
    )
    return published
  }

  return {
    definitions,
    definitionsMeta,
    loading,
    error,
    fetchDefinitions,
    createDefinition,
    cloneVersion,
    validateVersion,
    publishVersion,
  }
}
