<script setup lang="ts">
import { useInactivityTimer } from '~/composables/useInactivityTimer'
import InactivityBanner from '~/components/layout/InactivityBanner.vue'
import { TooltipProvider } from '@/components/ui/tooltip'

const { isWarning, extend } = useInactivityTimer()
</script>

<template>
  <!-- Single app-wide tooltip context: shared open delay + skip-delay so help
       tooltips behave consistently across every authenticated surface. -->
  <TooltipProvider :delay-duration="300" :skip-delay-duration="150">
    <InactivityBanner :visible="isWarning" @extend="extend" />
    <slot />
  </TooltipProvider>
</template>
