<script setup lang="ts">
import { Check } from 'lucide-vue-next'
import { ROLE_BUCKETS, STATUS_LABELS, getBusinessStatus } from '@/constants/workflow'
import { RequestStatus, UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{
  status?: RequestStatus
  currentStatus?: RequestStatus
  compact?: boolean
  userRole?: UserRole
}>(), {
  compact: false,
})

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
]

const steps = computed(() => {
  const buckets = ROLE_BUCKETS[role.value]
  if (buckets) {
    return buckets
      .filter(b => !b.statuses.every(s => REJECT_STATUSES.includes(s) || RETURN_STATUSES.includes(s)))
      .map(b => ({ key: b.key, label: b.label, statuses: b.statuses }))
  }
  return Object.values(RequestStatus)
    .filter(s => !REJECT_STATUSES.includes(s) && !RETURN_STATUSES.includes(s))
    .map(s => ({ key: s, label: STATUS_LABELS[s], statuses: [s] }))
})

const currentIndex = computed(() =>
  steps.value.findIndex(step => step.statuses.includes(resolvedStatus.value)),
)
const completedAll = computed(() =>
  TERMINAL_DONE.includes(resolvedStatus.value) || currentIndex.value === steps.value.length - 1,
)
</script>

<template>
  <div class="rounded-2xl border bg-white p-5 shadow">
    <div class="mb-4 flex items-center justify-between">
      <div class="text-sm font-semibold">
        سير العملية التنظيمية
      </div>
      <span
        v-if="RETURN_STATUSES.includes(resolvedStatus) || REJECT_STATUSES.includes(resolvedStatus)"
        :class="cn(
          'rounded-full px-2 py-0.5 text-[10px] font-medium',
          RETURN_STATUSES.includes(resolvedStatus) && 'bg-amber-50/15 text-amber-600',
          REJECT_STATUSES.includes(resolvedStatus) && 'bg-red-700/15 text-red-700',
        )"
      >
        {{ RETURN_STATUSES.includes(resolvedStatus) ? 'مُعاد للتعديل' : 'مرفوض' }}
      </span>
    </div>

    <ol class="relative">
      <li
        v-for="(step, index) in steps"
        :key="step.key"
        :class="cn('relative flex items-start gap-3', index < steps.length - 1 && 'pb-5', index === currentIndex && 'wp-step--current')"
      >
        <span
          v-if="index < steps.length - 1"
          :class="cn(
            'absolute end-[11px] top-7 h-[calc(100%-1.25rem)] w-px',
            completedAll || index < currentIndex ? 'bg-gray-900/80' : 'bg-border',
          )"
        />

        <div class="relative z-10 grid h-[22px] w-[22px] shrink-0 place-items-center">
          <span
            v-if="completedAll || index < currentIndex"
            class="grid h-[22px] w-[22px] place-items-center rounded-full bg-gray-900 text-white"
          >
            <Check class="h-3 w-3" :stroke-width="3" />
          </span>
          <span
            v-else-if="index === currentIndex"
            class="grid h-[22px] w-[22px] place-items-center rounded-full bg-gray-900 ring-4 ring-foreground/15"
          >
            <span class="h-2 w-2 rounded-full bg-background" />
          </span>
          <span
            v-else
            class="h-[22px] w-[22px] rounded-full border-2 border-gray-200 bg-gray-50/40"
          />
        </div>

        <div class="-mt-0.5 flex-1">
          <div
            :class="cn(
              'text-sm leading-snug',
              index === currentIndex ? 'font-semibold text-gray-900' : index < currentIndex ? 'text-gray-900' : 'text-gray-600',
            )"
          >
            {{ step.label }}
          </div>
          <div
            v-if="!compact"
            :class="cn(
              'mt-0.5 text-[11px] leading-tight',
              index === currentIndex ? 'text-blue-600' : index < currentIndex ? 'text-green-700' : 'text-gray-600/70',
            )"
          >
            {{ index === currentIndex ? 'المرحلة الحالية' : index < currentIndex ? 'مكتملة' : 'بانتظار' }}
          </div>
        </div>
      </li>
    </ol>
  </div>
</template>
