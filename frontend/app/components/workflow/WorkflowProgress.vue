<script setup lang="ts">
import { computed } from 'vue'
import { RequestStatus, UserRole } from '../../types/enums'
import { ROLE_BUCKETS, getBusinessStatus, getStatusProgress } from '../../constants/workflow'
import type { StageBucket } from '../../constants/workflow'

const props = defineProps<{
  currentStatus: RequestStatus
  userRole: UserRole
}>()

const REJECT_STATUSES = new Set([
  RequestStatus.SUPPORT_REJECTED,
  RequestStatus.EXECUTIVE_REJECTED,
  RequestStatus.DRAFT_REJECTED_INTERNAL,
])

const DONE_STATUSES = new Set([
  RequestStatus.COMPLETED,
  RequestStatus.CUSTOMS_DECLARATION_ISSUED,
])

const buckets = computed((): StageBucket[] => {
  return ROLE_BUCKETS[props.userRole] ?? ROLE_BUCKETS[UserRole.CBY_ADMIN] ?? []
})

const displayStatus = computed(() => getBusinessStatus(props.currentStatus, props.userRole))

const progress = computed(() => getStatusProgress(props.currentStatus, props.userRole))

const effectiveStatuses = computed(() => {
  const canonicalStatus = displayStatus.value.canonicalStatus
  return canonicalStatus === props.currentStatus
    ? [props.currentStatus]
    : [props.currentStatus, canonicalStatus]
})

const currentBucketIndex = computed(() => {
  return buckets.value.findIndex(bucket =>
    effectiveStatuses.value.some(status => bucket.statuses.includes(status)),
  )
})

function bucketState(idx: number): 'done' | 'current' | 'rejected' | 'future' {
  const bucket = buckets.value[idx]
  if (!bucket) return 'future'
  const isCurrentBucket = effectiveStatuses.value.some(status => bucket.statuses.includes(status))
  if (isCurrentBucket && REJECT_STATUSES.has(props.currentStatus)) return 'rejected'
  if (isCurrentBucket) return 'current'
  if (idx < currentBucketIndex.value) return 'done'
  return 'future'
}

const isRejected = computed(() => REJECT_STATUSES.has(props.currentStatus))
const isCompleted = computed(() => DONE_STATUSES.has(props.currentStatus))

const currentLabel = computed(() => displayStatus.value.label)
</script>

<template>
  <div class="workflow-progress" dir="rtl">
    <div class="wp-header">
      <span class="wp-title">سير العملية التنظيمية</span>
      <span
        v-if="isRejected"
        class="wp-badge wp-badge--reject"
      >مرفوض</span>
      <span
        v-else-if="isCompleted"
        class="wp-badge wp-badge--done"
      >مكتمل</span>
    </div>

    <div class="wp-progress-bar">
      <div class="wp-progress-track">
        <div
          class="wp-progress-fill"
          :class="{ 'wp-progress-fill--reject': isRejected, 'wp-progress-fill--done': isCompleted }"
          :style="{ width: `${progress}%` }"
        />
      </div>
      <span class="wp-progress-pct">{{ progress }}%</span>
    </div>

    <div class="wp-stage-label">المرحلة الحالية: {{ currentLabel }}</div>

    <ul class="wp-steps" role="list" aria-label="مراحل سير العمل">
      <li
        v-for="(bucket, idx) in buckets"
        :key="bucket.key"
        class="wp-step"
        :class="`wp-step--${bucketState(idx)}`"
      >
        <!-- Connector line above (except first) -->
        <div v-if="idx > 0" class="wp-connector" aria-hidden="true" />

        <div class="wp-step-body">
          <!-- Node circle -->
          <div class="wp-node" :class="`wp-node--${bucketState(idx)}`" aria-hidden="true">
            <svg
              v-if="bucketState(idx) === 'done'"
              width="10"
              height="10"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <svg
              v-else-if="bucketState(idx) === 'rejected'"
              width="10"
              height="10"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
            >
              <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            <div v-else-if="bucketState(idx) === 'current'" class="wp-node-inner" />
          </div>

          <!-- Step label -->
          <div class="wp-step-content">
            <span class="wp-step-label">{{ bucket.label }}</span>
            <span class="wp-step-sub">
              <template v-if="bucketState(idx) === 'done'">مكتملة</template>
              <template v-else-if="bucketState(idx) === 'current' && isRejected">مرفوض في هذه المرحلة</template>
              <template v-else-if="bucketState(idx) === 'current'">المرحلة الحالية</template>
              <template v-else>بانتظار</template>
            </span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.workflow-progress {
  display: flex;
  flex-direction: column;
  gap: 12px;
  direction: rtl;
}

.wp-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.wp-title {
  font-size: 14px;
  font-weight: 600;
  color: #1c222b;
}

.wp-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

.wp-badge--reject {
  background: #fff0f0;
  color: #c62828;
}

.wp-badge--done {
  background: #e8f5e9;
  color: #1b5e20;
}

.wp-progress-bar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.wp-progress-track {
  flex: 1;
  height: 6px;
  background: #f0f0f0;
  border-radius: 999px;
  overflow: hidden;
}

.wp-progress-fill {
  height: 100%;
  background: #0066cc;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.wp-progress-fill--reject {
  background: #c62828;
}

.wp-progress-fill--done {
  background: #1b5e20;
}

.wp-progress-pct {
  font-size: 12px;
  font-weight: 600;
  color: #6c757d;
  min-width: 32px;
  text-align: left;
}

.wp-stage-label {
  font-size: 12px;
  color: #6c757d;
}

.wp-steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.wp-step {
  display: flex;
  flex-direction: column;
}

.wp-connector {
  width: 2px;
  height: 12px;
  background: #cccccc;
  margin-right: 9px;
  flex-shrink: 0;
}

.wp-step--done .wp-connector {
  background: #0066cc;
}

.wp-step--rejected .wp-connector {
  background: #c62828;
}

.wp-step-body {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px 0;
}

/* Node */
.wp-node {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 1px;
}

.wp-node--done {
  background: #0066cc;
  color: #ffffff;
}

.wp-node--current {
  background: #ffffff;
  border: 2.5px solid #0066cc;
}

.wp-node--rejected {
  background: #ffffff;
  border: 2.5px solid #c62828;
  color: #c62828;
}

.wp-node--future {
  background: #ffffff;
  border: 2px solid #cccccc;
}

.wp-node-inner {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #0066cc;
}

.wp-step-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.wp-step-label {
  font-size: 13px;
  font-weight: 500;
  color: #1c222b;
}

.wp-step--future .wp-step-label {
  color: #6c757d;
}

.wp-step-sub {
  font-size: 11px;
  color: #6c757d;
}

.wp-step--done .wp-step-sub {
  color: #0066cc;
}

.wp-step--current .wp-step-sub {
  color: #0066cc;
  font-weight: 600;
}

.wp-step--rejected .wp-step-sub {
  color: #c62828;
  font-weight: 600;
}
</style>
