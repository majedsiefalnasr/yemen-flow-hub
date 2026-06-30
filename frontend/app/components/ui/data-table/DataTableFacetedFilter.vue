<script setup lang="ts" generic="TData, TValue">
import type { Column } from '@tanstack/vue-table'
import { Check, PlusCircle } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Separator } from '@/components/ui/separator'

const props = defineProps<{
  column: Column<TData, TValue>
  title: string
  options: Array<{
    label: string
    value: string
    icon?: unknown
    count?: number
  }>
}>()

const selectedValues = computed(() => {
  const filterValue = props.column.getFilterValue()
  if (!filterValue) return new Set<string>()
  if (Array.isArray(filterValue)) return new Set<string>(filterValue.map((v) => String(v)))
  return new Set<string>([String(filterValue)])
})

function toggleOption(value: string) {
  const current = new Set(selectedValues.value)
  if (current.has(value)) {
    current.delete(value)
  } else {
    current.add(value)
  }
  const values = Array.from(current)
  props.column.setFilterValue(values.length ? values : undefined)
}

function clearFilters() {
  props.column.setFilterValue(undefined)
}
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="outline" size="sm" class="h-8 border-dashed">
        <PlusCircle class="me-2 h-4 w-4" />
        {{ title }}
        <template v-if="selectedValues.size > 0">
          <Separator orientation="vertical" class="mx-2 h-4" />
          <Badge variant="secondary" class="rounded-sm px-1 font-normal lg:hidden">
            {{ selectedValues.size }}
          </Badge>
          <div class="hidden space-x-1 lg:flex">
            <template v-if="selectedValues.size > 2">
              <Badge variant="secondary" class="rounded-sm px-1 font-normal">
                {{ selectedValues.size }} محدد
              </Badge>
            </template>
            <template v-else>
              <Badge
                v-for="value in Array.from(selectedValues)"
                :key="value"
                variant="secondary"
                class="rounded-sm px-1 font-normal"
              >
                {{ options.find((o) => o.value === value)?.label ?? value }}
              </Badge>
            </template>
          </div>
        </template>
      </Button>
    </PopoverTrigger>

    <PopoverContent class="w-[200px] p-0" align="start">
      <Command>
        <CommandInput :placeholder="title" />
        <CommandList>
          <CommandEmpty>لا توجد خيارات.</CommandEmpty>
          <CommandGroup>
            <CommandItem
              v-for="option in options"
              :key="option.value"
              :value="option.value"
              class="flex items-center gap-2"
              @select="toggleOption(option.value)"
            >
              <div
                class="border-primary flex h-4 w-4 items-center justify-center rounded-sm border"
                :class="
                  selectedValues.has(option.value)
                    ? 'bg-primary text-primary-foreground'
                    : 'opacity-50'
                "
              >
                <Check v-if="selectedValues.has(option.value)" class="h-3 w-3" />
              </div>
              <component
                :is="option.icon"
                v-if="option.icon"
                class="text-muted-foreground h-4 w-4"
              />
              <span>{{ option.label }}</span>
              <span
                v-if="option.count !== undefined"
                class="text-muted-foreground ms-auto font-mono text-xs"
              >
                {{ option.count }}
              </span>
            </CommandItem>
          </CommandGroup>

          <template v-if="selectedValues.size > 0">
            <CommandSeparator />
            <CommandGroup>
              <CommandItem
                value="clear-filters"
                class="text-muted-foreground justify-center text-center text-sm"
                @select="clearFilters"
              >
                مسح الفلاتر
              </CommandItem>
            </CommandGroup>
          </template>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
