<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useNotificationsStore } from '../../stores/notifications.store'
import { ROLE_LABELS } from '../../constants/workflow'
import { useColorScheme } from '../../composables/useColorScheme'
import { formatRelativeTime } from '../../utils/formatRelativeTime'
import Icon from '../ui/Icon.vue'
import Popover from '../ui/Popover.vue'
import DropdownMenu from '../ui/DropdownMenu.vue'
import GlobalSearch from './GlobalSearch.vue'
import RoleSwitcher from './RoleSwitcher.vue'

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
  <header class="app-header">
    <div class="header-start">
      <button class="mobile-menu-btn" aria-label="فتح القائمة" @click="emit('toggleMobileMenu')">
        <Icon name="menu" />
      </button>
      <button class="mobile-search-btn" aria-label="بحث" @click="mobileSearchOpen = !mobileSearchOpen">
        <Icon name="search" />
      </button>
    </div>

    <div class="header-center">
      <GlobalSearch />
    </div>

    <div class="header-end">
      <button class="icon-btn" :aria-label="isDark ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن'" @click="toggleColorScheme">
        <Icon :name="isDark ? 'sun' : 'moon'" />
      </button>
      <RoleSwitcher v-if="isDemoMode" />

      <Popover v-model:open="notificationOpen">
        <template #trigger>
          <button class="icon-btn" aria-label="الإشعارات" @click="toggleNotifications">
            <Icon name="bell" />
            <span
              v-if="notificationsStore.unreadCount > 0"
              class="notification-badge"
              :aria-label="`${notificationsStore.unreadCount} إشعارات غير مقروءة`"
            >
              {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
            </span>
          </button>
        </template>
        <div class="notif-popover">
          <div class="notif-popover-header">
            <div class="notif-title-wrap">
              <span>الإشعارات</span>
              <span class="notif-count">{{ notificationsStore.unreadCount }}</span>
            </div>
            <button class="notif-mark-all" @click="handleMarkAllRead">قراءة الكل</button>
          </div>
          <div v-if="notificationsStore.items.length === 0" class="notif-empty">
            <div class="notif-empty-icon" aria-hidden="true">
              <Icon name="bell" />
            </div>
            <p class="notif-empty-title">لا توجد إشعارات بعد</p>
          </div>
          <div v-else class="notif-list">
            <div v-for="n in notificationsStore.items" :key="n.id" class="notif-item">
              <span class="notif-dot" :class="{ 'notif-dot--read': !!n.read_at }" />
              <div class="notif-copy">
                <p class="notif-message">{{ n.data.message }}</p>
                <p class="notif-sub">{{ n.data.reference_number ?? '—' }}</p>
              </div>
              <time class="notif-time">{{ formatRelativeTime(n.created_at) }}</time>
            </div>
          </div>
          <NuxtLink class="notif-footer-link" to="/notifications">عرض كل الإشعارات</NuxtLink>
        </div>
      </Popover>

      <DropdownMenu v-if="auth.user" v-model:open="userMenuOpen">
        <template #trigger>
          <button class="user-trigger" @click="userMenuOpen = !userMenuOpen">
            <div class="avatar-btn">{{ userInitial }}</div>
            <div class="user-meta">
              <span class="user-display-name">{{ auth.user.name }}</span>
              <span class="role-label">{{ ROLE_LABELS[auth.user.role] ?? auth.user.role }}</span>
            </div>
          </button>
        </template>
        <button class="menu-item" @click="goToProfile">الملف الشخصي</button>
        <button class="menu-item" @click="goToSettings">الإعدادات</button>
        <hr class="menu-separator">
        <button class="menu-item menu-item--danger" @click="handleLogout">تسجيل الخروج</button>
      </DropdownMenu>
    </div>

    <div v-if="mobileSearchOpen" class="mobile-search-overlay">
      <GlobalSearch mobile />
      <button class="icon-btn" aria-label="إغلاق البحث" @click="mobileSearchOpen = false">
        <Icon name="x" />
      </button>
    </div>
  </header>
</template>

<style scoped>
.app-header { display: flex; align-items: center; justify-content: space-between; height: 64px; padding: 0 20px; background: color-mix(in srgb, var(--color-surface) 88%, transparent); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); position: sticky; top: 0; z-index: 30; flex-direction: row-reverse; gap: 12px; }
.header-start, .header-end { display: flex; align-items: center; }
.header-start { gap: 4px; }
.header-end { gap: 12px; }
.header-center { flex: 1; display: flex; justify-content: center; min-width: 0; }
.mobile-menu-btn, .mobile-search-btn, .icon-btn { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: none; background: transparent; border-radius: 8px; cursor: pointer; color: var(--color-text-secondary); }
.mobile-menu-btn, .mobile-search-btn { display: none; }
.icon-btn { position: relative; }
.mobile-menu-btn:hover, .mobile-search-btn:hover, .icon-btn:hover { background-color: var(--color-background); }
.notification-badge { position: absolute; top: 4px; inset-inline-end: 4px; min-width: 16px; height: 16px; padding: 0 3px; border-radius: 8px; background-color: var(--color-rejected); border: 2px solid var(--color-surface); color: #fff; font-size: 9px; font-weight: 600; line-height: 12px; display: flex; align-items: center; justify-content: center; }
.user-trigger { border: none; background: transparent; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.avatar-btn { width: 36px; height: 36px; border-radius: 9999px; background: var(--color-primary); color: var(--color-on-primary); display: grid; place-items: center; font-weight: 700; }
.user-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
.user-display-name { font-size: 14px; font-weight: 500; color: var(--color-text-primary); line-height: 1.2; }
.role-label { font-size: 11px; color: var(--color-text-secondary); line-height: 1.2; }
.notif-popover { width: 360px; max-width: calc(100vw - 24px); }
.notif-popover-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--color-outline-variant); }
.notif-title-wrap { display: flex; gap: 8px; align-items: center; }
.notif-count { font-size: 11px; background: var(--color-primary-container); color: var(--color-on-primary-container); border-radius: 9999px; padding: 1px 6px; }
.notif-mark-all { border: none; background: transparent; color: var(--color-primary); cursor: pointer; }
.notif-list { max-height: 24rem; overflow: auto; }
.notif-item { display: flex; gap: 8px; padding: 10px 12px; border-bottom: 1px solid var(--color-outline-variant); }
.notif-dot { width: 8px; height: 8px; margin-top: 6px; border-radius: 9999px; background: var(--color-primary); flex-shrink: 0; }
.notif-dot--read { background: var(--color-border); }
.notif-copy { flex: 1; }
.notif-message, .notif-sub, .notif-time { margin: 0; font-size: 12px; }
.notif-sub, .notif-time { color: var(--color-text-secondary); }
.notif-footer-link { display: block; padding: 10px 12px; font-size: 13px; color: var(--color-primary); text-decoration: none; }
.notif-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 24px 12px; color: var(--color-text-secondary); }
.notif-empty-icon { width: 44px; height: 44px; border-radius: 9999px; background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface)); color: var(--color-primary); display: grid; place-items: center; }
.notif-empty-title { margin: 0; font-size: 13px; color: var(--color-text-secondary); }
.menu-item { width: 100%; border: none; background: transparent; text-align: start; padding: 8px 10px; border-radius: 8px; cursor: pointer; }
.menu-item:hover { background: var(--sidebar-accent); }
.menu-separator { border: none; border-top: 1px solid var(--color-outline-variant); margin: 6px 0; }
.menu-item--danger { color: var(--color-error-text); }
.mobile-search-overlay { display: none; position: absolute; top: 0; left: 0; right: 0; height: 64px; background-color: var(--color-surface); border-bottom: 1px solid var(--color-border); padding: 0 12px; align-items: center; gap: 8px; z-index: 30; }
@media (max-width: 1023px) { .mobile-menu-btn { display: flex; } }
@media (max-width: 767px) { .mobile-search-btn { display: flex; } .header-center { display: none; } .mobile-search-overlay { display: flex; } .user-meta { display: none; } }
</style>
