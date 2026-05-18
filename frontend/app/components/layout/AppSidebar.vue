<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.store'
import { NAV_ITEMS, ROLE_LABELS } from '../../constants/workflow'
import SidebarIcon from './SidebarIcon.vue'

const props = defineProps<{
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  closeMobile: []
}>()

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const visibleNavItems = computed(() => {
  if (!auth.user) return []
  return NAV_ITEMS.filter(item => item.roles.includes(auth.user!.role))
})

function isActive(itemRoute: string): boolean {
  return route.path === itemRoute || route.path.startsWith(itemRoute + '/')
}

async function handleLogout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <!-- Mobile overlay -->
  <div
    v-if="props.mobileOpen"
    class="sidebar-overlay"
    aria-hidden="true"
    @click="emit('closeMobile')"
  />

  <!-- Sidebar -->
  <aside
    class="sidebar"
    :class="{ 'sidebar--mobile-open': props.mobileOpen }"
    aria-label="القائمة الرئيسية"
  >
    <!-- Brand -->
    <div class="sidebar-brand">
      <span class="brand-logo">🏦</span>
      <span class="brand-name">Yemen Flow Hub</span>
    </div>

    <div class="sidebar-divider" />

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="روابط التنقل">
      <NuxtLink
        v-for="item in visibleNavItems"
        :key="item.route"
        :to="item.route"
        class="nav-item"
        :class="{ 'nav-item--active': isActive(item.route) }"
        @click="emit('closeMobile')"
      >
        <span class="nav-icon" aria-hidden="true">
          <SidebarIcon :name="item.icon" />
        </span>
        <span class="nav-label">{{ item.label }}</span>
      </NuxtLink>
    </nav>

    <!-- User section at bottom -->
    <div class="sidebar-footer">
      <div class="sidebar-divider" />
      <div class="user-info">
        <div class="user-avatar" aria-hidden="true">
          {{ auth.user?.name?.charAt(0) ?? '؟' }}
        </div>
        <div class="user-details">
          <span class="user-name">{{ auth.user?.name }}</span>
          <span class="user-role-chip">{{ auth.user ? (ROLE_LABELS[auth.user.role] ?? auth.user.role) : '' }}</span>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout">
        تسجيل الخروج
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(29, 29, 31, 0.4);
  z-index: 40;
  display: none;
}

.sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  inset-inline-end: 0; /* RTL: right side */
  width: 264px;
  background-color: var(--color-surface);
  border-inline-start: 1px solid var(--color-border); /* RTL: left border */
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow-y: auto;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px 16px 16px;
}

.brand-logo {
  font-size: 24px;
  flex-shrink: 0;
}

.brand-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary);
  font-family: var(--font-latin);
}

.sidebar-divider {
  height: 1px;
  background-color: var(--color-border);
  margin: 4px 16px;
}

.sidebar-nav {
  flex: 1;
  padding: 8px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 400;
  color: var(--color-text-primary);
  text-decoration: none;
  transition: background-color 120ms ease, color 120ms ease;
}

.nav-item:hover:not(.nav-item--active) {
  background-color: var(--color-background);
}

.nav-item--active {
  background-color: var(--color-primary);
  color: #ffffff;
  font-weight: 500;
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.nav-label {
  flex: 1;
  text-align: start;
}

/* Footer */
.sidebar-footer {
  padding: 8px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 8px;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--color-primary);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 600;
  flex-shrink: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role-chip {
  display: inline-block;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-primary);
  background-color: #e8f3fd;
  padding: 1px 6px;
  border-radius: 9999px;
  font-family: var(--font-latin);
  letter-spacing: 0;
}

.logout-btn {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background-color: transparent;
  color: var(--color-text-secondary);
  font-size: 13px;
  font-family: var(--font-arabic);
  cursor: pointer;
  text-align: start;
  transition: background-color 120ms ease, color 120ms ease;
  margin-top: 4px;
}

.logout-btn:hover {
  background-color: var(--color-background);
  color: var(--color-rejected);
}

/* Mobile (≤600px): hide sidebar by default, show when mobileOpen */
@media (max-width: 600px) {
  .sidebar {
    transform: translateX(100%); /* hidden off-screen to the right */
    transition: transform 200ms ease;
  }

  .sidebar--mobile-open {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: block;
  }
}
</style>
