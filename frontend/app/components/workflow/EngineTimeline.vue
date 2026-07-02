<!-- app/components/workflow/EngineTimeline.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry } from '@/types/models'
import { buildTimeline } from '@/composables/useEngineTimeline'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { History } from 'lucide-vue-next'

const props = defineProps<{ entries: EngineHistoryEntry[] }>()
const items = computed(() => buildTimeline(props.entries))
</script>

<template>
  <ol v-if="items.length" dir="rtl" class="relative flex flex-col gap-4 border-s ps-6">
    <li v-for="item in items" :key="item.id" class="relative">
      <span
        class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-background"
        :class="item.isLast ? 'bg-primary' : 'bg-muted-foreground/40'"
      />
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-medium">{{ item.fromLabel ?? '—' }}</span>
        <span class="text-muted-foreground">←</span>
        <span class="font-medium">{{ item.toLabel ?? '—' }}</span>
        <span v-if="item.actionCode" class="text-muted-foreground text-xs">({{ item.actionCode }})</span>
      </div>
      <p class="text-muted-foreground text-xs">{{ item.actorName }} · {{ item.timestamp }}</p>
      <p v-if="item.comment" class="text-foreground/80 mt-1 text-xs">{{ item.comment }}</p>
    </li>
  </ol>

  <Empty v-else>
    <EmptyMedia variant="icon"><History /></EmptyMedia>
    <EmptyHeader>
      <EmptyTitle>لا يوجد سجل بعد</EmptyTitle>
      <EmptyDescription>لم تُنفَّذ أي إجراءات على هذا الطلب حتى الآن.</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
