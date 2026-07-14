<!-- app/components/workflow/EngineStageBanner.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineRequest } from '@/types/models'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { ShieldCheck, Lock } from 'lucide-vue-next'

// Current-stage banner + progress for the request view. Shows the active stage
// name, an ACTIVE/CLOSED/REJECTED status badge, an access badge (دورك / عرض فقط),
// and a progress bar of the request's position in the workflow.
const props = defineProps<{
  request: EngineRequest
  percent: number
  currentIndex: number
  total: number
  canExecute: boolean
}>()

const statusLabel: Record<EngineRequest['status'], string> = {
  ACTIVE: 'نشط',
  CLOSED: 'مكتمل',
  REJECTED: 'غير مؤهل',
}

// Status badge color token, matching DESIGN.md status categories.
const statusClass = computed(() => {
  switch (props.request.status) {
    case 'CLOSED':
      return 'border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
    case 'REJECTED':
      return 'border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'
    default:
      return 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
  }
})

const stageName = computed(() => props.request.current_stage?.name ?? '—')
</script>

<template>
  <Card class="border-0 shadow" aria-labelledby="stage-banner-heading">
    <CardContent class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <span
          class="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
          aria-hidden="true"
        >
          <ShieldCheck class="h-5 w-5" />
        </span>
        <div class="flex min-w-0 flex-col">
          <span class="text-muted-foreground text-xs">المرحلة الحالية</span>
          <span id="stage-banner-heading" class="text-foreground text-sm font-semibold">
            {{ stageName }}
          </span>
        </div>

        <Badge :class="statusClass" class="ms-auto">{{ statusLabel[request.status] }}</Badge>

        <Badge v-if="canExecute" variant="outline" class="border-primary/40 text-primary gap-1">
          <ShieldCheck class="h-3 w-3" aria-hidden="true" />
          دورك
        </Badge>
        <Badge v-else variant="outline" class="gap-1">
          <Lock class="h-3 w-3" aria-hidden="true" />
          عرض فقط
        </Badge>
      </div>

      <div class="flex items-center gap-3">
        <Progress :model-value="percent" class="h-1.5 flex-1" />
        <span class="text-muted-foreground text-xs whitespace-nowrap">
          {{ percent }}% · المرحلة {{ currentIndex }} من {{ total }}
        </span>
      </div>
    </CardContent>
  </Card>
</template>
