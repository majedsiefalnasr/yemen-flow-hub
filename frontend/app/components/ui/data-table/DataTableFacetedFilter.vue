<script setup lang="ts" generic="TData">
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
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Separator } from '@/components/ui/separator'

export interface FacetOption {
  label: string
  value: string
  icon?: object
  count?: number
}

const props = defineProps<{
  column: Column<TData, unknown>
  title: string
  options: FacetOption[]
}>()

const open = ref(false)
const selectedValues = computed(() => {
  const val = props.column.getFilterValue()
  return new Set(Array.isArray(val) ? val : [])
})

function toggleOption(value: string) {
  const current = new Set(selectedValues.value)
  current.has(value) ? current.delete(value) : current.add(value)
  props.column.setFilterValue(current.size ? [...current] : undefined)
}

function clearFilter() {
  props.column.setFilterValue(undefined)
  open.value = false
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button variant="outline" size="sm" class="h-8 border-dashed text-xs">
        <PlusCircle class="ms-1 h-3.5 w-3.5" />
        {{ title }}
        <template v-if="selectedValues.size > 0">
          <Separator orientation="vertical" class="mx-2 h-4" />
          <Badge variant="secondary" class="rounded-sm px-1 font-normal lg:hidden">
            {{ selectedValues.size }}
          </Badge>
          <div class="hidden space-x-1 lg:flex">
            <Badge
              v-if="selectedValues.size > 2"
              variant="secondary"
              class="rounded-sm px-1 font-normal"
            >
              {{ selectedValues.size }} محدد
            </Badge>
            <template v-else>
              <Badge
                v-for="opt in options.filter(o => selectedValues.has(o.value))"
                :key="opt.value"
                variant="secondary"
                class="rounded-sm px-1 font-normal"
              >
                {{ opt.label }}
              </Badge>
            </template>
          </div>
        </template>
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-48 p-0" align="start">
      <Command>
        <CommandInput :placeholder="`بحث في ${title}…`" />
        <CommandList>
          <CommandEmpty>لا نتائج</CommandEmpty>
          <CommandGroup>
            <CommandItem
              v-for="opt in options"
              :key="opt.value"
              :value="opt.value"
              @select="toggleOption(opt.value)"
            >
              <div
                :class="[
                  'me-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                  selectedValues.has(opt.value)
                    ? 'bg-primary text-primary-foreground'
                    : 'opacity-50 [&_svg]:invisible',
                ]"
              >
                <Check class="h-4 w-4" />
              </div>
              <component :is="opt.icon" v-if="opt.icon" class="me-2 h-4 w-4 text-muted-foreground" />
              <span>{{ opt.label }}</span>
              <span v-if="opt.count !== undefined" class="ms-auto font-mono text-xs text-muted-foreground">
                {{ opt.count }}
              </span>
            </CommandItem>
          </CommandGroup>
          <template v-if="selectedValues.size > 0">
            <CommandSeparator />
            <CommandGroup>
              <CommandItem value="__clear__" class="justify-center text-center text-xs" @select="clearFilter">
                مسح التصفية
              </CommandItem>
            </CommandGroup>
          </template>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
