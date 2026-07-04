<script setup lang="ts">
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import BoringAvatar from '@/components/shared/BoringAvatar.vue'
import type { DemoUser } from '~/types/models'

defineProps<{
  user: DemoUser
}>()

const emit = defineEmits<{
  select: []
}>()

defineOptions({
  inheritAttrs: false,
})
</script>

<template>
  <Card
    class="hover:bg-accent focus-visible:ring-ring flex cursor-pointer flex-row flex-nowrap items-center gap-3 border-0 p-3 text-start shadow transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
    role="button"
    tabindex="0"
    :aria-label="`تسجيل الدخول كـ ${user.name}`"
    @click="emit('select')"
    @keydown.enter="emit('select')"
    @keydown.space.prevent="emit('select')"
  >
    <BoringAvatar
      :name="user.name"
      :identity="user.email"
      :size="48"
      square
      class="size-12 shrink-0 overflow-hidden rounded-lg"
    />

    <div class="min-w-0 flex-1">
      <p class="text-foreground truncate text-sm leading-tight font-semibold">
        {{ user.name }}
      </p>
      <p class="text-muted-foreground mt-1 truncate text-xs">
        {{ user.email }}
      </p>
      <Badge variant="secondary" class="mt-1.5 block max-w-full truncate text-[11px] leading-none">
        {{ user.role_label }}
      </Badge>
    </div>
  </Card>
</template>
