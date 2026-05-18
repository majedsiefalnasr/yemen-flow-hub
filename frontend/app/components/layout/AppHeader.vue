<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useNotificationsStore } from '../../stores/notifications.store'
import SidebarIcon from './SidebarIcon.vue'
import GlobalSearch from './GlobalSearch.vue'
import { ROLE_LABELS } from '../../constants/workflow'

const emit = defineEmits<{
  toggleMobileMenu: []
}>()

const auth = useAuthStore()
const notificationsStore = useNotificationsStore()
const router = useRouter()

const mobileSearchOpen = ref(false)

function goToNotifications() {
  router.push('/notifications')
}

onMounted(() => {
  void notificationsStore.refreshUnreadCount()
})
</script>

<template>
  <header class="app-header">
    <!-- Right side (RTL): mobile menu toggle + mobile search icon -->
    <div class="header-start">
      <button
        class="mobile-menu-btn"
        aria-label="فتح القائمة"
        @click="emit('toggleMobileMenu')"
      >
        <SidebarIcon name="menu" />
      </button>
      <button
        class="mobile-search-btn"
        aria-label="بحث"
        @click="mobileSearchOpen = !mobileSearchOpen"
      >
        <SidebarIcon name="search" />
      </button>
    </div>

    <!-- Center: global search (desktop) -->
    <div class="header-center">
      <GlobalSearch />
    </div>

    <!-- Left side (RTL): user info + notifications -->
    <div class="header-end">
      <!-- Notification bell -->
      <button class="icon-btn" aria-label="الإشعارات" @click="goToNotifications">
        <SidebarIcon name="bell" />
        <span
          v-if="notificationsStore.unreadCount > 0"
          class="notification-badge"
          :aria-label="`${notificationsStore.unreadCount} إشعارات غير مقروءة`"
        >
          {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
        </span>
      </button>

      <!-- User info -->
      <div v-if="auth.user" class="user-meta">
        <span class="user-display-name">{{ auth.user.name }}</span>
        <span class="role-label">{{ ROLE_LABELS[auth.user.role] ?? auth.user.role }}</span>
      </div>
    </div>

    <!-- Mobile search overlay (full-width, shown when mobileSearchOpen) -->
    <div v-if="mobileSearchOpen" class="mobile-search-overlay">
      <GlobalSearch mobile />
      <button class="icon-btn" aria-label="إغلاق البحث" @click="mobileSearchOpen = false">
        <SidebarIcon name="x" />
      </button>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 20px;
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--color-border);
  position: sticky;
  top: 0;
  z-index: 30;
  /* RTL: flex-row-reverse so "start" = right, "end" = left */
  flex-direction: row-reverse;
  gap: 12px;
}

.header-start {
  display: flex;
  align-items: center;
  gap: 4px;
}

.header-center {
  flex: 1;
  display: flex;
  justify-content: center;
  min-width: 0;
}

.header-end {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-menu-btn,
.mobile-search-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: none;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  color: var(--color-text-secondary);
}

.mobile-menu-btn:hover,
.mobile-search-btn:hover {
  background-color: var(--color-background);
}

.icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: none;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  color: var(--color-text-secondary);
}

.icon-btn:hover {
  background-color: var(--color-background);
}

.notification-badge {
  position: absolute;
  top: 4px;
  inset-inline-end: 4px;
  min-width: 16px;
  height: 16px;
  padding: 0 3px;
  border-radius: 8px;
  background-color: var(--color-rejected);
  border: 2px solid var(--color-surface);
  color: #ffffff;
  font-size: 9px;
  font-weight: 600;
  line-height: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}

.user-display-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
  line-height: 1.2;
}

.role-label {
  font-size: 11px;
  color: var(--color-text-secondary);
  line-height: 1.2;
}

.mobile-search-overlay {
  display: none;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 56px;
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  padding: 0 12px;
  align-items: center;
  gap: 8px;
  z-index: 30;
}

/* Mobile (≤600px) */
@media (max-width: 600px) {
  .mobile-menu-btn,
  .mobile-search-btn {
    display: flex;
  }

  .header-center {
    display: none;
  }

  .mobile-search-overlay {
    display: flex;
  }
}
</style>
