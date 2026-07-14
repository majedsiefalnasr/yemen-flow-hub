<!-- app/components/workflow/EngineOrgProcessRail.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry, WorkflowGraph } from '@/types/models'
import { buildStagePath } from '@/composables/useEngineStagePath'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Check } from 'lucide-vue-next'

// Vertical "سير العملية التنظيمية" rail for the request view. Each stage shows a
// ✓ (visited), ◉ (current), or number (upcoming) marker, with a "دورك" badge on
// any stage the signed-in user may execute (derived from graph.execute_stage_ids).
const props = defineProps<{
  graph: WorkflowGraph | null
  currentStageId: number | null
  history: EngineHistoryEntry[]
}>()

const steps = computed(() => buildStagePath(props.graph, props.currentStageId, props.history))
</script>

<template>
  <Card class="border-0 shadow" aria-labelledby="org-rail-heading">
    <CardHeader class="pb-2">
      <CardTitle id="org-rail-heading" class="text-sm font-semibold"
        >سير العملية التنظيمية</CardTitle
      >
    </CardHeader>
    <CardContent class="pt-0">
      <ol v-if="steps.length" class="flex flex-col">
        <li
          v-for="(step, index) in steps"
          :key="step.id"
          class="relative flex gap-3 pb-5 last:pb-0"
        >
          <!-- Connector line between markers -->
          <span
            v-if="index < steps.length - 1"
            class="bg-border absolute top-6 h-[calc(100%-1.5rem)] w-px"
            :style="{ insetInlineStart: '11px' }"
            aria-hidden="true"
          />
          <span
            class="z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
            :class="[
              step.status === 'visited'
                ? 'bg-[var(--severity-green)]/15 text-[var(--severity-green)]'
                : step.status === 'current'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground',
            ]"
            aria-hidden="true"
          >
            <Check v-if="step.status === 'visited'" class="h-3.5 w-3.5" />
            <span v-else>{{ index + 1 }}</span>
          </span>
          <div class="flex min-w-0 flex-1 flex-col gap-1 pt-0.5">
            <div class="flex items-center gap-2">
              <span
                class="truncate text-sm"
                :class="
                  step.status === 'current'
                    ? 'text-foreground font-semibold'
                    : 'text-muted-foreground'
                "
              >
                {{ step.label }}
              </span>
              <Badge
                v-if="step.isYours"
                variant="outline"
                class="border-primary/40 text-primary h-4 shrink-0 px-1 text-[10px]"
              >
                دورك
              </Badge>
            </div>
          </div>
        </li>
      </ol>
      <p v-else class="text-muted-foreground text-xs">لا توجد مراحل معرّفة لهذا المسار.</p>
    </CardContent>
  </Card>
</template>
