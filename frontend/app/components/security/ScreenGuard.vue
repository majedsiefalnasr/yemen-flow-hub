<script setup lang="ts">
import type { ScreenCapability } from '@/types/models'
import { useScreenPermissions } from '@/composables/useScreenPermissions'

const props = withDefaults(
  defineProps<{
    screen: string
    capability?: ScreenCapability
  }>(),
  {
    capability: 'VIEW',
  },
)

const { can } = useScreenPermissions()
</script>

<template>
  <slot v-if="can(props.screen, props.capability)" />
</template>
