<script setup lang="ts">
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import { ref } from 'vue'

const mobileMenuOpen = ref(false)

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}
</script>

<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <AppSidebar :mobile-open="mobileMenuOpen" @close-mobile="mobileMenuOpen = false" />

    <!-- Main area -->
    <div class="app-main">
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
  /* offset for expanded sidebar (280px) */
  margin-inline-end: var(--sidebar-expanded, 280px);
}

.app-content {
  flex: 1;
  padding: 24px;
  max-width: var(--container-max, 1600px);
  width: 100%;
  margin: 0 auto;
}

/* Mobile (≤600px): sidebar hidden, no margin offset */
@media (max-width: 600px) {
  .app-main {
    margin-inline-end: 0;
  }
}
</style>
