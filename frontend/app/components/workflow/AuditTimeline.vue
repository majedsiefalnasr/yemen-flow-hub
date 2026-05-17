<script setup lang="ts">
import type { RequestStageHistory } from '../../types/models'
import { STATUS_LABELS, ROLE_LABELS } from '../../constants/workflow'
import { RequestStatus, UserRole } from '../../types/enums'

defineProps<{
  entries: RequestStageHistory[]
}>()

const GREEN_STATUSES = new Set<string>([
  RequestStatus.BANK_APPROVED,
  RequestStatus.SUPPORT_APPROVED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
  RequestStatus.COMPLETED,
])

const RED_STATUSES = new Set<string>([
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const AMBER_STATUSES = new Set<string>([
  RequestStatus.DRAFT_REJECTED_INTERNAL,
  RequestStatus.SUBMITTED,
])

const INDIGO_STATUSES = new Set<string>([
  RequestStatus.SUPPORT_REVIEW_PENDING,
  RequestStatus.SUPPORT_REVIEW_IN_PROGRESS,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
])

function entryColor(entry: RequestStageHistory): string {
  const s = entry.to_status
  if (!s) return '#8e8e93'
  if (GREEN_STATUSES.has(s)) return '#34c759'
  if (RED_STATUSES.has(s)) return '#ff3b30'
  if (AMBER_STATUSES.has(s)) return '#ff9f0a'
  if (INDIGO_STATUSES.has(s)) return '#5856d6'
  return '#8e8e93'
}

function statusLabel(value: string | null): string {
  if (!value) return '—'
  return STATUS_LABELS[value as RequestStatus] ?? value
}

function roleLabel(value: string | null): string {
  if (!value) return ''
  return ROLE_LABELS[value as UserRole] ?? value
}

function actionLabel(action: string): string {
  const MAP: Record<string, string> = {
    submit: 'تقديم الطلب',
    bank_approve: 'موافقة البنك',
    bank_reject: 'رفض البنك',
    return_to_entry: 'إعادة إلى المُدخل',
    support_claim: 'حجز المراجعة',
    support_release: 'إلغاء الحجز',
    support_approve: 'موافقة لجنة الدعم',
    support_reject: 'رفض لجنة الدعم',
    swift_upload: 'رفع مستند SWIFT',
    start_voting: 'فتح جلسة التصويت',
    finalize_approved: 'اعتماد نهائي — موافقة',
    finalize_rejected: 'اعتماد نهائي — رفض',
    issue_customs: 'إصدار البيان الجمركي',
    complete: 'إتمام الطلب',
  }
  return MAP[action] ?? action
}

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
  <div class="audit-timeline" dir="rtl" role="list" aria-label="سجل تدقيق الأحداث">
    <p v-if="entries.length === 0" class="audit-empty">
      لا توجد أحداث مسجّلة بعد.
    </p>

    <div
      v-for="(entry, idx) in entries"
      :key="entry.id"
      class="audit-entry"
      role="listitem"
    >
      <!-- Connector above (except first) -->
      <div v-if="idx > 0" class="audit-connector" aria-hidden="true" />

      <div class="audit-body">
        <!-- Color dot -->
        <div
          class="audit-dot"
          :style="{ background: entryColor(entry) }"
          aria-hidden="true"
        />

        <!-- Entry content -->
        <div class="audit-content">
          <!-- Action title -->
          <span class="audit-action">{{ actionLabel(entry.action) }}</span>

          <!-- from → to status transition -->
          <span v-if="entry.from_status || entry.to_status" class="audit-transition">
            <span v-if="entry.from_status" class="audit-status-chip audit-status-chip--from">
              {{ statusLabel(entry.from_status) }}
            </span>
            <span v-if="entry.from_status && entry.to_status" class="audit-arrow" aria-hidden="true">←</span>
            <span v-if="entry.to_status" class="audit-status-chip audit-status-chip--to" :style="{ borderColor: entryColor(entry), color: entryColor(entry) }">
              {{ statusLabel(entry.to_status) }}
            </span>
          </span>

          <!-- Actor -->
          <span class="audit-actor">
            {{ entry.performed_by?.name ?? `#${entry.actor_id}` }}
            <span v-if="entry.actor_role" class="audit-role">
              ({{ roleLabel(entry.actor_role) }})
            </span>
          </span>

          <!-- Notes -->
          <span v-if="entry.notes" class="audit-notes">
            {{ entry.notes }}
          </span>

          <!-- Timestamp -->
          <span class="audit-timestamp">{{ formatDate(entry.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.audit-timeline {
  display: flex;
  flex-direction: column;
  padding: 8px 0;
}

.audit-empty {
  text-align: center;
  color: #6e6e73;
  font-size: 14px;
  padding: 32px 0;
  margin: 0;
}

.audit-entry {
  display: flex;
  flex-direction: column;
}

.audit-connector {
  width: 2px;
  height: 16px;
  background: #d2d2d7;
  margin-right: 9px;
  flex-shrink: 0;
}

.audit-body {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 0;
}

.audit-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 1px;
}

.audit-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.audit-action {
  font-size: 14px;
  font-weight: 600;
  color: #1d1d1f;
}

.audit-transition {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.audit-status-chip {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 20px;
  border: 1px solid #d2d2d7;
  color: #6e6e73;
  background: #f5f5f7;
}

.audit-status-chip--to {
  background: transparent;
  font-weight: 600;
}

.audit-arrow {
  font-size: 12px;
  color: #8e8e93;
}

.audit-actor {
  font-size: 13px;
  color: #6e6e73;
}

.audit-role {
  font-size: 12px;
  color: #8e8e93;
}

.audit-notes {
  font-size: 13px;
  color: #1d1d1f;
  background: #f5f5f7;
  border-radius: 6px;
  padding: 4px 8px;
  font-style: italic;
}

.audit-timestamp {
  font-size: 12px;
  color: #8e8e93;
}
</style>
