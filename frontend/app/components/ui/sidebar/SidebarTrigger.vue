<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

import { PanelRightIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { useSidebar } from './utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const { toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon-sm"
    :class="cn('', props.class)"
    @click="toggleSidebar"
  >
    <PanelRightIcon />
    <span class="sr-only">تبديل الشريط الجانبي</span>
  </Button>
</template>
