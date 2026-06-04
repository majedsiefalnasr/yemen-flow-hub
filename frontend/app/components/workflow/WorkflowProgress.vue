<script setup lang="ts">
import { Check, Circle, Dot } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import {
  Stepper,
  StepperDescription,
  StepperItem,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import { ROLE_BUCKETS, STATUS_LABELS, getStatusProgress } from '@/constants/workflow'
import { RequestStatus, UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    status?: RequestStatus
    currentStatus?: RequestStatus
    compact?: boolean
    userRole?: UserRole
    /** Pass `request.is_claimed_by_me` so the SUPPORT_COMMITTEE stepper shows
     *  only the step that applies: "أعمل عليها" when true, "محجوزة لأعضاء آخرين"
     *  when false. When undefined the component falls back to showing both. */
    isClaimedByMe?: boolean
  }>(),
  {
    compact: false,
  },
)

const authStore = useAuthStore()
const resolvedStatus = computed(() => props.status ?? props.currentStatus ?? RequestStatus.DRAFT)
const role = computed(() => props.userRole ?? authStore.user?.role ?? UserRole.CBY_ADMIN)

const REJECT_STATUSES: RequestStatus[] = [
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.BANK_REJECTED,
]
const RETURN_STATUSES: RequestStatus[] = [
  RequestStatus.BANK_RETURNED,
  RequestStatus.SUPPORT_RETURNED,
  RequestStatus.DRAFT_REJECTED_INTERNAL,
]
const TERMINAL_DONE: RequestStatus[] = [
  RequestStatus.COMPLETED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
]
const COMPLETED_STATUSES = new Set<RequestStatus>([
  RequestStatus.COMPLETED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.FX_CONFIRMATION_PENDING,
  RequestStatus.EXECUTIVE_APPROVED,
])

const steps = computed(() => {
  const currentStatus = resolvedStatus.value
  const isRejected = REJECT_STATUSES.includes(currentStatus)
  const buckets = ROLE_BUCKETS[role.value]
  if (buckets) {
    return buckets
      .filter((b) => {
        const isBranchOnly = b.statuses.every(
          (s) => REJECT_STATUSES.includes(s) || RETURN_STATUSES.includes(s),
        )
        const isCompletedOnly = b.statuses.every((s) => COMPLETED_STATUSES.has(s))
        if (isRejected && isCompletedOnly) return false
        // For SUPPORT_COMMITTEE: my_claims and in_progress are mutually exclusive.
        // When isClaimedByMe is defined, show only the applicable step.
        if (props.isClaimedByMe !== undefined) {
          if (b.key === 'my_claims' && !props.isClaimedByMe) return false
          if (b.key === 'in_progress' && props.isClaimedByMe) return false
        }
        return !isBranchOnly || b.statuses.includes(currentStatus)
      })
      .map((b, i) => ({ step: i + 1, key: b.key, label: b.label, statuses: b.statuses }))
  }
  return Object.values(RequestStatus)
    .filter((s) => {
      if (isRejected && COMPLETED_STATUSES.has(s)) return false
      return s === currentStatus || (!REJECT_STATUSES.includes(s) && !RETURN_STATUSES.includes(s))
    })
    .map((s, i) => ({ step: i + 1, key: s, label: STATUS_LABELS[s], statuses: [s] }))
})

const currentStepValue = computed(() => {
  const idx = steps.value.findIndex((s) => s.statuses.includes(resolvedStatus.value))
  if (idx === -1) return 1
  // If all done, mark last step active
  if (TERMINAL_DONE.includes(resolvedStatus.value)) return steps.value.length
  return idx + 1
})

const progressPercent = computed(() => getStatusProgress(resolvedStatus.value, role.value))

const statusChip = computed(() => {
  if (RETURN_STATUSES.includes(resolvedStatus.value))
    return {
      label: 'معاد للتعديل',
      class: 'bg-[var(--severity-amber)]/15 text-[var(--severity-amber)]',
    }
  if (REJECT_STATUSES.includes(resolvedStatus.value))
    return { label: 'مرفوض', class: 'bg-[var(--severity-red)]/15 text-[var(--severity-red)]' }
  return null
})

function activeDescription(status: RequestStatus): { label: string; class: string } {
  if (REJECT_STATUSES.includes(status)) {
    return { label: 'توقف المسار بالرفض', class: 'text-[var(--severity-red)]' }
  }
  if (RETURN_STATUSES.includes(status)) {
    return { label: 'بانتظار تصحيح البيانات', class: 'text-[var(--severity-amber)]' }
  }
  if (status === RequestStatus.COMPLETED || status === RequestStatus.CUSTOMS_DECLARATION_ISSUED) {
    return { label: 'اكتمل المسار بنجاح', class: 'text-[var(--severity-green)]' }
  }
  if (status === RequestStatus.FX_CONFIRMATION_PENDING) {
    return { label: 'بانتظار تأكيد المصارفة الخارجية', class: 'text-[var(--info)]' }
  }
  if (
    status === RequestStatus.SUPPORT_REVIEW_PENDING ||
    status === RequestStatus.WAITING_FOR_SWIFT ||
    status === RequestStatus.WAITING_FOR_VOTING_OPEN
  ) {
    return { label: 'بانتظار إجراء من الجهة المسؤولة', class: 'text-[var(--severity-amber)]' }
  }
  if (
    status === RequestStatus.BANK_REVIEW ||
    status === RequestStatus.SUPPORT_REVIEW_IN_PROGRESS ||
    status === RequestStatus.EXECUTIVE_VOTING_OPEN ||
    status === RequestStatus.EXECUTIVE_VOTING_CLOSED
  ) {
    return { label: 'قيد المعالجة الآن', class: 'text-primary' }
  }
  if (
    status === RequestStatus.BANK_APPROVED ||
    status === RequestStatus.SUPPORT_APPROVED ||
    status === RequestStatus.EXECUTIVE_APPROVED ||
    status === RequestStatus.SWIFT_UPLOADED
  ) {
    return { label: 'اكتملت هذه المرحلة', class: 'text-[var(--severity-green)]' }
  }
  return { label: 'بانتظار المتابعة', class: 'text-muted-foreground' }
}

