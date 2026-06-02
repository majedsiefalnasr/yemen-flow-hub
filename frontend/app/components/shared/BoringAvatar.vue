<script setup lang="ts">
import Avatar from 'vue-boring-avatars'
import { computed } from 'vue'
import {
  AVATAR_PALETTE,
  DEFAULT_AVATAR_VARIANT,
  readUserAvatar,
  type AvatarVariant,
} from '@/composables/useUserAvatar'

const props = withDefaults(defineProps<{
  /** Seed for the generator. Use a stable identifier (email, id, or full name). */
  name: string
  /**
   * Explicit variant override. When omitted, the variant is resolved from the
   * persisted preference keyed by `identity` (or by `name` as a fallback).
   */
  variant?: AvatarVariant
  /**
   * Identity used to look up the persisted preference. Defaults to `name` so
   * the component is usable with a single prop for casual list rendering.
   */
  identity?: string
  /** Render size in px. */
  size?: number
  /** Whether to render square (true) or circular (false, default). */
  square?: boolean
}>(), {
  size: 40,
  square: false,
})

const resolvedVariant = computed<AvatarVariant>(() => {
  if (props.variant) return props.variant
  const id = props.identity ?? props.name
  return id ? readUserAvatar(id).variant : DEFAULT_AVATAR_VARIANT
})

const palette = [...AVATAR_PALETTE]
</script>

<template>
  <Avatar
    :variant="resolvedVariant"
    :name="name"
    :size="size"
    :square="square"
    :colors="palette"
    :title="false"
  />
</template>
