<script setup lang="ts">
import Avatar from 'vue-boring-avatars'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import {
  AVATAR_PALETTE,
  AVATAR_VARIANTS,
  DEFAULT_AVATAR_VARIANT,
  type AvatarVariant,
} from '@/composables/useUserAvatar'

const props = withDefaults(defineProps<{
  /** Currently selected variant. */
  modelValue?: AvatarVariant
  /**
   * Stable seed used to generate every preview swatch. Pass the user's email,
   * id, or full name. When empty the picker falls back to a neutral seed so
   * the swatches still render before the form has been filled in.
   */
  seed?: string
  /** Avatar size in px for each option in the strip. */
  size?: number
  /** Optional descriptive heading shown above the strip. */
  label?: string
  /** Optional helper text shown under the heading. */
  description?: string
  /** Hide the heading row entirely. */
  hideLabel?: boolean
  disabled?: boolean
}>(), {
  size: 56,
  hideLabel: false,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: AvatarVariant]
}>()

const VARIANT_LABELS: Record<AvatarVariant, string> = {
  marble: 'رخامي',
  beam: 'وجه',
  pixel: 'بكسل',
  sunset: 'غروب',
  ring: 'حلقات',
  bauhaus: 'باوهاوس',
}

const selectedVariant = computed<AvatarVariant>(() => props.modelValue ?? DEFAULT_AVATAR_VARIANT)
const generatorSeed = computed(() => (props.seed?.trim() ? props.seed : 'Yemen Flow Hub'))
const palette = [...AVATAR_PALETTE]

function selectVariant(variant: AvatarVariant) {
  if (props.disabled) return
  emit('update:modelValue', variant)
}
</script>

<template>
  <div class="space-y-3" data-testid="avatar-picker">
    <div v-if="!hideLabel">
      <p class="text-sm font-medium">
        {{ label ?? 'مظهر الصورة الرمزية' }}
      </p>
      <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">
        {{ description }}
      </p>
    </div>

    <div
      class="flex flex-wrap items-center gap-3"
      role="radiogroup"
      aria-label="اختيار مظهر الصورة الرمزية"
    >
      <button
        v-for="variant in AVATAR_VARIANTS"
        :key="variant"
        type="button"
        role="radio"
        :aria-checked="selectedVariant === variant"
        :aria-label="VARIANT_LABELS[variant]"
        :title="VARIANT_LABELS[variant]"
        :data-variant="variant"
        :disabled="disabled"
        :class="cn(
          'group relative grid place-items-center rounded-full transition-all overflow-hidden cursor-pointer',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background',
          'disabled:cursor-not-allowed disabled:opacity-50',
          selectedVariant === variant
            ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
            : 'ring-1 ring-border hover:ring-foreground/30',
        )"
        :style="{ width: `${size}px`, height: `${size}px` }"
        @click="selectVariant(variant)"
      >
        <Avatar
          :variant="variant"
          :name="generatorSeed"
          :size="size"
          :colors="palette"
          :title="false"
        />
      </button>
    </div>
  </div>
</template>
