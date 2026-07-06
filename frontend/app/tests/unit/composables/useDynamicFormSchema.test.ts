import { describe, expect, it } from 'vitest'
import { buildDynamicSchema } from '@/composables/useDynamicFormSchema'
import type { ResolvedFieldGroup } from '@/types/models'

function group(fields: ResolvedFieldGroup['fields']): ResolvedFieldGroup[] {
  return [{ id: 1, name: 'g1', label: 'مجموعة', sort_order: 0, fields }]
}

function baseField(overrides: Partial<ResolvedFieldGroup['fields'][number]>) {
  return {
    id: 1,
    key: 'field_key',
    label: 'حقل',
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

describe('buildDynamicSchema', () => {
  it('omits fields where is_visible is false', () => {
    const schema = buildDynamicSchema(group([baseField({ is_visible: false })]))
    expect(schema.shape.field_key).toBeUndefined()
  })

  it('TEXT field with is_required true rejects empty string', () => {
    const schema = buildDynamicSchema(group([baseField({ type: 'TEXT', is_required: true })]))
    const result = schema.safeParse({ field_key: '' })
    expect(result.success).toBe(false)
  })

  it('TEXT field respects min_length and max_length', () => {
    const schema = buildDynamicSchema(
      group([baseField({ type: 'TEXT', min_length: 3, max_length: 5 })]),
    )
    expect(schema.safeParse({ field_key: 'ab' }).success).toBe(false)
    expect(schema.safeParse({ field_key: 'abcdef' }).success).toBe(false)
    expect(schema.safeParse({ field_key: 'abcd' }).success).toBe(true)
  })

  it('TEXT field respects regex_pattern', () => {
    const schema = buildDynamicSchema(
      group([baseField({ type: 'TEXT', regex_pattern: '^[0-9]+$' })]),
    )
    expect(schema.safeParse({ field_key: 'abc' }).success).toBe(false)
    expect(schema.safeParse({ field_key: '123' }).success).toBe(true)
  })

  it('NUMBER and CURRENCY fields respect min_value and max_value', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({ key: 'num', type: 'NUMBER', min_value: 10, max_value: 100 }),
        baseField({ key: 'cur', type: 'CURRENCY', min_value: 1 }),
      ]),
    )
    expect(schema.safeParse({ num: 5, cur: 5 }).success).toBe(false)
    expect(schema.safeParse({ num: 50, cur: 5 }).success).toBe(true)
  })

  it('NUMBER field is optional when is_required is false', () => {
    const schema = buildDynamicSchema(
      group([baseField({ key: 'num', type: 'NUMBER', is_required: false })]),
    )
    expect(schema.safeParse({}).success).toBe(true)
  })

  it('SELECT field validates against options values', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({
          key: 'choice',
          type: 'SELECT',
          options: [
            { value: 'A', label: 'أ' },
            { value: 'B', label: 'ب' },
          ],
          is_required: true,
        }),
      ]),
    )
    expect(schema.safeParse({ choice: 'C' }).success).toBe(false)
    expect(schema.safeParse({ choice: 'A' }).success).toBe(true)
  })

  it('DYNAMIC_SELECT field validates against dynamic_options values', () => {
    const schema = buildDynamicSchema(
      group([
        baseField({
          key: 'merchant',
          type: 'DYNAMIC_SELECT',
          dynamic_options: [{ value: 7, label: 'تاجر' }],
          is_required: true,
        }),
      ]),
    )
    expect(schema.safeParse({ merchant: 9 }).success).toBe(false)
    expect(schema.safeParse({ merchant: 7 }).success).toBe(true)
  })

  it('CHECKBOX field accepts boolean', () => {
    const schema = buildDynamicSchema(group([baseField({ key: 'agree', type: 'CHECKBOX' })]))
    expect(schema.safeParse({ agree: true }).success).toBe(true)
    expect(schema.safeParse({ agree: 'yes' }).success).toBe(false)
  })

  it('DATE field accepts an ISO date string', () => {
    const schema = buildDynamicSchema(
      group([baseField({ key: 'd', type: 'DATE', is_required: true })]),
    )
    expect(schema.safeParse({ d: '2026-06-25' }).success).toBe(true)
    expect(schema.safeParse({ d: '' }).success).toBe(false)
  })

  it('FILE field accepts document id references when required, empty array when not', () => {
    const required = buildDynamicSchema(
      group([baseField({ key: 'docs', type: 'FILE', is_required: true })]),
    )
    expect(required.safeParse({ docs: [] }).success).toBe(false)
    expect(required.safeParse({ docs: [1] }).success).toBe(true)
    expect(required.safeParse({ docs: [{ mime: 'application/pdf', size_kb: 50 }] }).success).toBe(
      false,
    )

    const optional = buildDynamicSchema(
      group([baseField({ key: 'docs', type: 'FILE', is_required: false })]),
    )
    expect(optional.safeParse({ docs: [] }).success).toBe(true)
  })

  it('TEXTAREA field behaves like TEXT for required/length', () => {
    const schema = buildDynamicSchema(
      group([baseField({ key: 'notes', type: 'TEXTAREA', is_required: true, max_length: 10 })]),
    )
    expect(schema.safeParse({ notes: '' }).success).toBe(false)
    expect(schema.safeParse({ notes: 'a'.repeat(11) }).success).toBe(false)
    expect(schema.safeParse({ notes: 'short' }).success).toBe(true)
  })

  it('flattens fields across multiple field groups into one schema', () => {
    const groups: ResolvedFieldGroup[] = [
      { id: 1, name: 'g1', label: 'أ', sort_order: 0, fields: [baseField({ key: 'a' })] },
      { id: 2, name: 'g2', label: 'ب', sort_order: 1, fields: [baseField({ key: 'b' })] },
    ]
    const schema = buildDynamicSchema(groups)
    expect(Object.keys(schema.shape)).toEqual(['a', 'b'])
  })
})
