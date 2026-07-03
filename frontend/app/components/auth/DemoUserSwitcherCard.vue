<script setup lang="ts">
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { DemoUser } from '~/types/models'

const props = defineProps<{
  user: DemoUser
}>()

const emit = defineEmits<{
  select: []
}>()

defineOptions({
  inheritAttrs: false,
})

const subLine = props.user.team?.name ?? props.user.organization?.name ?? ''
</script>

<template>
  <Card
    class="flex cursor-pointer items-center gap-3 border-0 p-3 shadow transition-shadow hover:shadow-md"
    role="button"
    tabindex="0"
    :aria-label="`تسجيل الدخول كـ ${user.name}`"
    @click="emit('select')"
    @keydown.enter="emit('select')"
    @keydown.space.prevent="emit('select')"
  >
    <div class="min-w-0 flex-1 text-start">
      <p class="text-foreground truncate text-sm leading-tight font-semibold">
        {{ user.name }}
      </p>
      <p class="text-muted-foreground mt-0.5 truncate text-xs">
        {{ user.email }}
      </p>
      <p v-if="subLine" class="text-muted-foreground/70 truncate text-xs">
        {{ subLine }}
      </p>
    </div>

    <Badge variant="secondary" class="shrink-0 text-xs leading-none">
      {{ user.role_label }}
    </Badge>
  </Card>
</template>
