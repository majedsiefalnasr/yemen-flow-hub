<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Menu, Search, X, Sun, Moon, Bell } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth.store'
import { useNotificationsStore } from '../../stores/notifications.store'
import { ROLE_LABELS } from '../../constants/workflow'
import { useColorScheme } from '../../composables/useColorScheme'
import { formatRelativeTime } from '../../utils/formatRelativeTime'
import GlobalSearch from './GlobalSearch.vue'
import RoleSwitcher from './RoleSwitcher.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '../ui/popover'

const emit = defineEmits<{ toggleMobileMenu: [] }>()
const auth = useAuthStore()
const notificationsStore = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const config = useRuntimeConfig()
const mobileSearchOpen = ref(false)
const notificationOpen = ref(false)
const userMenuOpen = ref(false)
const { isDark, toggle: toggleColorScheme } = useColorScheme()
const isDemoMode = (config.public as Record<string, unknown>).demoMode === true
  || (config.public as Record<string, unknown>).demoMode === 'true'
const userInitial = computed(() => auth.user?.name?.trim()?.charAt(0) ?? '؟')

function closeHeaderMenus() {
  mobileSearchOpen.value = false
  notificationOpen.value = false
  userMenuOpen.value = false
}

async function toggleNotifications() {
  notificationOpen.value = !notificationOpen.value
  if (notificationOpen.value) await notificationsStore.fetchRecent()
}

async function goToProfile() {
  closeHeaderMenus()
  await router.push('/profile')
}

async function goToSettings() {
  closeHeaderMenus()
  await router.push('/settings')
}

async function handleMarkAllRead() {
  await notificationsStore.markAllRead()
}

async function handleLogout() {
  closeHeaderMenus()
  await auth.logout()
  await router.push('/login')
}

onMounted(() => {
  void notificationsStore.refreshUnreadCount()
})

watch(() => route.fullPath, () => {
  closeHeaderMenus()
})
</script>

