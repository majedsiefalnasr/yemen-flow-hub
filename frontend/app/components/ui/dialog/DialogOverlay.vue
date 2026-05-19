<script setup lang="ts">
defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <div class="dialog-overlay" @click="$emit('click', $event)">
    <slot />
  </div>
</template>
