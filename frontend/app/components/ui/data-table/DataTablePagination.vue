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
  totalRows?: number
}>()
</script>

<template>
  <div class="flex items-center justify-between px-2">
    <div class="flex-1 text-sm text-muted-foreground">
      {{
        table.getState().pagination.pageSize === 0
          ? 'لا توجد صفوف'
          : `عرض ${(table.getState().pagination.pageIndex * table.getState().pagination.pageSize + 1).toLocaleString('ar-EG')}`
            + ` إلى ${Math.min((table.getState().pagination.pageIndex + 1) * table.getState().pagination.pageSize, totalRows ?? table.getFilteredRowModel().rows.length).toLocaleString('ar-EG')}`
            + ` من ${(totalRows !== undefined ? totalRows : table.getFilteredRowModel().rows.length).toLocaleString('ar-EG')} صف`
      }}
    </div>

    <div class="flex items-center gap-6 lg:gap-8">
      <div class="flex items-center gap-2">
        <p class="text-sm font-medium">الصفوف في الصفحة</p>
        <Select
          :model-value="`${table.getState().pagination.pageSize}`"
          @update:model-value="value => table.setPageSize(Number(value))"
        >
          <SelectTrigger class="h-8 w-[70px]">
            <SelectValue :placeholder="`${table.getState().pagination.pageSize}`" />
          </SelectTrigger>
          <SelectContent side="top">
            <SelectItem v-for="size in [10, 20, 30, 50, 100]" :key="size" :value="`${size}`">
              {{ size }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="flex w-[100px] items-center justify-center text-sm font-medium">
        صفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          class="hidden h-8 w-8 p-0 lg:flex"
          :disabled="!table.getCanPreviousPage()"
          @click="table.setPageIndex(0)"
        >
          <span class="sr-only">الصفحة الأولى</span>
          <ChevronsRight class="h-4 w-4" />
        </Button>
        <Button
          variant="outline"
          class="h-8 w-8 p-0"
          :disabled="!table.getCanPreviousPage()"
          @click="table.previousPage()"
        >
          <span class="sr-only">الصفحة السابقة</span>
          <ChevronRight class="h-4 w-4" />
        </Button>
        <Button
          variant="outline"
          class="h-8 w-8 p-0"
          :disabled="!table.getCanNextPage()"
          @click="table.nextPage()"
        >
          <span class="sr-only">الصفحة التالية</span>
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <Button
          variant="outline"
          class="hidden h-8 w-8 p-0 lg:flex"
          :disabled="!table.getCanNextPage()"
          @click="table.setPageIndex(Math.max(table.getPageCount() - 1, 0))"
        >
          <span class="sr-only">الصفحة الأخيرة</span>
          <ChevronsLeft class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
