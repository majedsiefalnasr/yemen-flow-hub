<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.store'
import { useSidebar } from '../../composables/useSidebar'
import { NAV_ITEMS, ROLE_LABELS } from '../../constants/workflow'
import Icon from '../ui/Icon.vue'

const props = defineProps<{
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  closeMobile: []
}>()

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { isCollapsed, toggle } = useSidebar()

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
    :class="{
      'sidebar--mobile-open': props.mobileOpen,
      'sidebar--collapsed': isCollapsed,
    }"
    aria-label="القائمة الرئيسية"
  >
    <!-- Brand -->
    <div class="sidebar-brand">
      <span class="brand-logo" aria-hidden="true">ب م</span>
      <span class="brand-copy">
        <span class="brand-name">منصة الواردات</span>
        <span class="brand-subtitle">البنك المركزي اليمني</span>
      </span>
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
        :title="isCollapsed ? item.label : undefined"
        @click="emit('closeMobile')"
      >
        <span class="nav-icon" aria-hidden="true">
          <Icon :name="item.icon" />
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

      <!-- Collapse toggle chevron -->
      <button class="collapse-btn" @click="toggle">
        {{ isCollapsed ? 'توسيع ›' : '‹ طي الشريط الجانبي' }}
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
  width: var(--sidebar-expanded, 280px);
  background-color: var(--sidebar, #ffffff);
  border-inline-start: 1px solid var(--sidebar-border, #cccccc);
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow-y: auto;
  overflow-x: hidden;
  transition: width 200ms ease;
}

.sidebar--collapsed {
  width: var(--sidebar-collapsed, 72px);
}

.sidebar--collapsed .brand-name,
.sidebar--collapsed .nav-label,
.sidebar--collapsed .user-details,
.sidebar--collapsed .logout-btn {
  display: none;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px 16px 16px;
  overflow: hidden;
  white-space: nowrap;
}

.brand-logo {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 12px;
  background: var(--sidebar-primary, #0066cc);
  color: var(--sidebar-primary-foreground, #ffffff);
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}

.brand-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--sidebar-foreground, #1c222b);
}

.brand-subtitle {
  font-size: 11px;
  color: color-mix(in srgb, var(--sidebar-foreground, #1c222b) 60%, transparent);
}

.sidebar-divider {
  height: 1px;
  background-color: var(--sidebar-border, #cccccc);
  margin: 4px 16px;
}

.sidebar--collapsed .sidebar-divider {
  margin: 4px 8px;
}

.sidebar-nav {
  flex: 1;
  padding: 8px;
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
  color: var(--sidebar-foreground, #1c222b);
  text-decoration: none;
  transition: background-color 120ms ease, color 120ms ease;
  overflow: hidden;
  white-space: nowrap;
}

.sidebar--collapsed .nav-item {
  justify-content: center;
  padding: 10px 0;
}

.nav-item:hover:not(.nav-item--active) {
  background-color: var(--sidebar-accent, #f0f4fa);
}

.nav-item--active {
  background-color: var(--sidebar-primary, #0066cc);
  color: var(--sidebar-primary-foreground, #ffffff);
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
  overflow: hidden;
}

.sidebar--collapsed .user-info {
  justify-content: center;
  padding: 10px 0;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--sidebar-primary, #0066cc);
  color: var(--sidebar-primary-foreground, #ffffff);
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
  color: var(--sidebar-foreground, #1c222b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role-chip {
  display: inline-block;
  font-size: 11px;
  font-weight: 500;
  color: var(--sidebar-primary, #0066cc);
  background-color: var(--color-primary-container, #e3f2fd);
  padding: 1px 6px;
  border-radius: 9999px;
  font-family: var(--font-latin);
  letter-spacing: 0;
}

.logout-btn {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--sidebar-border, #cccccc);
  border-radius: 8px;
  background-color: transparent;
  color: var(--color-text-secondary, #6c757d);
  font-size: 13px;
  font-family: var(--font-arabic);
  cursor: pointer;
  text-align: start;
  transition: background-color 120ms ease, color 120ms ease;
  margin-top: 4px;
}

.logout-btn:hover {
  background-color: var(--sidebar-accent, #f0f4fa);
  color: var(--color-rejected, #c62828);
}

/* Collapse chevron button */
.collapse-btn {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 100%;
  padding: 10px 12px;
  margin-top: 4px;
  border: 1px solid var(--sidebar-border, #cccccc);
  border-radius: 8px;
  background-color: transparent;
  color: var(--sidebar-foreground, #1c222b);
  font-size: 13px;
  cursor: pointer;
  transition: background-color 120ms ease, color 120ms ease;
}

.collapse-btn:hover {
  background-color: var(--sidebar-accent, #f0f4fa);
  color: var(--sidebar-foreground, #1c222b);
}

.sidebar--collapsed .brand-copy {
  display: none;
}

.sidebar--collapsed .collapse-btn {
  justify-content: center;
}

/* Mobile (≤600px): hide sidebar by default, show when mobileOpen */
@media (max-width: 600px) {
  .sidebar {
    transform: translateX(100%); /* hidden off-screen to the right (RTL) */
    transition: transform 200ms ease, width 200ms ease;
    width: var(--sidebar-expanded, 280px) !important; /* always full width on mobile */
  }

  .sidebar--mobile-open {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: block;
  }

  .collapse-btn {
    display: none; /* no collapse on mobile — drawer handles show/hide */
  }

  .sidebar--collapsed .brand-name,
  .sidebar--collapsed .nav-label,
  .sidebar--collapsed .user-details,
  .sidebar--collapsed .logout-btn {
    display: flex;
  }

  .sidebar--collapsed .logout-btn {
    display: block;
  }

  .sidebar--collapsed .nav-item,
  .sidebar--collapsed .user-info {
    justify-content: flex-start;
    padding: 10px 12px;
  }
}
</style>
