<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { LogOut, Settings, User } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import CommandPalette from '@/components/CommandPalette.vue'
import BoringAvatar from '@/components/shared/BoringAvatar.vue'
import { ROLE_LABELS } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useThemingStore } from '@/stores/theming.store'
import { DEFAULT_AVATAR_VARIANT, type AvatarVariant } from '@/composables/useUserAvatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { Separator } from '@/components/ui/separator'

const authStore = useAuthStore()
const themingStore = useThemingStore()
const router = useRouter()

const user = computed(() => authStore.user)
const isMobile = useMediaQuery('(max-width: 768px)')
const roleLabel = computed(() => {
  const role = user.value?.role
  return role ? ROLE_LABELS[role] : 'مستخدم'
})
const canToggleSidebar = computed(
  () => isMobile.value || themingStore.sidebarCollapsible !== 'none',
)
const displayName = computed(() => user.value?.name ?? 'المستخدم')
const displayEmail = computed(() => user.value?.email ?? '')

const avatarIdentity = computed(() => user.value?.email || user.value?.name || 'user')
const avatarVariant = computed<AvatarVariant>(() => {
  const value = user.value?.avatar_variant
  return value && ['marble', 'beam', 'pixel', 'sunset', 'ring', 'bauhaus'].includes(value)
    ? (value as AvatarVariant)
    : DEFAULT_AVATAR_VARIANT
})

async function handleLogout() {
  await authStore.logout()
  await router.push('/login')
}
</script>

<template>
  <header
    class="bg-background/95 supports-backdrop-filter:bg-background/85 sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b px-4 backdrop-blur sm:px-6"
    aria-label="الشريط العلوي"
  >
    <div class="flex min-w-0 flex-1 items-center gap-2">
      <template v-if="canToggleSidebar">
        <SidebarTrigger class="-ms-1 shrink-0" aria-label="تبديل الشريط الجانبي" />
        <Separator orientation="vertical" class="h-4 data-[orientation=vertical]:h-4" />
      </template>
      <div class="w-full max-w-2xs min-w-0">
        <CommandPalette />
      </div>
    </div>

    <div class="flex min-w-0 items-center justify-end gap-1.5">
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" class="h-9 gap-2 rounded-lg px-2" aria-label="قائمة المستخدم">
            <BoringAvatar
              :name="displayName"
              :identity="avatarIdentity"
              :variant="avatarVariant"
              :size="48"
              square
              class="size-7 overflow-hidden rounded-lg"
              data-testid="topbar-user-avatar"
            />
            <span class="hidden min-w-0 flex-col text-start leading-tight sm:flex">
              <span class="max-w-28 truncate text-sm font-medium">{{ displayName }}</span>
              <span class="text-muted-foreground max-w-28 truncate text-xs">{{ roleLabel }}</span>
            </span>
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-64 rounded-lg" align="end" side="bottom" :side-offset="8">
          <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
              <BoringAvatar
                :name="displayName"
                :identity="avatarIdentity"
                :variant="avatarVariant"
                :size="36"
                square
                class="h-12 w-12 overflow-hidden rounded-lg"
              />
              <div class="grid min-w-0 flex-1 leading-tight">
                <span class="truncate font-medium">{{ displayName }}</span>
                <span class="text-muted-foreground truncate text-xs">{{ displayEmail }}</span>
                <span class="text-primary truncate text-xs">{{ roleLabel }}</span>
              </div>
            </div>
          </DropdownMenuLabel>

          <DropdownMenuSeparator />

          <DropdownMenuGroup>
            <DropdownMenuItem as-child>
              <NuxtLink
                to="/settings?section=profile"
                class="flex cursor-pointer items-center gap-2"
              >
                <User class="h-4 w-4" />
                <span>الملف الشخصي</span>
              </NuxtLink>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
              <NuxtLink to="/settings" class="flex cursor-pointer items-center gap-2">
                <Settings class="h-4 w-4" />
                <span>الإعدادات</span>
              </NuxtLink>
            </DropdownMenuItem>
          </DropdownMenuGroup>

          <DropdownMenuSeparator />

          <DropdownMenuItem
            class="text-destructive focus:text-destructive cursor-pointer"
            @click="handleLogout"
          >
            <LogOut class="h-4 w-4" />
            <span>تسجيل الخروج</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
