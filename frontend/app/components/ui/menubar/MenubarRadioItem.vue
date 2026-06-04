<script setup lang="ts">
import type { MenubarRadioItemEmits, MenubarRadioItemProps } from 'reka-ui'

import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { CheckIcon } from 'lucide-vue-next'
import { MenubarItemIndicator, MenubarRadioItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<MenubarRadioItemProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<MenubarRadioItemEmits>()

const delegatedProps = reactiveOmit(props, 'class')

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <MenubarRadioItem
    data-slot="menubar-radio-item"
    v-bind="forwarded"
    :class="
      cn(
        'focus:bg-accent focus:text-accent-foreground focus:**:text-accent-foreground relative flex cursor-default items-center gap-1.5 rounded-md py-1 ps-7 pe-1.5 text-sm outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-inset:ps-7 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
        props.class,
      )
    "
  >
    <span
      class="pointer-events-none absolute start-1.5 flex size-4 items-center justify-center [&_svg:not([class*=size-])]:size-4"
    >
      <MenubarItemIndicator>
        <slot name="indicator-icon">
          <CheckIcon />
        </slot>
      </MenubarItemIndicator>
    </span>
    <slot />
  </MenubarRadioItem>
</template>
