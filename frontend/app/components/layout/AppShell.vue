<script setup lang="ts">
import {
  Bell,
  AlertTriangle,
  Moon,
  Search,
  Sun,
  LogOut,
} from 'lucide-vue-next'
import AppSidebar from '@/components/AppSidebar.vue'
import RoleSwitcher from './RoleSwitcher.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useTheme } from '@/composables/useTheme'
import { ROLE_LABELS } from '@/constants/workflow'
import {
  SidebarInset,
  SidebarProvider,
} from '@/components/ui/sidebar'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { ScrollArea } from '@/components/ui/scroll-area'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Input } from '@/components/ui/input'

const route = useRoute()
const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()
const { isDark, setTheme } = useTheme()

const user = computed(() => authStore.user)
const notifications = computed(() => notificationsStore.items ?? [])
const unreadCount = computed(() => notificationsStore.unreadCount)

onMounted(() => {
  notificationsStore.refreshUnreadCount()
})

function goTo(path: string) {
  return navigateTo(path)
}

async function logoutAndGo() {
  await authStore.logout()
  await navigateTo('/login')
}

function userInitials(name: string) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('')
}
</script>

<template>
  <div
    v-if="user"
    class="min-h-screen bg-background text-foreground"
    dir="rtl"
  >
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset class="flex flex-col">
        <!-- Top Header -->
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-2 border-b bg-background/95 px-3 sm:gap-3 sm:px-6 backdrop-blur-sm">
          <!-- Left: Search (hidden on mobile) -->
          <div class="relative hidden flex-1 max-w-md md:block">
            <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="ابحث عن طلب، تاجر، أو رقم فاتورة..."
              class="border-transparent bg-muted/50 pe-10 focus-visible:bg-card"
            />
          </div>

          <!-- Right: Controls + User Menu -->
          <div class="ms-auto flex items-center gap-2">
            <RoleSwitcher />

            <Button
              variant="ghost"
              size="icon"
              class="hidden sm:inline-flex"
              @click="setTheme(isDark ? 'light' : 'dark')"
            >
              <Sun
                v-if="isDark"
                class="h-5 w-5"
              />
              <Moon
                v-else
                class="h-5 w-5"
              />
            </Button>

            <!-- Notifications -->
            <Popover>
              <PopoverTrigger as-child>
                <Button
                  variant="ghost"
                  size="icon"
                  class="relative"
                >
                  <Bell class="h-5 w-5" />
                  <span
                    v-if="unreadCount > 0"
                    class="absolute start-1.5 top-1.5 h-2 w-2 rounded-full bg-destructive ring-2 ring-card"
                  />
                </Button>
              </PopoverTrigger>

              <PopoverContent
                align="end"
                class="w-[min(24rem,calc(100vw-1rem))] overflow-hidden p-0"
              >
                <div class="flex items-center justify-between border-b px-4 py-3">
                  <div class="font-semibold">الإشعارات</div>
                  <div class="flex items-center gap-2">
                    <Badge variant="secondary">
                      {{ unreadCount }} جديد
                    </Badge>
                    <Button
                      type="button"
                      variant="link"
                      size="sm"
                      class="h-auto p-0 text-[11px] text-primary"
                      @click="notificationsStore.markAllRead()"
                    >
                      قراءة الكل
                    </Button>
                  </div>
                </div>
                <ScrollArea class="h-96">
                  <div class="divide-y">
                    <NuxtLink
                      v-for="notification in notifications.slice(0, 12)"
                      :key="notification.id"
                      :to="notification.data.request_id ? `/requests/${notification.data.request_id}` : '/notifications'"
                      class="flex gap-3 p-4 hover:bg-muted/50"
                    >
                      <Badge
                        :class="[
                          'mt-1 h-2 w-2 shrink-0 rounded-full p-0',
                          !notification.read_at ? 'bg-accent' : 'bg-muted',
                        ]"
                      />
                      <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">
                          {{ notification.data.reference_number || notification.type }}
                        </div>
                        <div class="truncate text-xs text-muted-foreground">
                          {{ notification.data.message }}
                        </div>
                        <div class="mt-1 text-[10px] text-muted-foreground">
                          {{ notification.created_at }}
                        </div>
                      </div>
                    </NuxtLink>
                  </div>
                </ScrollArea>
                <div class="border-t p-2 text-center">
                  <NuxtLink
                    to="/notifications"
                    class="text-xs text-primary hover:underline"
                  >
                    عرض كل الإشعارات
                  </NuxtLink>
                </div>
              </PopoverContent>
            </Popover>

            <!-- User Menu -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button
                  variant="ghost"
                  class="h-auto gap-3 rounded-full py-1 ps-1 pe-2"
                >
                  <div class="hidden text-end leading-tight sm:block">
                    <div class="text-sm font-semibold">
                      {{ user.name }}
                    </div>
                    <div class="text-[11px] text-muted-foreground">
                      {{ ROLE_LABELS[user.role] }}
                    </div>
                  </div>
                  <Avatar size="sm">
                    <AvatarFallback class="bg-primary text-sm font-bold text-primary-foreground">
                      {{ userInitials(user.name) }}
                    </AvatarFallback>
                  </Avatar>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent
                align="end"
                class="w-64"
              >
                <DropdownMenuLabel>
                  <div class="font-semibold">
                    {{ user.name }}
                  </div>
                  <div class="text-xs font-normal text-muted-foreground">
                    {{ user.email }}
                  </div>
                  <div
                    v-if="user.bank_name_ar"
                    class="mt-0.5 text-xs font-normal text-muted-foreground"
                  >
                    {{ user.bank_name_ar }}
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem @select="goTo('/profile')">
                  الملف الشخصي
                </DropdownMenuItem>
                <DropdownMenuItem @select="goTo('/settings')">
                  الإعدادات
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  @select="logoutAndGo"
                >
                  <LogOut class="ms-2 h-4 w-4" />
                  تسجيل الخروج
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto min-w-0 max-w-[1600px] flex-1 w-full p-3 sm:p-5 lg:p-8">
          <slot />
        </main>

        <!-- Footer -->
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t px-3 py-4 text-[10px] text-muted-foreground sm:px-6 sm:text-xs">
          <div>© 2025 البنك المركزي اليمني</div>
          <div class="flex shrink-0 items-center gap-2">
            <AlertTriangle class="h-3.5 w-3.5" />
            بيئة عرض توضيحي
          </div>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  </div>
</template>
