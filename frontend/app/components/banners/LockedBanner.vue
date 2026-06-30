<script setup lang="ts">
import { Lock, Eye, Clock, Ban } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { NOT_ELIGIBLE_LABEL_AR } from '../../constants/workflow'

type LockedBannerVariant = 'locked' | 'readonly' | 'pending' | 'bank_rejected'

const props = defineProps<{
  variant: LockedBannerVariant
  comment?: string
}>()

const VARIANT_CONFIG: Record<
  LockedBannerVariant,
  { icon: any; message: string; variant: 'default' | 'destructive' }
> = {
  locked: {
    icon: Lock,
    message: 'هذا الطلب مقفل ولا يمكن اتخاذ أي إجراء عليه',
    variant: 'default',
  },
  readonly: {
    icon: Eye,
    message: 'هذا الطلب في وضع القراءة فقط',
    variant: 'default',
  },
  pending: {
    icon: Clock,
    message: 'هذا الطلب قيد المراجعة، ولا يمكن تعديله حتى تكتمل المرحلة الحالية.',
    variant: 'default',
  },
  bank_rejected: {
    icon: Ban,
    message: `صنّف البنك هذا الطلب ${NOT_ELIGIBLE_LABEL_AR} نهائيا، لذلك لا يمكن اتخاذ أي إجراء عليه.`,
    variant: 'destructive',
  },
}

const config = VARIANT_CONFIG[props.variant]
</script>

<template>
  <Alert :variant="config.variant" class="flex items-start gap-3">
    <component :is="config.icon" class="mt-0.5 h-5 w-5 flex-shrink-0" aria-hidden="true" />
    <div class="flex flex-1 flex-col gap-1">
      <AlertDescription class="text-sm font-medium">{{ config.message }}</AlertDescription>
      <p v-if="variant === 'bank_rejected' && comment" class="text-xs italic opacity-80">
        {{ comment }}
      </p>
    </div>
  </Alert>
</template>
