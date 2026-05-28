<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { Settings2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

defineProps<{
  table: Table<TData>
  columnLabels?: Record<string, string>
}>()
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="outline"
        size="sm"
        class="ms-auto hidden h-8 lg:flex"
      >
        <Settings2 class="me-2 h-4 w-4" />
        الأعمدة
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[150px]">
      <DropdownMenuLabel>عرض الأعمدة</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuCheckboxItem
        v-for="column in table.getAllColumns().filter(col => col.getCanHide())"
        :key="column.id"
        :checked="column.getIsVisible()"
        class="capitalize"
        @update:checked="(value: boolean | 'indeterminate') => column.toggleVisibility(value === true)"
      >
        {{ columnLabels?.[column.id] ?? column.id }}
      </DropdownMenuCheckboxItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
