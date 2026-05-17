<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { useNotificationsStore } from '../../stores/notifications.store'
import SidebarIcon from './SidebarIcon.vue'
import { ROLE_LABELS } from '../../constants/workflow'

const emit = defineEmits<{
  toggleMobileMenu: []
}>()

const auth = useAuthStore()
const notificationsStore = useNotificationsStore()
const router = useRouter()

function goToNotifications() {
  router.push('/notifications')
}

onMounted(() => {
  void notificationsStore.refreshUnreadCount()
})
</script>

<template>
  <header class="app-header">
    <!-- Right side (RTL): mobile menu toggle -->
    <div class="header-start">
      <button
        class="mobile-menu-btn"
        aria-label="فتح القائمة"
        @click="emit('toggleMobileMenu')"
      >
        <SidebarIcon name="menu" />
      </button>
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
      <div class="user-meta" v-if="auth.user">
        <span class="user-display-name">{{ auth.user.name }}</span>
        <span class="role-label">{{ ROLE_LABELS[auth.user.role] ?? auth.user.role }}</span>
      </div>
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
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  position: sticky;
  top: 0;
  z-index: 20;
  /* RTL: flex-row-reverse so "start" = right, "end" = left */
  flex-direction: row-reverse;
}

.header-start {
  display: flex;
  align-items: center;
}

.header-end {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-menu-btn {
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

.mobile-menu-btn:hover {
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
  inset-inline-end: 4px; /* RTL-aware */
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
  align-items: flex-end; /* RTL: align to the right */
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

/* Mobile (≤600px) */
@media (max-width: 600px) {
  .mobile-menu-btn {
    display: flex;
  }
}
</style>
