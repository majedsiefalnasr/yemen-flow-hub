<script setup lang="ts">
import { useAuthStore } from '../stores/auth.store'

// Root route redirects authenticated users to the dashboard, guests to login.
// Auth hydration already completed in plugins/01.auth.client.ts before this runs.
const auth = useAuthStore()
await navigateTo(auth.isAuthenticated ? '/dashboard' : '/login', { replace: true })
</script>

<template>
  <span />
</template>