<template>
  <header class="sticky top-0 z-30 flex items-center justify-between h-16 px-5 bg-white/88 backdrop-blur border-b border-gray-300 flex-row-reverse gap-3" dir="rtl">
    <!-- Start: Mobile menu & search buttons -->
    <div class="flex items-center gap-1">
      <button
        class="max-lg:flex hidden items-center justify-center w-10 h-10 border-none bg-transparent rounded-lg cursor-pointer text-gray-600 hover:bg-gray-100"
        aria-label="فتح القائمة"
        @click="emit('toggleMobileMenu')"
      >
        <Menu class="w-5 h-5" />
      </button>
      <button
        class="max-md:flex hidden items-center justify-center w-10 h-10 border-none bg-transparent rounded-lg cursor-pointer text-gray-600 hover:bg-gray-100"
        aria-label="بحث"
        @click="mobileSearchOpen = !mobileSearchOpen"
      >
        <Search class="w-5 h-5" />
      </button>
    </div>

    <!-- Center: Global search (hidden on mobile) -->
    <div class="flex-1 flex justify-center min-w-0 max-md:hidden">
      <GlobalSearch />
    </div>

    <!-- End: Color scheme, notifications, user menu -->
    <div class="flex items-center gap-3">
      <button
        class="flex items-center justify-center w-10 h-10 border-none bg-transparent rounded-lg cursor-pointer text-gray-600 hover:bg-gray-100"
        :aria-label="isDark ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن'"
        @click="toggleColorScheme"
      >
        <Sun v-if="isDark" class="w-5 h-5" />
        <Moon v-else class="w-5 h-5" />
      </button>
      <RoleSwitcher v-if="isDemoMode" />

      <!-- Notifications -->
      <Popover v-model:open="notificationOpen">
        <PopoverTrigger asChild>
          <button
            class="relative flex items-center justify-center w-10 h-10 border-none bg-transparent rounded-lg cursor-pointer text-gray-600 hover:bg-gray-100"
            aria-label="الإشعارات"
            @click="toggleNotifications"
          >
            <Bell class="w-5 h-5" />
            <span
              v-if="notificationsStore.unreadCount > 0"
              class="absolute top-1 end-1 min-w-4 h-4 px-0.75 rounded-full bg-red-700 text-white text-xs font-semibold flex items-center justify-center border-2 border-white"
              :aria-label="`${notificationsStore.unreadCount} إشعارات غير مقروءة`"
            >
              {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
            </span>
          </button>
        </PopoverTrigger>
        <PopoverContent class="w-96 max-w-[calc(100vw-24px)] p-0" side="bottom" align="start">
          <div class="flex items-center justify-between px-3 py-2.5 border-b border-gray-200">
            <div class="flex gap-2 items-center">
              <span class="text-sm font-medium">الإشعارات</span>
              <span v-if="notificationsStore.unreadCount > 0" class="text-xs bg-blue-50 text-blue-600 rounded-full px-1.5 py-0.5">
                {{ notificationsStore.unreadCount }}
              </span>
            </div>
            <button class="text-sm text-blue-600 bg-transparent border-none cursor-pointer hover:underline" @click="handleMarkAllRead">قراءة الكل</button>
          </div>
          <div v-if="notificationsStore.items.length === 0" class="flex flex-col items-center gap-2 px-3 py-6 text-center">
            <div class="w-11 h-11 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
              <Bell class="w-5 h-5" />
            </div>
            <p class="text-sm text-gray-600">لا توجد إشعارات بعد</p>
          </div>
          <div v-else class="max-h-96 overflow-auto">
            <div v-for="n in notificationsStore.items" :key="n.id" class="flex gap-2 px-3 py-2.5 border-b border-gray-200 last:border-b-0">
              <div
                class="w-2 h-2 mt-1.5 rounded-full flex-shrink-0"
                :class="n.read_at ? 'bg-gray-300' : 'bg-blue-600'"
              />
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900">{{ n.data.message }}</p>
                <p class="text-xs text-gray-600">{{ n.data.reference_number ?? '—' }}</p>
              </div>
              <time class="text-xs text-gray-600 flex-shrink-0">{{ formatRelativeTime(n.created_at) }}</time>
            </div>
          </div>
          <NuxtLink to="/notifications" class="block px-3 py-2.5 text-sm text-blue-600 font-medium no-underline hover:bg-gray-50">عرض كل الإشعارات</NuxtLink>
        </PopoverContent>
      </Popover>

      <!-- User menu -->
      <DropdownMenu v-if="auth.user" v-model:open="userMenuOpen">
        <DropdownMenuTrigger asChild>
          <button
            class="flex items-center gap-2 border-none bg-transparent cursor-pointer hover:opacity-80 transition-opacity"
            @click="userMenuOpen = !userMenuOpen"
          >
            <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
              {{ userInitial }}
            </div>
            <div class="flex flex-col items-end gap-0.5 max-md:hidden">
              <span class="text-sm font-medium text-gray-900 leading-tight">{{ auth.user.name }}</span>
              <span class="text-xs text-gray-600 leading-tight">{{ ROLE_LABELS[auth.user.role] ?? auth.user.role }}</span>
            </div>
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-48" align="start">
          <button
            class="w-full text-start px-2.5 py-2 text-sm bg-transparent border-none cursor-pointer rounded hover:bg-gray-100 transition-colors"
            @click="goToProfile"
          >
            الملف الشخصي
          </button>
          <button
            class="w-full text-start px-2.5 py-2 text-sm bg-transparent border-none cursor-pointer rounded hover:bg-gray-100 transition-colors"
            @click="goToSettings"
          >
            الإعدادات
          </button>
          <hr class="border-t border-gray-200 my-1.5 mx-0" />
          <button
            class="w-full text-start px-2.5 py-2 text-sm text-red-700 bg-transparent border-none cursor-pointer rounded hover:bg-gray-100 transition-colors"
            @click="handleLogout"
          >
            تسجيل الخروج
          </button>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>

    <!-- Mobile search overlay -->
    <div v-if="mobileSearchOpen" class="absolute top-16 inset-x-0 h-16 bg-white border-b border-gray-300 px-3 py-2 flex items-center gap-2 z-30 max-md:flex hidden" dir="rtl">
      <GlobalSearch mobile />
      <button
        class="flex items-center justify-center w-10 h-10 border-none bg-transparent rounded-lg cursor-pointer text-gray-600"
        aria-label="إغلاق البحث"
        @click="mobileSearchOpen = false"
      >
        <X class="w-5 h-5" />
      </button>
    </div>
  </header>
</template>
