import { describe, expect, it } from 'vitest'
import { formatFieldValue } from '@/composables/useEngineFieldDisplay'
import type { ResolvedFieldDefinition } from '@/types/models'

function field(overrides: Partial<ResolvedFieldDefinition>): ResolvedFieldDefinition {
  return {
    id: 1,
    key: 'k',
    semantic_tag: null,
    label: 'Field',
    type: 'TEXT',
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

describe('formatFieldValue', () => {
  it('returns an em dash for empty values', () => {
    expect(formatFieldValue(field({ type: 'TEXT' }), null)).toBe('—')
    expect(formatFieldValue(field({ type: 'TEXT' }), '')).toBe('—')
  })

  it('resolves a SELECT option label from its value', () => {
    const f = field({
      type: 'SELECT',
      options: [
        { value: 'aden_port', label: 'ميناء عدن' },
        { value: 'hodeidah_port', label: 'ميناء الحديدة' },
      ],
    })
    expect(formatFieldValue(f, 'aden_port')).toBe('ميناء عدن')
  })

  it('prefers dynamic_options over static options for DYNAMIC_SELECT', () => {
    const f = field({
      type: 'DYNAMIC_SELECT',
      dynamic_options: [{ value: 7, label: 'التاجر السابع' }],
    })
    expect(formatFieldValue(f, 7)).toBe('التاجر السابع')
  })

  it('marks inactive dynamic options in read-only display', () => {
    const f = field({
      type: 'DYNAMIC_SELECT',
      dynamic_options: [{ value: 7, label: 'التاجر السابع', inactive: true }],
    })
    expect(formatFieldValue(f, 7)).toBe('التاجر السابع (غير نشط)')
  })

  it('falls back to the raw value when no option matches', () => {
    const f = field({ type: 'SELECT', options: [{ value: 'a', label: 'A' }] })
    expect(formatFieldValue(f, 'z')).toBe('z')
  })

  it('renders CHECKBOX as نعم/لا', () => {
    expect(formatFieldValue(field({ type: 'CHECKBOX' }), true)).toBe('نعم')
    expect(formatFieldValue(field({ type: 'CHECKBOX' }), false)).toBe('لا')
  })

  it('returns an empty string for FILE fields', () => {
    expect(formatFieldValue(field({ type: 'FILE' }), ['doc'])).toBe('')
  })
})
