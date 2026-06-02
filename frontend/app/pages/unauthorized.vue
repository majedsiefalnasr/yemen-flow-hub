<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue'

definePageMeta({
  layout: false,
  middleware: ['guest'],
})

const route = useRoute()

const nextPath = computed(() => {
  const candidate = route.query.next
  if (typeof candidate !== 'string') return '/dashboard'
  if (!candidate.startsWith('/')) return '/dashboard'
  return candidate
})
</script>

<template>
  <main class="min-h-screen bg-background px-4" >
    <ErrorState
      :code="401"
      title="تسجيل الدخول مطلوب"
      description="سجل الدخول أولاً ثم ارجع إلى الصفحة المطلوبة."
      :actions="[
        {
          label: 'الانتقال إلى تسجيل الدخول',
          variant: 'default',
          onClick: () => navigateTo(`/login${nextPath && nextPath !== '/dashboard' ? `?next=${encodeURIComponent(nextPath)}` : ''}`),
        },
      ]"
    />
  </main>
</template>
