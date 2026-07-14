import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { FieldDefinition, FieldGroup } from '../../../types/models'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()

vi.mock('../../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, del: mockDelete }),
}))

vi.stubGlobal('extractApiErrorMessage', (_cause: unknown, fallback: string) => fallback)

const { useWorkflowFields } = await import('../../../composables/useWorkflowFields')

function makeField(overrides: Partial<FieldDefinition> = {}): FieldDefinition {
  return {
    id: 1,
    workflow_version_id: 7,
    field_group_id: 100,
    key: 'amount',
    label: 'Amount',
    type: 'CURRENCY',
    semantic_tag: null,
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    reference_table_id: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: false,
    is_required: false,
    is_system: false,
    sort_order: 0,
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

function makeGroup(overrides: Partial<FieldGroup> = {}): FieldGroup {
  return {
    id: 100,
    workflow_version_id: 7,
    name: 'request_data',
    label: 'بيانات الطلب',
    sort_order: 0,
    fields: [],
    created_at: null,
    updated_at: null,
    version: 1,
    ...overrides,
  }
}

describe('useWorkflowFields', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches groups with nested fields', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeGroup({ fields: [makeField()] })] })
    const { groups, fetchGroups } = useWorkflowFields()
    await fetchGroups(7)

    expect(mockGet).toHaveBeenCalledWith('/api/v1/workflow-versions/7/field-groups')
    expect(groups.value[0]?.fields).toHaveLength(1)
  })

  it('appends a created group with empty fields', async () => {
    mockGet.mockResolvedValueOnce({ data: [] })
    mockPost.mockResolvedValueOnce({ data: makeGroup({ id: 200, name: 'supplier' }) })

    const { groups, fetchGroups, createGroup } = useWorkflowFields()
    await fetchGroups(7)
    await createGroup(7, { name: 'supplier', label: 'المورد' })

    expect(groups.value[0]?.name).toBe('supplier')
    expect(groups.value[0]?.fields).toEqual([])
  })

  it('adds a created field to its parent group', async () => {
    mockGet.mockResolvedValueOnce({ data: [makeGroup()] })
    mockPost.mockResolvedValueOnce({ data: makeField({ id: 2, field_group_id: 100 }) })

    const { groups, fetchGroups, createField } = useWorkflowFields()
    await fetchGroups(7)
    await createField(7, { field_group_id: 100, key: 'amount', label: 'Amount', type: 'CURRENCY' })

    expect(groups.value[0]?.fields).toHaveLength(1)
    expect(groups.value[0]?.fields[0]?.key).toBe('amount')
  })

  it('removes a deleted field from its group', async () => {
    const field = makeField({ id: 5 })
    mockGet.mockResolvedValueOnce({ data: [makeGroup({ fields: [field] })] })
    mockDelete.mockResolvedValueOnce(undefined)

    const { groups, fetchGroups, deleteField } = useWorkflowFields()
    await fetchGroups(7)
    await deleteField(7, field)

    expect(mockDelete).toHaveBeenCalledWith('/api/v1/workflow-versions/7/fields/5')
    expect(groups.value[0]?.fields).toHaveLength(0)
  })

  it('records an error on fetch failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('boom'))
    const { error, groups, fetchGroups } = useWorkflowFields()
    await fetchGroups(7)

    expect(error.value).toBeTruthy()
    expect(groups.value).toHaveLength(0)
  })

  it('createField accepts a semantic_tag in the payload and forwards it verbatim', async () => {
    mockPost.mockResolvedValueOnce({ data: makeField({ semantic_tag: 'INVOICE_NUMBER' }) })
    const { createField } = useWorkflowFields()

    await createField(7, {
      field_group_id: 100,
      key: 'invoice_number',
      label: 'رقم الفاتورة',
      type: 'TEXT',
      semantic_tag: 'INVOICE_NUMBER',
    })

    const [, body] = mockPost.mock.calls[0] as [string, Record<string, unknown>]
    expect(body.semantic_tag).toBe('INVOICE_NUMBER')
  })
})
