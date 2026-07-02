<!-- app/components/workflow/EngineStageStepper.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry, WorkflowGraph } from '@/types/models'
import { buildStagePath } from '@/composables/useEngineStagePath'
import { Stepper, StepperItem, StepperIndicator, StepperSeparator, StepperTitle, StepperTrigger } from '@/components/ui/stepper'
import { Check } from 'lucide-vue-next'

const props = defineProps<{
  graph: WorkflowGraph | null
  currentStageId: number | null
  history: EngineHistoryEntry[]
}>()

const steps = computed(() => buildStagePath(props.graph, props.currentStageId, props.history))
const currentIndex = computed(() => {
  const i = steps.value.findIndex((s) => s.status === 'current')
  return i === -1 ? 0 : i + 1
})
</script>

<template>
  <div v-if="steps.length" dir="rtl" class="w-full overflow-x-auto">
    <Stepper :model-value="currentIndex" class="min-w-max">
      <StepperItem
        v-for="(step, index) in steps"
        :key="step.id"
        :step="index + 1"
        :completed="step.status === 'visited'"
        :disabled="true"
        class="flex-1"
      >
        <StepperTrigger class="pointer-events-none flex-col gap-1">
          <StepperIndicator>
            <Check v-if="step.status === 'visited'" class="h-4 w-4" />
            <span v-else>{{ index + 1 }}</span>
          </StepperIndicator>
          <StepperTitle
            :class="step.status === 'current' ? 'text-foreground font-semibold' : 'text-muted-foreground'"
          >
            {{ step.label }}
          </StepperTitle>
        </StepperTrigger>
        <StepperSeparator v-if="index < steps.length - 1" class="mx-2" />
      </StepperItem>
    </Stepper>
  </div>
  <p v-else class="text-muted-foreground text-xs">لا توجد مراحل معرّفة لهذا المسار.</p>
</template>
