<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
})

await navigateTo('/admin/banks', { redirectCode: 301, replace: true })
</script>

<template>
  <div class="sr-only" aria-hidden="true">Redirecting to /admin/banks</div>
</template>
