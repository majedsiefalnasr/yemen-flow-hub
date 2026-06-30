<script setup lang="ts">
import { LogOut, MoreVertical, Settings, User } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import BoringAvatar from '@/components/shared/BoringAvatar.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { useAuthStore } from '@/stores/auth.store'
import { DEFAULT_AVATAR_VARIANT, type AvatarVariant } from '@/composables/useUserAvatar'

interface User {
  name: string
  email: string
  avatar?: string
  avatar_variant?: string | null
}

const props = defineProps<{ user: User }>()

const { isMobile } = useSidebar()
const authStore = useAuthStore()
const router = useRouter()
const showLogoutDialog = ref(false)

const settingsRoute = '/settings'

const avatarIdentity = computed(() => props.user.email || props.user.name || 'user')
const avatarVariant = computed<AvatarVariant>(() => {
  const value = props.user.avatar_variant
  return value && ['marble', 'beam', 'pixel', 'sunset', 'ring', 'bauhaus'].includes(value)
    ? (value as AvatarVariant)
    : DEFAULT_AVATAR_VARIANT
})

async function handleLogout() {
  showLogoutDialog.value = false
  await authStore.logout()
  await router.push('/login')
}
</script>

<template>
  <SidebarMenu>
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <SidebarMenuButton
            size="lg"
            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
          >
            <BoringAvatar
              :name="user.name || avatarIdentity"
              :identity="avatarIdentity"
              :variant="avatarVariant"
              :size="32"
              square
              class="h-8 w-8 overflow-hidden rounded-lg"
              data-testid="nav-user-avatar"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
              <span class="truncate font-medium">{{ user.name }}</span>
              <span class="text-muted-foreground truncate text-xs">{{ user.email }}</span>
            </div>
            <MoreVertical class="ms-auto h-4 w-4" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>

        <DropdownMenuContent
          class="w-56 rounded-lg"
          :side="isMobile ? 'bottom' : 'right'"
          :side-offset="4"
          align="end"
        >
          <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
              <BoringAvatar
                :name="user.name || avatarIdentity"
                :identity="avatarIdentity"
                :variant="avatarVariant"
                :size="32"
                square
                class="h-8 w-8 overflow-hidden rounded-lg"
              />
              <div class="grid flex-1 text-start text-sm leading-tight">
                <span class="truncate font-medium">{{ user.name }}</span>
                <span class="text-muted-foreground truncate text-xs">{{ user.email }}</span>
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
              <NuxtLink :to="settingsRoute" class="flex cursor-pointer items-center gap-2">
                <Settings class="h-4 w-4" />
                <span>الإعدادات</span>
              </NuxtLink>
            </DropdownMenuItem>
          </DropdownMenuGroup>

          <DropdownMenuSeparator />

          <DropdownMenuItem
            class="text-destructive focus:text-destructive cursor-pointer"
            @click="showLogoutDialog = true"
          >
            <LogOut class="h-4 w-4" />
            <span>تسجيل الخروج</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>

  <AlertDialog v-model:open="showLogoutDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>تسجيل الخروج</AlertDialogTitle>
        <AlertDialogDescription>
          هل أنت متأكد من رغبتك في تسجيل الخروج من النظام؟ سيتم إنهاء جلستك الحالية.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>إلغاء</AlertDialogCancel>
        <AlertDialogAction
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          @click="handleLogout"
        >
          تسجيل الخروج
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
