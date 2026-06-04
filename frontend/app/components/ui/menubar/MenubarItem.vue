<script setup lang="ts">
import type { MenubarItemEmits, MenubarItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { MenubarItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<
  MenubarItemProps & {
    class?: HTMLAttributes['class']
    inset?: boolean
    variant?: 'default' | 'destructive'
  }
>()

const emits = defineEmits<MenubarItemEmits>()

const delegatedProps = reactiveOmit(props, 'class', 'inset', 'variant')
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <MenubarItem
    data-slot="menubar-item"
    :data-inset="inset ? '' : undefined"
    :data-variant="variant"
    v-bind="forwarded"
    :class="
      cn(
        'focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 not-data-[variant=destructive]:focus:**:text-accent-foreground group/menubar-item relative flex cursor-default items-center gap-1.5 rounded-md px-1.5 py-1 text-sm outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-inset:ps-7 data-[variant=destructive]:text-red-700 data-[variant=destructive]:focus:text-red-700 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4 data-[variant=destructive]:*:[svg]:text-red-700!',
        props.class,
      )
    "
  >
    <slot />
  </MenubarItem>
</template>
