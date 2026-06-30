<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })
const router = useRouter()
onMounted(() => router.replace('/settings'))
</script>
<template><div /></template>
