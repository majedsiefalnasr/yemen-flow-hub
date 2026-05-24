<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'

withDefaults(defineProps<{
  variant?: 'draft_rejected' | 'bank_returned' | 'support_returned'
  rejectionReason?: string | null
  reviewerComment?: string | null
  supportComment?: string | null
}>(), {
  variant: 'draft_rejected',
})
</script>

<template>
  <Alert variant="destructive" dir="rtl" class="flex items-start gap-3 border-warning bg-amber-50/10">
    <AlertTriangle class="h-5 w-5 flex-shrink-0 mt-0.5 text-amber-600" aria-hidden="true" />
    <div class="flex flex-col gap-1 flex-1">
      <AlertDescription v-if="variant === 'bank_returned'" class="text-sm font-medium">
        إعادة من المراجع — يرجى التعديل وإعادة الإرسال
      </AlertDescription>
      <AlertDescription v-else-if="variant === 'support_returned'" class="text-sm font-medium">
        إعادة من لجنة المساندة — يرجى التعديل وإعادة الإرسال
      </AlertDescription>
      <AlertDescription v-else class="text-sm font-medium">
        تم إرجاع الطلب للتصحيح من المراجعة الداخلية — يرجى مراجعة الملاحظات وتعديل الطلب.
      </AlertDescription>
      <p v-if="variant === 'bank_returned' && reviewerComment" class="text-xs text-amber-600">
        تعليق المراجع: {{ reviewerComment }}
      </p>
      <p v-else-if="variant === 'support_returned' && supportComment" class="text-xs text-amber-600">
        تعليق لجنة المساندة: {{ supportComment }}
      </p>
      <p v-else-if="rejectionReason" class="text-xs text-amber-600">سبب الإرجاع: {{ rejectionReason }}</p>
    </div>
  </Alert>
</template>
