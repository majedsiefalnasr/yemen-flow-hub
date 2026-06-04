<script setup lang="ts">
const route = useRoute()
await navigateTo({ path: '/organization', query: route.query }, { replace: true })
</script>
<template>
  <div />
</template>
