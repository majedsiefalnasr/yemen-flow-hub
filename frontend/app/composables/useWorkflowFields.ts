import { ref } from 'vue'
import type { DynamicFieldSource, FieldDefinition, FieldGroup, FieldType } from '@/types/models'
import { useApi } from '@/composables/useApi'

export type FieldGroupPayload = {
  name: string
  label: string
  sort_order?: number
}

export type FieldDefinitionPayload = {
  field_group_id: number
  key: string
  label: string
  type: FieldType
  placeholder?: string | null
  help_text?: string | null
  default_value?: string | null
  min_value?: number | null
  max_value?: number | null
  min_length?: number | null
  max_length?: number | null
  regex_pattern?: string | null
  options?: Array<{ value: string; label: string }> | null
  reference_table_id?: number | null
  dynamic_source?: DynamicFieldSource | null
  allowed_file_types?: string[] | null
  max_file_size?: number | null
  multiple?: boolean
  is_required?: boolean
  sort_order?: number
}

export function useWorkflowFields() {
  const api = useApi()
  const groups = ref<FieldGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestToken = 0

  const fetchGroups = async (versionId: number) => {
    const token = ++requestToken
    loading.value = true
    error.value = null
    try {
      const response = await api.get<{ data: FieldGroup[] }>(
        `/api/v1/workflow-versions/${versionId}/field-groups`,
      )
      if (token === requestToken) {
        groups.value = response.data
      }
    } catch (cause: unknown) {
      if (token === requestToken) {
        groups.value = []
        error.value = extractApiErrorMessage(cause, 'تعذر تحميل الحقول.')
      }
    } finally {
      if (token === requestToken) {
        loading.value = false
      }
    }
  }

  const createGroup = async (versionId: number, payload: FieldGroupPayload) => {
    const response = await api.post<{ data: FieldGroup }>(
      `/api/v1/workflow-versions/${versionId}/field-groups`,
      payload,
    )
    groups.value = [...groups.value, { ...response.data, fields: [] }].sort(
      (a, b) => a.sort_order - b.sort_order,
    )
    return response.data
  }

  const createField = async (versionId: number, payload: FieldDefinitionPayload) => {
    const response = await api.post<{ data: FieldDefinition }>(
      `/api/v1/workflow-versions/${versionId}/fields`,
      payload,
    )
    const field = response.data
    groups.value = groups.value.map((group) =>
      group.id === field.field_group_id
        ? { ...group, fields: [...group.fields, field].sort((a, b) => a.sort_order - b.sort_order) }
        : group,
    )
    return field
  }

  const deleteField = async (versionId: number, field: FieldDefinition) => {
    await api.del(`/api/v1/workflow-versions/${versionId}/fields/${field.id}`)
    groups.value = groups.value.map((group) =>
      group.id === field.field_group_id
        ? { ...group, fields: group.fields.filter((f) => f.id !== field.id) }
        : group,
    )
  }

  /**
   * Update a field (e.g. reassign to another group via field_group_id). PATCH
   * semantics: only the supplied keys are sent.
   */
  const updateField = async (
    versionId: number,
    field: FieldDefinition,
    payload: Partial<FieldDefinitionPayload>,
  ) => {
    const response = await api.put<{ data: FieldDefinition }>(
      `/api/v1/workflow-versions/${versionId}/fields/${field.id}`,
      { ...payload, version: field.version },
    )
    const updated = response.data
    // Remove from old group, insert into new group (or same group if only other
    // fields changed).
    groups.value = groups.value
      .map((group) => ({
        ...group,
        fields: group.fields.filter((f) => f.id !== updated.id),
      }))
      .map((group) =>
        group.id === updated.field_group_id
          ? {
              ...group,
              fields: [...group.fields, updated].sort((a, b) => a.sort_order - b.sort_order),
            }
          : group,
      )
    return updated
  }

  const updateGroup = async (
    versionId: number,
    group: FieldGroup,
    payload: Partial<FieldGroupPayload>,
  ) => {
    const response = await api.put<{ data: FieldGroup }>(
      `/api/v1/workflow-versions/${versionId}/field-groups/${group.id}`,
      payload,
    )
    const updated = response.data
    groups.value = groups.value
      .map((g) => (g.id === updated.id ? { ...g, ...updated, fields: g.fields } : g))
      .sort((a, b) => a.sort_order - b.sort_order)
    return updated
  }

  const deleteGroup = async (versionId: number, group: FieldGroup) => {
    await api.del(`/api/v1/workflow-versions/${versionId}/field-groups/${group.id}`)
    // Backend nullOnDelete on fields → orphaned fields drop out of this group.
    groups.value = groups.value.filter((g) => g.id !== group.id)
  }

  /**
   * Persist a new sort order for every group after a local reorder. Sends one
   * PUT per group with its new sort_order.
   */
  const persistGroupOrder = async (versionId: number, orderedIds: number[]) => {
    const updates = orderedIds.map((id, index) => {
      const group = groups.value.find((g) => g.id === id)
      if (!group) return Promise.resolve()
      return updateGroup(versionId, group, { sort_order: index }).catch(() => {})
    })
    await Promise.all(updates)
  }

  return {
    groups,
    loading,
    error,
    fetchGroups,
    createGroup,
    createField,
    deleteField,
    updateField,
    updateGroup,
    deleteGroup,
    persistGroupOrder,
  }
}
