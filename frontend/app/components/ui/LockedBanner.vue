<script setup lang="ts">
import { computed } from 'vue'
import { RequestStatus } from '../../types/enums'

const props = defineProps<{
  status: RequestStatus
}>()

const message = computed(() => {
  switch (props.status) {
    case RequestStatus.SUBMITTED:
    case RequestStatus.BANK_REVIEW:
      return 'هذا الطلب قيد المراجعة من قِبل البنك ولا يمكن تعديله.'
    case RequestStatus.BANK_APPROVED:
    case RequestStatus.SUPPORT_REVIEW_PENDING:
    case RequestStatus.SUPPORT_REVIEW_IN_PROGRESS:
    case RequestStatus.WAITING_FOR_SWIFT:
      return 'هذا الطلب قيد المعالجة من قِبل CBY ولا يمكن تعديله.'
    case RequestStatus.SUPPORT_APPROVED:
    case RequestStatus.SWIFT_UPLOADED:
    case RequestStatus.WAITING_FOR_VOTING_OPEN:
    case RequestStatus.EXECUTIVE_VOTING_OPEN:
    case RequestStatus.EXECUTIVE_VOTING_CLOSED:
      return 'هذا الطلب في مرحلة التصويت التنفيذي ولا يمكن تعديله.'
    case RequestStatus.EXECUTIVE_APPROVED:
    case RequestStatus.CUSTOMS_DECLARATION_ISSUED:
    case RequestStatus.COMPLETED:
      return 'تم اعتماد هذا الطلب ولا يمكن تعديله.'
    case RequestStatus.SUPPORT_REJECTED:
    case RequestStatus.EXECUTIVE_REJECTED:
      return 'تم رفض هذا الطلب نهائياً ولا يمكن تعديله.'
    default:
      return 'هذا الطلب مقفل ولا يمكن تعديله.'
  }
})
</script>

<template>
  <div class="locked-banner" role="alert" aria-live="polite" dir="rtl">
    <span class="locked-icon" aria-hidden="true">🔒</span>
    <span class="locked-message">{{ message }}</span>
  </div>
</template>

<style scoped>
.locked-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f5f5f7;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  color: #8e8e93;
  font-size: 15px;
  font-weight: 500;
}

.locked-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.locked-message {
  flex: 1;
}
</style>
