<script setup lang="ts">
import { Building2, Edit, Pause, Play } from 'lucide-vue-next'
import type { Merchant } from '../../types/models'
import { Card } from '../ui/card'
import { Button } from '../ui/button'
import { Badge } from '../ui/badge'

const props = defineProps<{
  merchant: Merchant
}>()

const emit = defineEmits<{
  edit: [merchant: Merchant]
  toggleStatus: [merchant: Merchant]
}>()

function metaVal(val: string | null | undefined): string {
  return val ?? '—'
}

function businessTypeLabel(type: string | null | undefined): string {
  const MAP: Record<string, string> = {
    import: 'استيراد',
    export: 'تصدير',
    retail: 'تجارة تجزئة',
    wholesale: 'تجارة جملة',
    manufacturing: 'تصنيع',
    services: 'خدمات',
  }
  return type ? (MAP[type] ?? type) : '—'
}
</script>

<template>
  <Card  class="hover:shadow-md transition-shadow">
    <!-- Header: icon tile + status badge -->
    <div class="flex items-start justify-between gap-3 pb-3 border-b border-gray-200">
      <div class="w-12 h-12 rounded-lg bg-primary/10 text-primary flex items-center justify-center flex-shrink-0" aria-hidden="true">
        <Building2 :size="24" />
      </div>
      <Badge
        :variant="props.merchant.is_active ? 'default' : 'secondary'"
        role="status"
      >
        {{ props.merchant.is_active ? 'نشط' : 'موقوف' }}
      </Badge>
    </div>

    <!-- Name + category -->
    <div class="flex flex-col gap-1 py-3 border-b border-gray-200">
      <p class="text-sm font-semibold text-gray-900">{{ props.merchant.name }}</p>
      <p class="text-xs text-gray-600">{{ businessTypeLabel(props.merchant.business_type) }}</p>
    </div>

    <!-- Metadata rows -->
    <dl class="flex flex-col gap-2 py-3 border-b border-gray-200 text-xs">
      <div class="flex justify-between items-center gap-2">
        <dt class="text-gray-600">السجل التجاري</dt>
        <dd class="font-mono text-gray-900 font-medium">{{ metaVal(props.merchant.commercial_register) }}</dd>
      </div>
      <div class="flex justify-between items-center gap-2">
        <dt class="text-gray-600">الرقم الضريبي</dt>
        <dd class="font-mono text-gray-900 font-medium">{{ metaVal(props.merchant.tax_number) }}</dd>
      </div>
      <div class="flex justify-between items-center gap-2">
        <dt class="text-gray-600">البنك</dt>
        <dd class="text-gray-900 font-medium overflow-hidden text-ellipsis whitespace-nowrap max-w-xs">{{ metaVal(props.merchant.bank_name) }}</dd>
      </div>
      <div class="flex justify-between items-center gap-2">
        <dt class="text-gray-600">العنوان</dt>
        <dd class="text-gray-900 font-medium overflow-hidden text-ellipsis whitespace-nowrap max-w-xs">{{ metaVal(props.merchant.address) }}</dd>
      </div>
      <div class="flex justify-between items-center gap-2">
        <dt class="text-gray-600">هاتف</dt>
        <dd class="font-mono text-gray-900 font-medium direction-ltr">{{ metaVal(props.merchant.phone) }}</dd>
      </div>
    </dl>

    <!-- Footer: transaction count + actions -->
    <div class="flex items-center justify-between gap-2 pt-3">
      <div class="text-xs flex items-center gap-1">
        <span class="text-gray-600">المعاملات:</span>
        <span class="font-mono font-bold text-gray-900">{{ props.merchant.transaction_count ?? 0 }}</span>
      </div>
      <div class="flex gap-1">
        <Button
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
          :aria-label="props.merchant.is_active ? 'تعليق التاجر' : 'تفعيل التاجر'"
          :title="props.merchant.is_active ? 'تعليق' : 'تفعيل'"
          @click="emit('toggleStatus', props.merchant)"
        >
          <Pause v-if="props.merchant.is_active" :size="16" />
          <Play v-else :size="16" />
        </Button>
        <Button
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
          aria-label="تعديل التاجر"
          title="تعديل"
          @click="emit('edit', props.merchant)"
        >
          <Edit :size="16" />
        </Button>
      </div>
    </div>
  </Card>
</template>
