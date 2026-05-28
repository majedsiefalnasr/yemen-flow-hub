<script setup lang="ts">
import { useInactivityTimer } from '~/composables/useInactivityTimer'
import InactivityBanner from '~/components/layout/InactivityBanner.vue'

const { isWarning, extend } = useInactivityTimer()
</script>

<template>
  <InactivityBanner :visible="isWarning" @extend="extend" />
  <slot />
</template>
