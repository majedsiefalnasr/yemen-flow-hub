<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { useForwardProps } from 'reka-ui'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useVueOTPContext } from 'vue-input-otp'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    index: number
    class?: HTMLAttributes['class']
    mask?: boolean
    maskCharacter?: string
    revealDurationMs?: number
  }>(),
  {
    mask: false,
    maskCharacter: '*',
    revealDurationMs: 300,
  },
)

const delegatedProps = reactiveOmit(props, 'class', 'mask', 'maskCharacter', 'revealDurationMs')

const forwarded = useForwardProps(delegatedProps)

const context = useVueOTPContext()

const slot = computed(() => context?.value.slots[props.index])
const isRevealed = ref(false)
let revealTimer: ReturnType<typeof setTimeout> | undefined

function clearRevealTimer() {
  if (revealTimer) {
    clearTimeout(revealTimer)
    revealTimer = undefined
  }
}

watch(
  () => slot.value?.char,
  (char, previousChar) => {
    clearRevealTimer()

    if (!props.mask || !char) {
      isRevealed.value = false
      return
    }

    isRevealed.value = char !== previousChar
    revealTimer = setTimeout(() => {
      isRevealed.value = false
      revealTimer = undefined
    }, props.revealDurationMs)
  },
)

const displayChar = computed(() => {
  const char = slot.value?.char
  if (!char) return ''
  if (!props.mask || isRevealed.value) return char
  return props.maskCharacter
})

onBeforeUnmount(clearRevealTimer)
</script>

<template>
  <div
    v-bind="forwarded"
    data-slot="input-otp-slot"
    :data-active="slot?.isActive"
    :class="
      cn(
        'dark:bg-input/30 border-input data-[active=true]:border-ring data-[active=true]:ring-ring/50 data-[active=true]:aria-invalid:ring-destructive/20 dark:data-[active=true]:aria-invalid:ring-destructive/40 aria-invalid:border-destructive data-[active=true]:aria-invalid:border-destructive relative flex size-8 items-center justify-center border-y border-e text-sm transition-all outline-none first:rounded-s-lg first:border-s last:rounded-e-lg data-[active=true]:z-10 data-[active=true]:ring-3',
        props.class,
      )
    "
  >
    {{ displayChar }}
    <div
      v-if="slot?.hasFakeCaret"
      class="pointer-events-none absolute inset-0 flex items-center justify-center"
    >
      <div class="animate-caret-blink bg-foreground h-4 w-px duration-1000" />
    </div>
  </div>
</template>
