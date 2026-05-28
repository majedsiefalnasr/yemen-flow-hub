<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { SlidersHorizontal } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const props = defineProps<{
  table: Table<TData>
  /** Columns that cannot be hidden */
  alwaysVisible?: string[]
}>()
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" size="sm" class="hidden h-8 lg:flex text-xs">
        <SlidersHorizontal class="ms-1 h-3.5 w-3.5" />
        الأعمدة
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-40">
      <DropdownMenuLabel class="text-xs">تبديل الأعمدة</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuCheckboxItem
        v-for="column in table.getAllColumns().filter(col => col.getCanHide())"
        :key="column.id"
        :checked="column.getIsVisible()"
        :disabled="props.alwaysVisible?.includes(column.id)"
        class="text-xs"
        @update:checked="column.toggleVisibility($event)"
      >
        {{ (column.columnDef.meta as Record<string, unknown> | undefined)?.label ?? column.id }}
      </DropdownMenuCheckboxItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
