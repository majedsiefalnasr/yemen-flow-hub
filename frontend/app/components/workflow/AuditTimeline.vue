<script setup lang="ts">
import { Clock } from 'lucide-vue-next'
import type { RequestStageHistory } from '@/types/models'

const props = withDefaults(defineProps<{
  entries: RequestStageHistory[]
  limit?: number
}>(), {
  limit: 25,
})

const visible = computed(() => props.entries.slice(0, props.limit))
</script>

<template>
  <div
    v-if="visible.length === 0"
    class="p-4 text-sm text-gray-600"
  >
    لا توجد إجراءات مسجلة بعد.
  </div>

  <ol
    v-else
    class="relative space-y-4 border-e-2 border-gray-200 pe-6"
  >
    <li
      v-for="entry in visible"
      :key="entry.id"
      class="relative"
    >
      <span class="absolute -end-[31px] top-1.5 h-3 w-3 rounded-full bg-blue-600 ring-4 ring-card" />
      <div class="rounded-lg border bg-white p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="text-sm font-semibold">
            {{ entry.action }}
          </div>
          <div class="flex items-center gap-1 text-[11px] text-gray-600">
            <Clock class="h-3 w-3" />
            {{ new Date(entry.created_at).toLocaleString('ar-EG') }}
          </div>
        </div>
        <div class="mt-1 text-xs text-gray-600">
          {{ entry.performed_by?.name ?? 'غير معروف' }}
          <span v-if="entry.from_status && entry.to_status">
            — من «{{ entry.from_status }}» إلى «{{ entry.to_status }}»
          </span>
        </div>
        <div
          v-if="entry.metadata?.notes"
          class="mt-1.5 rounded bg-gray-50/40 px-2 py-1 text-xs"
        >
          {{ entry.metadata.notes }}
        </div>
      </div>
    </li>
  </ol>
</template>
