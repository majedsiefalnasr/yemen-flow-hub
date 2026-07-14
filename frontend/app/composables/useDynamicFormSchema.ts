import * as z from 'zod'
import type { ResolvedFieldDefinition, ResolvedFieldGroup } from '@/types/models'

function buildFieldSchema(field: ResolvedFieldDefinition): z.ZodTypeAny {
  switch (field.type) {
    case 'TEXT':
    case 'TEXTAREA': {
      let s = z.string()
      if (field.min_length !== null) s = s.min(field.min_length, 'القيمة قصيرة جداً.')
      if (field.max_length !== null) s = s.max(field.max_length, 'القيمة طويلة جداً.')
      if (field.regex_pattern !== null)
        s = s.regex(new RegExp(field.regex_pattern), 'صيغة غير صحيحة.')
      if (field.is_required) s = s.min(1, 'هذا الحقل مطلوب.')
      return field.is_required ? s : s.optional()
    }
    case 'NUMBER':
    case 'CURRENCY': {
      let n = z.number()
      if (field.min_value !== null) n = n.min(field.min_value, 'القيمة أقل من الحد المسموح.')
      if (field.max_value !== null) n = n.max(field.max_value, 'القيمة أكبر من الحد المسموح.')
      return field.is_required ? n : n.optional()
    }
    case 'DATE': {
      const s = z.string()
      return field.is_required ? s.min(1, 'هذا الحقل مطلوب.') : s.optional()
    }
    case 'SELECT': {
      const values = (field.options ?? []).map((o) => o.value)
      const e = values.length > 0 ? z.enum(values as [string, ...string[]]) : z.string()
      return field.is_required ? e : e.optional()
    }
    case 'DYNAMIC_SELECT': {
      const values = (field.dynamic_options ?? []).map((o) => o.value)
      const u = z
        .union([z.string(), z.number()])
        .refine((v) => values.length === 0 || values.includes(v), { message: 'اختر قيمة صحيحة.' })
      return field.is_required ? u : u.optional()
    }
    case 'CHECKBOX': {
      return field.is_required ? z.boolean() : z.boolean().optional()
    }
    case 'FILE': {
      const a = z.array(z.number().int().positive())
      return field.is_required ? a.min(1, 'يجب إرفاق ملف واحد على الأقل.') : a
    }
    default:
      return z.unknown()
  }
}

export function buildDynamicSchema(
  fieldGroups: ResolvedFieldGroup[],
): z.ZodObject<Record<string, z.ZodTypeAny>> {
  const shape: Record<string, z.ZodTypeAny> = {}

  for (const group of fieldGroups) {
    for (const field of group.fields) {
      if (!field.is_visible) continue
      shape[field.key] = buildFieldSchema(field)
    }
  }

  return z.object(shape)
}
