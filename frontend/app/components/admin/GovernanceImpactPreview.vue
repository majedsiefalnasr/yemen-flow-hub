<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import {
  isGovernanceActionBlocked,
  type GovernanceImpactPayload,
  type GovernanceLifecycleAction,
} from '@/types/governance-impact'

const props = defineProps<{
  impact: GovernanceImpactPayload | null
  action: GovernanceLifecycleAction
  loading?: boolean
}>()

const blocked = computed(() =>
  props.impact ? isGovernanceActionBlocked(props.impact, props.action) : false,
)

const affectedLabel = computed(() =>
  props.action === 'delete' ? 'مسارات العمل المتأثرة' : 'المراحل المتأثرة',
)
</script>

<template>
  <div class="space-y-3">
    <div v-if="loading" class="space-y-2">
      <Skeleton class="h-4 w-3/4" />
      <Skeleton class="h-4 w-1/2" />
    </div>

    <Alert v-else-if="blocked" variant="destructive">
      <AlertTriangle class="size-4" />
      <AlertTitle>لا يمكن تنفيذ العملية</AlertTitle>
      <AlertDescription>
        {{
          action === 'delete'
            ? 'هذا السجل مرتبط بمسار عمل منشور ولا يمكن حذفه.'
            : 'إيقاف هذا السجل سيترك مرحلة منشورة بلا منفّذ فعّال.'
        }}
      </AlertDescription>
    </Alert>

    <Alert v-else-if="impact?.referenced_by_draft_only" variant="default">
      <AlertTriangle class="size-4" />
      <AlertTitle>تحذير: مسودات فقط</AlertTitle>
      <AlertDescription>يوجد ربط بمسودات غير منشورة — لن يُحظر الحذف/الإيقاف.</AlertDescription>
    </Alert>

    <div v-if="impact && impact.affected.length > 0" class="rounded-xl border border-[var(--outline-variant)] p-3">
      <p class="mb-2 text-sm font-medium text-[var(--on-surface)]">{{ affectedLabel }}</p>
      <ul class="space-y-2 text-sm text-[var(--on-surface-variant)]">
        <li v-for="(entry, index) in impact.affected" :key="index">
          <span class="font-medium text-[var(--on-surface)]">
            {{ entry.workflow_definition?.name ?? 'مسار عمل' }}
          </span>
          <span v-if="entry.stage"> — {{ entry.stage.name }}</span>
          <span v-if="entry.field"> — {{ entry.field.label }}</span>
          <span v-if="entry.executor_count_after !== undefined">
            (منفّذون: {{ entry.executor_count }} → {{ entry.executor_count_after }})
          </span>
        </li>
      </ul>
    </div>

    <ul
      v-if="impact?.warnings?.length"
      class="list-disc space-y-1 ps-5 text-sm text-[var(--on-surface-variant)]"
    >
      <li v-for="(warning, index) in impact.warnings" :key="index">{{ warning }}</li>
    </ul>
  </div>
</template>
