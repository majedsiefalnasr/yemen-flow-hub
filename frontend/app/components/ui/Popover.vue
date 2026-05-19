<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  open?: boolean
}>(), {
  open: false,
})

const emit = defineEmits<{
  'update:open': [boolean]
}>()

const triggerRef = ref<HTMLElement | null>(null)
const contentRef = ref<HTMLElement | null>(null)
const top = ref(0)
const left = ref(0)
const minWidth = ref(260)

const isOpen = computed(() => props.open === true)

function close() {
  emit('update:open', false)
}

function updatePosition() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  top.value = rect.bottom + 8
  left.value = rect.right - Math.max(rect.width, 320)
  minWidth.value = Math.max(rect.width, 260)
}

function onDocClick(event: MouseEvent) {
  if (!isOpen.value) return
  const target = event.target as Node
  if (contentRef.value?.contains(target) || triggerRef.value?.contains(target)) return
  close()
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) close()
}

watch(isOpen, (value) => {
  if (value) updatePosition()
})

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', updatePosition)
  window.addEventListener('scroll', updatePosition, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', updatePosition, true)
})
</script>

<template>
  <div ref="triggerRef" class="popover-trigger">
    <slot name="trigger" />
  </div>
  <Teleport to="body">
    <div
      v-if="isOpen"
      ref="contentRef"
      class="popover-content"
      :style="{ top: `${top}px`, left: `${left}px`, minWidth: `${minWidth}px` }"
      role="dialog"
      aria-modal="false"
    >
      <slot />
    </div>
  </Teleport>
</template>

<style scoped>
.popover-trigger {
  display: inline-flex;
}

.popover-content {
  position: fixed;
  z-index: 60;
  border: 1px solid var(--color-outline-variant);
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow: 0 4px 16px rgba(29, 29, 31, 0.08);
}
</style>
