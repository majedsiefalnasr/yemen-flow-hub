<script lang="ts" setup>
import type { CalendarCellTriggerProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { CalendarCellTrigger, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

const props = withDefaults(defineProps<CalendarCellTriggerProps & { class?: HTMLAttributes['class'] }>(), {
  as: 'button',
})

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <CalendarCellTrigger
    data-slot="calendar-cell-trigger"
    :class="cn(
      buttonVariants({ variant: 'ghost' }),
      'size-8 p-0 font-normal aria-selected:opacity-100 cursor-default',
      '[&[data-today]:not([data-selected])]:bg-accent [&[data-today]:not([data-selected])]:text-accent-foreground',
      // Selected
      'data-[selected]:bg-blue-600 data-[selected]:text-blue-600-foreground data-[selected]:opacity-100 data-[selected]:hover:bg-blue-600 data-[selected]:hover:text-blue-600-foreground data-[selected]:focus:bg-blue-600 data-[selected]:focus:text-blue-600-foreground',
      // Disabled
      'data-[disabled]:text-gray-600 data-[disabled]:opacity-50',
      // Unavailable
      'data-[unavailable]:text-red-700-foreground data-[unavailable]:line-through',
      // Outside months
      'data-[outside-view]:text-gray-600',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCellTrigger>
</template>
