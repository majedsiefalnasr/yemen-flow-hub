<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { refDebounced, useEventListener } from '@vueuse/core'
import { Download, Printer, Search, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

const props = defineProps<{
  table: Table<TData>
  searchColumn?: string
  searchPlaceholder?: string
  hasFilters?: boolean
  /** Hide the search input entirely */
  hideSearch?: boolean
  /** Selected rows count for bulk toolbar mode */
  selectedCount?: number
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  reset: []
  exportSelected: []
  clearSelection: []
  printSelected: []
}>()

const searchInput = ref('')
const debouncedSearch = refDebounced(searchInput, 300)
const searchRef = ref<InstanceType<typeof Input> | null>(null)

watch(debouncedSearch, (value) => {
  emit('update:search', value)
})

function resetFilters() {
  searchInput.value = ''
  emit('reset')
}

// Focus search on "/" keypress (skip when already in an input/textarea)
useEventListener(document, 'keydown', (e: KeyboardEvent) => {
  if (props.hideSearch) return
  const tag = (e.target as HTMLElement)?.tagName?.toLowerCase()
  if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && !e.ctrlKey && !e.metaKey) {
    e.preventDefault()
    const el = searchRef.value?.$el as HTMLInputElement | undefined
    el?.focus()
  }
})
</script>

<template>
  <div v-if="(selectedCount ?? 0) > 0" class="flex items-center gap-2">
    <span class="font-medium text-primary">{{ selectedCount }} محدد</span>
    <div class="mx-2 h-4 w-px bg-border" />
    <Button variant="outline" class="gap-1.5" @click="emit('exportSelected')">
      <Download class="size-4" />
      تصدير
    </Button>
    <Button variant="outline" class="gap-1.5" @click="emit('printSelected')">
      <Printer class="size-4" />
      طباعة
    </Button>
    <Button
      variant="ghost"
      size="sm"
      class="ms-auto gap-1 text-muted-foreground"
      @click="emit('clearSelection')"
    >
      <X class="size-4" />
      إلغاء التحديد
    </Button>
  </div>

  <div v-else class="flex flex-wrap items-center gap-2">
    <!-- Search -->
    <div v-if="!hideSearch" class="relative min-w-[180px] max-w-sm flex-1">
      <Search class="absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
      <Input
        ref="searchRef"
        v-model="searchInput"
        :placeholder="searchPlaceholder || 'بحث...'"
        class="h-8 ps-8 pe-9 text-sm"
      />
      <kbd
        class="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2 hidden h-5 select-none items-center rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground sm:flex"
        aria-hidden="true"
      >
        /
      </kbd>
    </div>

    <!-- Faceted filters and other filter controls -->
    <slot name="filters" :table="table" />

    <!-- Reset filters -->
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

    <!-- Right-aligned actions: View, Export, etc. -->
    <div class="ms-auto flex items-center gap-2">
      <slot name="actions" :table="table" />
    </div>
  </div>
</template>
