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

const props = withDefaults(
  defineProps<{
    table: Table<TData>
    /** Filename without extension. Defaults to 'export'. */
    filename?: string
    /**
     * Column definitions for export.
     * If omitted, all visible accessor columns are auto-detected.
     */
    exportColumns?: ExportColumn<TData>[]
    /**
     * Filter out hidden columns when explicit exportColumns are provided.
     * Requires columnId on export column when the key differs from the table column id.
     */
    respectColumnVisibility?: boolean
    /** Human-readable labels for auto-detected columns. Falls back to column id. */
    columnLabels?: Record<string, string>
    /** Which export formats to show. Defaults to all five. */
    formats?: ExportFormat[]
  }>(),
  {
    filename: 'export',
    formats: () => ['csv', 'tsv', 'json', 'excel', 'pdf'] as ExportFormat[],
    respectColumnVisibility: false,
  },
)

const { exportToCSV, exportToJSON, exportToTSV, exportToExcel, exportToPDF } = useTableExport()

const resolvedColumns = computed<ExportColumn<TData>[]>(() => {
  if (props.exportColumns) {
    if (!props.respectColumnVisibility) return props.exportColumns

    const visibleIds = new Set(props.table.getVisibleLeafColumns().map((col) => col.id))
    return props.exportColumns.filter((col) => {
      const id = col.columnId?.trim()
      return id ? visibleIds.has(id) : true
    })
  }

  return props.table
    .getAllColumns()
    .filter(
      (col) => col.getIsVisible() && col.getCanHide() && typeof col.accessorFn !== 'undefined',
    )
    .map((col) => ({
      key: col.id as keyof TData,
      label: props.columnLabels?.[col.id] ?? col.id,
    }))
})

function getRows() {
  return props.table.getFilteredRowModel().rows.map((row) => row.original)
}

const formatLabel: Record<ExportFormat, string> = {
  csv: 'CSV',
  tsv: 'TSV',
  json: 'JSON',
  excel: 'Excel',
  pdf: 'PDF',
}

function doExport(format: ExportFormat) {
  const rows = getRows()
  const cols = resolvedColumns.value
  const name = props.filename

  switch (format) {
    case 'csv':
      exportToCSV(rows, cols, name)
      break
    case 'tsv':
      exportToTSV(rows, cols, name)
      break
    case 'excel':
      exportToExcel(rows, cols, name)
      break
    case 'pdf':
      exportToPDF(rows, cols, name)
      break
    default:
      exportToJSON(rows, cols, name)
  }
}

const canExport = computed(() => resolvedColumns.value.length > 0 && getRows().length > 0)
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" size="sm" class="ms-auto flex h-8" :disabled="!canExport">
        <Download class="me-2 h-4 w-4" />
        تصدير
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuLabel>تصدير كـ</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuGroup>
        <DropdownMenuItem
          v-for="format in formats"
          :key="format"
          :disabled="!canExport"
          @click="doExport(format)"
        >
          {{ formatLabel[format] }}
        </DropdownMenuItem>
      </DropdownMenuGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
