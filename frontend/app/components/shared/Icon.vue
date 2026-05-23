<script setup lang="ts">
import { computed } from 'vue'
import { ICONS } from '../../utils/icon-map'
import type { IconName } from '../../utils/icon-map'

const props = withDefaults(defineProps<{
  name: IconName
  size?: number
}>(), {
  size: 18,
})

const component = computed(() => ICONS[props.name])
</script>

<template>
  <component :is="component" :size="size" :aria-hidden="true" />
</template>
