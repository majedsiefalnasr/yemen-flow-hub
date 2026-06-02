<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { refDebounced, useEventListener } from '@vueuse/core'
import { Search, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'

const props = defineProps<{
  table: Table<TData>
  searchColumn?: string
  searchPlaceholder?: string
  hasFilters?: boolean
  hideSearch?: boolean
  selectedCount?: number
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  reset: []
  clearSelection: []
}>()

const searchInput = ref('')
const debouncedSearch = refDebounced(searchInput, 300)
const searchRef = ref<InstanceType<typeof Input> | null>(null)

watch(debouncedSearch, value => emit('update:search', value))

function resetFilters() {
  searchInput.value = ''
  emit('reset')
}

if (typeof document !== 'undefined') {
  useEventListener(document, 'keydown', (e: KeyboardEvent) => {
    if (props.hideSearch) return
    const tag = (e.target as HTMLElement)?.tagName?.toLowerCase()
    if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && !e.ctrlKey && !e.metaKey) {
      e.preventDefault()
      const el = searchRef.value?.$el as HTMLInputElement | undefined
      el?.focus()
    }
  })
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-2">
      <div v-if="!hideSearch" class="relative min-w-[180px] max-w-sm flex-1">
        <Search class="absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
        <Input
          ref="searchRef"
          v-model="searchInput"
          :placeholder="searchPlaceholder ?? 'بحث...'"
          class="h-8 ps-8 pe-9 text-sm"
        />
        <kbd
          class="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2 hidden h-5 select-none items-center rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground sm:flex"
          aria-hidden="true"
        >
          /
        </kbd>
      </div>

      <slot name="filters" :table="table" />

      <Button
        v-if="hasFilters"
        variant="ghost"
        size="sm"
        class="h-8 px-2 text-muted-foreground"
        @click="resetFilters"
      >
        إعادة ضبط
        <X class="me-1 h-4 w-4" />
      </Button>

      <div class="ms-auto flex items-center gap-2">
        <slot name="actions" :table="table" />
      </div>
    </div>

    <template v-if="(selectedCount ?? 0) > 0">
      <Separator />
      <div class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-1.5">
        <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
        <div class="mx-2 h-4 w-px bg-border" />
        <slot name="bulk-actions" />
        <Button
          variant="ghost"
          size="sm"
          class="ms-auto h-7 gap-1 text-xs text-muted-foreground"
          @click="emit('clearSelection')"
        >
          <X class="size-3.5" />
          إلغاء التحديد
        </Button>
      </div>
    </template>
  </div>
</template>
