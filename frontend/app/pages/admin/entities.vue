<script setup lang="ts">
import { UserRole } from '../../types/enums'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.CBY_ADMIN],
})

await navigateTo('/banks', { replace: true })
</script>

<template>
  <div />
</template>
