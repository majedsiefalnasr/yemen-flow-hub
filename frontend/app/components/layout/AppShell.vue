<script setup lang="ts">
import {
  Bell,
  Building2,
  AlertTriangle,
  FileCheck2,
  FilePlus2,
  FileText,
  KeyRound,
  Languages,
  LayoutDashboard,
  Menu,
  Moon,
  Network,
  PackageCheck,
  ScrollText,
  Search,
  Settings,
  Sun,
  UserCog,
  Users,
  X,
  BarChart3,
  ChevronLeft,
  LogOut,
} from 'lucide-vue-next'
import RoleSwitcher from '@/components/workflow/RoleSwitcher.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useTheme } from '@/composables/useTheme'
import { ROLE_LABELS } from '@/constants/workflow'
import { UserRole } from '@/types/enums'

type NavItem = {
  to: string
  label: string
  icon: unknown
  roles?: UserRole[]
}

const navItems: NavItem[] = [
  { to: '/', label: 'اللوحة الرئيسية', icon: LayoutDashboard },
  { to: '/requests', label: 'طلبات التمويل', icon: FileText },
  { to: '/requests/new', label: 'تقديم طلب جديد', icon: FilePlus2, roles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN] },
  { to: '/merchants', label: 'إدارة التجار', icon: Building2, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { to: '/customs', label: 'إذن إصدار بيان جمركي', icon: PackageCheck, roles: [UserRole.COMMITTEE_DIRECTOR] },
  { to: '/reports', label: 'التقارير والتحليلات', icon: BarChart3, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN] },
  { to: '/audit', label: 'التدقيق والامتثال', icon: ScrollText, roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR] },
  { to: '/notifications', label: 'الإشعارات', icon: Bell },
  { to: '/admin/entities', label: 'إدارة البنوك', icon: Network, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/cby-staff', label: 'مستخدمي النظام', icon: UserCog, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/workflow-docs', label: 'قواعد المستندات', icon: FileCheck2, roles: [UserRole.CBY_ADMIN] },
  { to: '/admin/roles', label: 'الأدوار والصلاحيات', icon: KeyRound, roles: [UserRole.CBY_ADMIN] },
  { to: '/staff', label: 'موظفو الجهة', icon: Users, roles: [UserRole.BANK_ADMIN] },
  { to: '/settings', label: 'إعدادات النظام', icon: Settings, roles: [UserRole.CBY_ADMIN] },
]

const route = useRoute()
const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()
const { isDark, setTheme } = useTheme()

const user = computed(() => authStore.user)
const mobileOpen = ref(false)
const collapsed = ref(false)

const visibleNavItems = computed(() => {
  if (!user.value) return []
  return navItems.filter((item) => {
    if (item.roles) return item.roles.includes(user.value!.role)
    return true
  })
})

const notifications = computed(() => notificationsStore.items ?? [])
const unreadCount = computed(() => notificationsStore.unreadCount)

onMounted(() => {
  notificationsStore.refreshUnreadCount()
})

watch(() => route.fullPath, () => {
  mobileOpen.value = false
})

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

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
  >
    <div class="lg:hidden">
      <Sheet v-model:open="mobileOpen">
        <SheetContent
          side="right"
          :show-close-button="false"
          class="w-72 border-e border-sidebar-border bg-sidebar p-3 text-sidebar-foreground sm:max-w-xs"
        >
        <div class="mb-4 flex items-center justify-between border-b border-sidebar-border pb-4">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-sidebar-primary font-bold text-sidebar-primary-foreground">
              ب م
            </div>
            <div>
              <div class="font-bold">
                منصة الواردات
              </div>
              <div class="text-[11px] text-sidebar-foreground/60">
                البنك المركزي اليمني
              </div>
            </div>
          </div>
          <Button
            variant="ghost"
            size="icon"
            @click="mobileOpen = false"
          >
            <X class="h-4 w-4" />
          </Button>
        </div>

        <ScrollArea class="h-[calc(100svh-6rem)]">
          <nav class="space-y-1">
            <NuxtLink
              v-for="item in visibleNavItems"
              :key="item.to"
              :to="item.to"
              :class="[
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all',
                isActive(item.to)
                  ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-soft'
                  : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
              ]"
            >
              <component
                :is="item.icon"
                class="h-5 w-5"
              />
              <span>{{ item.label }}</span>
            </NuxtLink>
          </nav>
        </ScrollArea>
        </SheetContent>
      </Sheet>
    </div>

    <aside
      :class="[
        'fixed inset-y-0 start-0 hidden border-e border-sidebar-border bg-sidebar text-sidebar-foreground transition-all duration-300 lg:block',
        collapsed ? 'w-20' : 'w-72',
      ]"
    >
      <div class="flex h-full flex-col">
        <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sidebar-primary font-bold text-sidebar-primary-foreground">
            ب م
          </div>
          <div
            v-if="!collapsed"
            class="leading-tight"
          >
            <div class="font-bold">
              منصة الواردات
            </div>
            <div class="text-[11px] text-sidebar-foreground/60">
              البنك المركزي اليمني
            </div>
          </div>
        </div>

        <ScrollArea class="flex-1">
          <nav class="space-y-1 p-3">
            <NuxtLink
              v-for="item in visibleNavItems"
              :key="item.to"
              :to="item.to"
              :class="[
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all',
                isActive(item.to)
                  ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-soft'
                  : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
              ]"
            >
              <component
                :is="item.icon"
                class="h-5 w-5 shrink-0"
              />
              <span
                v-if="!collapsed"
                class="truncate"
              >
                {{ item.label }}
              </span>
              <ChevronLeft
                v-if="!collapsed && isActive(item.to)"
                class="ms-auto h-4 w-4 opacity-60"
              />
            </NuxtLink>
          </nav>
        </ScrollArea>

        <div class="border-t border-sidebar-border bg-sidebar p-3">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="w-full text-xs text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-foreground"
            @click="collapsed = !collapsed"
          >
            {{ collapsed ? 'توسيع ›' : '‹ طي الشريط الجانبي' }}
          </Button>
        </div>
      </div>
    </aside>

    <div
      :class="[
        'flex min-h-screen flex-col transition-[padding] duration-300',
        collapsed ? 'lg:ps-20' : 'lg:ps-72',
      ]"
    >
      <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b bg-card/80 px-3 backdrop-blur-md sm:gap-3 sm:px-6">
        <Button
          variant="ghost"
          size="icon"
          class="lg:hidden"
          @click="mobileOpen = true"
        >
          <Menu class="h-5 w-5" />
        </Button>

        <div class="relative hidden w-full max-w-md md:block">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="ابحث عن طلب، تاجر، أو رقم فاتورة..."
            class="border-transparent bg-muted/50 pe-10 focus-visible:bg-card"
          />
        </div>

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
                <div class="font-semibold">
                  الإشعارات
                </div>
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

      <main class="mx-auto min-w-0 max-w-[1600px] flex-1 w-full p-3 sm:p-5 lg:p-8">
        <slot />
      </main>

      <footer class="flex flex-wrap items-center justify-between gap-2 border-t px-3 py-4 text-[10px] text-muted-foreground sm:px-6 sm:text-xs">
        <div>© 2025 البنك المركزي اليمني</div>
        <div class="flex shrink-0 items-center gap-2">
          <AlertTriangle class="h-3.5 w-3.5" />
          بيئة عرض توضيحي
        </div>
      </footer>
    </div>
  </div>
</template>
