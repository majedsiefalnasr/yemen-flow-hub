import { computed, ref, watch } from 'vue'

/**
 * boring-avatars supported variants. Kept in sync with the backend enum
 * `App\Enums\AvatarVariant`. Adding a value requires a backend release first.
 */
export const AVATAR_VARIANTS = [
  'marble',
  'beam',
  'pixel',
  'sunset',
  'ring',
  'bauhaus',
] as const

export type AvatarVariant = typeof AVATAR_VARIANTS[number]

export const DEFAULT_AVATAR_VARIANT: AvatarVariant = 'beam'

/**
 * Single, fixed palette used to generate every avatar in the app. Users no
 * longer pick their own colours — only the variant is configurable — so this
 * lives here as the canonical source of truth for both `BoringAvatar` and
 * `AvatarPicker`.
 */
export const AVATAR_PALETTE = [
  '#5b1d99',
  '#0074b4',
  '#00b34c',
  '#ffd41f',
  '#fc6e3d',
] as const

const STORAGE_PREFIX = 'yfh:avatar:'

export interface StoredAvatar {
  variant: AvatarVariant
}

function storageKey(identity: string): string {
  return `${STORAGE_PREFIX}${identity.toLowerCase()}`
}

function readStored(identity: string): StoredAvatar | null {
  if (!import.meta.client || !identity) return null
  try {
    const raw = window.localStorage.getItem(storageKey(identity))
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<StoredAvatar>
    const variant = (AVATAR_VARIANTS as readonly string[]).includes(parsed.variant ?? '')
      ? (parsed.variant as AvatarVariant)
      : DEFAULT_AVATAR_VARIANT
    return { variant }
  }
  catch {
    return null
  }
}

function writeStored(identity: string, value: StoredAvatar): void {
  if (!import.meta.client || !identity) return
  try {
    window.localStorage.setItem(storageKey(identity), JSON.stringify(value))
  }
  catch {
    // localStorage may be unavailable (private mode, quota); fail silently —
    // avatar selection is a cosmetic preference, never block the user.
  }
}

/**
 * Reactive accessor for the persisted avatar preference of an arbitrary
 * identity (typically a user's email or id). When `identity` changes, the
 * stored value is re-read.
 */
export function useUserAvatar(identity: () => string | null | undefined) {
  const variant = ref<AvatarVariant>(DEFAULT_AVATAR_VARIANT)

  function reload() {
    const id = identity() ?? ''
    const stored = readStored(id)
    variant.value = stored?.variant ?? DEFAULT_AVATAR_VARIANT
  }

  reload()

  watch(() => identity(), reload, { immediate: false })

  function persist() {
    const id = identity() ?? ''
    if (!id) return
    writeStored(id, { variant: variant.value })
  }

  function setVariant(next: AvatarVariant) {
    variant.value = next
    persist()
  }

  return {
    variant: computed(() => variant.value),
    setVariant,
    reload,
  }
}

/** Persist an avatar preference for a known identity outside of a component scope. */
export function persistUserAvatar(identity: string, value: { variant: AvatarVariant }): void {
  writeStored(identity, { variant: value.variant })
}

/** Read-only lookup for the full preference. */
export function readUserAvatar(identity: string): StoredAvatar {
  return readStored(identity) ?? { variant: DEFAULT_AVATAR_VARIANT }
}

/**
 * Like {@link readUserAvatar} but returns `null` when no preference has ever
 * been persisted for this identity. Callers that already have their own
 * fallback (e.g. a snapshot on a `SavedAccount` record) need to distinguish
 * "nothing stored" from "stored as default" — `readUserAvatar` collapses
 * those two cases together.
 */
export function tryReadUserAvatar(identity: string): StoredAvatar | null {
  return readStored(identity)
}
