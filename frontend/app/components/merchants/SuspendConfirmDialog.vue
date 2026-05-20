<script setup lang="ts">
import { Pause, Play } from 'lucide-vue-next'
import type { Merchant } from '../../types/models'

const props = defineProps<{
  merchant: Merchant
  submitting: boolean
  error: string | null
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

function requestClose() {
  if (!props.submitting) {
    emit('cancel')
  }
}
</script>

<template>
  <div class="dialog-backdrop" role="alertdialog" aria-modal="true" :aria-label="props.merchant.is_active ? 'تأكيد تعليق التاجر' : 'تأكيد تفعيل التاجر'" @click.self="requestClose">
    <div class="dialog" dir="rtl">
      <div class="dialog-icon" :class="props.merchant.is_active ? 'icon-suspend' : 'icon-activate'" aria-hidden="true">
        <Pause v-if="props.merchant.is_active" :size="24" />
        <Play v-else :size="24" />
      </div>

      <h3 class="dialog-title">
        {{ props.merchant.is_active ? 'تأكيد تعليق التاجر' : 'تأكيد تفعيل التاجر' }}
      </h3>

      <p class="dialog-message">
        <template v-if="props.merchant.is_active">
          هل أنت متأكد من تعليق التاجر <strong>{{ props.merchant.name }}</strong>؟ لن يتمكن المستخدمون من اختياره في الطلبات الجديدة.
        </template>
        <template v-else>
          هل أنت متأكد من تفعيل التاجر <strong>{{ props.merchant.name }}</strong>؟
        </template>
      </p>

      <div v-if="props.error" class="dialog-error" role="alert">
        {{ props.error }}
      </div>

      <div class="dialog-actions">
        <button class="btn-cancel" :disabled="props.submitting" @click="requestClose">إلغاء</button>
        <button
          :class="['btn-confirm', props.merchant.is_active ? 'btn-suspend' : 'btn-activate']"
          :disabled="props.submitting"
          @click="emit('confirm')"
        >
          {{ props.submitting ? 'جارٍ التحديث…' : (props.merchant.is_active ? 'تعليق' : 'تفعيل') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.dialog {
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 400px;
  max-width: 90vw;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.dialog-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.icon-suspend {
  background: #fff3e0;
  color: #f57f17;
}

.icon-activate {
  background: #e8f5e9;
  color: #1b5e20;
}

.dialog-title {
  font-size: 18px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.dialog-message {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  line-height: 1.6;
}

.dialog-actions {
  display: flex;
  gap: 12px;
  width: 100%;
}

.dialog-error {
  width: 100%;
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 12px;
  padding: 10px 14px;
  color: #c62828;
  font-size: 13px;
}

.btn-cancel {
  flex: 1;
  height: 44px;
  background: transparent;
  color: #1c222b;
  border: 1px solid #cccccc;
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}

.btn-cancel:disabled,
.btn-confirm:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-confirm {
  flex: 1;
  height: 44px;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-suspend {
  background: #c62828;
  color: #ffffff;
}

.btn-activate {
  background: #1b5e20;
  color: #ffffff;
}
</style>
