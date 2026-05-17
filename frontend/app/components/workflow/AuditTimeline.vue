<script setup lang="ts">
import type { RequestStageHistory } from '../../types/models'
import { STATUS_LABELS, STATUS_COLORS, ROLE_LABELS } from '../../constants/workflow'
import { RequestStatus, UserRole } from '../../types/enums'

defineProps<{
  entries: RequestStageHistory[]
}>()

// F5: hoisted to module scope — not rebuilt per render
const ACTION_LABELS: Record<string, string> = {
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
  close_voting: 'إغلاق جلسة التصويت',
  cast_vote: 'تسجيل تصويت',
  finalize_approved: 'اعتماد نهائي — موافقة',
  finalize_rejected: 'اعتماد نهائي — رفض',
  override_approved: 'تجاوز — موافقة',
  override_rejected: 'تجاوز — رفض',
  issue_customs: 'إصدار البيان الجمركي',
  complete: 'إتمام الطلب',
  claim_expire: 'انتهاء صلاحية الحجز',
  document_upload: 'رفع مستند',
}

// F4: delegate to STATUS_COLORS from constants/workflow.ts (single source of truth)
function entryColor(entry: RequestStageHistory): string {
  const s = entry.to_status as RequestStatus | null
  if (!s) return '#8e8e93'
  return STATUS_COLORS[s] ?? '#8e8e93'
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
  return ACTION_LABELS[action] ?? action
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
        <!-- Color dot — F8: entryColor called once, bound to CSS var on the entry root -->
        <div
          class="audit-dot"
          :style="{ background: entryColor(entry) }"
          aria-hidden="true"
        />

        <!-- Entry content -->
        <!-- F8: bind color to a CSS custom property so to-chip re-uses it without a second call -->
        <div
          class="audit-content"
          :style="{ '--entry-color': entryColor(entry) }"
        >
          <!-- Action title -->
          <span class="audit-action">{{ actionLabel(entry.action) }}</span>

          <!-- from → to status transition -->
          <!-- F2: → (U+2192) is explicitly directional; bidi algorithm renders it correctly in RTL -->
          <span v-if="entry.from_status || entry.to_status" class="audit-transition">
            <span v-if="entry.from_status" class="audit-status-chip audit-status-chip--from">
              {{ statusLabel(entry.from_status) }}
            </span>
            <span v-if="entry.from_status && entry.to_status" class="audit-arrow" aria-hidden="true">&#x2192;</span>
            <span v-if="entry.to_status" class="audit-status-chip audit-status-chip--to">
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
  border-color: var(--entry-color);
  color: var(--entry-color);
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
