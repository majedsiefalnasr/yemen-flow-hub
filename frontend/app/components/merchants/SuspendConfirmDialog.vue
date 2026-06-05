<script setup lang="ts">
import { computed } from 'vue'
import { Pause, Play, AlertCircle } from 'lucide-vue-next'
import type { Merchant } from '../../types/models'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogContent from '@/components/ui/dialog/DialogContent.vue'
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue'
import Button from '@/components/ui/button/Button.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'

const props = defineProps<{
  merchant: Merchant
  submitting: boolean
  error: string | null
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const isSuspend = computed(() => props.merchant.is_active)
const title = computed(() => (isSuspend.value ? 'إيقاف نشاط المستورد' : 'تفعيل المستورد'))
const message = computed(() =>
  isSuspend.value
    ? `لن يظهر ${props.merchant.name} ضمن اختيارات الطلبات الجديدة بعد الإيقاف.`
    : `سيظهر ${props.merchant.name} مرة أخرى ضمن اختيارات الطلبات الجديدة.`,
)
const confirmLabel = computed(() =>
  props.submitting
    ? 'جارٍ تحديث حالة المستورد...'
    : isSuspend.value
      ? 'إيقاف نشاط المستورد'
      : 'تفعيل المستورد',
)

function requestClose() {
  if (!props.submitting) {
    emit('cancel')
  }
}
</script>

<template>
  <Dialog :open="true" @update:open="(open) => !open && requestClose()">
    <div class="fixed inset-0 z-50 flex items-center justify-center">
      <DialogContent
        class="flex max-w-md flex-col items-center gap-4 p-8 text-center"
        :aria-label="title"
      >
        <div
          class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full"
          :class="
            isSuspend
              ? 'bg-[var(--color-surface-warning)] text-[var(--color-text-warning)]'
              : 'bg-[var(--color-surface-success)] text-[var(--color-text-success)]'
          "
          aria-hidden="true"
        >
          <Pause v-if="isSuspend" :size="24" />
          <Play v-else :size="24" />
        </div>

        <h3 class="font-heading text-base leading-6 font-semibold text-[var(--color-text-primary)]">
          {{ title }}
        </h3>

        <p class="text-sm leading-relaxed text-[var(--color-text-subtle)]">
          {{ message }}
        </p>

        <Alert
          v-if="props.error"
          class="w-full border border-[var(--color-border-error)] bg-[var(--color-surface-error)]"
          role="alert"
        >
          <AlertCircle class="h-4 w-4 text-[var(--color-text-error)]" aria-hidden="true" />
          <AlertDescription class="text-sm text-[var(--color-text-error)]">{{
            props.error
          }}</AlertDescription>
        </Alert>

        <DialogFooter class="flex w-full gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            :disabled="props.submitting"
            class="flex-1"
            @click="requestClose"
          >
            إلغاء
          </Button>
          <Button
            type="button"
            :variant="isSuspend ? 'destructive' : 'default'"
            :disabled="props.submitting"
            class="flex-1"
            @click="emit('confirm')"
          >
            {{ confirmLabel }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </div>
  </Dialog>
</template>
