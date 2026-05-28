<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

defineProps<{
  table: Table<TData>
  pageSizeOptions?: number[]
  /** Override total when server-side */
  totalRows?: number
}>()
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
    <!-- Selection count -->
    <div class="shrink-0">
      <template v-if="table.getFilteredSelectedRowModel().rows.length > 0">
        {{ table.getFilteredSelectedRowModel().rows.length }} من
        {{ totalRows ?? table.getFilteredRowModel().rows.length }} محدد
      </template>
      <template v-else>
        {{ totalRows ?? table.getFilteredRowModel().rows.length }} صف
      </template>
    </div>

    <div class="flex items-center gap-4">
      <!-- Rows per page -->
      <div class="flex items-center gap-2">
        <span class="hidden sm:inline">صفوف في الصفحة</span>
        <Select
          :model-value="String(table.getState().pagination.pageSize)"
          @update:model-value="table.setPageSize(Number($event))"
        >
          <SelectTrigger class="h-7 w-16 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="size in (pageSizeOptions ?? [10, 20, 30, 50])"
              :key="size"
              :value="String(size)"
              class="text-xs"
            >
              {{ size }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Page info -->
      <span class="shrink-0">
        الصفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
      </span>

      <!-- Navigation -->
      <div class="flex items-center gap-1">
        <Button
          variant="outline"
          size="icon"
          class="h-7 w-7"
          :disabled="!table.getCanPreviousPage()"
          @click="table.setPageIndex(0)"
        >
          <ChevronsRight class="h-4 w-4" />
          <span class="sr-only">الصفحة الأولى</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          class="h-7 w-7"
          :disabled="!table.getCanPreviousPage()"
          @click="table.previousPage()"
        >
          <ChevronRight class="h-4 w-4" />
          <span class="sr-only">السابق</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          class="h-7 w-7"
          :disabled="!table.getCanNextPage()"
          @click="table.nextPage()"
        >
          <ChevronLeft class="h-4 w-4" />
          <span class="sr-only">التالي</span>
        </Button>
        <Button
          variant="outline"
          size="icon"
          class="h-7 w-7"
          :disabled="!table.getCanNextPage()"
          @click="table.setPageIndex(table.getPageCount() - 1)"
        >
          <ChevronsLeft class="h-4 w-4" />
          <span class="sr-only">الصفحة الأخيرة</span>
        </Button>
      </div>
    </div>
  </div>
</template>
