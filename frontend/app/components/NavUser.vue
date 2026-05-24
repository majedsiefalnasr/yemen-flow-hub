<script setup lang="ts">
import { LogOut, CreditCard, Bell, User, MoreVertical, Moon, Sun } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useTheme } from '@/composables/useTheme'
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from '@/components/ui/avatar'
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
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationsStore } from '@/stores/notifications.store'

interface User {
  name: string
  email: string
  avatar: string
}

defineProps<{
  user: User
}>()

const { isMobile } = useSidebar()
const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()
const router = useRouter()
const { isDark, setTheme } = useTheme()

const notifications = computed(() => notificationsStore.items ?? [])
const unreadCount = computed(() => notificationsStore.unreadCount)

onMounted(() => {
  notificationsStore.refreshUnreadCount()
})

function userInitials(name: string) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

const handleLogout = async () => {
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
            <Avatar class="h-8 w-8 rounded-lg grayscale">
              <AvatarImage :src="user.avatar" :alt="user.name" />
              <AvatarFallback class="rounded-lg">
                {{ userInitials(user.name) }}
              </AvatarFallback>
            </Avatar>
            <div class="grid flex-1 text-start text-sm leading-tight">
              <span class="truncate font-medium">{{ user.name }}</span>
              <span class="truncate text-xs text-muted-foreground">
                {{ user.email }}
              </span>
            </div>
            <MoreVertical class="ml-auto h-4 w-4" />
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
              <Avatar class="h-8 w-8 rounded-lg">
                <AvatarImage :src="user.avatar" :alt="user.name" />
                <AvatarFallback class="rounded-lg">
                  {{ userInitials(user.name) }}
                </AvatarFallback>
              </Avatar>
              <div class="grid flex-1 text-start text-sm leading-tight">
                <span class="truncate font-medium">{{ user.name }}</span>
                <span class="truncate text-xs text-muted-foreground">
                  {{ user.email }}
                </span>
              </div>
            </div>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuGroup>
            <DropdownMenuItem as-child>
              <NuxtLink to="/profile" class="flex items-center gap-2 cursor-pointer">
                <User class="h-4 w-4" />
                <span>الملف الشخصي</span>
              </NuxtLink>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
              <NuxtLink to="/settings" class="flex items-center gap-2 cursor-pointer">
                <CreditCard class="h-4 w-4" />
                <span>الإعدادات</span>
              </NuxtLink>
            </DropdownMenuItem>
            <DropdownMenuItem @click="setTheme(isDark ? 'light' : 'dark')" class="cursor-pointer">
              <Sun v-if="isDark" class="h-4 w-4" />
              <Moon v-else class="h-4 w-4" />
              <span>{{ isDark ? 'المضيء' : 'الداكن' }}</span>
            </DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <div class="px-2 py-1.5">
            <div class="flex items-center justify-between gap-2 mb-2">
              <div class="text-sm font-medium">الإشعارات</div>
              <Badge v-if="unreadCount > 0" variant="secondary" class="text-xs">
                {{ unreadCount }} جديد
              </Badge>
            </div>
            <ScrollArea class="h-64">
              <div v-if="notifications.length === 0" class="text-center py-8 text-xs text-muted-foreground">
                لا توجد إشعارات
              </div>
              <div v-else class="space-y-2">
                <NuxtLink
                  v-for="notification in notifications.slice(0, 8)"
                  :key="notification.id"
                  :to="notification.data.request_id ? `/requests/${notification.data.request_id}` : '/notifications'"
                  class="block p-2 text-xs rounded hover:bg-sidebar-accent transition-colors"
                >
                  <div class="flex items-start gap-2">
                    <Badge
                      :class="[
                        'mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full p-0',
                        !notification.read_at ? 'bg-accent' : 'bg-gray-50',
                      ]"
                    />
                    <div class="min-w-0 flex-1">
                      <div class="truncate font-medium text-foreground">
                        {{ notification.data.reference_number || notification.type }}
                      </div>
                      <div class="truncate text-muted-foreground">
                        {{ notification.data.message }}
                      </div>
                    </div>
                  </div>
                </NuxtLink>
              </div>
            </ScrollArea>
            <NuxtLink
              to="/notifications"
              class="block text-center mt-2 text-xs text-primary hover:underline"
            >
              عرض كل الإشعارات
            </NuxtLink>
          </div>
          <DropdownMenuSeparator />
          <DropdownMenuItem @click="handleLogout" class="text-red-700 cursor-pointer">
            <LogOut class="h-4 w-4" />
            <span>تسجيل الخروج</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>
</template>
