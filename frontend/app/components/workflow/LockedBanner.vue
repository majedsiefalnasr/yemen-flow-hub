<script setup lang="ts">
import { AlertCircle, Lock } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{
  variant?: 'locked' | 'readonly' | 'pending'
  message?: string
}>(), {
  variant: 'locked',
})

const config = computed(() => ({
  locked: {
    icon: Lock,
    title: 'هذا الطلب مغلق',
    defaultMsg: 'لا يمكن تعديل البيانات أو حذف المستندات بعد إحالته للبنك المركزي.',
    cls: 'bg-muted/60 border-border text-muted-foreground',
  },
  readonly: {
    icon: AlertCircle,
    title: 'وضع العرض فقط',
    defaultMsg: 'تستطيع الاطلاع على البيانات دون إجراء تعديلات.',
    cls: 'bg-info/10 border-info/30 text-info',
  },
  pending: {
    icon: AlertCircle,
    title: 'بانتظار إجراء',
    defaultMsg: 'هذا الطلب بانتظار خطوة من جهة أخرى.',
    cls: 'bg-warning/10 border-warning/30 text-warning',
  },
}[props.variant]))
</script>

<template>
  <div :class="cn('flex items-start gap-3 rounded-xl border px-4 py-3', config.cls)">
    <component :is="config.icon" class="mt-0.5 h-5 w-5 shrink-0" />
    <div class="flex-1 text-sm">
      <div class="font-semibold">
        {{ config.title }}
      </div>
      <div class="opacity-80">
        {{ message ?? config.defaultMsg }}
      </div>
    </div>
  </div>
</template>
