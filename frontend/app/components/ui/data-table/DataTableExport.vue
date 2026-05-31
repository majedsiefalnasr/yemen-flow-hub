<script setup lang="ts" generic="TData extends Record<string, unknown>">
import type { Table } from '@tanstack/vue-table'
import { Download } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { ExportColumn } from '@/composables/useTableExport'
import { useTableExport } from '@/composables/useTableExport'

type ExportFormat = 'csv' | 'tsv' | 'json' | 'excel' | 'pdf'

const props = withDefaults(defineProps<{
  table: Table<TData>
  /** Filename without extension. Defaults to 'export'. */
  filename?: string
  /**
   * Column definitions for export.
   * If omitted, all visible accessor columns are auto-detected.
   */
  exportColumns?: ExportColumn<TData>[]
  /**
   * Respect visible table columns when exporting explicit `exportColumns`.
   * Requires `columnId` in export column config when key differs from table id.
   */
  respectColumnVisibility?: boolean
  /** Labels for auto-detected columns. Falls back to column id. */
  columnLabels?: Record<string, string>
  /** Formats to offer in the dropdown menu. */
  formats?: ExportFormat[]
}>(), {
  filename: 'export',
  formats: () => ['csv', 'tsv', 'json', 'excel', 'pdf'],
  respectColumnVisibility: false,
})

const { exportToCSV, exportToJSON, exportToTSV, exportToExcel, exportToPDF } = useTableExport()

const resolvedColumns = computed<ExportColumn<TData>[]>(() => {
  if (props.exportColumns) {
    if (!props.respectColumnVisibility) return props.exportColumns

    const visibleColumnIds = new Set(props.table.getVisibleLeafColumns().map(column => column.id))
    return props.exportColumns.filter((column) => {
      const explicitColumnId = column.columnId?.trim()
      if (explicitColumnId) return visibleColumnIds.has(explicitColumnId)
      return true
    })
  }

  // Auto-detect from visible accessor columns
  return props.table
    .getAllColumns()
    .filter(col => col.getIsVisible() && col.getCanHide() && typeof col.accessorFn !== 'undefined')
    .map(col => ({
      key: col.id as keyof TData,
      label: props.columnLabels?.[col.id] ?? col.id,
    }))
})

function getRows() {
  return props.table.getFilteredRowModel().rows.map(row => row.original)
}

const formatLabel: Record<ExportFormat, string> = {
  csv: 'CSV',
  tsv: 'TSV',
  json: 'JSON',
  excel: 'Excel',
  pdf: 'PDF',
}

const exportOptions = computed(() => {
  const requestedFormats = (props.formats.length ? props.formats : ['csv', 'tsv', 'json', 'excel', 'pdf']) as ExportFormat[]
  return requestedFormats.map(format => ({
    id: format,
    format,
    label: formatLabel[format],
  }))
})

function doExport(format: ExportFormat) {
  const rows = getRows()
  const cols = resolvedColumns.value
  const name = props.filename

  if (format === 'csv') {
    exportToCSV(rows, cols, name)
    return
  }

  if (format === 'tsv') {
    exportToTSV(rows, cols, name)
    return
  }

  if (format === 'excel') {
    exportToExcel(rows, cols, name)
    return
  }

  if (format === 'pdf') {
    exportToPDF(rows, cols, name)
    return
  }

  exportToJSON(rows, cols, name)
}

const canExport = computed(() =>
  resolvedColumns.value.length > 0
  && getRows().length > 0,
)
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="outline"
        size="sm"
        class="ms-auto h-8 flex"
        :disabled="!canExport"
      >
        <Download class="me-2 h-4 w-4" />
        تصدير
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuLabel>تصدير كـ</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuGroup>
        <DropdownMenuItem
          v-for="option in exportOptions"
          :key="option.id"
          :disabled="!canExport"
          @click="doExport(option.format)"
        >
          <span>{{ option.label }}</span>
        </DropdownMenuItem>
      </DropdownMenuGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
