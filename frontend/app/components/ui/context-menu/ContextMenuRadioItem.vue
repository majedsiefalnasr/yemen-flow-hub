<script setup lang="ts">
import type { ContextMenuRadioItemEmits, ContextMenuRadioItemProps } from 'reka-ui'

import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { CheckIcon } from 'lucide-vue-next'
import { ContextMenuItemIndicator, ContextMenuRadioItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ContextMenuRadioItemProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<ContextMenuRadioItemEmits>()

const delegatedProps = reactiveOmit(props, 'class')

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ContextMenuRadioItem
    data-slot="context-menu-radio-item"
    v-bind="forwarded"
    :class="
      cn(
        'focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-1.5 rounded-md py-1 ps-1.5 pe-8 text-sm outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-inset:ps-7 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
        props.class,
      )
    "
  >
    <span class="pointer-events-none absolute end-2">
      <ContextMenuItemIndicator>
        <slot name="indicator-icon">
          <CheckIcon />
        </slot>
      </ContextMenuItemIndicator>
    </span>
    <slot />
  </ContextMenuRadioItem>
</template>
