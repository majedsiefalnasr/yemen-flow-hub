<script setup lang="ts">
import { useAuthStore } from './stores/auth.store'

const auth = useAuthStore()

// Hydrate auth state on app load — 401 is caught silently inside fetchUser()
onMounted(async () => {
  await auth.fetchUser()
})
</script>

<template>
  <Html dir="rtl" lang="ar">
    <Head>
      <Title>Yemen Flow Hub</Title>
      <Meta charset="utf-8" />
      <Meta name="viewport" content="width=device-width, initial-scale=1" />
    </Head>
    <Body>
      <NuxtLayout>
        <NuxtPage />
      </NuxtLayout>
    </Body>
  </Html>
</template>
