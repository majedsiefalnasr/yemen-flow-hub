<script setup lang="ts" generic="TData, TValue">
import type { ColumnDef, ColumnFiltersState, PaginationState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/vue-table'
import type { Ref } from 'vue'
import {
  FlexRender,
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { useVModel } from '@vueuse/core'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = withDefaults(defineProps<{
  data: TData[]
  columns: ColumnDef<TData, TValue>[]
  loading?: boolean
  pageCount?: number
  pagination?: { pageIndex: number; pageSize: number }
  sorting?: SortingState
  columnFilters?: ColumnFiltersState
  columnVisibility?: VisibilityState
  rowSelection?: Record<string, boolean>
  rowClass?: string | ((row: TData) => string)
  isRowExpanded?: (row: TData) => boolean
}>(), {
  loading: false,
  pageCount: -1,
})

const emit = defineEmits<{
  'update:sorting': [value: SortingState]
  'update:columnFilters': [value: ColumnFiltersState]
  'update:columnVisibility': [value: VisibilityState]
  'update:rowSelection': [value: Record<string, boolean>]
  'update:pagination': [value: { pageIndex: number; pageSize: number }]
  'row-click': [value: TData]
}>()

const sorting = useVModel(props, 'sorting', emit, { defaultValue: [] as SortingState, passive: true }) as Ref<SortingState>
const columnFilters = useVModel(props, 'columnFilters', emit, { defaultValue: [] as ColumnFiltersState, passive: true }) as Ref<ColumnFiltersState>
const columnVisibility = useVModel(props, 'columnVisibility', emit, { defaultValue: {} as VisibilityState, passive: true }) as Ref<VisibilityState>
const rowSelection = useVModel(props, 'rowSelection', emit, { defaultValue: {} as RowSelectionState, passive: true }) as Ref<RowSelectionState>
const pagination = useVModel(props, 'pagination', emit, { defaultValue: { pageIndex: 0, pageSize: 20 } as PaginationState, passive: true }) as Ref<PaginationState>

function resolveHeaderClass(meta?: unknown) {
  return (meta as { headerClass?: string } | undefined)?.headerClass
}

function resolveCellClass(meta?: unknown) {
  return (meta as { cellClass?: string } | undefined)?.cellClass
}

function resolveRowClass(row: TData) {
  if (typeof props.rowClass === 'function') return props.rowClass(row)
  return props.rowClass ?? ''
}

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
  get pageCount() { return props.pageCount },
  manualPagination: props.pageCount !== -1,
  manualSorting: props.pageCount !== -1,
  manualFiltering: props.pageCount !== -1,
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
    get pagination() { return pagination.value },
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onSortingChange: (updater) => {
    sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater
    emit('update:sorting', sorting.value)
  },
  onColumnFiltersChange: (updater) => {
    columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater
    emit('update:columnFilters', columnFilters.value)
  },
  onColumnVisibilityChange: (updater) => {
    columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater
    emit('update:columnVisibility', columnVisibility.value)
  },
  onRowSelectionChange: (updater) => {
    rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater
    emit('update:rowSelection', rowSelection.value)
  },
  onPaginationChange: (updater) => {
    pagination.value = typeof updater === 'function' ? updater(pagination.value) : updater
    emit('update:pagination', pagination.value)
  },
})

defineExpose({ table })
</script>

<template>
  <div class="space-y-4">
    <slot name="toolbar" :table="table" />

    <!-- Table — only rendered when loading or rows exist -->
    <div v-if="loading || table.getRowModel().rows.length > 0" class="rounded-lg border overflow-x-auto">
      <Table class="min-w-full">
        <TableHeader>
          <TableRow
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
          >
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              :data-pinned="header.column.getIsPinned()"
              :class="resolveHeaderClass(header.column.columnDef.meta)"
            >
              <FlexRender
                v-if="!header.isPlaceholder"
                :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="loading">
            <TableRow v-for="i in 5" :key="i">
              <TableCell v-for="col in columns" :key="String(col)" class="py-3">
                <Skeleton class="h-4 w-full" />
              </TableCell>
            </TableRow>
          </template>
          <template v-else>
            <template v-for="row in table.getRowModel().rows" :key="row.id">
              <TableRow
                :data-state="row.getIsSelected() ? 'selected' : undefined"
                class="cursor-pointer transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
                :class="resolveRowClass(row.original)"
                @click="emit('row-click', row.original)"
              >
                <TableCell
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  :class="resolveCellClass(cell.column.columnDef.meta)"
                >
                  <FlexRender
                    :render="cell.column.columnDef.cell"
                    :props="cell.getContext()"
                  />
                </TableCell>
              </TableRow>
              <TableRow v-if="props.isRowExpanded?.(row.original)" class="bg-muted/20">
                <TableCell :colspan="row.getVisibleCells().length" class="px-6 py-4">
                  <slot name="row-expanded" :row="row.original" :colspan="row.getVisibleCells().length" />
                </TableCell>
              </TableRow>
            </template>
          </template>
        </TableBody>
      </Table>
    </div>

    <!-- Empty state — rendered outside the table when not loading and no rows -->
    <template v-else>
      <slot name="empty">
        <div class="flex min-h-[200px] items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
          لا توجد بيانات
        </div>
      </slot>
    </template>

    <slot name="pagination" :table="table" />
  </div>
</template>
