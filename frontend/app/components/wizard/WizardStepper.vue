<script setup lang="ts">
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next'

type StepStatus = 'future' | 'active' | 'completed' | 'error'

const props = defineProps<{
  steps: string[]
  currentStep: number
  stepStatuses: StepStatus[]
}>()

const emit = defineEmits<{
  'step-click': [step: number]
}>()

function isStepClickable(status: StepStatus | undefined): boolean {
  return status === 'completed' || status === 'error'
}

function handleStepClick(index: number): void {
  const status = props.stepStatuses[index]
  if (isStepClickable(status)) {
    emit('step-click', index + 1)
  }
}
</script>

<template>
  <div role="navigation" aria-label="خطوات الطلب" class="my-6">
    <div class="flex items-center justify-center gap-0">
      <template v-for="(label, index) in steps" :key="index">
        <!-- Step -->
        <div
          class="flex min-w-24 flex-shrink-0 flex-col items-center gap-2 transition-all"
          :class="{
            'cursor-pointer': isStepClickable(stepStatuses[index]),
          }"
          :aria-current="stepStatuses[index] === 'active' ? 'step' : undefined"
          :role="isStepClickable(stepStatuses[index]) ? 'button' : undefined"
          :tabindex="isStepClickable(stepStatuses[index]) ? 0 : undefined"
          @click="handleStepClick(index)"
          @keydown.enter="handleStepClick(index)"
          @keydown.space.prevent="handleStepClick(index)"
        >
          <!-- Step circle -->
          <div
            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border-2 transition-all"
            :class="{
              'border-border bg-card': stepStatuses[index] === 'future',
              'border-primary bg-primary shadow-primary/20 shadow-lg':
                stepStatuses[index] === 'active',
              'border-[var(--color-border-success)] bg-[var(--color-surface-success)]':
                stepStatuses[index] === 'completed',
              'border-destructive bg-destructive/10': stepStatuses[index] === 'error',
            }"
          >
            <CheckCircle2
              v-if="stepStatuses[index] === 'completed'"
              class="h-5 w-5 text-white"
              aria-hidden="true"
            />
            <AlertCircle
              v-else-if="stepStatuses[index] === 'error'"
              class="text-destructive h-5 w-5"
              aria-hidden="true"
            />
            <span
              v-else
              class="text-sm leading-none font-medium"
              :class="{
                'text-muted-foreground': stepStatuses[index] === 'future',
                'text-white': stepStatuses[index] === 'active',
              }"
            >
              {{ index + 1 }}
            </span>
          </div>

          <!-- Label -->
          <span
            class="text-xs transition-colors"
            :class="{
              'text-muted-foreground font-normal': stepStatuses[index] === 'future',
              'text-primary font-semibold': stepStatuses[index] === 'active',
              'font-normal text-[var(--color-text-success)]': stepStatuses[index] === 'completed',
              'text-destructive font-medium': stepStatuses[index] === 'error',
              'hover:underline': isStepClickable(stepStatuses[index]),
            }"
          >
            {{ label }}
          </span>
        </div>

        <!-- Connector line (not after last step) -->
        <div
          v-if="index < steps.length - 1"
          class="h-0.5 min-w-6 flex-1 transition-colors"
          :class="{
            'bg-border': stepStatuses[index] !== 'completed',
            'bg-[var(--color-surface-success)]': stepStatuses[index] === 'completed',
          }"
          aria-hidden="true"
        />
      </template>
    </div>
  </div>
</template>
