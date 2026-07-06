<!-- app/components/workflow/FinancingUtilizationBar.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertTriangle } from 'lucide-vue-next'
import {
  LOW_REMAINING_THRESHOLD,
  FINANCING_ADVISORY_MESSAGE,
} from '@/composables/useFinancingLedger'

const props = defineProps<{
  usedPercent: number | null
  remainingPercent: number | null
  blocked: boolean
  loading: boolean
  error: string | null
}>()

// Nothing to show until the ledger has resolved a tax/invoice pair and
// returned percentages, is actively fetching, or has an error to surface.
const hasData = computed(() => props.usedPercent !== null && props.remainingPercent !== null)
const showNothing = computed(() => !hasData.value && !props.loading && !props.error)

const isLowRemaining = computed(
  () =>
    !props.blocked &&
    props.remainingPercent !== null &&
    props.remainingPercent <= LOW_REMAINING_THRESHOLD,
)
</script>

<template>
  <div v-if="!showNothing" dir="rtl" class="flex flex-col gap-2">
    <Skeleton v-if="loading" class="h-16 w-full rounded-xl" />

    <Card v-else-if="hasData" class="border-0 shadow">
      <CardContent class="flex flex-col gap-2 p-4">
        <div class="flex items-center justify-between gap-2 text-xs">
          <span class="text-muted-foreground">المستخدم {{ usedPercent }}%</span>
          <span class="text-muted-foreground">المتبقي {{ remainingPercent }}%</span>
        </div>
        <Progress :model-value="usedPercent ?? 0" class="h-1.5" />
      </CardContent>
    </Card>

    <!-- Blocked: advisory only — backend enforcement is authoritative, form stays usable. -->
    <Card
      v-if="blocked"
      class="border-0 border-s-4 border-s-[var(--severity-red)] bg-[var(--severity-red)]/5 shadow-sm"
      role="status"
    >
      <CardContent class="flex items-center gap-3 pt-4 pb-4">
        <AlertTriangle
          class="h-5 w-5 flex-shrink-0 text-[var(--severity-red)]"
          aria-hidden="true"
        />
        <p class="text-foreground min-w-0 flex-1 text-sm">{{ FINANCING_ADVISORY_MESSAGE }}</p>
      </CardContent>
    </Card>

    <!-- Low capacity: not blocking, just a heads-up before submit. -->
    <Card
      v-else-if="isLowRemaining"
      class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
      role="status"
    >
      <CardContent class="flex items-center gap-3 pt-4 pb-4">
        <AlertTriangle
          class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]"
          aria-hidden="true"
        />
        <p class="text-foreground min-w-0 flex-1 text-sm">
          النسبة المتبقية من السقف التمويلي العالمي منخفضة.
        </p>
      </CardContent>
    </Card>

    <!-- Indicator unavailable: advisory-only, must not alarm the operator. -->
    <p v-if="error" class="text-muted-foreground text-xs">{{ error }}</p>
  </div>
</template>
