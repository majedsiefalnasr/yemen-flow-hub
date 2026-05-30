<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue'

const props = defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    message?: string
  }
}>()

const router = useRouter()

function reload() {
  if (import.meta.client) {
    window.location.reload()
  }
}

function goHome() {
  clearError({ redirect: '/dashboard' })
}

function goBack() {
  router.back()
}

const actions = computed(() => {
  const code = props.error.statusCode ?? 500
  const items: Array<{
    label: string
    variant: 'default' | 'outline' | 'ghost' | 'destructive'
    onClick: () => void
  }> = []

  if (code !== 401) {
    items.push({ label: 'العودة إلى لوحة التحكم', variant: 'default', onClick: goHome })
  }
  if (code === 500 || code === 503) {
    items.push({ label: 'إعادة المحاولة', variant: 'outline', onClick: reload })
  }
  if (code !== 401) {
    items.push({ label: 'العودة للخلف', variant: 'ghost', onClick: goBack })
  }
  if (code === 401) {
    items.push({ label: 'تسجيل الدخول', variant: 'default', onClick: () => clearError({ redirect: '/login' }) })
  }
  return items
})
</script>

<template>
  <main class="min-h-screen bg-background">
    <ErrorState
      :code="error.statusCode"
      :actions="actions"
    />
  </main>
</template>
