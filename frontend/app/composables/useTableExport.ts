import type { ColumnDef } from '@tanstack/vue-table'

interface ExportOptions<TData> {
  filename?: string
  /** Only export visible columns. Pass column definitions to resolve headers. */
  columns?: ColumnDef<TData, unknown>[]
  /** Columns to exclude from export by id */
  excludeColumns?: string[]
}

/**
 * Client-side CSV/JSON export for table data.
 * Prepends UTF-8 BOM (\uFEFF) to CSV for Arabic Excel compatibility.
 */
export function useTableExport<TData extends Record<string, unknown>>() {
  function exportCsv(rows: TData[], options: ExportOptions<TData> = {}) {
    if (!rows.length) return

    const { filename = 'export', excludeColumns = [] } = options

    const firstRow = rows[0]
    if (!firstRow) return
    const keys = (Object.keys(firstRow) as string[]).filter(k => !excludeColumns.includes(k))

    const escape = (val: unknown): string => {
      const str = val === null || val === undefined ? '' : String(val)
      // Wrap in quotes if contains comma, newline, or quote
      if (/[,"\n\r]/.test(str)) return `"${str.replace(/"/g, '""')}"`
      return str
    }

    const lines = [
      keys.map(escape).join(','),
      ...rows.map(row => keys.map(k => escape(row[k])).join(',')),
    ]

    const bom = '\uFEFF'
    const blob = new Blob([bom + lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    triggerDownload(blob, `${filename}.csv`)
  }

  function exportJson(rows: TData[], options: ExportOptions<TData> = {}) {
    if (!rows.length) return

    const { filename = 'export', excludeColumns = [] } = options
    const filtered = rows.map(row =>
      Object.fromEntries(Object.entries(row).filter(([k]) => !excludeColumns.includes(k))),
    )

    const blob = new Blob([JSON.stringify(filtered, null, 2)], { type: 'application/json;charset=utf-8;' })
    triggerDownload(blob, `${filename}.json`)
  }

  function triggerDownload(blob: Blob, filename: string) {
    if (!import.meta.client) return
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  }

  return { exportCsv, exportJson }
}
