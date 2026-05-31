<script setup lang="ts" generic="TData, TValue">
import type {
  ColumnDef,
  ColumnFiltersState,
  PaginationState,
  RowSelectionState,
  SortingState,
  VisibilityState,
} from '@tanstack/vue-table'
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
import type { Ref } from 'vue'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { valueUpdater } from '@/components/ui/table/utils'

const props = withDefaults(defineProps<{
  data: TData[]
  columns: ColumnDef<TData, TValue>[]
  loading?: boolean
  /**
   * For server-side pagination: total number of pages the server reports.
   * When provided, manualPagination is enabled and TanStack will not slice data.
   * Omit (or -1) for client-side pagination.
   */
  pageCount?: number
  pagination?: PaginationState
  sorting?: SortingState
  columnFilters?: ColumnFiltersState
  columnVisibility?: VisibilityState
  rowSelection?: RowSelectionState
  rowClass?: string | ((row: TData) => string)
  isRowExpanded?: (row: TData) => boolean
}>(), {
  loading: false,
  pageCount: -1,
})

const emit = defineEmits<{
  'update:pagination': [value: PaginationState]
  'update:sorting': [value: SortingState]
  'update:columnFilters': [value: ColumnFiltersState]
  'update:columnVisibility': [value: VisibilityState]
  'update:rowSelection': [value: RowSelectionState]
  'row-click': [value: TData]
}>()

// Each state is a ref. The parent can control it via v-model props,
// or leave it uncontrolled and TanStack manages it internally.
const pagination = useVModel(props, 'pagination', emit, {
  defaultValue: { pageIndex: 0, pageSize: 20 } as PaginationState,
  passive: true,
}) as Ref<PaginationState>

const sorting = useVModel(props, 'sorting', emit, {
  defaultValue: [] as SortingState,
  passive: true,
}) as Ref<SortingState>

const columnFilters = useVModel(props, 'columnFilters', emit, {
  defaultValue: [] as ColumnFiltersState,
  passive: true,
}) as Ref<ColumnFiltersState>

const columnVisibility = useVModel(props, 'columnVisibility', emit, {
  defaultValue: {} as VisibilityState,
  passive: true,
}) as Ref<VisibilityState>

const rowSelection = useVModel(props, 'rowSelection', emit, {
  defaultValue: {} as RowSelectionState,
  passive: true,
}) as Ref<RowSelectionState>

// Server-side mode is decided once at init. In server-side mode the server
// already returns exactly the rows for the current page, so we must NOT register
// getPaginationRowModel — it would slice the already-paginated data down to
// pageSize. In client-side mode we register it so TanStack slices locally.
const isServerSide = props.pageCount !== -1

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
  // Only feed TanStack an explicit pageCount in server-side mode. In client-side
  // mode leave it undefined so TanStack derives the page count from the row model;
  // passing -1 would make getPageCount() return -1 (e.g. "صفحة 1 من -1").
  get pageCount() { return props.pageCount === -1 ? undefined : props.pageCount },
  manualPagination: isServerSide,
  manualSorting: isServerSide,
  manualFiltering: isServerSide,
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
    get pagination() { return pagination.value },
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  // Only slice client-side. Server-side data arrives pre-paginated.
  ...(isServerSide ? {} : { getPaginationRowModel: getPaginationRowModel() }),
  getSortedRowModel: getSortedRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  onSortingChange: updater => valueUpdater(updater, sorting),
  onColumnFiltersChange: updater => valueUpdater(updater, columnFilters),
  onColumnVisibilityChange: updater => valueUpdater(updater, columnVisibility),
  onRowSelectionChange: updater => valueUpdater(updater, rowSelection),
  // passive useVModel updates the local ref AND emits update:pagination, so
  // uncontrolled tables keep their page size locally while controlled tables
  // (URL-driven) still receive the event.
  onPaginationChange: updater => valueUpdater(updater, pagination),
})

function resolveHeaderClass(meta: unknown) {
  return (meta as { headerClass?: string } | undefined)?.headerClass
}

function resolveCellClass(meta: unknown) {
  return (meta as { cellClass?: string } | undefined)?.cellClass
}

function resolveRowClass(row: TData) {
  if (typeof props.rowClass === 'function') return props.rowClass(row)
  return props.rowClass ?? ''
}

defineExpose({ table })
</script>

<template>
  <div class="space-y-4">
    <slot name="toolbar" :table="table" />

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

              <TableRow v-if="isRowExpanded?.(row.original)" class="bg-muted/20">
                <TableCell :colspan="row.getVisibleCells().length" class="px-6 py-4">
                  <slot name="row-expanded" :row="row.original" />
                </TableCell>
              </TableRow>
            </template>
          </template>
        </TableBody>
      </Table>
    </div>

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
