<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { LogOut, Moon, Settings, Sun, User } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import CommandPalette from '@/components/CommandPalette.vue'
import { ROLE_LABELS } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useThemingStore } from '@/stores/theming.store'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
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
const isDark = computed(() => themingStore.isDark)
const canToggleSidebar = computed(() => isMobile.value || themingStore.sidebarCollapsible !== 'none')
const displayName = computed(() => user.value?.name ?? 'المستخدم')
const displayEmail = computed(() => user.value?.email ?? '')

function userInitials(name: string) {
  return name.split(' ').map(part => part[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
}

function toggleTheme() {
  themingStore.setMode(isDark.value ? 'light' : 'dark')
}

async function handleLogout() {
  await authStore.logout()
  await router.push('/login')
}
</script>

<template>
  <header
    class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur supports-backdrop-filter:bg-background/85 sm:px-6"
    aria-label="الشريط العلوي"
  >
    <div class="flex min-w-0 flex-1 items-center gap-2">
      <template v-if="canToggleSidebar">
        <SidebarTrigger class="-ms-1 shrink-0" aria-label="تبديل الشريط الجانبي" />
        <Separator orientation="vertical" class="h-4 data-[orientation=vertical]:h-4" />
      </template>
      <div class="min-w-0 w-full max-w-2xs">
        <CommandPalette />
      </div>
    </div>

    <div class="flex min-w-0 items-center justify-end gap-1.5">
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button
            variant="ghost"
            class="h-9 gap-2 rounded-lg px-2"
            aria-label="قائمة المستخدم"
          >
            <Avatar class="h-7 w-7 rounded-lg">
              <AvatarFallback class="rounded-lg text-[11px]">
                {{ userInitials(displayName) }}
              </AvatarFallback>
            </Avatar>
            <span class="hidden min-w-0 flex-col text-start leading-tight sm:flex">
              <span class="max-w-28 truncate text-sm font-medium">{{ displayName }}</span>
              <span class="max-w-28 truncate text-xs text-muted-foreground">{{ roleLabel }}</span>
            </span>
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-64 rounded-lg" align="end" side="bottom" :side-offset="8">
          <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
              <Avatar class="h-9 w-9 rounded-lg">
                <AvatarFallback class="rounded-lg">
                  {{ userInitials(displayName) }}
                </AvatarFallback>
              </Avatar>
              <div class="grid min-w-0 flex-1 leading-tight">
                <span class="truncate font-medium">{{ displayName }}</span>
                <span class="truncate text-xs text-muted-foreground">{{ displayEmail }}</span>
                <span class="truncate text-xs text-primary">{{ roleLabel }}</span>
              </div>
            </div>
          </DropdownMenuLabel>

          <DropdownMenuSeparator />

          <DropdownMenuGroup>
            <DropdownMenuItem as-child>
              <NuxtLink to="/settings?tab=profile" class="flex cursor-pointer items-center gap-2">
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
            <DropdownMenuItem class="cursor-pointer" @click="toggleTheme">
              <Sun v-if="isDark" class="h-4 w-4" />
              <Moon v-else class="h-4 w-4" />
              <span>{{ isDark ? 'الوضع المضيء' : 'الوضع الداكن' }}</span>
            </DropdownMenuItem>
          </DropdownMenuGroup>

          <DropdownMenuSeparator />

          <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive" @click="handleLogout">
            <LogOut class="h-4 w-4" />
            <span>تسجيل الخروج</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
