<script setup lang="ts">
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
  <div class="wizard-stepper" dir="rtl" role="navigation" aria-label="خطوات الطلب">
    <div class="stepper-inner">
      <template v-for="(label, index) in steps" :key="index">
        <!-- Step -->
        <div
          class="step-item"
          :class="{
            'step-item--active': stepStatuses[index] === 'active',
            'step-item--completed': stepStatuses[index] === 'completed',
            'step-item--future': stepStatuses[index] === 'future',
            'step-item--clickable': stepStatuses[index] === 'completed',
          }"
          :aria-current="stepStatuses[index] === 'active' ? 'step' : undefined"
          :role="stepStatuses[index] === 'completed' ? 'button' : undefined"
          :tabindex="stepStatuses[index] === 'completed' ? 0 : undefined"
          @click="handleStepClick(index)"
          @keydown.enter="handleStepClick(index)"
          @keydown.space.prevent="handleStepClick(index)"
        >
          <div class="step-circle">
            <!-- Completed: checkmark -->
            <svg
              v-if="stepStatuses[index] === 'completed'"
              class="step-check"
              viewBox="0 0 16 16"
              fill="none"
              aria-hidden="true"
            >
              <path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <!-- Active / Future: number -->
            <span v-else class="step-number">{{ index + 1 }}</span>
          </div>
          <span class="step-label">{{ label }}</span>
        </div>

        <!-- Connector line (not after last step) -->
        <div
          v-if="index < steps.length - 1"
          class="step-connector"
          :class="{ 'step-connector--done': stepStatuses[index] === 'completed' }"
          aria-hidden="true"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.wizard-stepper {
  padding: 20px 24px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.stepper-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
}

/* Each step item */
.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  min-width: 96px;
}

.step-item--clickable {
  cursor: pointer;
}

.step-item--clickable:hover .step-label {
  text-decoration: underline;
}

/* Step circle */
.step-circle {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 200ms, border-color 200ms;
}

.step-item--future .step-circle {
  background: #ffffff;
  border: 2px solid #cccccc;
}

.step-item--active .step-circle {
  background: #0066cc;
  border: 2px solid #0066cc;
  box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
}

.step-item--completed .step-circle {
  background: #1b5e20;
  border: 2px solid #1b5e20;
}

/* Numbers / icons */
.step-number {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  font-weight: 500;
  line-height: 1;
}

.step-item--future .step-number {
  color: #6c757d;
}

.step-item--active .step-number {
  color: #ffffff;
}

.step-check {
  width: 20px;
  height: 20px;
  color: #ffffff;
}

/* Step labels */
.step-label {
  font-family: 'IBM Plex Sans Arabic', sans-serif;
  font-size: 13px;
  white-space: nowrap;
  transition: color 200ms;
}

.step-item--future .step-label {
  color: #6c757d;
  font-weight: 400;
}

.step-item--active .step-label {
  color: #0066cc;
  font-weight: 600;
}

.step-item--completed .step-label {
  color: #1b5e20;
  font-weight: 400;
}

/* Connector line */
.step-connector {
  flex: 1;
  height: 2px;
  background: #cccccc;
  align-self: flex-start;
  margin-top: 19px; /* center on 40px circle */
  min-width: 24px;
  transition: background 200ms;
}

.step-connector--done {
  background: #1b5e20;
}
</style>
