<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next'

const props = defineProps<{
  steps: string[]
  currentStep: number
  stepStatuses: Array<'future' | 'active' | 'completed'>
}>()

const emit = defineEmits<{
  'step-click': [step: number]
}>()

function handleStepClick(index: number): void {
  const status = props.stepStatuses[index]
  if (status === 'completed') {
    emit('step-click', index + 1)
  }
}
</script>

<template>
  <div dir="rtl" role="navigation" aria-label="خطوات الطلب" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-center gap-0">
      <template v-for="(label, index) in steps" :key="index">
        <!-- Step -->
        <div
          class="flex flex-col items-center gap-2 flex-shrink-0 min-w-24 transition-all"
          :class="{
            'cursor-pointer': stepStatuses[index] === 'completed',
          }"
          :aria-current="stepStatuses[index] === 'active' ? 'step' : undefined"
          :role="stepStatuses[index] === 'completed' ? 'button' : undefined"
          :tabindex="stepStatuses[index] === 'completed' ? 0 : undefined"
          @click="handleStepClick(index)"
          @keydown.enter="handleStepClick(index)"
          @keydown.space.prevent="handleStepClick(index)"
        >
          <!-- Step circle -->
          <div
            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border-2 transition-all"
            :class="{
              'border-gray-200 bg-white': stepStatuses[index] === 'future',
              'border-primary bg-blue-600 shadow-lg shadow-blue-600/20': stepStatuses[index] === 'active',
              'border-green-200 bg-green-50': stepStatuses[index] === 'completed',
            }"
          >
            <!-- Completed: checkmark icon -->
            <CheckCircle2 v-if="stepStatuses[index] === 'completed'" class="h-5 w-5 text-white" aria-hidden="true" />
            <!-- Active / Future: number -->
            <span
              v-else
              class="text-sm font-medium leading-none"
              :class="{
                'text-gray-600': stepStatuses[index] === 'future',
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
              'text-gray-600 font-normal': stepStatuses[index] === 'future',
              'text-blue-600 font-semibold': stepStatuses[index] === 'active',
              'text-green-700 font-normal': stepStatuses[index] === 'completed',
            }"
            :class="{ 'hover:underline': stepStatuses[index] === 'completed' }"
          >
            {{ label }}
          </span>
        </div>

        <!-- Connector line (not after last step) -->
        <div
          v-if="index < steps.length - 1"
          class="h-0.5 flex-1 min-w-6 transition-colors"
          :class="{
            'bg-border': stepStatuses[index] !== 'completed',
            'bg-green-50': stepStatuses[index] === 'completed',
          }"
          aria-hidden="true"
        />
      </template>
    </div>
  </div>
</template>
