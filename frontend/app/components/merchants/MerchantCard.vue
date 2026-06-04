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
  <Card class="transition-shadow hover:shadow-md">
    <!-- Header: icon tile + status badge -->
    <div class="border-border flex items-start justify-between gap-3 border-b pb-3">
      <div
        class="bg-primary/10 text-primary flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg"
        aria-hidden="true"
      >
        <Building2 :size="24" />
      </div>
      <Badge :variant="props.merchant.is_active ? 'default' : 'secondary'" role="status">
        {{ props.merchant.is_active ? 'نشط' : 'موقوف' }}
      </Badge>
    </div>

    <!-- Name + category -->
    <div class="border-border flex flex-col gap-1 border-b py-3">
      <p class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ props.merchant.name }}
      </p>
      <p class="text-xs text-[var(--color-text-subtle)]">
        {{ businessTypeLabel(props.merchant.business_type) }}
      </p>
    </div>

    <!-- Metadata rows -->
    <dl class="border-border flex flex-col gap-2 border-b py-3 text-xs">
      <div class="flex items-center justify-between gap-2">
        <dt class="text-[var(--color-text-subtle)]">السجل التجاري</dt>
        <dd class="font-mono font-medium text-[var(--color-text-primary)]">
          {{ metaVal(props.merchant.commercial_register) }}
        </dd>
      </div>
      <div class="flex items-center justify-between gap-2">
        <dt class="text-[var(--color-text-subtle)]">الرقم الضريبي</dt>
        <dd class="font-mono font-medium text-[var(--color-text-primary)]">
          {{ metaVal(props.merchant.tax_number) }}
        </dd>
      </div>
      <div class="flex items-center justify-between gap-2">
        <dt class="text-[var(--color-text-subtle)]">البنك</dt>
        <dd
          class="max-w-xs overflow-hidden font-medium text-ellipsis whitespace-nowrap text-[var(--color-text-primary)]"
        >
          {{ metaVal(props.merchant.bank_name) }}
        </dd>
      </div>
      <div class="flex items-center justify-between gap-2">
        <dt class="text-[var(--color-text-subtle)]">العنوان</dt>
        <dd
          class="max-w-xs overflow-hidden font-medium text-ellipsis whitespace-nowrap text-[var(--color-text-primary)]"
        >
          {{ metaVal(props.merchant.address) }}
        </dd>
      </div>
      <div class="flex items-center justify-between gap-2">
        <dt class="text-[var(--color-text-subtle)]">هاتف</dt>
        <dd class="direction-ltr font-mono font-medium text-[var(--color-text-primary)]">
          {{ metaVal(props.merchant.phone) }}
        </dd>
      </div>
    </dl>

    <!-- Footer: transaction count + actions -->
    <div class="flex items-center justify-between gap-2 pt-3">
      <div class="flex items-center gap-1 text-xs">
        <span class="font-section leading-5 font-medium text-[var(--color-text-subtle)]"
          >المعاملات:</span
        >
        <span
          class="font-mono leading-5 font-semibold text-[var(--color-text-primary)] tabular-nums"
          >{{ props.merchant.transaction_count ?? 0 }}</span
        >
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
