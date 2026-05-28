<script setup lang="ts" generic="TData">
import type { Table } from '@tanstack/vue-table'
import { refDebounced } from '@vueuse/core'
import { X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

defineProps<{
  table: Table<TData>
  searchColumn?: string
  searchPlaceholder?: string
  hasFilters?: boolean
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  reset: []
}>()

const searchInput = ref('')
const debouncedSearch = refDebounced(searchInput, 300)

watch(debouncedSearch, (value) => {
  emit('update:search', value)
})

function resetFilters() {
  searchInput.value = ''
  emit('reset')
}

const searchRef = ref<InstanceType<typeof Input> | null>(null)
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="relative min-w-[200px] max-w-sm flex-1">
      <Input
        ref="searchRef"
        v-model="searchInput"
        :placeholder="searchPlaceholder || 'بحث...'"
        class="h-8 text-sm"
        dir="rtl"
      />
    </div>
    <slot name="filters" :table="table" />
    <Button
      v-if="hasFilters"
      variant="ghost"
      size="sm"
      class="h-8 px-2"
      @click="resetFilters"
    >
      إعادة ضبط
      <X class="me-1 h-4 w-4" />
    </Button>
    <div class="ms-auto flex items-center gap-2">
      <slot name="actions" :table="table" />
    </div>
  </div>
</template>
