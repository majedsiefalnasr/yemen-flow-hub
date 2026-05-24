<script lang="ts" setup>
import type { RangeCalendarCellTriggerProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { RangeCalendarCellTrigger, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

const props = withDefaults(defineProps<RangeCalendarCellTriggerProps & { class?: HTMLAttributes['class'] }>(), {
  as: 'button',
})

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <RangeCalendarCellTrigger
    data-slot="range-calendar-trigger"
    :class="cn(
      buttonVariants({ variant: 'ghost' }),
      'h-8 w-8 p-0 font-normal data-[selected]:opacity-100',
      '[&[data-today]:not([data-selected])]:bg-accent [&[data-today]:not([data-selected])]:text-accent-foreground',
      // Selection Start
      'data-[selection-start]:bg-blue-600 data-[selection-start]:text-blue-600-foreground data-[selection-start]:hover:bg-blue-600 data-[selection-start]:hover:text-blue-600-foreground data-[selection-start]:focus:bg-blue-600 data-[selection-start]:focus:text-blue-600-foreground',
      // Selection End
      'data-[selection-end]:bg-blue-600 data-[selection-end]:text-blue-600-foreground data-[selection-end]:hover:bg-blue-600 data-[selection-end]:hover:text-blue-600-foreground data-[selection-end]:focus:bg-blue-600 data-[selection-end]:focus:text-blue-600-foreground',
      // Outside months
      'data-[outside-view]:text-gray-600',
      // Disabled
      'data-[disabled]:text-gray-600 data-[disabled]:opacity-50',
      // Unavailable
      'data-[unavailable]:text-red-700-foreground data-[unavailable]:line-through',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot />
  </RangeCalendarCellTrigger>
</template>
