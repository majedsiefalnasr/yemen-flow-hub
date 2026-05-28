<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { Search, X } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

const props = defineProps<{
  table: Table<TData>
  /** Column id to filter on (defaults to first string column) */
  filterColumn?: string
  placeholder?: string
}>()

const filterValue = ref('')
const debouncedFilter = useDebounce(filterValue, 300)

watch(debouncedFilter, (val) => {
  if (props.filterColumn) {
    props.table.getColumn(props.filterColumn)?.setFilterValue(val || undefined)
  }
})

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)

function reset() {
  filterValue.value = ''
  props.table.resetColumnFilters()
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="relative max-w-sm flex-1">
      <Search class="absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
      <Input
        v-model="filterValue"
        :placeholder="placeholder ?? 'بحث…'"
        class="ps-8 h-8 w-full"
        aria-label="بحث في الجدول"
      />
    </div>

    <!-- Faceted filter slots -->
    <slot name="filters" :table="table" />

    <Button
      v-if="isFiltered"
      variant="ghost"
      size="sm"
      class="h-8 px-2 text-xs"
      @click="reset"
    >
      مسح
      <X class="ms-1 h-3.5 w-3.5" />
    </Button>

    <!-- View options slot -->
    <div class="ms-auto flex items-center gap-2">
      <slot name="actions" :table="table" />
    </div>
  </div>
</template>
