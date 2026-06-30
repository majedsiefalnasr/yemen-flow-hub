<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'

withDefaults(
  defineProps<{
    variant?: 'draft_rejected' | 'bank_returned' | 'support_returned'
    rejectionReason?: string | null
    reviewerComment?: string | null
    supportComment?: string | null
  }>(),
  {
    variant: 'draft_rejected',
  },
)
</script>

<template>
  <Alert
    variant="destructive"
    class="border-warning flex items-start gap-3 bg-[var(--color-surface-warning)]"
  >
    <AlertTriangle
      class="mt-0.5 h-5 w-5 flex-shrink-0 text-[var(--color-text-warning)]"
      aria-hidden="true"
    />
    <div class="flex flex-1 flex-col gap-1">
      <AlertDescription v-if="variant === 'bank_returned'" class="text-sm font-medium">
        أُعيد الطلب إلى مدخل البيانات للتصحيح. راجع التعليق، عدّل البيانات، ثم أعد الإرسال.
      </AlertDescription>
      <AlertDescription v-else-if="variant === 'support_returned'" class="text-sm font-medium">
        أعادت لجنة المساندة الطلب للتصحيح. راجع التعليق، عدّل البيانات، ثم أعد الإرسال.
      </AlertDescription>
      <AlertDescription v-else class="text-sm font-medium">
        أُعيد الطلب للتصحيح من المراجعة الداخلية. راجع الملاحظات وعدّل الطلب.
      </AlertDescription>
      <p
        v-if="variant === 'bank_returned' && reviewerComment"
        class="text-xs text-[var(--color-text-warning)]"
      >
        تعليق المراجع: {{ reviewerComment }}
      </p>
      <p
        v-else-if="variant === 'support_returned' && supportComment"
        class="text-xs text-[var(--color-text-warning)]"
      >
        تعليق لجنة المساندة: {{ supportComment }}
      </p>
      <p v-else-if="rejectionReason" class="text-xs text-[var(--color-text-warning)]">
        سبب الإرجاع: {{ rejectionReason }}
      </p>
    </div>
  </Alert>
</template>
