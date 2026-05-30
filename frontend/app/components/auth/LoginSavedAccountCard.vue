<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { computed } from 'vue'
import type { SavedAccount } from '~/composables/useSavedAccounts'
import { getAvatarColor, getInitials } from '~/composables/useSavedAccounts'
import { Avatar, AvatarFallback } from '~/components/ui/avatar'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { ROLE_LABELS } from '~/constants/workflow'

const props = defineProps<{
  account: SavedAccount
  selected?: boolean
  /** Compact mode: no remove button, no department row, smaller padding */
  compact?: boolean
}>()

const emit = defineEmits<{
  select: []
  remove: []
}>()

const initials = computed(() => getInitials(props.account.name))
const avatarBg = computed(() => getAvatarColor(props.account.id))
const roleLabel = computed(() => ROLE_LABELS[props.account.role] ?? props.account.role)
</script>

<template>
  <button
    type="button"
    :class="[
      'saved-account-card',
      selected && 'saved-account-card--selected',
      compact && 'saved-account-card--compact',
    ]"
    :aria-pressed="selected"
    @click="emit('select')"
  >
    <Avatar size="lg" class="shrink-0">
      <AvatarFallback
        class="text-sm font-semibold text-white"
        :style="{ backgroundColor: avatarBg }"
      >
        {{ initials }}
      </AvatarFallback>
    </Avatar>

    <div class="min-w-0 flex-1 text-start">
      <p class="truncate text-sm font-semibold text-foreground leading-tight">
        {{ account.name }}
      </p>
      <p class="truncate text-xs text-muted-foreground mt-0.5">
        {{ account.bankName }}
      </p>
      <p
        v-if="account.department && !compact"
        class="truncate text-xs text-muted-foreground/70"
      >
        {{ account.department }}
      </p>
    </div>

    <div class="flex shrink-0 items-center gap-1.5">
      <Badge variant="secondary" class="hidden text-[10px] sm:flex">
        {{ roleLabel }}
      </Badge>
      <Button
        v-if="!compact"
        type="button"
        variant="ghost"
        size="icon"
        class="size-7 text-muted-foreground/60 hover:text-destructive hover:bg-destructive/10"
        :aria-label="`إزالة حساب ${account.name}`"
        @click.stop="emit('remove')"
      >
        <X class="size-3.5" />
      </Button>
    </div>
  </button>
</template>

<style scoped>
.saved-account-card {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  background: var(--background);
  cursor: pointer;
  transition: border-color 120ms ease, background-color 120ms ease, box-shadow 120ms ease;
  text-align: start;
}

.saved-account-card:hover {
  border-color: var(--primary);
  background: color-mix(in srgb, var(--primary) 4%, var(--background));
}

.saved-account-card:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

.saved-account-card--selected {
  border-color: var(--primary);
  background: color-mix(in srgb, var(--primary) 6%, var(--background));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent);
}

.saved-account-card--compact {
  padding: 10px;
  border-radius: 10px;
  cursor: default;
  pointer-events: none;
  background: color-mix(in srgb, var(--muted) 50%, var(--background));
}
</style>
