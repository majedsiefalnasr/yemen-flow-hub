<script setup lang="ts" generic="TData, TValue">
import type { Column } from '@tanstack/vue-table'
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff } from 'lucide-vue-next'
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
  <div v-if="!column.getCanSort()" :class="cn('flex items-center gap-2', props.class)">
    {{ title }}
  </div>

  <div v-else :class="cn('flex items-center gap-2', props.class)">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button
          variant="ghost"
          size="sm"
          class="-me-3 h-8 data-[state=open]:bg-accent"
        >
          <span>{{ title }}</span>
          <ArrowDown v-if="column.getIsSorted() === 'desc'" class="ms-2 h-4 w-4" />
          <ArrowUp v-else-if="column.getIsSorted() === 'asc'" class="ms-2 h-4 w-4" />
          <ChevronsUpDown v-else class="ms-2 h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        <DropdownMenuItem @click="column.toggleSorting(false)">
          <ArrowUp class="me-2 h-3.5 w-3.5 text-muted-foreground/70" />
          تصاعدي
        </DropdownMenuItem>
        <DropdownMenuItem @click="column.toggleSorting(true)">
          <ArrowDown class="me-2 h-3.5 w-3.5 text-muted-foreground/70" />
          تنازلي
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem @click="column.toggleVisibility(false)">
          <EyeOff class="me-2 h-3.5 w-3.5 text-muted-foreground/70" />
          إخفاء
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>
