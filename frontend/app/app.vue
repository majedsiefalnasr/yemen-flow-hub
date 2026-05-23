<script setup lang="ts">
import AppShell from '@/components/layout/AppShell.vue'
import { Toaster } from 'vue-sonner'
import { ConfigProvider } from 'reka-ui'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/stores/auth.store'

const route = useRoute()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { isDark, initTheme } = useTheme()

// Initialize theme from localStorage/system preference
onBeforeMount(() => {
  initTheme()
})

useHead({
  htmlAttrs: {
    lang: 'ar',
    dir: 'rtl',
    class: computed(() => isDark.value ? 'dark' : ''),
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
