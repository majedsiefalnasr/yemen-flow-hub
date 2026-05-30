<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue'

definePageMeta({
  layout: false,
})

function reloadPage() {
  if (import.meta.client) {
    window.location.reload()
  }
}
</script>

<template>
  <main class="min-h-screen bg-background px-4" >
    <ErrorState
      :code="503"
      title="المنصة قيد الصيانة"
      description="نعمل على تحسين الخدمة حالياً. يرجى المحاولة مرة أخرى بعد قليل."
      :actions="[
        {
          label: 'إعادة المحاولة',
          variant: 'default',
          onClick: reloadPage,
        },
        {
          label: 'الذهاب إلى تسجيل الدخول',
          variant: 'outline',
          onClick: () => navigateTo('/login'),
        },
      ]"
    />
  </main>
</template>
