<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import AppSidebar from '@/components/AppSidebar.vue'
import GlobalTopbar from '@/components/layout/GlobalTopbar.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useThemingStore } from '@/stores/theming.store'
import {
  SidebarInset,
  SidebarProvider,
} from '@/components/ui/sidebar'

const authStore = useAuthStore()
const themingStore = useThemingStore()
const user = computed(() => authStore.user)
</script>

<template>
  <div v-if="user" class="min-h-dvh bg-background text-foreground" >
    <SidebarProvider  class="flex-row">
      <AppSidebar
        side="right"
        :variant="themingStore.sidebarVariant"
        :collapsible="themingStore.sidebarCollapsible"
      />
      <SidebarInset>
        <GlobalTopbar />

        <main class="flex flex-1 flex-col overflow-auto p-4 pt-4">
          <div
            :class="[
              'content-layout w-full',
              themingStore.layout === 'boxed' ? 'layout-boxed' : 'layout-full',
            ]"
          >
            <slot />
          </div>
        </main>

        <!-- Footer -->
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-[10px] text-muted-foreground sm:px-6 sm:text-xs">
          <div>© 2025 البنك المركزي اليمني</div>
          <div class="flex shrink-0 items-center gap-2 text-amber-600 dark:text-amber-400">
            <AlertTriangle class="h-3.5 w-3.5" />
            بيئة عرض توضيحي
          </div>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  </div>
</template>
