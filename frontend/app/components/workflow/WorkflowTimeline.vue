<script setup lang="ts">
import { computed } from 'vue'
import { Check, Circle, Dot, Lock } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Stepper,
  StepperDescription,
  StepperItem,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import { RequestStatus } from '../../types/enums'
import { STATUS_LABELS } from '../../constants/workflow'
import type { RequestStageHistory } from '../../types/models'

const props = defineProps<{
  currentStatus: RequestStatus
  history: RequestStageHistory[]
}>()

const WORKFLOW_STAGE_ORDER: RequestStatus[] = [
  RequestStatus.DRAFT,
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.SUBMITTED,
  RequestStatus.BANK_REVIEW,
  RequestStatus.BANK_APPROVED,
  RequestStatus.BANK_RETURNED,
  RequestStatus.BANK_REJECTED,
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.SUPPORT_RETURNED,
  RequestStatus.WAITING_FOR_SWIFT,
  RequestStatus.SWIFT_UPLOADED,
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
  RequestStatus.COMPLETED,
]

const BRANCH_STATUSES = new Set<RequestStatus>([
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.BANK_RETURNED,
  RequestStatus.SUPPORT_RETURNED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.BANK_REJECTED,
])

const TERMINAL_STATUSES = new Set<RequestStatus>([
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.BANK_REJECTED,
])

const currentIndex = computed(() => WORKFLOW_STAGE_ORDER.indexOf(props.currentStatus))

const sortedHistory = computed(() =>
  [...props.history].sort((a, b) => a.created_at.localeCompare(b.created_at)),
)

const visitedStatuses = computed(() =>
  new Set(sortedHistory.value.map(e => e.to_status).filter(Boolean)),
)

const currentEntry = computed(() =>
  [...sortedHistory.value].reverse().find(e => e.to_status === props.currentStatus) ?? null,
)

type ExtraState = 'terminal' | 'skipped' | null

interface StageItem {
  status: RequestStatus
  label: string
  stepNumber: number
  extraState: ExtraState
  entry: RequestStageHistory | null
}

const stages = computed((): StageItem[] => {
  const knownIndex = currentIndex.value
  return WORKFLOW_STAGE_ORDER.map((status, idx) => {
    const isCurrent = status === props.currentStatus
    let extraState: ExtraState = null

    if (TERMINAL_STATUSES.has(status) && isCurrent) extraState = 'terminal'
    else if (
      knownIndex !== -1
      && idx < knownIndex
      && BRANCH_STATUSES.has(status)
      && !visitedStatuses.value.has(status)
    ) extraState = 'skipped'

    return {
      status,
      label: STATUS_LABELS[status],
      stepNumber: idx + 1,
      extraState,
      entry: isCurrent ? currentEntry.value : null,
    }
  })
})

// Stepper model value: 1-based index of current step
const stepperValue = computed(() => currentIndex.value + 1)

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <Stepper
    :model-value="stepperValue"
    orientation="vertical"
    class="flex w-full flex-col justify-start gap-4"
    aria-label="مسار سير العمل"
  >
    <StepperItem
      v-for="stage in stages"
      :key="stage.status"
      v-slot="{ state }"
      class="relative flex w-full items-start gap-4"
      :step="stage.stepNumber"
    >
      <StepperSeparator
        v-if="stage.stepNumber !== stages[stages.length - 1]?.stepNumber"
        class="absolute inset-s-[12px] top-[20px] block h-[110%] w-0.5 shrink-0 rounded-full bg-muted group-data-[state=completed]:bg-primary"
      />

      <StepperTrigger as-child>
        <Button
          :variant="state === 'completed' || state === 'active' ? 'default' : 'outline'"
          size="icon"
          class="z-10 rounded-full shrink-0 size-6 pointer-events-none"
          :class="[state === 'active' && 'ring-2 ring-ring ring-offset-2 ring-offset-background']"
        >
          <Lock v-if="stage.extraState === 'terminal'" class="size-3.5" />
          <Check v-else-if="state === 'completed'" class="size-3" />
          <Circle v-else-if="state === 'active'" class="size-3" />
          <Dot v-else class="size-3" />
        </Button>
      </StepperTrigger>

      <div class="flex flex-col gap-0.5 pt-0.5 flex-1">
        <StepperTitle
          class="text-sm leading-snug"
          :class="[
            stage.extraState === 'skipped' ? 'italic text-muted-foreground font-normal' :
            state === 'active' ? 'font-semibold text-foreground' :
            state === 'completed' ? 'text-foreground' : 'font-normal text-muted-foreground',
          ]"
        >
          {{ stage.label }}
        </StepperTitle>

        <StepperDescription
          v-if="state === 'active' && stage.entry"
          class="flex flex-col gap-0.5"
        >
          <span class="text-xs text-muted-foreground">
            {{ stage.entry.performed_by?.name ?? `#${stage.entry.actor_id}` }}
          </span>
          <span class="text-xs text-muted-foreground">{{ formatDate(stage.entry.created_at) }}</span>
        </StepperDescription>

        <StepperDescription
          v-if="stage.extraState === 'terminal'"
          class="text-xs font-semibold text-muted-foreground"
        >
          نهائي — لا إجراءات إضافية
        </StepperDescription>
      </div>
    </StepperItem>
  </Stepper>
</template>
