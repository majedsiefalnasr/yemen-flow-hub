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
</script>

<template>
  <div class="workflow-timeline" dir="rtl" role="list" aria-label="مسار سير العمل">
    <div
      v-for="(stage, idx) in stages"
      :key="stage.status"
      class="stage-item"
      :class="`stage-item--${stage.state}`"
      role="listitem"
      :aria-current="stage.state === 'current' || stage.state === 'terminal' ? 'step' : undefined"
    >
      <!-- Connector line (except for first item) -->
      <div
        v-if="idx > 0"
        class="stage-connector"
        :class="{
          'stage-connector--done': stage.state === 'completed' || stage.state === 'current' || stage.state === 'terminal',
        }"
        aria-hidden="true"
      />

      <div class="stage-body">
        <!-- Node icon -->
        <div class="stage-node" aria-hidden="true">
          <!-- Completed: green checkmark -->
          <svg v-if="stage.state === 'completed'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="icon-check" role="img" aria-label="مكتمل">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          <!-- Terminal (EXECUTIVE_REJECTED): lock icon -->
          <svg v-else-if="stage.state === 'terminal'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-lock" role="img" aria-label="نهائي">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          <!-- Skipped branch (not visited): dash -->
          <span v-else-if="stage.state === 'skipped'" class="node-dot node-dot--skipped" aria-hidden="true" />
          <!-- Current: filled circle -->
          <span v-else-if="stage.state === 'current'" class="node-dot node-dot--current" aria-hidden="true" />
          <!-- Future: empty circle -->
          <span v-else class="node-dot node-dot--future" aria-hidden="true" />
        </div>

        <!-- Stage content -->
        <div class="stage-content">
          <span class="stage-label">{{ stage.label }}</span>

          <!-- Current/terminal stage: show actor + timestamp from history -->
          <template v-if="(stage.state === 'current' || stage.state === 'terminal') && stage.entry">
            <span class="stage-actor">
              {{ stage.entry.performed_by?.name ?? `#${stage.entry.actor_id}` }}
            </span>
            <span class="stage-timestamp">{{ formatDate(stage.entry.created_at) }}</span>
          </template>

          <!-- Terminal label — EXECUTIVE_REJECTED only (dead-end, no further actions) -->
          <span v-if="stage.state === 'terminal'" class="stage-terminal-label">
            نهائي — لا إجراءات إضافية
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.workflow-timeline {
  display: flex;
  flex-direction: column;
  padding: 8px 0;
  position: relative;
}

.stage-item {
  position: relative;
  display: flex;
  flex-direction: column;
}

/* Vertical connector line between nodes */
.stage-connector {
  width: 2px;
  height: 20px;
  background: #d2d2d7;
  margin-right: 9px; /* aligns with node center (20px node / 2 - 1px) */
  margin-bottom: 0;
  flex-shrink: 0;
}

.stage-connector--done {
  background: #34c759;
}

.stage-body {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 4px 0;
}

/* Node circle/icon */
.stage-node {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.node-dot {
  display: block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.node-dot--current {
  background: #0071e3;
  box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.2);
}

.node-dot--future {
  background: transparent;
  border: 2px solid #d2d2d7;
}

.node-dot--skipped {
  background: transparent;
  border: 2px dashed #d2d2d7;
}

.icon-check {
  color: #34c759;
}

.icon-lock {
  color: #8e8e93;
}

/* Stage content text */
.stage-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  padding-bottom: 4px;
}

.stage-label {
  font-size: 14px;
  font-weight: 500;
  color: #1d1d1f;
}

.stage-item--future .stage-label,
.stage-item--skipped .stage-label {
  color: #8e8e93;
  font-weight: 400;
}

.stage-item--current .stage-label {
  color: #0071e3;
  font-weight: 700;
}

.stage-item--terminal .stage-label {
  color: #8e8e93;
  font-weight: 700;
}

.stage-actor {
  font-size: 12px;
  color: #6e6e73;
}

.stage-timestamp {
  font-size: 12px;
  color: #8e8e93;
}

.stage-terminal-label {
  font-size: 12px;
  font-weight: 600;
  color: #8e8e93;
  margin-top: 2px;
}

/* Current and terminal stage elevated card effect */
.stage-item--current .stage-body,
.stage-item--terminal .stage-body {
  background: #f5f5f7;
  border-radius: 8px;
  padding: 8px 12px;
  margin: 0 -12px;
}

.stage-item--skipped .stage-label {
  font-style: italic;
}
</style>
