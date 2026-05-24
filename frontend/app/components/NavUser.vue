<script setup lang="ts">
import { LogOut, CreditCard, Bell, User } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
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
import { useAuthStore } from '@/stores/auth.store'

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
const router = useRouter()

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
            <Avatar class="h-8 w-8 rounded-lg">
              <AvatarImage :src="user.avatar" :alt="user.name" />
              <AvatarFallback class="rounded-lg">
                {{ user.name.charAt(0).toUpperCase() }}
              </AvatarFallback>
            </Avatar>
            <div class="grid flex-1 text-left text-sm leading-tight">
              <span class="truncate font-medium">{{ user.name }}</span>
              <span class="truncate text-xs text-gray-600">
                {{ user.email }}
              </span>
            </div>
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-56 rounded-lg"
          :side="isMobile ? 'bottom' : 'right'"
          :side-offset="4"
          align="end"
        >
          <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
              <Avatar class="h-8 w-8 rounded-lg">
                <AvatarImage :src="user.avatar" :alt="user.name" />
                <AvatarFallback class="rounded-lg">
                  {{ user.name.charAt(0).toUpperCase() }}
                </AvatarFallback>
              </Avatar>
              <div class="grid flex-1 text-left text-sm leading-tight">
                <span class="truncate font-medium">{{ user.name }}</span>
                <span class="truncate text-xs text-gray-600">
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
            <DropdownMenuItem as-child>
              <NuxtLink to="/notifications" class="flex items-center gap-2 cursor-pointer">
                <Bell class="h-4 w-4" />
                <span>الإشعارات</span>
              </NuxtLink>
            </DropdownMenuItem>
          </DropdownMenuGroup>
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
