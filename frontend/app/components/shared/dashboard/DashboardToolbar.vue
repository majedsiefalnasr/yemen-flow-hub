<script setup lang="ts">
import type { Component } from 'vue'
import { Clock } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

withDefaults(
  defineProps<{
    badgeLabel?: string
    badgeIcon?: Component
    lastUpdated?: string
    refreshLabel?: string
  }>(),
  {
    refreshLabel: 'تحديث',
  },
)

const emit = defineEmits<{
  refresh: []
}>()
</script>

<template>
  <div
    class="border-border bg-muted/30 flex flex-wrap items-center justify-between gap-3 rounded-xl border"
  >
    <div class="flex items-center gap-2">
      <Badge
        v-if="badgeLabel"
        variant="outline"
        class="text-muted-foreground border-border gap-1 rounded-full px-3 py-1 text-xs font-medium"
      >
        <component :is="badgeIcon" v-if="badgeIcon" class="size-3" aria-hidden="true" />
        {{ badgeLabel }}
      </Badge>
      <slot name="left" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="emit('refresh')">
        {{ refreshLabel }}
      </Button>
      <span v-if="lastUpdated" class="text-muted-foreground text-xs">
        <Clock class="me-1 inline size-3" aria-hidden="true" />
        آخر تحديث: {{ lastUpdated }}
      </span>
      <slot />
    </div>
  </div>
</template>
