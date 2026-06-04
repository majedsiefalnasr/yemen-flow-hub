<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { computed } from 'vue'
import type { SavedAccount } from '~/composables/useSavedAccounts'
import BoringAvatar from '~/components/shared/BoringAvatar.vue'
import {
  AVATAR_VARIANTS,
  DEFAULT_AVATAR_VARIANT,
  tryReadUserAvatar,
  type AvatarVariant,
} from '~/composables/useUserAvatar'
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

const roleLabel = computed(() => ROLE_LABELS[props.account.role] ?? props.account.role)

function isKnownVariant(value: any): value is AvatarVariant {
  return typeof value === 'string' && (AVATAR_VARIANTS as readonly string[]).includes(value)
}

// Prefer the latest per-identity avatar cache (kept in sync whenever an admin
// or the user themselves edits the avatar on this device). The snapshot stored
// on the saved-account record is only a fallback for the case where this
// device has never written a per-identity preference for this email — without
// this lookup the card would freeze on the variant captured at the moment the
// account was first saved.
const avatarVariant = computed<AvatarVariant>(() => {
  const cached = tryReadUserAvatar(props.account.email)
  if (cached && isKnownVariant(cached.variant)) return cached.variant
  if (isKnownVariant(props.account.avatarVariant)) return props.account.avatarVariant
  return DEFAULT_AVATAR_VARIANT
})
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
    <BoringAvatar
      :name="account.name || account.email"
      :identity="account.email"
      :variant="avatarVariant"
      :size="40"
      class="shrink-0 overflow-hidden rounded-full"
      data-testid="saved-account-avatar"
    />

    <div class="min-w-0 flex-1 text-start">
      <p class="text-foreground truncate text-sm leading-tight font-semibold">
        {{ account.name }}
      </p>
      <p class="text-muted-foreground mt-0.5 truncate text-xs">
        {{ account.bankName }}
      </p>
      <p v-if="account.department && !compact" class="text-muted-foreground/70 truncate text-xs">
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
        class="text-muted-foreground/60 hover:text-destructive hover:bg-destructive/10 size-7"
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
  transition:
    border-color 120ms ease,
    background-color 120ms ease,
    box-shadow 120ms ease;
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
