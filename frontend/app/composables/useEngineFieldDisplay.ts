import type { ResolvedFieldDefinition } from '@/types/models'

function formatSelectLabel(option: { label: string; inactive?: boolean }): string {
  return option.inactive ? `${option.label} (غير نشط)` : option.label
}

const numberFormatter = new Intl.NumberFormat('ar-EG')
const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

/**
 * Format a stored request-data value for read-only display, resolving SELECT
 * option labels and formatting numbers/dates/currency. FILE fields are handled
 * separately by the documents panel and return an empty string here.
 */
export function formatFieldValue(field: ResolvedFieldDefinition, raw: unknown): string {
  if (raw == null || raw === '') return '—'

  switch (field.type) {
    case 'SELECT':
    case 'DYNAMIC_SELECT': {
      const options = field.dynamic_options ?? field.options ?? []
      const match = options.find((o) => String(o.value) === String(raw))
      return match ? formatSelectLabel(match) : String(raw)
    }
    case 'CHECKBOX':
      return raw ? 'نعم' : 'لا'
    case 'NUMBER':
    case 'CURRENCY': {
      const value = typeof raw === 'number' ? raw : Number(raw)
      return Number.isFinite(value) ? numberFormatter.format(value) : String(raw)
    }
    case 'DATE': {
      const date = new Date(String(raw))
      return Number.isNaN(date.getTime()) ? String(raw) : dateFormatter.format(date)
    }
    case 'FILE':
      return ''
    default:
      return String(raw)
  }
}
