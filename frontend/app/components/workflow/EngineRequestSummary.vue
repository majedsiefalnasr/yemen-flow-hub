<!-- app/components/workflow/EngineRequestSummary.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineRequest } from '@/types/models'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{ request: EngineRequest }>()

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

const statusLabel: Record<EngineRequest['status'], string> = {
  ACTIVE: 'نشط',
  CLOSED: 'مكتمل',
  REJECTED: 'غير مؤهل',
}

const amountText = computed(() => {
  if (props.request.amount === null) return '—'
  const value = new Intl.NumberFormat('ar-EG').format(props.request.amount)
  return props.request.currency ? `${value} ${props.request.currency}` : value
})

const createdText = computed(() =>
  props.request.created_at ? dateFormatter.format(new Date(props.request.created_at)) : '—',
)

const items = computed(() => [
  { label: 'المرجع', value: props.request.reference },
  { label: 'المرحلة الحالية', value: props.request.current_stage?.name ?? '—' },
  { label: 'البنك', value: props.request.bank?.name ?? '—' },
  { label: 'التاجر', value: props.request.merchant?.name ?? '—' },
  { label: 'المبلغ', value: amountText.value },
  { label: 'تاريخ الإنشاء', value: createdText.value },
  { label: 'مُطالَب بواسطة', value: props.request.claimed_by_user?.name ?? '—' },
])
</script>

<template>
  <div dir="rtl" class="bg-muted/30 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-lg border p-4">
    <div class="flex flex-col">
      <span class="text-muted-foreground text-xs">الحالة</span>
      <Badge variant="outline" class="mt-1 w-fit">{{ statusLabel[request.status] }}</Badge>
    </div>
    <div v-for="item in items" :key="item.label" class="flex flex-col">
      <span class="text-muted-foreground text-xs">{{ item.label }}</span>
      <span class="text-foreground mt-1 text-sm font-medium">{{ item.value }}</span>
    </div>
  </div>
</template>
