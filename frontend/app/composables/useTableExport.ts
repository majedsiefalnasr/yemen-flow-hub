export interface ExportColumn<T> {
  key: keyof T | string
  label: string
  format?: (value: unknown, row: T) => string
}

export function useTableExport() {
  function exportToCSV<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const headers = columns.map(c => `"${c.label.replace(/"/g, '""')}"`).join(',')
    const body = rows.map(row =>
      columns.map((column) => {
        const raw = column.key in row ? row[column.key as string] : undefined
        const value = column.format ? column.format(raw, row) : (raw ?? '')
        const normalized = String(value).replace(/"/g, '""')
        return `"${normalized}"`
      }).join(','),
    ).join('\n')

    const bom = '\uFEFF'
    const content = `${bom}${headers}\n${body}`
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${filename}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  function exportToJSON<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const data = rows.map(row =>
      columns.reduce((acc, column) => {
        const raw = column.key in row ? row[column.key as string] : undefined
        acc[column.label] = column.format ? column.format(raw, row) : (raw ?? '')
        return acc
      }, {} as Record<string, unknown>),
    )

    const content = JSON.stringify(data, null, 2)
    const blob = new Blob([content], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${filename}.json`
    link.click()
    URL.revokeObjectURL(url)
  }

  return { exportToCSV, exportToJSON }
}
