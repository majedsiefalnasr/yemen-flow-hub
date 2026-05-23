<script setup lang="ts">
import { computed } from 'vue'
import { RequestStatus, UserRole } from '../../types/enums'
import { getBusinessStatus } from '../../constants/workflow'

const props = defineProps<{
  status: RequestStatus
  role: UserRole
}>()

const badge = computed(() => getBusinessStatus(props.status, props.role))

const badgeStyle = computed(() => ({
  backgroundColor: `${badge.value.color}1a`,
  color: badge.value.color,
  borderColor: `${badge.value.color}33`,
  display: 'inline-flex',
  alignItems: 'center',
  direction: 'rtl',
  gap: '5px',
  height: '24px',
  padding: '0 10px',
  borderRadius: '12px',
  border: '1px solid',
  fontSize: '12px',
  fontWeight: '500',
  whiteSpace: 'nowrap',
  lineHeight: '1',
}))
</script>

<template>
  <span class="status-badge" role="img" :style="badgeStyle" :aria-label="badge.label">
    <svg
      class="flex-shrink-0"
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <!-- file -->
      <template v-if="badge.icon === 'file'">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
      </template>

      <!-- rotate-ccw (returned/correction) -->
      <template v-else-if="badge.icon === 'rotate-ccw'">
        <polyline points="1 4 1 10 7 10" />
        <path d="M3.51 15a9 9 0 1 0 .49-3.75" />
      </template>

      <!-- clock (pending/submitted) -->
      <template v-else-if="badge.icon === 'clock'">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
      </template>

      <!-- check-circle (approved/completed) -->
      <template v-else-if="badge.icon === 'check-circle'">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </template>

      <!-- x-circle (rejected) -->
      <template v-else-if="badge.icon === 'x-circle'">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
      </template>

      <!-- users (support review) -->
      <template v-else-if="badge.icon === 'users'">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </template>

      <!-- upload-cloud (SWIFT) -->
      <template v-else-if="badge.icon === 'upload-cloud'">
        <polyline points="16 16 12 12 8 16" />
        <line x1="12" y1="12" x2="12" y2="21" />
        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
      </template>

      <!-- vote (executive voting) -->
      <template v-else-if="badge.icon === 'vote'">
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <line x1="9" y1="9" x2="15" y2="9" />
        <line x1="9" y1="12" x2="15" y2="12" />
        <line x1="9" y1="15" x2="12" y2="15" />
      </template>

      <!-- lock (closed/immutable) -->
      <template v-else-if="badge.icon === 'lock'">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
      </template>

      <!-- file-check (customs issued) -->
      <template v-else-if="badge.icon === 'file-check'">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
        <polyline points="9 15 11 17 15 13" />
      </template>

      <!-- fallback: circle -->
      <template v-else>
        <circle cx="12" cy="12" r="10" />
      </template>
    </svg>
    <span class="status-badge__label">{{ badge.label }}</span>
  </span>
</template>
