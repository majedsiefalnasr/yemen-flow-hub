<script setup lang="ts">
import { UserRole } from '../../types/enums'
import RequestWizard from '../../components/wizard/RequestWizard.vue'

definePageMeta({
  middleware: ['auth'],
  requiredRoles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN],
})
</script>

<template>
  <div class="new-request-page" dir="rtl">
    <RequestWizard />
  </div>
</template>

<style scoped>
.new-request-page {
  padding: 24px;
}
</style>
