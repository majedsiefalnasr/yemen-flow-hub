<script setup lang="ts">
import AppShell from '@/components/layout/AppShell.vue'
import 'vue-sonner/style.css'
import { Toaster } from '@/components/ui/sonner'
import { ConfigProvider } from 'reka-ui'
import { useAuthStore } from '@/stores/auth.store'
import { DEFAULT_BRAND_LOGO_URL, useOrgStore } from '@/stores/org.store'
import { useThemingStore } from '@/stores/theming.store'
import DemoUserSwitcherButton from '@/components/auth/DemoUserSwitcherButton.vue'

const route = useRoute()
const authStore = useAuthStore()
const orgStore = useOrgStore()
const themingStore = useThemingStore()
const user = computed(() => authStore.user)
const appDir = computed<'rtl' | 'ltr'>(() => (authStore.preferredLanguage === 'en' ? 'ltr' : 'rtl'))
const toasterPosition = 'top-center'
let mediaQuery: MediaQueryList | null = null
const applySystemTheme = () => {
  if (themingStore.mode === 'system') {
    themingStore.applyTheme()
  }
}
const platformName = computed(
  () => orgStore.platformName.trim() || 'اللجنة الوطنية لتنظيم وتمويل الواردات',
)
const authorityName = computed(
  () => orgStore.authority.trim() || 'اللجنة الوطنية لتنظيم وتمويل الواردات',
)
const fullAppTitle = computed(() =>
  platformName.value === authorityName.value
    ? platformName.value
    : `${platformName.value}، ${authorityName.value}`,
)
const seoDescription = computed(
  () =>
    `${platformName.value} منصة مؤسسية لإدارة ومراجعة طلبات تمويل الواردات لدى ${authorityName.value}.`,
)
const faviconHref = computed(() => orgStore.brandLogoDataUrl || DEFAULT_BRAND_LOGO_URL)
const faviconType = computed(() => {
  const href = faviconHref.value
  if (href.startsWith('data:image/png')) return 'image/png'
  if (href.startsWith('data:image/jpeg')) return 'image/jpeg'
  if (href.endsWith('.png')) return 'image/png'
  if (href.endsWith('.jpg') || href.endsWith('.jpeg')) return 'image/jpeg'
  return 'image/svg+xml'
})

// Initialize persisted user/system theme before the app shell renders.
onBeforeMount(() => {
  themingStore.loadSettings()
})

// On a new device loadSettings() runs before login (no auth hint) so it
// falls back to /api/settings/public with no user preferences. Re-run once
// the user becomes authenticated — covers both new devices (isAuthenticated
// transitions false→true after login) and saved devices that reach the watch
// with an expired session that is then refreshed via PIN login.
watch(
  () => authStore.isAuthenticated,
  (authenticated) => {
    if (authenticated) {
      themingStore.loadSettings()
    }
  },
)

// For saved devices whose session was still valid when the app loaded, the
// auth plugin set isAuthenticated=true before this watch was registered, so
// the watch above misses that initial transition. Drive a fresh server load
// here once the component has mounted and the plugin is guaranteed done.
onMounted(() => {
  if (authStore.isAuthenticated) {
    themingStore.loadSettings()
  }
})

onMounted(() => {
  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', applySystemTheme)
})

onUnmounted(() => {
  mediaQuery?.removeEventListener('change', applySystemTheme)
})

useHead(() => ({
  htmlAttrs: {
    lang: authStore.preferredLanguage === 'en' ? 'en' : 'ar',
    dir: appDir.value,
    class: themingStore.isDark ? 'dark' : '',
  },
  titleTemplate: (titleChunk) =>
    titleChunk ? `${titleChunk}، ${platformName.value}` : fullAppTitle.value,
  link: [
    {
      key: 'favicon',
      rel: 'icon',
      type: faviconType.value,
      href: faviconHref.value,
    },
    {
      key: 'shortcut-icon',
      rel: 'shortcut icon',
      type: faviconType.value,
      href: faviconHref.value,
    },
  ],
  meta: [
    {
      name: 'viewport',
      content: 'width=device-width, initial-scale=1',
    },
    {
      name: 'description',
      content: seoDescription.value,
    },
    {
      name: 'application-name',
      content: platformName.value,
    },
    {
      name: 'apple-mobile-web-app-title',
      content: platformName.value,
    },
    {
      name: 'theme-color',
      content: themingStore.brandColor,
    },
    {
      property: 'og:site_name',
      content: platformName.value,
    },
    {
      property: 'og:title',
      content: fullAppTitle.value,
    },
    {
      property: 'og:description',
      content: seoDescription.value,
    },
    {
      property: 'og:type',
      content: 'website',
    },
    {
      property: 'og:image',
      content: faviconHref.value,
    },
    {
      name: 'twitter:card',
      content: 'summary',
    },
    {
      name: 'twitter:title',
      content: fullAppTitle.value,
    },
    {
      name: 'twitter:description',
      content: seoDescription.value,
    },
    {
      name: 'twitter:image',
      content: faviconHref.value,
    },
  ],
}))

const showShell = computed(() => route.path !== '/login' && Boolean(user.value))
</script>

<template>
  <ConfigProvider :dir="appDir">
    <NuxtLoadingIndicator color="var(--primary)" />
    <NuxtRouteAnnouncer />
    <Toaster :position="toasterPosition" rich-colors />
    <DemoUserSwitcherButton />

    <AppShell v-if="showShell">
      <NuxtLayout>
        <NuxtPage />
      </NuxtLayout>
    </AppShell>
    <NuxtLayout v-else>
      <NuxtPage />
    </NuxtLayout>
  </ConfigProvider>
</template>
