<script setup lang="ts">
import type { WithClassAsProps } from './interface'

import type { ButtonVariants } from '@/components/ui/button'
import { ChevronRightIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { useCarousel } from './useCarousel'

const props = withDefaults(
  defineProps<
    {
      variant?: ButtonVariants['variant']
      size?: ButtonVariants['size']
    } & WithClassAsProps
  >(),
  {
    variant: 'outline',
    size: 'icon-sm',
  },
)

const { orientation, canScrollNext, scrollNext } = useCarousel()
</script>

<template>
  <Button
    data-slot="carousel-next"
    :disabled="!canScrollNext"
    :class="
      cn(
        'absolute touch-manipulation rounded-full',
        orientation === 'horizontal'
          ? '-end-12 top-1/2 -translate-y-1/2'
          : 'start-1/2 -bottom-12 -translate-x-1/2 rotate-90 rtl:translate-x-1/2',
        props.class,
      )
    "
    :variant="variant"
    :size="size"
    @click="scrollNext"
  >
    <slot>
      <ChevronRightIcon class="rtl:rotate-180" />
      <span class="sr-only">Next slide</span>
    </slot>
  </Button>
</template>
