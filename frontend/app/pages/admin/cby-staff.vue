<script setup lang="ts">
import IdentityUsersPage from '@/components/admin/IdentityUsersPage.vue'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'users' })
</script>

<template>
  <IdentityUsersPage audience="committee" />
</template>
