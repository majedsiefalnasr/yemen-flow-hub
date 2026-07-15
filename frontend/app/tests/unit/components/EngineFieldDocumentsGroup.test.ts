// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EngineFieldDocumentsGroup from '@/components/workflow/EngineFieldDocumentsGroup.vue'
import type { ResolvedFieldGroup, EngineRequestDocument } from '@/types/models'

function makeField(overrides: Partial<ResolvedFieldGroup['fields'][number]> = {}) {
  return {
    id: 1,
    key: 'invoice_doc',
    semantic_tag: null,
    label: 'فاتورة',
    type: 'FILE' as const,
    placeholder: null,
    help_text: null,
    default_value: null,
    min_value: null,
    max_value: null,
    min_length: null,
    max_length: null,
    regex_pattern: null,
    options: null,
    dynamic_source: null,
    allowed_file_types: null,
    max_file_size: null,
    multiple: true,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

function makeGroup(fields: ResolvedFieldGroup['fields']): ResolvedFieldGroup {
  return { id: 10, name: 'documents', label: 'المستندات', sort_order: 1, fields }
}

function makeDoc(overrides: Partial<EngineRequestDocument> = {}): EngineRequestDocument {
  return {
    id: 100,
    request_id: 5,
    field_id: 1,
    stage_id: 1,
    original_name: 'invoice.pdf',
    mime: 'application/pdf',
    size: 1024,
    uploaded_by: { id: 1, name: 'Test User' },
    created_at: '2026-06-25T00:00:00Z',
    ...overrides,
  }
}

const stubs = { EngineDocumentsPanel: true }

describe('EngineFieldDocumentsGroup', () => {
  it('renders one EngineDocumentsPanel per FILE field, filtered by field_id', () => {
    const fieldA = makeField({ id: 1, key: 'invoice_doc', label: 'فاتورة' })
    const fieldB = makeField({ id: 2, key: 'contract_doc', label: 'عقد' })
    const docs = [
      makeDoc({ id: 100, field_id: 1 }),
      makeDoc({ id: 101, field_id: 2 }),
      makeDoc({ id: 102, field_id: 1 }),
    ]
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([fieldA, fieldB]),
        documents: docs,
        requestId: 5,
        canManage: true,
      },
      global: { stubs },
    })

    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels).toHaveLength(2)
    expect(panels[0]!.props('documents')).toEqual([docs[0], docs[2]])
    expect(panels[1]!.props('documents')).toEqual([docs[1]])
  })

  it('passes canManage=false for a field that is not editable even when the group-level canManage is true', () => {
    const editableField = makeField({ id: 1, is_editable: true })
    const readOnlyField = makeField({ id: 2, key: 'readonly_doc', is_editable: false })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([editableField, readOnlyField]),
        documents: [],
        requestId: 5,
        canManage: true,
      },
      global: { stubs },
    })

    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels[0]!.props('canManage')).toBe(true)
    expect(panels[1]!.props('canManage')).toBe(false)
  })

  it('never allows management when the group-level canManage is false, regardless of field is_editable', () => {
    const field = makeField({ id: 1, is_editable: true })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: false },
      global: { stubs },
    })

    expect(wrapper.findComponent({ name: 'EngineDocumentsPanel' }).props('canManage')).toBe(false)
  })

  it('skips non-visible fields', () => {
    const visible = makeField({ id: 1 })
    const hidden = makeField({ id: 2, key: 'hidden_doc', is_visible: false })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([visible, hidden]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })

    expect(wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })).toHaveLength(1)
  })

  it('re-emits upload with the originating field id', async () => {
    const field = makeField({ id: 1 })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })
    const panel = wrapper.findComponent({ name: 'EngineDocumentsPanel' })
    const file = new File(['x'], 'a.pdf', { type: 'application/pdf' })
    panel.vm.$emit('upload', file)

    expect(wrapper.emitted('upload')).toEqual([[1, file]])
  })

  it('re-emits remove with the document id unchanged', async () => {
    const field = makeField({ id: 1 })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: { group: makeGroup([field]), documents: [], requestId: 5, canManage: true },
      global: { stubs },
    })
    const panel = wrapper.findComponent({ name: 'EngineDocumentsPanel' })
    panel.vm.$emit('remove', 999)

    expect(wrapper.emitted('remove')).toEqual([[999]])
  })

  it('renders a read-only orphaned-documents section for docs whose field_id matches no current field', () => {
    const field = makeField({ id: 1 })
    const orphan = makeDoc({ id: 200, field_id: 999, original_name: 'legacy.pdf' })
    const wrapper = mount(EngineFieldDocumentsGroup, {
      props: {
        group: makeGroup([field]),
        documents: [orphan],
        requestId: 5,
        canManage: true,
      },
      global: { stubs: { EngineDocumentsPanel: true } },
    })

    // The orphan panel is present and rendered with canManage=false (read-only),
    // separate from the field-scoped panel.
    const panels = wrapper.findAllComponents({ name: 'EngineDocumentsPanel' })
    expect(panels).toHaveLength(2)
    const orphanPanel = panels.find((p) =>
      (p.props('documents') as EngineRequestDocument[]).includes(orphan),
    )
    expect(orphanPanel).toBeTruthy()
    expect(orphanPanel!.props('canManage')).toBe(false)
  })
})
