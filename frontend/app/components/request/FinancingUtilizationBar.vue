<script setup lang="ts">
import { computed, toRef, watch } from 'vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Skeleton } from '@/components/ui/skeleton'
import {
  FINANCING_ADVISORY_MESSAGE,
  LOW_REMAINING_THRESHOLD,
  useFinancingLedger,
} from '@/composables/useFinancingLedger'

const props = defineProps<{
  taxNumber?: string | null
  invoiceNumber?: string | null
  requestPercentage?: string | number | null
  excludeRequestId?: number | null
}>()

const emit = defineEmits<{
  'advisory-block': [blocked: boolean]
}>()

const { usedPercent, remainingPercent, blocked, loading, error } = useFinancingLedger({
  taxNumber: toRef(props, 'taxNumber'),
  invoiceNumber: toRef(props, 'invoiceNumber'),
  excludeRequestId: toRef(props, 'excludeRequestId'),
})

const requestedPercent = computed(() => {
  const value = Number(props.requestPercentage)
  return Number.isFinite(value) ? value : null
})

const lowRemaining = computed(() => {
  if (remainingPercent.value === null) return false
  return remainingPercent.value > 0 && remainingPercent.value <= LOW_REMAINING_THRESHOLD
})

// Advisory only — backend FINANCING_LIMIT_EXCEEDED remains authoritative (Story 17-D.2).
const advisoryBlocked = computed(() => {
  if (blocked.value) return true
  if (remainingPercent.value === null || requestedPercent.value === null) return false
  return requestedPercent.value > remainingPercent.value
})

watch(
  advisoryBlocked,
  (value) => {
    emit('advisory-block', value)
  },
  { immediate: true },
)
</script>

<template>
  <Card class="border-border/80">
    <CardHeader class="pb-3">
      <CardTitle class="text-base">مؤشر الاستخدام التمويلي العالمي</CardTitle>
    </CardHeader>
    <CardContent class="grid gap-3">
      <div v-if="loading" class="grid gap-2" data-test="financing-loading">
        <Skeleton class="h-4 w-40" />
        <Skeleton class="h-2 w-full rounded-full" />
        <Skeleton class="h-4 w-56" />
      </div>

      <Alert v-else-if="error" variant="destructive" data-test="financing-error">
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>

      <template v-else-if="usedPercent !== null && remainingPercent !== null">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
          <span>المستخدم: {{ usedPercent.toFixed(2) }}%</span>
          <span>المتبقي: {{ remainingPercent.toFixed(2) }}%</span>
        </div>

        <Progress :model-value="usedPercent" class="h-2" data-test="financing-progress" />

        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">السقف العالمي 100%</Badge>
          <Badge
            v-if="lowRemaining"
            class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
            data-test="financing-low-remaining"
          >
            متبقٍ منخفض
          </Badge>
          <Badge
            v-if="blocked"
            class="border border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]"
            data-test="financing-blocked"
          >
            السقف مستنفد
          </Badge>
        </div>

        <Alert v-if="advisoryBlocked" variant="destructive" data-test="financing-advisory-message">
          <AlertDescription>{{ FINANCING_ADVISORY_MESSAGE }}</AlertDescription>
        </Alert>
      </template>

      <p v-else class="text-muted-foreground text-sm">
        أدخل رقم الوعاء الضريبي ورقم الفاتورة لعرض مؤشر التمويل.
      </p>
    </CardContent>
  </Card>
</template>