function activeIconClass(status: RequestStatus): string {
  if (REJECT_STATUSES.includes(status))
    return 'border-[var(--severity-red)] bg-[var(--severity-red)] text-white ring-[var(--severity-red)]/35'
  if (RETURN_STATUSES.includes(status))
    return 'border-[var(--severity-amber)] bg-[var(--severity-amber)] text-white ring-[var(--severity-amber)]/35'
  if (status === RequestStatus.COMPLETED || status === RequestStatus.CUSTOMS_DECLARATION_ISSUED) {
    return 'border-[var(--severity-green)] bg-[var(--severity-green)] text-white ring-[var(--severity-green)]/35'
  }
  if (status === RequestStatus.FX_CONFIRMATION_PENDING || status === RequestStatus.SWIFT_UPLOADED) {
    return 'border-[var(--info)] bg-[var(--info)] text-white ring-[var(--info)]/35'
  }
  if (
    status === RequestStatus.SUPPORT_REVIEW_PENDING ||
    status === RequestStatus.WAITING_FOR_SWIFT ||
    status === RequestStatus.WAITING_FOR_VOTING_OPEN
  ) {
    return 'border-[var(--severity-amber)] bg-[var(--severity-amber)] text-white ring-[var(--severity-amber)]/35'
  }
  if (
    status === RequestStatus.BANK_APPROVED ||
    status === RequestStatus.SUPPORT_APPROVED ||
    status === RequestStatus.EXECUTIVE_APPROVED
  ) {
    return 'border-[var(--severity-green)] bg-[var(--severity-green)] text-white ring-[var(--severity-green)]/35'
  }
  return 'border-primary bg-primary text-primary-foreground ring-primary/35'
}

function stepDescription(state: string): { label: string; class: string } {
  if (state === 'active') return activeDescription(resolvedStatus.value)
  if (state === 'completed') return { label: 'مكتملة', class: 'text-[var(--severity-green)]' }
  return { label: 'بانتظار', class: 'text-muted-foreground/60' }
}

function stepIconClass(state: string): string {
  if (state === 'active') return activeIconClass(resolvedStatus.value)
  return ''
}
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <span class="text-sm font-semibold">مسار الطلب</span>
      <div class="flex items-center gap-2">
        <span
          v-if="statusChip"
          :class="cn('rounded-full px-2 py-0.5 text-[10px] font-medium', statusChip.class)"
        >
          {{ statusChip.label }}
        </span>
        <span class="text-muted-foreground text-xs">{{ progressPercent }}%</span>
      </div>
    </div>

    <Stepper
      :model-value="currentStepValue"
      orientation="vertical"
      class="flex w-full flex-col justify-start gap-4"
    >
      <StepperItem
        v-for="s in steps"
        :key="s.key"
        v-slot="{ state }"
        class="relative flex w-full items-start gap-4"
        :step="s.step"
      >
        <StepperSeparator
          v-if="s.step !== steps[steps.length - 1]?.step"
          class="bg-muted group-data-[state=completed]:bg-primary absolute inset-s-[17px] top-[36px] block h-[110%] w-0.5 shrink-0 rounded-full"
        />

        <StepperTrigger as-child>
          <Button
            :variant="state === 'completed' || state === 'active' ? 'default' : 'outline'"
            size="icon"
            class="pointer-events-none z-10 size-9 shrink-0 rounded-full"
            :class="[
              state === 'active' && 'ring-offset-background ring-2 ring-offset-2',
              stepIconClass(state),
            ]"
          >
            <Check v-if="state === 'completed'" class="size-4" />
            <Circle v-else-if="state === 'active'" class="size-4" />
            <Dot v-else class="size-4" />
          </Button>
        </StepperTrigger>

        <template v-if="compact">
          <Tooltip>
            <TooltipTrigger as-child>
              <div class="flex cursor-default flex-col gap-0.5 pt-1.5">
                <StepperTitle
                  :class="[
                    state === 'active'
                      ? 'text-foreground font-semibold'
                      : state === 'completed'
                        ? 'text-foreground'
                        : 'text-muted-foreground font-normal',
                  ]"
                  class="text-sm leading-snug"
                >
                  {{ s.label }}
                </StepperTitle>
              </div>
            </TooltipTrigger>
            <TooltipContent side="left" class="text-xs">
              <p :class="stepDescription(state).class">{{ stepDescription(state).label }}</p>
            </TooltipContent>
          </Tooltip>
        </template>
        <div v-else class="flex flex-col gap-0.5 pt-1.5">
          <StepperTitle
            :class="[
              state === 'active'
                ? 'text-foreground font-semibold'
                : state === 'completed'
                  ? 'text-foreground'
                  : 'text-muted-foreground font-normal',
            ]"
            class="text-sm leading-snug"
          >
            {{ s.label }}
          </StepperTitle>
          <StepperDescription :class="stepDescription(state).class" class="text-[11px]">
            {{ stepDescription(state).label }}
          </StepperDescription>
        </div>
      </StepperItem>
    </Stepper>
  </div>
</template>
