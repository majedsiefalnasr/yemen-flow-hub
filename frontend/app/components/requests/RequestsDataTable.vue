<script setup lang="ts">
import type { ColumnFiltersState, SortingState, VisibilityState } from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { SearchX, X } from 'lucide-vue-next'
import {
  DataTableFacetedFilter,
  DataTablePagination,
  DataTableViewOptions,
} from '@/components/ui/data-table'
import { Button } from '@/components/ui/button'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { REQUESTS_COLUMN_LABELS, STATUS_FILTER_OPTIONS, useRequestsColumns } from '@/composables/useRequestsColumns'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import type { ImportRequest } from '@/types/models'

const props = defineProps<{
  data: ImportRequest[]
  loading?: boolean
  noData?: boolean
  role: UserRole
}>()

const emit = defineEmits<{
  rowClick: [requestId: number]
  previewClick: [request: ImportRequest]
  'update:selectedCount': [count: number]
}>()

const authStore = useAuthStore()
const currentUserId = computed(() => authStore.user?.id ?? null)
const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const rowSelection = ref<Record<string, boolean>>({})

const initialVisibility: VisibilityState = {
  last_activity: false,
  cby_age: false,
  cby_sla: false,
  cby_voting: false,
  cby_fx: false,
  cby_risk: false,
  director_ready_to_close: false,
  director_fx_state: false,
  swift_documents: false,
}
const columnVisibility = ref<VisibilityState>({ ...initialVisibility })

const { columns } = useRequestsColumns({
  role: computed(() => props.role),
  currentUserId,
  onPreviewClick: request => emit('previewClick', request),
})

const table = useVueTable({
  get data() { return props.data },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  onSortingChange: updater => sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater,
  onColumnFiltersChange: updater => columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater,
  onColumnVisibilityChange: updater => {
    columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater
  },
  onRowSelectionChange: updater => rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater,
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
})

const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)
const hasActiveFilters = computed(() => columnFilters.value.length > 0)

watch(selectedCount, count => emit('update:selectedCount', count))

function resetFilters() {
  columnFilters.value = []
}

function supportCommitteeRowClass(request: ImportRequest): string {
  if (props.role !== UserRole.SUPPORT_COMMITTEE) return 'hover:bg-muted/30'
  const mine = request.is_claimed_by_me || (currentUserId.value != null && request.claimed_by?.id === currentUserId.value)
  if (mine) return 'bg-[var(--voting)]/8 hover:bg-[var(--voting)]/12'
  if (request.claimed_by) return 'bg-muted/40 hover:bg-muted/60'
  return 'hover:bg-muted/30'
}

defineExpose({
  clearSelection: () => table.resetRowSelection(),
  table,
})
</script>

<template>
  <div class="relative flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <DataTableFacetedFilter
        v-if="table.getColumn('status')"
        :column="table.getColumn('status')!"
        title="الحالة"
        :options="STATUS_FILTER_OPTIONS"
      />
      <Button
        v-if="hasActiveFilters"
        variant="ghost"
        size="sm"
        class="h-8 px-2 text-muted-foreground"
        @click="resetFilters"
      >
        إعادة ضبط
        <X class="me-1 h-4 w-4" />
      </Button>
      <DataTableViewOptions
        class="ms-auto"
        :table="table"
        :column-labels="REQUESTS_COLUMN_LABELS"
      />
    </div>

    <div v-if="loading || table.getRowModel().rows.length > 0" class="overflow-x-auto rounded-lg border">
      <Table class="min-w-max w-full">
        <TableHeader class="sticky top-0 z-30 bg-muted">
          <TableRow
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
            class="hover:bg-transparent"
          >
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              :col-span="header.colSpan"
              class="h-10 text-sm font-medium text-foreground"
              :class="header.column.id === 'actions' ? 'sticky end-0 z-20 w-12 bg-muted px-2' : 'px-4'"
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
            <TableRow v-for="i in 10" :key="i">
              <TableCell class="px-4 py-3"><Skeleton class="size-4 rounded-sm" /></TableCell>
              <TableCell class="px-4 py-3">
                <div class="flex flex-col gap-1.5">
                  <Skeleton class="h-4 w-40" />
                  <Skeleton class="h-3 w-28" />
                </div>
              </TableCell>
              <TableCell class="px-4 py-3">
                <div class="flex flex-col gap-1.5">
                  <Skeleton class="h-4 w-48" />
                  <Skeleton class="h-3 w-32" />
                </div>
              </TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-4 w-24" /></TableCell>
              <TableCell class="px-4 py-3"><Skeleton class="h-6 w-24 rounded-md" /></TableCell>
              <TableCell class="w-12 px-2 py-3"><Skeleton class="h-8 w-8 rounded-md" /></TableCell>
            </TableRow>
          </template>

          <template v-else>
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              class="group/row cursor-pointer transition-colors"
              :class="supportCommitteeRowClass(row.original)"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
              @click="emit('rowClick', row.original.id)"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="py-3 align-middle"
                :class="cell.column.id === 'actions' ? 'sticky end-0 z-10 w-12 bg-background px-2 group-hover/row:bg-muted/30' : 'px-4'"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
        </TableBody>
      </Table>
    </div>

    <Empty
      v-if="!loading && !table.getRowModel().rows.length"
      class="min-h-[280px] rounded-xl border border-dashed bg-muted/20"
    >
      <EmptyHeader>
        <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
          <SearchX class="size-5" />
        </div>
        <EmptyTitle>{{ noData ? 'لا توجد طلبات بعد' : 'لا توجد طلبات مطابقة' }}</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <EmptyDescription>
          {{ noData ? 'لم يتم تقديم أي طلبات حتى الآن.' : 'جرّب تغيير البحث أو الفلاتر لعرض الطلبات المتاحة.' }}
        </EmptyDescription>
      </EmptyContent>
    </Empty>

    <DataTablePagination :table="table" />
  </div>
</template>
