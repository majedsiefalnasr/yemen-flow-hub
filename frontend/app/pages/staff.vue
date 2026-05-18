<script setup lang="ts">
import { UserRole } from '../types/enums'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.BANK_ADMIN],
})

await navigateTo('/users', { replace: true })
</script>

<template>
  <div />
</template>
