<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue'

definePageMeta({
  layout: false,
  middleware: ['auth'],
})

const route = useRoute()

const blockedPath = computed(() => {
  const candidate = route.query.path
  if (typeof candidate !== 'string') return null
  return candidate
})
</script>

<template>
  <main class="bg-background min-h-screen px-4">
    <ErrorState
      :code="403"
      title="ليس لديك صلاحية لهذا المسار"
      :description="
        blockedPath
          ? `ليس لديك صلاحية للوصول إلى ${blockedPath}. إذا كنت ترى أن ذلك خطأ، تواصل مع مسؤول النظام.`
          : 'ليس لديك صلاحية للوصول إلى هذه الصفحة. إذا كنت ترى أن ذلك خطأ، تواصل مع مسؤول النظام.'
      "
      :actions="[
        {
          label: 'العودة إلى لوحة التحكم',
          variant: 'default',
          onClick: () => navigateTo('/dashboard'),
        },
      ]"
    />
  </main>
</template>
