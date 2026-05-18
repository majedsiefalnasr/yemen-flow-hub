<script setup lang="ts">
import { computed, ref } from 'vue'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import { useSidebar } from '../composables/useSidebar'

const mobileMenuOpen = ref(false)
const { isCollapsed } = useSidebar()

const mainMargin = computed(() =>
  isCollapsed.value ? 'var(--sidebar-collapsed, 72px)' : 'var(--sidebar-expanded, 280px)'
)

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}
</script>

<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <AppSidebar :mobile-open="mobileMenuOpen" @close-mobile="mobileMenuOpen = false" />

    <!-- Main area -->
    <div class="app-main" :style="{ marginInlineEnd: mainMargin }">
      <AppHeader @toggle-mobile-menu="toggleMobileMenu" />
      <main class="app-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  flex-direction: row-reverse; /* RTL: sidebar on the right */
  min-height: 100vh;
  background-color: var(--color-background);
}

.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  transition: margin-inline-end 200ms ease;
}

.app-content {
  flex: 1;
  padding: 24px;
  max-width: var(--container-max, 1600px);
  width: 100%;
  margin: 0 auto;
}

/* Tablet (601px–1024px) */
@media (min-width: 601px) and (max-width: 1024px) {
  .app-content {
    padding: 16px;
  }
}

/* Mobile (≤600px): sidebar hidden, no margin offset */
@media (max-width: 600px) {
  .app-main {
    margin-inline-end: 0 !important;
  }

  .app-content {
    padding: 12px;
  }
}
</style>
