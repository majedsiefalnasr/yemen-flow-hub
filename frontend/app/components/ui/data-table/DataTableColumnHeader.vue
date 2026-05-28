<script setup lang="ts" generic="TData, TValue">
import type { Column } from '@tanstack/vue-table'
import { ArrowDown, ArrowUp, ArrowUpDown, EyeOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'

const props = defineProps<{
  column: Column<TData, TValue>
  title: string
  class?: string
}>()
</script>

<template>
  <div :class="cn('flex items-center gap-1', props.class)">
    <DropdownMenu v-if="column.getCanSort()">
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" size="sm" class="-ms-1 h-7 text-xs font-medium data-[state=open]:bg-accent">
          {{ title }}
          <ArrowDown v-if="column.getIsSorted() === 'desc'" class="ms-1 h-3.5 w-3.5" />
          <ArrowUp v-else-if="column.getIsSorted() === 'asc'" class="ms-1 h-3.5 w-3.5" />
          <ArrowUpDown v-else class="ms-1 h-3.5 w-3.5 text-muted-foreground" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        <DropdownMenuItem class="text-xs" @click="column.toggleSorting(false)">
          <ArrowUp class="me-2 h-3.5 w-3.5 text-muted-foreground" />
          تصاعدي
        </DropdownMenuItem>
        <DropdownMenuItem class="text-xs" @click="column.toggleSorting(true)">
          <ArrowDown class="me-2 h-3.5 w-3.5 text-muted-foreground" />
          تنازلي
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem class="text-xs" @click="column.toggleVisibility(false)">
          <EyeOff class="me-2 h-3.5 w-3.5 text-muted-foreground" />
          إخفاء
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
    <span v-else class="text-xs font-medium">{{ title }}</span>
  </div>
</template>
