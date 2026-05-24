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
const title = computed(() => isSuspend.value ? 'تأكيد تعليق التاجر' : 'تأكيد تفعيل التاجر')
const message = computed(() =>
  isSuspend.value
    ? `هل أنت متأكد من تعليق التاجر ${props.merchant.name}؟ لن يتمكن المستخدمون من اختياره في الطلبات الجديدة.`
    : `هل أنت متأكد من تفعيل التاجر ${props.merchant.name}؟`
)
const confirmLabel = computed(() => props.submitting ? 'جارٍ التحديث…' : (isSuspend.value ? 'تعليق' : 'تفعيل'))

function requestClose() {
  if (!props.submitting) {
    emit('cancel')
  }
}
</script>

<template>
  <Dialog :open="true" @update:open="open => !open && requestClose()">
    <div class="fixed inset-0 flex items-center justify-center z-50">
      <DialogContent
        class="max-w-md flex flex-col items-center text-center gap-4 p-8"
        dir="rtl"
        :aria-label="title"
      >
        <div
          class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0"
          :class="isSuspend ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success'"
          aria-hidden="true"
        >
          <Pause v-if="isSuspend" :size="24" />
          <Play v-else :size="24" />
        </div>

        <h3 class="text-lg font-semibold text-foreground">
          {{ title }}
        </h3>

        <p class="text-sm text-muted-foreground leading-relaxed">
          {{ message }}
        </p>

        <Alert v-if="props.error" class="border-l-4 border-l-red-600 bg-destructive/10 border-0 w-full" role="alert">
          <AlertCircle class="h-4 w-4 text-destructive" aria-hidden="true" />
          <AlertDescription class="text-destructive text-sm">{{ props.error }}</AlertDescription>
        </Alert>

        <DialogFooter class="flex gap-3 w-full pt-2">
          <Button type="button" variant="outline" :disabled="props.submitting" class="flex-1" @click="requestClose">
            إلغاء
          </Button>
          <Button
            type="button"
            :disabled="props.submitting"
            class="flex-1"
            :class="isSuspend ? 'bg-destructive hover:opacity-90 text-white' : 'bg-success hover:bg-success text-white'"
            @click="emit('confirm')"
          >
            {{ confirmLabel }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </div>
  </Dialog>
</template>
