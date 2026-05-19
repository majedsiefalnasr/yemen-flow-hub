<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  open?: boolean
}>(), {
  open: false,
})

const emit = defineEmits<{
  'update:open': [boolean]
}>()

const triggerRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const top = ref(0)
const left = ref(0)
const panelWidth = ref(220)

const isOpen = computed(() => props.open === true)

function close() {
  emit('update:open', false)
}

function updatePosition() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const viewportPadding = 8
  const availableWidth = Math.max(window.innerWidth - viewportPadding * 2, 0)
  panelWidth.value = Math.min(220, availableWidth)
  const desiredLeft = rect.right - panelWidth.value
  left.value = Math.max(
    viewportPadding,
    Math.min(desiredLeft, window.innerWidth - panelWidth.value - viewportPadding),
  )

  const menuHeight = menuRef.value?.offsetHeight ?? 200
  const preferredTop = rect.bottom + 8
  const fallbackTop = rect.top - menuHeight - 8
  const placedTop = (preferredTop + menuHeight + viewportPadding <= window.innerHeight)
    ? preferredTop
    : fallbackTop
  top.value = Math.max(
    viewportPadding,
    Math.min(placedTop, window.innerHeight - menuHeight - viewportPadding),
  )
}

function onDocClick(event: MouseEvent) {
  if (!isOpen.value) return
  const target = event.target as Node
  if (menuRef.value?.contains(target) || triggerRef.value?.contains(target)) return
  close()
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) close()
}

watch(isOpen, async (value) => {
  if (!value) return
  await nextTick()
  updatePosition()
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
  <div ref="triggerRef" class="dropdown-trigger">
    <slot name="trigger" />
  </div>
  <Teleport to="body">
    <div
      v-if="isOpen"
      ref="menuRef"
      class="dropdown-content"
      :style="{ top: `${top}px`, left: `${left}px`, width: `${panelWidth}px`, maxWidth: `${panelWidth}px` }"
      role="menu"
    >
      <slot />
    </div>
  </Teleport>
</template>

<style scoped>
.dropdown-trigger {
  display: inline-flex;
}

.dropdown-content {
  position: fixed;
  z-index: 60;
  border: 1px solid var(--color-outline-variant);
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow: 0 4px 16px rgba(29, 29, 31, 0.08);
  padding: 6px;
}
</style>
