<script setup lang="ts">
import AppShell from '@/components/layout/AppShell.vue'
import { Toaster } from 'vue-sonner'
import { ConfigProvider } from 'reka-ui'
import { useAuthStore } from '@/stores/auth.store'
import { useThemingStore } from '@/stores/theming.store'

const route = useRoute()
const authStore = useAuthStore()
const themingStore = useThemingStore()
const user = computed(() => authStore.user)
let mediaQuery: MediaQueryList | null = null
const applySystemTheme = () => {
  if (themingStore.mode === 'system') {
    themingStore.applyTheme()
  }
}

// Initialize persisted user/system theme before the app shell renders.
onBeforeMount(() => {
  themingStore.loadSettings()
})

onMounted(() => {
  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', applySystemTheme)
})

onUnmounted(() => {
  mediaQuery?.removeEventListener('change', applySystemTheme)
})

useHead({
  htmlAttrs: {
    lang: 'ar',
    dir: 'rtl',
    class: computed(() => themingStore.isDark ? 'dark' : ''),
  },
  titleTemplate: (titleChunk) => titleChunk
    ? `${titleChunk} — منصة إدارة وتمويل الواردات`
    : 'منصة إدارة وتمويل الواردات — البنك المركزي اليمني',
  meta: [
    {
      name: 'viewport',
      content: 'width=device-width, initial-scale=1',
    },
    {
      name: 'description',
      content: 'منصة رقمية لإدارة ومراجعة طلبات تمويل الواردات للبنك المركزي اليمني',
    },
  ],
})

const showShell = computed(() => route.path !== '/login' && Boolean(user.value))
</script>

<template>
  <ConfigProvider dir="rtl">
    <NuxtLoadingIndicator color="var(--primary)" />
    <NuxtRouteAnnouncer />
    <Toaster />

    <AppShell v-if="showShell">
      <NuxtPage />
    </AppShell>
    <NuxtPage v-else />
  </ConfigProvider>
</template>
