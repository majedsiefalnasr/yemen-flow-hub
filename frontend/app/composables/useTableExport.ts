export interface ExportColumn<T> {
  key: keyof T | string
  /**
   * Optional TanStack table column id tied to this export field.
   * Use this when export key differs from visible table column id.
   */
  columnId?: string
  label: string
  format?: (value: unknown, row: T) => string
}

export function useTableExport() {
  function escapeDelimitedValue(value: unknown) {
    const normalized = String(value ?? '').replace(/"/g, '""')
    return `"${normalized}"`
  }

  function toDelimitedContent<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    delimiter: ',' | '\t',
  ) {
    const headers = columns.map(c => escapeDelimitedValue(c.label)).join(delimiter)
    const body = rows.map(row =>
      columns.map((column) => {
        const raw = column.key in row ? row[column.key as string] : undefined
        const value = column.format ? column.format(raw, row) : (raw ?? '')
        return escapeDelimitedValue(value)
      }).join(delimiter),
    ).join('\n')
    return { headers, body }
  }

  function downloadBlob(content: string, type: string, filename: string) {
    const blob = new Blob([content], { type })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  }

  function exportToCSV<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const { headers, body } = toDelimitedContent(rows, columns, ',')

    const bom = '\uFEFF'
    const content = `${bom}${headers}\n${body}`
    downloadBlob(content, 'text/csv;charset=utf-8;', `${filename}.csv`)
  }

  function exportToTSV<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const { headers, body } = toDelimitedContent(rows, columns, '\t')
    const bom = '\uFEFF'
    const content = `${bom}${headers}\n${body}`
    downloadBlob(content, 'text/tab-separated-values;charset=utf-8;', `${filename}.tsv`)
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
    downloadBlob(content, 'application/json', `${filename}.json`)
  }

  function normalizeRowsForObjects<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
  ) {
    return rows.map(row =>
      columns.reduce((acc, column) => {
        const raw = column.key in row ? row[column.key as string] : undefined
        acc[column.label] = column.format ? column.format(raw, row) : (raw ?? '')
        return acc
      }, {} as Record<string, unknown>),
    )
  }

  function exportToExcel<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const normalizedRows = normalizeRowsForObjects(rows, columns)
    const headerHtml = columns
      .map(column => `<th>${String(column.label)}</th>`)
      .join('')
    const bodyHtml = normalizedRows
      .map((row) => {
        const cells = columns
          .map(column => `<td>${String(row[column.label] ?? '')}</td>`)
          .join('')
        return `<tr>${cells}</tr>`
      })
      .join('')

    const content = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8" /></head>
<body>
  <table border="1">
    <thead><tr>${headerHtml}</tr></thead>
    <tbody>${bodyHtml}</tbody>
  </table>
</body>
</html>`

    downloadBlob(content, 'application/vnd.ms-excel;charset=utf-8;', `${filename}.xls`)
  }

  function exportToPDF<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client || !rows.length) return

    const normalizedRows = normalizeRowsForObjects(rows, columns)
    const headerHtml = columns
      .map(column => `<th style="border:1px solid #ccc;padding:6px;text-align:start">${String(column.label)}</th>`)
      .join('')
    const bodyHtml = normalizedRows
      .map((row) => {
        const cells = columns
          .map(column => `<td style="border:1px solid #ccc;padding:6px">${String(row[column.label] ?? '')}</td>`)
          .join('')
        return `<tr>${cells}</tr>`
      })
      .join('')

    const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=1024,height=768')
    if (!printWindow) return

    printWindow.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>${filename}.pdf</title>
</head>
<body>
  <h3 style="margin-bottom:12px">${filename}</h3>
  <table style="border-collapse:collapse;width:100%">
    <thead><tr>${headerHtml}</tr></thead>
    <tbody>${bodyHtml}</tbody>
  </table>
</body>
</html>`)
    printWindow.document.close()
    printWindow.focus()
    printWindow.print()
    printWindow.close()
  }

  return { exportToCSV, exportToTSV, exportToJSON, exportToExcel, exportToPDF }
}
