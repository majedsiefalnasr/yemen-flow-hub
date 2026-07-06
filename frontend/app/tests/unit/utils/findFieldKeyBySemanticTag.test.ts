import { describe, expect, it } from 'vitest'
import { findFieldKeyBySemanticTag, groupHasSemanticTag } from '@/utils/findFieldKeyBySemanticTag'
import type { ResolvedFieldGroup } from '@/types/models'

function field(overrides: Partial<ResolvedFieldGroup['fields'][number]>) {
  return {
    id: 1,
    key: 'k',
    semantic_tag: null,
    label: 'Field',
    type: 'TEXT' as const,
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
    multiple: false,
    is_visible: true,
    is_editable: true,
    is_required: false,
    dynamic_options: null,
    ...overrides,
  }
}

const groups: ResolvedFieldGroup[] = [
  {
    id: 1,
    name: 'main',
    label: 'Main',
    sort_order: 0,
    fields: [field({ key: 'amount' })],
  },
  {
    id: 2,
    name: 'invoice_details',
    label: 'Invoice Details',
    sort_order: 1,
    fields: [
      field({ key: 'tax_number_field', semantic_tag: 'MERCHANT_TAX_NUMBER' }),
      field({ key: 'invoice_number_field', semantic_tag: 'INVOICE_NUMBER' }),
    ],
  },
]

describe('findFieldKeyBySemanticTag', () => {
  it('returns the key of the field carrying the given semantic tag', () => {
    expect(findFieldKeyBySemanticTag(groups, 'MERCHANT_TAX_NUMBER')).toBe('tax_number_field')
    expect(findFieldKeyBySemanticTag(groups, 'INVOICE_NUMBER')).toBe('invoice_number_field')
  })

  it('returns null when no field carries the tag (never falls back to a hardcoded key)', () => {
    expect(findFieldKeyBySemanticTag(groups, 'SUPPLIER_NAME')).toBeNull()
    expect(findFieldKeyBySemanticTag([], 'INVOICE_NUMBER')).toBeNull()
  })
})

describe('groupHasSemanticTag', () => {
  it('reports true only for the group holding the tagged field', () => {
    expect(groupHasSemanticTag(groups[0]!, 'MERCHANT_TAX_NUMBER')).toBe(false)
    expect(groupHasSemanticTag(groups[1]!, 'MERCHANT_TAX_NUMBER')).toBe(true)
    expect(groupHasSemanticTag(groups[1]!, 'INVOICE_NUMBER')).toBe(true)
    expect(groupHasSemanticTag(groups[1]!, 'SUPPLIER_NAME')).toBe(false)
  })
})
