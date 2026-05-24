<script setup lang="ts">
import { computed } from 'vue'
import { RequestStatus } from '../../types/enums'
import { STATUS_LABELS } from '../../constants/workflow'
import type { RequestStageHistory } from '../../types/models'

const props = defineProps<{
  currentStatus: RequestStatus
  history: RequestStageHistory[]
}>()

/** Ordered list of all 21 canonical workflow stages */
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
  RequestStatus.COMPLETED,
]

/**
 * Branch/rejection states that must not show a green "completed" checkmark
 * unless the request actually visited them (present in history).
 * These are side-branches off the happy path, not linear progressions.
 */
const BRANCH_STATUSES = new Set<RequestStatus>([
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.BANK_RETURNED,
  RequestStatus.SUPPORT_RETURNED,
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.BANK_REJECTED,
])

/** Dead-end terminal statuses — no further actions possible. */
const TERMINAL_STATUSES = new Set<RequestStatus>([
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.BANK_REJECTED,
])

const currentIndex = computed(
  () => WORKFLOW_STAGE_ORDER.indexOf(props.currentStatus),
)

/** History sorted ascending by created_at — defensive regardless of API sort order. */
const sortedHistory = computed(() =>
  [...props.history].sort((a, b) => a.created_at.localeCompare(b.created_at)),
)

/** Set of statuses the request actually visited, derived from history. */
const visitedStatuses = computed(() =>
  new Set(sortedHistory.value.map(e => e.to_status).filter(Boolean)),
)

/** History entry that transitioned INTO the current status (most recent). */
const currentEntry = computed(() =>
  [...sortedHistory.value].reverse().find(e => e.to_status === props.currentStatus) ?? null,
)

type StageState = 'completed' | 'current' | 'future' | 'terminal' | 'skipped'

interface StageItem {
  status: RequestStatus
  label: string
  state: StageState
  entry: RequestStageHistory | null
}

const stages = computed((): StageItem[] => {
  const knownIndex = currentIndex.value

  return WORKFLOW_STAGE_ORDER.map((status, idx) => {
    const isCurrent = status === props.currentStatus
    const isTerminal = TERMINAL_STATUSES.has(status) && isCurrent

    let state: StageState

    if (isTerminal) {
      state = 'terminal'
    }
    else if (isCurrent) {
      state = 'current'
    }
    else if (knownIndex === -1) {
      // currentStatus not in WORKFLOW_STAGE_ORDER — treat all as future
      state = 'future'
    }
    else if (idx < knownIndex) {
      if (BRANCH_STATUSES.has(status) && !visitedStatuses.value.has(status)) {
        // Branch stage the request never visited — show as skipped, not completed
        state = 'skipped'
      }
      else {
        state = 'completed'
      }
    }
    else {
      state = 'future'
    }

    const entry = isCurrent ? currentEntry.value : null

    return { status, label: STATUS_LABELS[status], state, entry }
  })
})

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('ar-YE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function stageItemClasses(state: StageState): string {
  const baseClasses = 'relative flex flex-col'
  const stateMap: Record<StageState, string> = {
    completed: '',
    current: '',
    future: '',
    terminal: '',
    skipped: '',
  }
  return `${baseClasses} ${stateMap[state]}`
}

function stageLabelClasses(state: StageState): string {
  const baseClasses = 'text-sm font-medium'
  const stateMap: Record<StageState, string> = {
    completed: 'text-gray-900',
    current: 'text-blue-600 font-bold',
    future: 'text-gray-600 font-normal',
    terminal: 'text-gray-600 font-bold',
    skipped: 'text-gray-600 font-normal italic',
  }
  return `${baseClasses} ${stateMap[state]}`
}

function stageBodyClasses(state: StageState): string {
  const baseClasses = 'flex items-start gap-3 py-1'
  if (state === 'current' || state === 'terminal') {
    return `${baseClasses} bg-gray-50 rounded-lg p-2 -mx-3`
  }
  return baseClasses
}

function connectorClasses(isDone: boolean): string {
  return `w-0.5 h-5 flex-shrink-0 -mr-[5.5px] mb-0 ${isDone ? 'bg-green-50' : 'bg-border'}`
}
</script>

<template>
  <div class="flex flex-col py-2 px-0 relative" dir="rtl" role="list" aria-label="مسار سير العمل">
    <div
      v-for="(stage, idx) in stages"
      :key="stage.status"
      :class="stageItemClasses(stage.state)"
      role="listitem"
      :aria-current="stage.state === 'current' || stage.state === 'terminal' ? 'step' : undefined"
    >
      <!-- Connector line (except for first item) -->
      <div
        v-if="idx > 0"
        :class="connectorClasses(stage.state === 'completed' || stage.state === 'current' || stage.state === 'terminal')"
        aria-hidden="true"
      />

      <div :class="stageBodyClasses(stage.state)">
        <!-- Node icon -->
        <div class="w-5 h-5 flex-shrink-0 flex items-center justify-center" aria-hidden="true">
          <!-- Completed: green checkmark -->
          <svg v-if="stage.state === 'completed'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-green-700" role="img" aria-label="مكتمل">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          <!-- Terminal (EXECUTIVE_REJECTED): lock icon -->
          <svg v-else-if="stage.state === 'terminal'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600" role="img" aria-label="نهائي">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          <!-- Skipped branch (not visited): dash -->
          <span v-else-if="stage.state === 'skipped'" class="w-3 h-3 flex-shrink-0 rounded-full border-2 border-dashed border-gray-200 bg-transparent" aria-hidden="true" />
          <!-- Current: filled circle -->
          <span v-else-if="stage.state === 'current'" class="w-3 h-3 rounded-full bg-blue-600 shadow-[0_0_0_3px_rgba(0,113,227,0.2)]" aria-hidden="true" />
          <!-- Future: empty circle -->
          <span v-else class="w-3 h-3 rounded-full border-2 border-gray-200 bg-transparent" aria-hidden="true" />
        </div>

        <!-- Stage content -->
        <div class="flex flex-col gap-0.5 flex-1 pb-1">
          <span :class="stageLabelClasses(stage.state)">{{ stage.label }}</span>

          <!-- Current/terminal stage: show actor + timestamp from history -->
          <template v-if="(stage.state === 'current' || stage.state === 'terminal') && stage.entry">
            <span class="text-xs text-gray-600">
              {{ stage.entry.performed_by?.name ?? `#${stage.entry.actor_id}` }}
            </span>
            <span class="text-xs text-gray-600">{{ formatDate(stage.entry.created_at) }}</span>
          </template>

          <!-- Terminal label — EXECUTIVE_REJECTED only (dead-end, no further actions) -->
          <span v-if="stage.state === 'terminal'" class="text-xs font-semibold text-gray-600 mt-0.5">
            نهائي — لا إجراءات إضافية
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
