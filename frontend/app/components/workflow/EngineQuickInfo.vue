<!-- app/components/workflow/EngineQuickInfo.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineRequest } from '@/types/models'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Building2, User, Coins, CalendarDays, Ship } from 'lucide-vue-next'

// "معلومات سريعة" summary card for the request view rail.
const props = defineProps<{ request: EngineRequest }>()

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

const amountText = computed(() => {
  if (props.request.amount == null) return '—'
  const value = new Intl.NumberFormat('ar-EG').format(props.request.amount)
  return props.request.currency ? `${value} ${props.request.currency}` : value
})

const createdText = computed(() =>
  props.request.created_at ? dateFormatter.format(new Date(props.request.created_at)) : '—',
)

// The arrival port lives in the free-form request data payload.
const arrivalPort = computed(() => {
  const value = props.request.data?.arrivalPort
  return typeof value === 'string' && value.length ? value : '—'
})

const rows = computed(() => [
  { icon: User, label: 'مُنشئ الطلب', value: props.request.creator?.name ?? '—' },
  { icon: Building2, label: 'البنك', value: props.request.bank?.name ?? '—' },
  { icon: Coins, label: 'المبلغ', value: amountText.value },
  { icon: Ship, label: 'ميناء الوصول', value: arrivalPort.value },
  { icon: CalendarDays, label: 'تاريخ الإنشاء', value: createdText.value },
])
</script>

<template>
  <Card class="border-0 shadow" aria-labelledby="quick-info-heading">
    <CardHeader class="pb-2">
      <CardTitle id="quick-info-heading" class="text-sm font-semibold">معلومات سريعة</CardTitle>
    </CardHeader>
    <CardContent class="flex flex-col gap-3 pt-0">
      <div v-for="row in rows" :key="row.label" class="flex items-center gap-2.5">
        <component
          :is="row.icon"
          class="text-muted-foreground h-4 w-4 shrink-0"
          aria-hidden="true"
        />
        <span class="text-muted-foreground text-xs">{{ row.label }}</span>
        <span class="text-foreground ms-auto truncate text-sm font-medium">{{ row.value }}</span>
      </div>
    </CardContent>
  </Card>
</template>
